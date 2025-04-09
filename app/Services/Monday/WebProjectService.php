<?php

namespace App\Services\Monday;

use App\Http\Actions\HolidayCheckerAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WebProjectService
{
    protected MondayClient $mondayClient;
    protected ItemService $itemService;
    protected GroupService $groupService;
    protected BoardService $boardService;
    protected TeamService $teamService;
    /**
     * Holiday checker action
     *
     * @var HolidayCheckerAction
     */
    private HolidayCheckerAction $holidayChecker;
    protected string $templateBoardId = '1901599141'; // Template board ID

    public function __construct()
    {
        $this->mondayClient = new MondayClient();
        $this->itemService = new ItemService($this->mondayClient);
        $this->groupService = new GroupService($this->mondayClient);
        $this->boardService = new BoardService($this->mondayClient);
        $this->teamService = new TeamService($this->mondayClient);
        // Initialize the holiday checker for Madrid
        $this->holidayChecker = $holidayChecker ?? new HolidayCheckerAction('ES');
    }

    /**
     * Get board groups (phases) for the template board
     */
    public function getBoardGroups($boardId = null): array
    {
        if (!$boardId) {
            $boardId = $this->templateBoardId;
        }

        $response = $this->groupService->getGroupsOfBoard($boardId)[0];

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
     * @param string $projectName
     * @param string $projectType
     * @param array $phases
     * @param array $teamAssignments
     * @return array
     */
    public function createWebProject(string $projectName, string $projectType, array $phases, array $teamAssignments): array
    {
        try {
            // 1. Create a new board from template
            $newBoardName = $projectName;
            $duplicateBoardResponse = $this->boardService->duplicateBoard($this->templateBoardId, $newBoardName)[0];
            sleep(5); // Wait for the board to be created
//dd(!isset($duplicateBoardResponse['data']['duplicate_board']['board']['id']));
            if (!isset($duplicateBoardResponse['data']['duplicate_board']['board']['id'])) {
                throw new \Exception('Error creating the board from template');
            }

//            $newBoardId = '1906498362';// Temporal // $duplicateBoardResponse['data']['duplicate_board']['board']['id'];
            $newBoardId = $duplicateBoardResponse['data']['duplicate_board']['board']['id'];
//            $boardUrl = 'https://somospecesvoladores.monday.com/boards/1906498362'; //Temporal $duplicateBoardResponse['data']['duplicate_board']['board']['url'];
            $boardUrl = $duplicateBoardResponse['data']['duplicate_board']['board']['url'];

            // Filter items based on project type
            $this->filterItemsByProjectType($newBoardId, $projectType);

            // 2. Update phase dates with dependencies
            $this->updatePhaseDatesWithDependencies($newBoardId, $phases);

            // 3. Assign team members
            $this->assignTeamMembers($newBoardId, $teamAssignments, 'multiple_person_mkppcp3k');

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

    /**
     * Filter items in a board based on project type
     *
     * @param string $boardId
     * @param string $projectType
     * @return void
     */
    private function filterItemsByProjectType(string $boardId, string $projectType): void
    {
        $cursor = null;
        $itemsToDelete = [];
        do {
            $itemsResponse = $this->itemService->getItemsByBoard($boardId, null, $cursor)[0];
            // dd($itemsResponse);

            if (isset($itemsResponse['data']['boards'][0]['items_page']['items'])) {
                foreach ($itemsResponse['data']['boards'][0]['items_page']['items'] as $item) {
                    $matchesProjectType = false;
                    foreach ($item['column_values'] as $columnValue) {
                        // dd($columnValue);
                        // Check if this is a label column with project type information
                        if ($columnValue['type'] === 'tags') {
                            // Check if the project type is in the labels
//                            dd($columnValue);
                            if (!empty($columnValue['text']) &&
                                stripos($columnValue['text'], $projectType) !== false) {
                                $matchesProjectType = true;
                                break;
                            }
                        }
                    }
                    if (!$matchesProjectType) {
                        $itemsToDelete[] = $item['id'];
                    }
                }
            }
            $cursor = $itemsResponse['data']['boards'][0]['items_page']['cursor'] ?? null;
        } while ($cursor);

        // Delete items that don't match the project type
        foreach ($itemsToDelete as $itemId) {
            $this->itemService->deleteItem($boardId, $itemId);
        }
    }

    /**
     * Update phase dates with dependencies
     *
     * @param string $boardId
     * @param array $phases
     * @param string|null $startingItemId Optional item to start date updates from
     * @return void
     */
    private function updatePhaseDatesWithDependencies(string $boardId, array $phases, string $startingItemId = null): void
    {
        $daysBetweenDatesColumnId = 'numeric_mkpp70x';
        $estimatedDateColumnId = 'date';
        $groupsResponse = $this->groupService->getGroupsOfBoard($boardId)[0];

        if (!isset($groupsResponse['data']['boards'][0]['groups'])) {
            Log::warning("No groups found in board $boardId");
            return;
        }

        $groups = $groupsResponse['data']['boards'][0]['groups'];
        $lastGroupEndDate = null;
        $startProcessingFromItem = ($startingItemId === null);
//        dd($phases);
        // Process groups in order
        foreach ($groups as $index => $group) {
            $groupId = $group['id'];
            $dates = $phases[$groupId];

            // Check if we need to adjust dates based on the previous group's end date
            if ($index > 0 && $lastGroupEndDate && isset($dates['start'])) {
                $currentGroupStart = Carbon::create($dates['start']);

                // If current group's start date is earlier than previous group's end date, adjust it
                if ($currentGroupStart->lt($lastGroupEndDate)) {
                    $dates['start'] = $lastGroupEndDate->format('Y-m-d');
                    $dates['end'] = $lastGroupEndDate->copy()->addDays(7)->format('Y-m-d'); // Add a default duration

                    // Update the group name with new dates
                    if (isset($dates['start']) && isset($dates['end'])) {
                        $groupName = $group['title'];
                        if (preg_match('/^(.*?)(\s*\(.*\)\s*)?$/', $groupName, $matches)) {
                            $baseName = $matches[1];
                            $groupName = $baseName . ' (' . $dates['start'] . ' - ' . $dates['end'] . ')';
                            $this->groupService->updateGroupTitle($boardId, $groupId, $groupName);
                        }
                    }
                }
            }

            // Update group title with dates
            if (isset($dates['start']) && isset($dates['end'])) {
                $groupName = $group['title'];
                if (preg_match('/^(.*?)(\s*\(.*\)\s*)?$/', $groupName, $matches)) {
                    $baseName = $matches[1];
                    $groupName = $baseName . ' (' . $dates['start'] . ' - ' . $dates['end'] . ')';
                    $this->groupService->updateGroupTitle($boardId, $groupId, $groupName);
                }
            }

            // Update items in this group
            $groupItems = $this->getItemsInGroup($boardId, $groupId);
            $lastItemDate = null;
            $itemStartDate = Carbon::create($dates['start']);
            foreach ($groupItems as $item) {
                // If we're waiting for a specific item to start processing
                if (!$startProcessingFromItem && $startingItemId !== null) {
                    if ($item['id'] === $startingItemId) {
                        $startProcessingFromItem = true;
                    } else {
                        continue;
                    }
                }

                // Update item date
                if (isset($dates['start'])) {


                    // Apply offset from days_between_dates column if present
                    $daysBetweenDates = 0;
                    foreach ($item['column_values'] as $columnValue) {
                        if ($columnValue['id'] === $daysBetweenDatesColumnId && !empty($columnValue['text'])) {
                            $daysBetweenDates = (int)$columnValue['text'];
                            break;
                        }
                    }

                    if ($daysBetweenDates > 0 && $lastItemDate) {
                        $itemStartDate = $lastItemDate->copy()->addDays($daysBetweenDates);
                    } elseif ($daysBetweenDates > 0) {
                        $itemStartDate = $itemStartDate->addDays($daysBetweenDates);
                    }

                    // Ensure it's a business day
                    if ($itemStartDate->isWeekend() || $this->holidayChecker->isHoliday($itemStartDate)) {
                        $itemStartDate = $this->holidayChecker->getNextBusinessDay($itemStartDate);
                    }

                    // Update the item date
                    $this->itemService->changeSimpleColumnValue(
                        $boardId,
                        $item['id'],
                        $estimatedDateColumnId,
                        $itemStartDate->format('Y-m-d')
                    );

                    // Update last item date for next item in sequence
                    $lastItemDate = $itemStartDate;
                }
            }

            // Store the last date of this group for the next group's dependency check
            if ($lastItemDate) {
                $lastGroupEndDate = $lastItemDate;
            } elseif (isset($dates['end'])) {
                $lastGroupEndDate = Carbon::create($dates['end']);
            }
        }
    }

    /**
     * Get items in a group
     *
     * @param string $boardId
     * @param string $groupId
     * @return array
     */
    private function getItemsInGroup(string $boardId, string $groupId): array
    {
        $cursor = null;
        $items = [];

        do {
            $response = $this->groupService->getItemsInGroup($boardId, $groupId, $cursor)[0];

            if (isset($response['data']['boards'][0]['groups'][0]['items_page']['items'])) {
                $items = array_merge($items, $response['data']['boards'][0]['groups'][0]['items_page']['items']);
            }

            $cursor = $response['data']['boards'][0]['groups'][0]['items_page']['cursor'] ?? null;
        } while ($cursor !== null);

        return $items;
    }

    /**
     * Extract phase ID from group ID
     *
     * @param string $groupId
     * @return string|null
     */
    private function getPhaseIdFromGroupId(string $groupId): ?string
    {
        // Phases are expected to have their ID as part of the group ID
        foreach (['planning', 'design', 'development', 'content', 'testing', 'qa', 'launch'] as $phaseId) {
            if (str_contains($groupId, $phaseId)) {
                return $phaseId;
            }
        }
        return null;
    }

    /**
     * Assign team members to items
     *
     * @param string $boardId
     * @param array $teamAssignments
     * @param string $referenceColumnId
     * @return void
     */
    private function assignTeamMembers(string $boardId, array $teamAssignments, string $referenceColumnId): void
    {

        foreach ($teamAssignments as $team => $userId) {
            // Find items with this team and assign the selected person
            $cursor = null;
            do {
                $itemsResponse = $this->itemService->getItemsByBoard($boardId, null, $cursor)[0];
                $items = $itemsResponse['data']['boards'][0]['items_page']['items'] ?? [];
                $cursor = $itemsResponse['data']['boards'][0]['items_page']['cursor'] ?? null;

                foreach ($items as $item) {
                    // Check if this item has the team we're looking for
                    $teamFound = false;
                    foreach ($item['column_values'] as $columnValue) {
//                        dd($columnValue);
                        if ($columnValue['id'] === $referenceColumnId &&
                            isset($columnValue['value']) &&
                            json_decode($columnValue['value'], true)['personsAndTeams'][0]['id'] == strtolower($team)) {
                            $teamFound = true;
                            break;
                        }
                    }

                    if ($teamFound) {
                        // Assign the person to this item
                        $this->itemService->changeSimpleColumnValue(
                            $boardId,
                            $item['id'],
                            'person',
                            $userId
                        );
                    }
                }
            } while ($cursor);
        }
    }

    /**
     * Update dates starting from a specific item
     *
     * @param string $boardId
     * @param array $phases
     * @param string $startingItemId
     * @return array
     */
    public function updateDatesFromItem(string $boardId, array $phases, string $startingItemId): array
    {
        try {
            $this->updatePhaseDatesWithDependencies($boardId, $phases, $startingItemId);

            return [
                'success' => true,
                'message' => 'Dates updated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Error updating dates from item', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
