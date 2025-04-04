<?php

namespace App\Services\Monday;

use Illuminate\Support\Facades\Log;

class WebProjectService
{
    protected MondayClient $mondayClient;
    protected ItemService $itemService;
    protected GroupService $groupService;
    protected BoardService $boardService;
    protected string $templateBoardId = '1901599141'; // Template board ID

    public function __construct()
    {
        $this->mondayClient = new MondayClient();
        $this->itemService = new ItemService($this->mondayClient);
        $this->groupService = new GroupService($this->mondayClient);
        $this->boardService = new BoardService($this->mondayClient);
    }

    /**
     * Get board groups (phases) for the template board
     */
    public function getBoardGroups($boardId = null): array
    {
        if (!$boardId) {
            $boardId = $this->templateBoardId;
        }

        $response = $this->groupService->getGroupsOfBoard($boardId);

        if (!isset($response['data']['boards'][0]['groups'])) {
            return [];
        }

        return $response['data']['boards'][0]['groups'];
    }

    /**
     * Get team members from the template board
     */
    public function getTeamMembers($boardId = null): array
    {
        if (!$boardId) {
            $boardId = $this->templateBoardId;
        }

        // Get all teams from the template board's "Equipo" column
        $teams = $this->getTeams($boardId);
        $teamMembers = [];

        // Get all users in Monday.com
        $userService = new UserService($this->mondayClient);
        $usersResponse = $userService->getUsers(1, 100);

        if (!isset($usersResponse['data']['users'])) {
            return [];
        }

        $users = $usersResponse['data']['users'];

        // For each user, assign them to their respective teams
        foreach ($users as $user) {
            // Determine the user's team(s) - this might require additional logic based on your Monday.com setup
            // For now, we'll make them available for all teams
            foreach ($teams as $team) {
                $teamMembers[] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'team' => $team,
                    'email' => $user['email'] ?? '',
                    'photo' => $user['photo_thumb_small'] ?? ''
                ];
            }
        }

        return $teamMembers;
    }

    /**
     * Get all teams from the template board's "Equipo" column
     */
    public function getTeams($boardId = null): array
    {
        if (!$boardId) {
            $boardId = $this->templateBoardId;
        }

        // First get the columns to find the "Equipo" column ID
        $query = <<<GRAPHQL
        {
          boards(ids: ["$boardId"]) {
            columns {
              id
              title
              type
            }
          }
        }
        GRAPHQL;

        $columnsResponse = $this->mondayClient->query($query);
        $equipoColumnId = null;

        if (isset($columnsResponse['data']['boards'][0]['columns'])) {
            foreach ($columnsResponse['data']['boards'][0]['columns'] as $column) {
                if (strtolower($column['title']) === 'equipo') {
                    $equipoColumnId = $column['id'];
                    break;
                }
            }
        }

        if (!$equipoColumnId) {
            return ['Desarrollo', 'Diseño', 'Marketing', 'Contenido']; // Default fallback teams
        }

        // Now get all unique values from the "Equipo" column
        $query = <<<GRAPHQL
        {
          boards(ids: ["$boardId"]) {
            items {
              column_values(ids: ["$equipoColumnId"]) {
                text
              }
            }
          }
        }
        GRAPHQL;

        $response = $this->mondayClient->query($query);
        $teams = [];

        if (isset($response['data']['boards'][0]['items'])) {
            foreach ($response['data']['boards'][0]['items'] as $item) {
                if (!empty($item['column_values'][0]['text'])) {
                    $teamName = trim($item['column_values'][0]['text']);
                    if (!empty($teamName) && !in_array($teamName, $teams)) {
                        $teams[] = $teamName;
                    }
                }
            }
        }

        // If no teams were found, return default teams
        if (empty($teams)) {
            return ['Desarrollo', 'Diseño', 'Marketing', 'Contenido'];
        }

        return $teams;
    }

    /**
     * Create a new web project in Monday.com
     */
    public function createWebProject($projectName, $projectType, $phases, $teamAssignments)
    {
        try {
            // 1. Create a new board from template
            $newBoardName = $projectName;
            $duplicateBoardResponse = $this->boardService->duplicateBoard($this->templateBoardId, $newBoardName);

            if (!isset($duplicateBoardResponse['data']['duplicate_board']['board']['id'])) {
                throw new \Exception('Error creating the board from template');
            }

            $newBoardId = $duplicateBoardResponse['data']['duplicate_board']['board']['id'];
            $boardUrl = $duplicateBoardResponse['data']['duplicate_board']['board']['url'];

            // 2. Update phase dates
            foreach ($phases as $phaseId => $dates) {
                // Find group by ID in the new board
                $groupResponse = $this->groupService->getGroupsOfBoard($newBoardId);
                $groupFound = false;

                foreach ($groupResponse['data']['boards'][0]['groups'] as $group) {
                    if (strpos($group['id'], $phaseId) !== false) {
                        // Update all items in this group with the start and end dates
                        $itemsResponse = $this->groupService->getItemsInGroup($newBoardId, $group['id']);

                        if (isset($itemsResponse['data']['boards'][0]['groups'][0]['items'])) {
                            foreach ($itemsResponse['data']['boards'][0]['groups'][0]['items'] as $item) {
                                // Update start date if provided
                                if (isset($dates['start'])) {
                                    $this->itemService->changeColumnValue(
                                        $newBoardId,
                                        $item['id'],
                                        'date4',
                                        '{"date":"' . $dates['start'] . '"}'
                                    );
                                }

                                // Update end date if provided
                                if (isset($dates['end'])) {
                                    $this->itemService->changeColumnValue(
                                        $newBoardId,
                                        $item['id'],
                                        'date',
                                        '{"date":"' . $dates['end'] . '"}'
                                    );
                                }
                            }
                        }

                        $groupFound = true;
                        break;
                    }
                }

                if (!$groupFound) {
                    Log::warning("Group with ID containing '$phaseId' not found in the new board");
                }
            }

            // 3. Assign team members
            foreach ($teamAssignments as $team => $userId) {
                // Find items with this team and assign the selected person
                $itemsResponse = $this->itemService->getItemsByBoard($newBoardId);

                if (isset($itemsResponse['data']['boards'][0]['items'])) {
                    foreach ($itemsResponse['data']['boards'][0]['items'] as $item) {
                        // Check if this item has the team we're looking for
                        $teamFound = false;
                        foreach ($item['column_values'] as $columnValue) {
                            if ($columnValue['column']['title'] === 'Equipo' &&
                                strtolower($columnValue['text']) === strtolower($team)) {
                                $teamFound = true;
                                break;
                            }
                        }

                        if ($teamFound) {
                            // Assign the person to this item
                            $this->itemService->changeColumnValue(
                                $newBoardId,
                                $item['id'],
                                'person',
                                '{"personsAndTeams":[{"id":' . $userId . ',"kind":"person"}]}'
                            );
                        }
                    }
                }
            }

            return [
                'success' => true,
                'board_id' => $newBoardId,
                'board_url' => $boardUrl
            ];

        } catch (\Exception $e) {
            Log::error('Error creating web project', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
