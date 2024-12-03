<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;

class TimeTrackingReportController extends Controller
{
    protected $client;
    protected $mondayToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN'); // Añade tu token en el archivo .env
    }

    //obtener los boards que tuvieron cambios en el rango de fechas


    /**
     * Obtener los datos de Monday.com mediante GraphQL con paginación.
     */
    public function getMondayData($boardsIds = [])
    {
        $allBoards = []; // Para almacenar todos los tableros
        $page = 1; // Comenzamos en la primera página
        $hasMoreBoards = true; // Bandera para seguir solicitando más tableros

        while ($hasMoreBoards) {
            // Construimos la consulta GraphQL para los tableros, con paginación usando 'page'
            $query = <<<GRAPHQL
        {
            boards(limit: 50, page: $page) {
                id
                name
                items_page(limit: 50) {
                    items {
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
                    cursor # Cursor para paginar los items
                }
            }
        }
        GRAPHQL;
//            dd($query);

            try {
                // Hacemos la solicitud a la API de Monday
                $response = $this->client->post('https://api.monday.com/v2', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->mondayToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'query' => $query,
                    ],
                ]);
            } catch (\Exception $e) {
                error_log('Error al obtener los boards de la API de Monday: ' . $e->getMessage());
                return [];
            }

            $data = json_decode($response->getBody(), true);
            $boards = $data['data']['boards'];

            // Si recibimos menos de 250 boards, significa que no hay más boards
            if (count($boards) < 50) {
                $hasMoreBoards = false;
            }

            // Agregamos los tableros obtenidos
            foreach ($boards as $board) {
                $boardItems = [];
                $itemsCursor = null;
                $boardId = $board['id'];
                // Mientras haya items para paginar dentro del tablero, seguimos obteniéndolos
                $itemsPage = $board['items_page'];
                do {

                    // Agregamos los items de esta página
                    $boardItems = array_merge($boardItems, $itemsPage['items']);

                    // Actualizamos el cursor de los items
                    $itemsCursor = $itemsPage['cursor'];

                    // Si hay más items (cursor), hacemos otra solicitud para paginar
                    if ($itemsCursor) {
                        $queryItemsCursor = <<<GRAPHQL
                    {
                        boards(ids:"$boardId", limit: 1) {
                            id
                            name
                            items_page(limit: 500, cursor: "$itemsCursor") {
                                items {
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
                                cursor # Cursor para paginar los items
                            }
                        }
                    }
                    GRAPHQL;
//dd($queryItemsCursor);
                        $itemsResponse = $this->client->post('https://api.monday.com/v2', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->mondayToken,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'query' => $queryItemsCursor,
                            ],
                        ]);

                        $itemsData = json_decode($itemsResponse->getBody(), true);

//                        dd($itemsData);
                        $itemsPage = $itemsData['data']['boards'][0]['items_page'];
                    }
                } while ($itemsCursor); // Repetir mientras haya más items (cursor) para este tablero

                // Añadir los items del tablero actual
                $board['items_page']['items'] = $boardItems;
                $allBoards[] = $board;
            }

            // Incrementar el número de página para la siguiente solicitud
            $page++;

        }

        return $allBoards; // Devolver todos los tableros con sus items paginados
    }


    /**
     * Formatear el cursor para su uso en la consulta GraphQL.
     * @param string|null $cursor
     * @return string
     */
    private function formatCursor($cursor)
    {
        return $cursor ? "\"$cursor\"" : 'null';
    }

    /**
     * Obtener el nombre del usuario por ID desde Monday.com.
     */
    public function getUser($userId)
    {
        $query = <<<GRAPHQL
        {
            users(ids: [$userId]) {
                id
                name
                email
            }
        }
        GRAPHQL;

        $response = $this->client->post('https://api.monday.com/v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->mondayToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $query,
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['data']['users'][0] ?? 'Desconocido';
    }

    /**
     * Procesar los datos obtenidos de Monday.com y generar el reporte basado en un rango de fechas.
     *
     * @param string $fromDate Fecha de inicio en formato 'YYYY-MM-DD'.
     * @param string|null $toDate Fecha de fin en formato 'YYYY-MM-DD'. Si es null, se asume la fecha actual.
     * @return array Datos agrupados por usuario y tablero.
     */
    public function processMondayData(string $fromDate, string $toDate = null, $boardsIds = [], $excludedUsers = ['42646029', '54540552'])
    {
        // Si no se proporciona $toDate, se asume la fecha actual
        $toDate = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();
        $fromDate = Carbon::parse($fromDate)->startOfDay(); // Asegurar que la fecha de inicio es al principio del día
        $mondayController = new MondayController();
        $data = $this->getMondayData();
//        dd($data);
        $usersData = []; // Agrupar las actividades por usuario

        // Iteramos sobre los tableros
        foreach ($data as $board) {
            foreach ($board['items_page']['items'] as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {

                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];

                            // Convertir a fechas de Carbon
                            $startTimeCarbon = Carbon::parse($startTime);
                            $endTimeCarbon = Carbon::parse($endTime);

                            // Si las fechas de inicio y fin están dentro del rango

                            if ($endTime !== false && $startTimeCarbon->between($fromDate, $toDate) && $endTimeCarbon->between($fromDate, $toDate)) {
                                $manuallyEntered = !empty($record['manually_entered_start_date']) || !empty($record['manually_entered_end_time']) ? 'Sí' : 'No';
                                $user = $mondayController->getUser($record['started_user_id']);
                                // Obtener nombres de usuario
                                $startedUserName = $user['name'];
                                if (!in_array($user['id'], $excludedUsers)) {
                                    // Solo procesar si es el mismo usuario quien inicia y termina
//                                if ($record['started_user_id'] === $record['ended_user_id']) {
                                    $userId = $record['started_user_id'];

                                    // Crear entrada para el usuario si no existe
                                    if (!isset($usersData[$userId])) {
                                        $usersData[$userId] = [
                                            'name' => $startedUserName,
                                            'slack_id' => $user['slack_user_id'] ?? null,
                                            'tableros' => []
                                        ];
                                    }

                                    // Crear entrada para el tablero si no existe
                                    if (!isset($usersData[$userId]['tableros'][$board['name']])) {
                                        $usersData[$userId]['tableros'][$board['name']] = [];
                                    }

                                    // Calcular la duración
                                    $duration = (strtotime($endTime) - strtotime($startTime)) / (60 * 60);
                                    $usersData[$userId]['tableros'][$board['name']][] = [
                                        'boardId' => $board['id'],
                                        'tarea' => $item['name'],
                                        'duracion' => number_format($duration, 2),
                                        'manual' => $manuallyEntered
                                    ];
//                                }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $usersData;
    }


    /**
     * Generar el reporte de horas trabajadas basado en un rango de fechas.
     *
     * @param string $fromDate Fecha de inicio en formato 'YYYY-MM-DD'.
     * @param string|null $toDate Fecha de fin en formato 'YYYY-MM-DD'.
     * Si solo se proporciona uno, o ambos son iguales, se calculará para solo ese día.
     */
    public function generateReport(string $fromDate, $type, string $toDate = null)
    {
        $slackController = new SlackController();
        // Si no se proporciona $toDate o si ambos son iguales, procesar solo para $fromDate
        if (is_null($toDate) || $fromDate === $toDate) {
            $toDate = $fromDate; // Si no hay toDate, tratamos ambos como el mismo día
        } elseif (is_null($toDate) && is_null($fromDate)) {
            $toDate = $fromDate; // Si toDate es null, tratamos ambos como el mismo día
        }

        // Procesar los datos de Monday con el rango de fechas
        $usersData = $this->processMondayData($fromDate, $toDate);
        //get all boards ids
        $boardsIds = [];
        foreach ($usersData as $user) {
            foreach ($user['tableros'] as $tablero => $actividades) {
                foreach ($actividades as $actividad) {
                    $boardsIds[] = $actividad['boardId'];
                }
            }
        }
        if (!$slackController->timeTrackingMondayBoardSummaryWithBoardIds($boardsIds)) {
            return 'No se encontraron datos en el rango de fechas seleccionado';
        }

        return $this->toReport($usersData, $type);
    }


    public function toReport($usersData, $type)
    {
        $report = '';
        $globalTotalHours = 0;
        foreach ($usersData as $user) {

            if ($user['slack_id'] !== null) {
                $userDisplayName = "<@{$user['slack_id']}>";
            } else {
                $userDisplayName = $user['name'];
            }
//            dd($userDisplayName, $user['name'], $user['slack_id'] == null);

            $report .= "Usuario: *$userDisplayName*\n";
            $totalUserHours = 0;
            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                $report .= "  Tablero: *$tablero*";

                $report .= ":\n";
                foreach ($actividades as $actividad) {
//                    dd($actividad);
                        if ($type != 'simple') {
                            $report .= "    Actividad: *{$actividad['tarea']}* - ";
                            $report .= "Tiempo: " . $this->formatTime($actividad['duracion']) . " horas - ";
                            $report .= "Manual: {$actividad['manual']}\n";
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

    public function formatTime($time)
    {
        $hours = floor($time);
        $minutes = ($time - $hours) * 60;
        return sprintf("%02d:%02d", $hours, $minutes);
    }

}
