<?php

namespace App\Services\Monday;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WebProjectService
{
    protected $boardService;
    protected $groupService;
    protected $itemService;
    protected $userService;
    protected $templateBoardId = '1901599141'; // Template board ID

    public function __construct()
    {
        $mondayClient = new MondayClient();
        $this->boardService = new BoardService($mondayClient);
        $this->groupService = new GroupService($mondayClient);
        $this->itemService = new ItemService($mondayClient);
        $this->userService = new UserService($mondayClient);
    }

    /**
     * Get the groups from a template board
     */
    public function getBoardGroups($boardId = null)
    {
        $boardId = $boardId ?? $this->templateBoardId;

        try {
            $response = $this->groupService->getGroupsOfBoard($boardId)[0];

            if (isset($response['data']['boards'][0]['groups'])) {
                return $response['data']['boards'][0]['groups'];
            }

            Log::error('Failed to get board groups', ['response' => $response]);
            return [];
        } catch (\Exception $e) {
            Log::error('Exception getting board groups', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get team members from a template board
     */
    public function getTeamMembers($boardId = null)
    {
        $boardId = $boardId ?? $this->templateBoardId;

        try {
            // Get all items in the board
            $response = $this->itemService->getItemsByBoard($boardId, 'PeopleValue');

            if ($response['status'] !== 200) {
                Log::error('Failed to get board items', ['response' => $response]);
                return [];
            }

            $items = $response['data']['data']['boards'][0]['items_page']['items'];
            $teams = [];
            $teamMembers = [];

            // Process items to extract team members
            foreach ($items as $item) {
                foreach ($item['column_values'] as $columnValue) {
                    if ($columnValue['column']['title'] === 'Equipo' && !empty($columnValue['text'])) {
                        $team = $columnValue['text'];

                        if (!isset($teams[$team])) {
                            $teams[$team] = [];
                        }
                    }

                    if ($columnValue['persons_and_teams'] ?? null) {
                        foreach ($columnValue['persons_and_teams'] as $person) {
                            if ($person['kind'] === 'person') {
                                $userId = $person['id'];
                                $user = $this->userService->getUser($userId);

                                if ($user && isset($team)) {
                                    $teamMembers[] = [
                                        'id' => $userId,
                                        'name' => $user['name'],
                                        'email' => $user['email'],
                                        'team' => $team
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            // Group by unique users
            $uniqueMembers = [];
            foreach ($teamMembers as $member) {
                $key = $member['id'] . '-' . $member['team'];
                $uniqueMembers[$key] = $member;
            }

            return array_values($uniqueMembers);
        } catch (\Exception $e) {
            Log::error('Exception getting team members', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a web project from the template
     */
    public function createWebProject($projectName, $projectType, $phases, $teamAssignments)
    {
        try {
            // 1. Duplicate the template board
            $duplicateResponse = $this->boardService->duplicateBoard($this->templateBoardId, $projectName);

            if (isset($duplicateResponse['data']['duplicate_board']['board']['id'])) {
                $newBoardId = $duplicateResponse['data']['duplicate_board']['board']['id'];
                $newBoardUrl = $duplicateResponse['data']['duplicate_board']['board']['url'] ?? '';

                // 2. Get groups from the new board
                $boardGroups = $this->getBoardGroups($newBoardId);

                // 3. Update group names with phase dates
                foreach ($boardGroups as $group) {
                    $groupId = $group['id'];
                    $groupTitle = $group['title'];

                    // Extract the phase ID from the group/phase data
                    foreach ($phases as $phaseId => $phaseDates) {
                        if (isset($group['id']) && $group['id'] === $phaseId) {
                            $startDate = $phaseDates['start'] ?? null;
                            $endDate = $phaseDates['end'] ?? null;

                            if ($startDate && $endDate) {
                                // Format the dates
                                $formattedStartDate = Carbon::parse($startDate)->format('d/m/Y');
                                $formattedEndDate = Carbon::parse($endDate)->format('d/m/Y');

                                // Parse the group title to replace the dates
                                $newTitle = preg_replace(
                                    '/\(.*?\)/',
                                    "({$formattedStartDate} - {$formattedEndDate})",
                                    $groupTitle
                                );

                                // Update the group title
                                $this->groupService->updateGroupTitle($newBoardId, $groupId, $newTitle);
                            }

                            break;
                        }
                    }
                }

                // 4. Get all items in the new board
                $itemsResponse = $this->itemService->getItemsByBoard($newBoardId);

                if ($itemsResponse['status'] === 200) {
                    $items = $itemsResponse['data']['data']['boards'][0]['items_page']['items'];

                    // Process each item
                    foreach ($items as $item) {
                        $itemId = $item['id'];
                        $itemTeam = null;
                        $daysToAdd = 0;
                        $groupId = null;

                        // Extract item details
                        foreach ($item['column_values'] as $columnValue) {
                            if ($columnValue['column']['title'] === 'Equipo' && !empty($columnValue['text'])) {
                                $itemTeam = $columnValue['text'];
                            }

                            if ($columnValue['column']['title'] === 'Días entre tareas' && !empty($columnValue['text'])) {
                                $daysToAdd = (int)$columnValue['text'];
                            }
                        }

                        // Find the group this item belongs to
                        foreach ($boardGroups as $group) {
                            if ($this->itemBelongsToGroup($item, $group['id'], $newBoardId)) {
                                $groupId = $group['id'];
                                break;
                            }
                        }

                        // Assign team member if available
                        if ($itemTeam && isset($teamAssignments[strtolower($itemTeam)])) {
                            $userId = $teamAssignments[strtolower($itemTeam)];

                            // Assign the person to the item
                            $this->assignPersonToItem($newBoardId, $itemId, $userId);
                        }

                        // Set dates based on phase dates and days between tasks
                        if ($groupId && isset($phases[$groupId])) {
                            $phaseStartDate = Carbon::parse($phases[$groupId]['start']);

                            // Find previous items in the same group to calculate the correct date
                            $itemDate = $this->calculateItemDate($items, $item, $groupId, $phaseStartDate, $newBoardId);

                            // Update the date column
                            if ($itemDate) {
                                $this->updateItemDate($newBoardId, $itemId, $itemDate);
                            }
                        }
                    }
                }

                return [
                    'success' => true,
                    'board_id' => $newBoardId,
                    'board_url' => $newBoardUrl
                ];
            } else {
                Log::error('Failed to duplicate board', ['response' => $duplicateResponse]);
                return [
                    'success' => false,
                    'error' => 'No se pudo duplicar el tablero.'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception creating web project', ['exception' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if an item belongs to a group
     */
    protected function itemBelongsToGroup($item, $groupId, $boardId)
    {
        // Try to get item details which include group information
        $response = $this->itemService->getItemById($item['id']);

        if (isset($response['data']['items'][0]['group']['id'])) {
            return $response['data']['items'][0]['group']['id'] === $groupId;
        }

        return false;
    }

    /**
     * Assign a person to an item
     */
    protected function assignPersonToItem($boardId, $itemId, $userId)
    {
        try {
            // Find the person column (usually "Responsable")
            $columnsQuery = <<<GRAPHQL
            {
                boards(ids: [$boardId]) {
                    columns {
                        id
                        title
                        type
                    }
                }
            }
            GRAPHQL;

            $client = new MondayClient();
            $response = $client->query($columnsQuery);

            if ($response['status'] === 200 && !empty($response[0]['data']['boards'][0]['columns'])) {
                $columns = $response[0]['data']['boards'][0]['columns'];
                $personColumnId = null;

                foreach ($columns as $column) {
                    if ($column['title'] === 'Responsable' && $column['type'] === 'people') {
                        $personColumnId = $column['id'];
                        break;
                    }
                }

                if ($personColumnId) {
                    // Update the person column with the assigned user
                    $value = json_encode(['personsAndTeams' => [['id' => $userId, 'kind' => 'person']]]);

                    $mutation = <<<GRAPHQL
                    mutation {
                        change_multiple_column_values(item_id: $itemId, board_id: $boardId, column_values: "$value") {
                            id
                        }
                    }
                    GRAPHQL;

                    $client->query($mutation);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error assigning person to item', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Calculate the date for an item based on phase start and days between tasks
     */
    protected function calculateItemDate($items, $currentItem, $groupId, $phaseStartDate, $boardId)
    {
        // First item in a phase starts on the phase start date
        $isFirstItem = true;
        $previousItemDate = null;
        $daysToAdd = 0;

        // Get all items in the same group
        $groupItems = [];
        foreach ($items as $item) {
            if ($this->itemBelongsToGroup($item, $groupId, $boardId)) {
                $groupItems[] = $item;
            }
        }

        // Sort items by position in group
        // For simplicity, we'll use the item's ID as a proxy for position
        usort($groupItems, function ($a, $b) {
            return $a['id'] <=> $b['id'];
        });

        // Find the current item's position and the previous item's date
        foreach ($groupItems as $index => $item) {
            if ($item['id'] === $currentItem['id']) {
                if ($index > 0) {
                    $isFirstItem = false;

                    // Get the date of the previous item
                    $prevItem = $groupItems[$index - 1];
                    $prevItemDate = $this->getItemDate($prevItem);

                    if ($prevItemDate) {
                        $previousItemDate = Carbon::parse($prevItemDate);
                    }
                }

                // Get the days to add for the current item
                foreach ($currentItem['column_values'] as $columnValue) {
                    if ($columnValue['column']['title'] === 'Días entre tareas' && !empty($columnValue['text'])) {
                        $daysToAdd = (int)$columnValue['text'];
                        break;
                    }
                }

                break;
            }
        }

        // Calculate the item date
        if ($isFirstItem) {
            return $phaseStartDate;
        } elseif ($previousItemDate) {
            return $previousItemDate->addDays($daysToAdd);
        }

        return $phaseStartDate;
    }

    /**
     * Get the date value from an item
     */
    protected function getItemDate($item)
    {
        foreach ($item['column_values'] as $columnValue) {
            if ($columnValue['column']['title'] === 'Fecha' && !empty($columnValue['text'])) {
                return $columnValue['text'];
            }
        }

        return null;
    }

    /**
     * Update the date column of an item
     */
    protected function updateItemDate($boardId, $itemId, $date)
    {
        try {
            // Find the date column
            $columnsQuery = <<<GRAPHQL
            {
                boards(ids: [$boardId]) {
                    columns {
                        id
                        title
                        type
                    }
                }
            }
            GRAPHQL;

            $client = new MondayClient();
            $response = $client->query($columnsQuery);

            if ($response['status'] === 200 && !empty($response[0]['data']['boards'][0]['columns'])) {
                $columns = $response[0]['data']['boards'][0]['columns'];
                $dateColumnId = null;

                foreach ($columns as $column) {
                    if ($column['title'] === 'Fecha' && $column['type'] === 'date') {
                        $dateColumnId = $column['id'];
                        break;
                    }
                }

                if ($dateColumnId) {
                    // Format the date for Monday.com
                    $formattedDate = $date->format('Y-m-d');
                    $value = json_encode(['date' => $formattedDate]);

                    $mutation = <<<GRAPHQL
                    mutation {
                        change_column_value(item_id: $itemId, board_id: $boardId, column_id: "$dateColumnId", value: '$value') {
                            id
                        }
                    }
                    GRAPHQL;

                    $client->query($mutation);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error updating item date', ['exception' => $e->getMessage()]);
        }
    }
}
