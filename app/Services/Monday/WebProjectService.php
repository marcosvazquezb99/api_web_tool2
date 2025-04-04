<?php

namespace App\Services\Monday;

use Illuminate\Support\Facades\Log;

class WebProjectService
{
    protected MondayClient $mondayClient;
    protected ItemService $itemService;
    protected GroupService $groupService;
    protected BoardService $boardService;
    protected TeamService $teamService;
    protected string $templateBoardId = '1901599141'; // Template board ID

    public function __construct()
    {
        $this->mondayClient = new MondayClient();
        $this->itemService = new ItemService($this->mondayClient);
        $this->groupService = new GroupService($this->mondayClient);
        $this->boardService = new BoardService($this->mondayClient);
        $this->teamService = new TeamService($this->mondayClient);
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
     * Get team members from Monday.com teams
     */
    public function getTeamMembers(): array
    {
        // Get all team members organized by teams
        return $this->teamService->getTeamsWithUsers();
    }

    /**
     * Get all unique team names from Monday.com
     */
    public function getTeams(): array
    {
        $teams = $this->teamService->getTeams();
        $teamNames = [];

        foreach ($teams as $team) {
            if (!empty($team['name']) && !in_array($team['name'], $teamNames)) {
                $teamNames[] = $team['name'];
            }
        }

        // If no teams were found, return default teams as fallback
        if (empty($teamNames)) {
            return ['Desarrollo', 'Diseño', 'Marketing', 'Contenido'];
        }

        return $teamNames;
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
                $groupResponse = $this->groupService->getGroupsOfBoard($newBoardId)[0];
                $groupFound = false;

                foreach ($groupResponse['data']['boards'][0]['groups'] as $group) {
                    if (str_contains($group['id'], $phaseId)) {
                        // Update all items in this group with the start and end dates
                        $itemsResponse = $this->groupService->getItemsInGroup($newBoardId, $group['id'])[0];

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
                // Find items with this team and assign the selected person
                $cursor = null;
                do {
                    $itemsResponse = $this->itemService->getItemsByBoard($newBoardId, null, $cursor)[0];
                    $items = $itemsResponse['data']['boards'][0]['items_page']['items'];
                    $cursor = $itemsResponse['data']['boards'][0]['items_page']['cursor'];

                    if (isset($items)) {
                        foreach ($items as $item) {
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
                } while ($cursor);
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
