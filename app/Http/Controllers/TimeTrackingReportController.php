<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;

class TimeTrackingReportController extends Controller
{
    protected $client;
    protected $mondayToken;
    protected $mondayController; // Inject MondayController

    public function __construct(MondayController $mondayController) // Dependency Injection
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN');
        $this->mondayController = $mondayController; // Initialize injected controller
    }

    private function getHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->mondayToken,
            'Content-Type' => 'application/json',
        ];
    }

    private function buildBoardsQuery($page, $boardIds = [])
    {
        if (empty($boardIds)) {
            // No board IDs provided, fetch all boards with pagination
            return <<<GRAPHQL
        {
            boards(limit: 50, page: $page) {
                id
                url
                name
                items_page(limit: 50) {
                    items {
                        url
                        id
                        name
                        updated_at
                        column_values {
                            ... on PeopleValue {
                                persons_and_teams {
                                    id
                                    kind
                                }
                            }
                            ... on TimeTrackingValue {
                                history {
                                    started_user_id
                                    ended_user_id
                                    started_at
                                    ended_at
                                    manually_entered_end_date
                                    manually_entered_end_time
                                    manually_entered_start_date
                                    manually_entered_start_time
                                }
                                running
                                started_at
                            }
                        }
                    }
                    cursor
                }
            }
        }
        GRAPHQL;
        } else {
            // Board IDs provided, fetch specific boards
            $boardIdsString = implode(',', $boardIds); // Convert array to comma-separated string
            return <<<GRAPHQL
        {
            boards(ids: [$boardIdsString], limit: 50, page: $page) {  # Use ids filter
                id
                url
                name
                items_page(limit: 50) {
                    items {
                        url
                        id
                        name
                        updated_at
                        column_values {
                            ... on PeopleValue {
                                persons_and_teams {
                                    id
                                    kind
                                }
                            }
                            ... on TimeTrackingValue {
                                history {
                                    started_user_id
                                    ended_user_id
                                    started_at
                                    ended_at
                                    manually_entered_end_date
                                    manually_entered_end_time
                                    manually_entered_start_date
                                    manually_entered_start_time
                                }
                                running
                                started_at
                            }
                        }
                    }
                    cursor
                }
            }
        }
        GRAPHQL;
        }
    }

    private function fetchBoardItems($boardId, $itemsCursor = null)
    {
        $boardItems = [];

        do {
            $queryItemsCursor = $this->buildItemsQuery($boardId, $itemsCursor);

            try {
                $itemsResponse = $this->client->post('https://api.monday.com/v2', [
                    'headers' => $this->getHeaders(),
                    'json' => ['query' => $queryItemsCursor],
                ]);

                $itemsData = json_decode($itemsResponse->getBody(), true);

                if (isset($itemsData['data']['boards'][0]['items_page'])) {
                    $itemsPage = $itemsData['data']['boards'][0]['items_page'];
                    $boardItems = array_merge($boardItems, $itemsPage['items']);
                    $itemsCursor = $itemsPage['cursor'];
                } else {
                    $itemsCursor = null; // Important: Stop the loop if data is missing
                }

            } catch (\Exception $e) {
                error_log("Error fetching items for board {$boardId}: " . $e->getMessage());
                return []; // Or throw the exception if you prefer
            }
        } while ($itemsCursor);

        return $boardItems;
    }

    private function buildItemsQuery($boardId, $itemsCursor)
    {
        $cursorClause = $itemsCursor ? 'cursor: "' . $itemsCursor . '"' : '';
        return <<<GRAPHQL
        {
            boards(ids:"$boardId", limit: 1) {
                id
                url
                name
                items_page(limit: 500, $cursorClause) {
                    items {
                        id
                        url
                        name
                        updated_at
                        column_values {
                            ... on PeopleValue {
                                persons_and_teams {
                                    id
                                    kind
                                }
                            }
                            ... on TimeTrackingValue {
                                history {
                                    started_user_id
                                    ended_user_id
                                    started_at
                                    ended_at
                                    manually_entered_end_date
                                    manually_entered_end_time
                                    manually_entered_start_date
                                    manually_entered_start_time
                                }
                                running
                                started_at
                            }
                        }
                    }
                    cursor
                }
            }
        }
        GRAPHQL;
    }

    public function getMondayData($boardIds = [])
    {
        $allBoards = [];
        $page = 1;
        $hasMoreBoards = true;

        while ($hasMoreBoards) {
            $query = $this->buildBoardsQuery($page, $boardIds); // Pass $boardIds to the query builder
            try {
                $response = $this->client->post('https://api.monday.com/v2', [
                    'headers' => $this->getHeaders(),
                    'json' => ['query' => $query],
                ]);

                $data = json_decode($response->getBody(), true);
                $boards = $data['data']['boards'];

                // Check if there are any boards returned. If not, no more boards to fetch.
                if (empty($boards) || count($boards) < 50) {
                    $hasMoreBoards = false;
                }

                foreach ($boards as &$board) {
                    $board['items_page']['items'] = $this->fetchBoardItems($board['id']);
                    $allBoards[] = $board;
                }
                unset($board);

                $page++;

            } catch (\Exception $e) {
                error_log('Error getting Monday data: ' . $e->getMessage());
                return [];
            }
        }
        return $allBoards;
    }


    public function processMondayData(string|null $fromDate, string|null $toDate = null, $boardsId = [], $excludedUsers = ['42646029', '54540552'])
    {
        $toDate = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();
        $fromDate = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $data = $this->getMondayData($boardsId);
        $usersData = [];

        foreach ($data as $board) {
            foreach ($board['items_page']['items'] as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {
                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];

                            if (!$startTime || !$endTime) continue; // Skip if start or end time is missing.

                            $startTimeCarbon = Carbon::parse($startTime);
                            $endTimeCarbon = Carbon::parse($endTime);

                            if (($endTimeCarbon->between($fromDate, $toDate) && $startTimeCarbon->between($fromDate, $toDate)) || is_null($fromDate)) {
                                $manuallyEntered = !empty($record['manually_entered_start_date']) || !empty($record['manually_entered_end_time']);
                                $user = $this->mondayController->getUser($record['started_user_id']); // Use injected controller

                                if (!in_array($user['id'], $excludedUsers)) {
                                    $userId = $record['started_user_id'];

                                    if (!isset($usersData[$userId])) {
                                        $usersData[$userId] = [
                                            'name' => $user['name'],  // Use name from getUser
                                            'slack_id' => $user['slack_user_id'] ?? null,
                                            'user_monday_id' => $userId,
                                            'tableros' => []
                                        ];
                                    }

                                    if (!isset($usersData[$userId]['tableros'][$board['name']])) {
                                        $usersData[$userId]['tableros'][$board['name']] = [];
                                    }

                                    $duration = $endTimeCarbon->diffInSeconds($startTimeCarbon) / 3600; // Use Carbon for duration calculation
                                    $usersData[$userId]['tableros'][$board['name']][] = [
                                        'boardId' => $board['id'],
                                        'boardUrl' => $board['url'],
                                        'boardName' => str_replace('>', "-", $board['name']), // Replace spaces
                                        'tarea' => $item['name'],
                                        'tareaUrl' => $item['url'],
                                        'duracion' => number_format($duration, 2),
                                        'startTime' => $startTimeCarbon, // Store Carbon objects
                                        'endTime' => $endTimeCarbon,      // Store Carbon objects
                                        'manual' => $manuallyEntered
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        return $usersData;
    }

    public function generateReport(string $fromDate, $type, string $toDate = null)
    {
        if (is_null($toDate) || $fromDate === $toDate) {
            $toDate = $fromDate;
        }

        $usersData = $this->processMondayData($fromDate, $toDate);

        if (empty($usersData)) {
            return 'No se encontraron datos en el rango de fechas seleccionado';
        }

        return $this->toReport($usersData, $type);
    }

    public function toReport($usersData, $type)
    {
        $report = '';
        $globalTotalHours = 0;

        foreach ($usersData as $user) {
            $userDisplayName = $user['slack_id'] ? "<@{$user['slack_id']}>" : $user['name'];

            $report .= "Usuario: *$userDisplayName*\n";
            $totalUserHours = 0;

            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                if (count($actividades) == 0) continue;
                $report .= "\tTablero: <{$actividades[0]['boardUrl']}|$tablero>:\n";

                foreach ($actividades as $actividad) {
                    if ($type != 'simple') {
                        $report .= "\t\t";
                        $report .= $actividad['manual'] ? '*' : '';
                        $report .= "Actividad: <{$actividad['tareaUrl']}|{$actividad['tarea']}> - ";
//                        $report .= "Tiempo: " . $this->formatTime($actividad['duracion']) . " horas - ";
                        $report .= "Tiempo";
                        $report .= ": {$actividad['startTime']->format('d/m/Y H:i')} - ";
                        $report .= "{$actividad['endTime']->format('d/m/Y H:i')} ({$this->formatTime($actividad['duracion'])})\n";
//                        $report .= "Manual: {$actividad['manual']}\n";
                    }
                    $totalHours += $actividad['duracion'];
                }

                $report .= " - Total de horas: " . $this->formatTime($totalHours) . " horas\n";
                $totalUserHours += $totalHours;
            }

            $globalTotalHours += $totalUserHours;
            $report .= "  Total de horas trabajadas por {$user['name']}: " . $this->formatTime($totalUserHours) . " horas\n";
            $report .= "*----------------------------------------*\n\n";
        }

        $report .= "Total de horas: " . $this->formatTime($globalTotalHours) . " horas\n";

        return $report;
    }

    private function formatTime($time)
    {
        $hours = floor($time);
        $minutes = ($time - $hours) * 60;
        return sprintf("%02d:%02d", $hours, $minutes);
    }
}
