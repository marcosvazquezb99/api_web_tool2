<?php

namespace App\Console\Commands\Actions;

use App\Http\Actions\HolidayCheckerAction;
use App\Http\Controllers\Holded\DocumentsHoldedController;
use App\Http\Controllers\MondayController;
use App\Models\Boards;
use App\Models\Client;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActionsRecurrentsServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'actions:recurrents-services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recurring services for clients';

    /**
     * Spanish holidays by year
     *
     * @var array
     */
    private array $holidays = [];

    /**
     * Holiday checker action
     *
     * @var HolidayCheckerAction
     */
    private HolidayCheckerAction $holidayChecker;

    /**
     * Tracks dates for which notifications have already been created
     *
     * @var array
     */
    private array $notificationDatesCreated = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HolidayCheckerAction $holidayChecker = null)
    {
        parent::__construct();
        Carbon::setLocale('es');

        // Initialize the holiday checker for Madrid
        $this->holidayChecker = $holidayChecker ?? new HolidayCheckerAction('ES');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = Carbon::today();
        $due = $now->copy()->subMonth();
        $documentsHoldedController = new DocumentsHoldedController();
        $documents = $documentsHoldedController->getDocuments('invoice', $due, $now);
        $now->subMonth();

        //RRSS
        $rrssColumnDateId = 'date';
        $rrssColumnStimatedDateId = 'texto__1';

        //Mantenimiento
        $maintenanceBoardId = 1451434435; // Replace with actual board ID
        $maintenanceColumnDateId = 'date';
        $maintenanceFrecuencyColumnId = 'color_mkpktq62';


        foreach ($documents as $document) {
            $document_id = $document['id'];
            $contact_name = $document['contactName'];
            $contact_id = $document['contact'];
            $contact = Client::where('holded_id', $contact_id)->first();

            if (is_null($contact) || $contact->internal_id == null) {
                continue;
            } else {
                $contact_internal_id = $contact->internal_id;
            }

            $products = $document['products'];
            foreach ($products as $product) {
                try {
                    $service_id = $product['serviceId'];
                } catch (\Exception $e) {
                    continue;
                }
                $databaseService = Service::where("holded_id", $service_id)->first();

                if (!$databaseService || !$databaseService->recurring) {
                    continue;
                }

                $type_service = $databaseService->type;
                if (is_null($type_service)) {
                    continue;
                }

                switch ($type_service) {
                    case 'redessociales':
                        $this->processSocialMediaService($contact, $contact_internal_id, $contact_name, $now, $rrssColumnDateId, $rrssColumnStimatedDateId);
//                        dd('RRSS');
                        break;
                    case 'mantenimiento':
                        $this->processMaintenanceService($contact, $contact_internal_id, $contact_name, $now, $maintenanceBoardId, $maintenanceColumnDateId, $maintenanceFrecuencyColumnId, $product);
                        //dd('Mantenimiento');
                        break;
                }
            }

            $this->info('Document ' . $contact_name . ' - ' . $document_id);
        }

        return 0;
    }

    /**
     * Process social media recurring service
     *
     * @param Client $contact
     * @param string $contact_internal_id
     * @param string $contact_name
     * @param Carbon $now
     * @param string $dateColumnId
     * @param string $estimatedDateColumnId
     * @return void
     */
    private function processSocialMediaService(Client $contact, string $contact_internal_id, string $contact_name, Carbon $now, string $dateColumnId, string $estimatedDateColumnId): void
    {
        $this->info("Processing social media service for client: {$contact_internal_id}");
        $mondayController = new MondayController();

        // Find the template board
        $template = Boards::where('name', 'like', 'Plantilla Clientes RRSS📱%')->first();
        if (!$template) {
            $this->error("Template board not found for social media services");
            return;
        }

        // Get all template groups and find matching one
        $response = $mondayController->getGroupsOfBoard($template->id);
        $responseData = $response->getData(true);

        $groups = $responseData[0]['groups'];
        $groupId = null;
        $defaultGroupId = null;

        foreach ($groups as $group) {
            $id = explode('_', $group['title'])[0];
            if ($id == $contact->internal_id) {
                $groupId = $group['id'];
                break;
            } elseif ($id == '*') {
                $defaultGroupId = $group['id'];
            }
        }

        // Use default group if no specific one was found
        if (!$groupId) {
            $groupId = $defaultGroupId;
        }

        if (!$groupId) {
            $this->error("No suitable group template found for client {$contact_internal_id}");
            return;
        }

        // Find client's board
        $mondayBoard = Boards::where('internal_id', $contact->internal_id)
            ->whereRaw("name REGEXP ?", ["^0*{$contact_internal_id}_.*interno\)$"])->first();

        if (!$mondayBoard) {
            $this->error("Client board not found for {$contact_internal_id}");
            return;
        }

        // Create new month group
        $nextMonth = $now->copy()->firstOfMonth()->addMonth();
//        dd($nextMonth);
        $groupName = ucfirst("{$nextMonth->translatedFormat('F Y')}");
        $response = $mondayController->createGroup($mondayBoard->id, $groupName);
        if (!isset($response[0]['data']['create_group']['id'])) {
            $this->error("Failed to create group for {$contact_internal_id}");
            return;
        }

        $destinationGroupId = $response[0]['data']['create_group']['id'];

        // Duplicate template group
        $response = $mondayController->duplicateGroup($template->id, $groupId, $contact_name);
        if (!isset($response[0]['data']['duplicate_group']['id'])) {
            $this->error("Failed to duplicate group for {$contact_internal_id}");
            return;
        }

        $newGroupId = $response[0]['data']['duplicate_group']['id'];

        // Get all items from the duplicated group
        $response = $mondayController->getItemsOfGroup($template->id, $newGroupId);
        $items = $response[0]['data']['boards'][0]['groups'][0]['items_page']['items'] ?? [];

        // Process and move each item
        foreach ($items as $item) {
            $this->moveAndUpdateItem($mondayController, $item, $mondayBoard->id, $destinationGroupId, $estimatedDateColumnId, $dateColumnId, $nextMonth);
        }

        // Cleanup: delete the temporary duplicated group
        $mondayController->deleteGroup($template->id, $newGroupId);
    }

    /**
     * Move an item to the client board and update the date
     *
     * @param MondayController $mondayController
     * @param array $item
     * @param int $boardId
     * @param string $groupId
     * @param string $estimatedDateColumnId
     * @param string $dateColumnId
     * @param Carbon $referencedStimatedDate
     * @return void
     */
    private function moveAndUpdateItem(MondayController $mondayController, array $item, int $boardId, string $groupId, string $estimatedDateColumnId, string $dateColumnId, Carbon $referencedStimatedDate): void
    {
        foreach ($item['column_values'] as $column) {
            if ($column['id'] == $estimatedDateColumnId) {
                // Move the item to the client's board
                $response = $mondayController->moveItemToBoard($boardId, $groupId, $item['id'])[0];

                if (!isset($response['data']['move_item_to_board']['id'])) {
                    $this->error("Failed to move item {$item['id']} to board {$boardId}");
                    continue;
                }

                // Update the deadline date if an estimated date is set
                if (isset($column['text']) && $column['text'] != '') {
                    try {
                        $day = explode('-', $column['text']);
                        if (count($day) == 1) {
                            $day = $day[0];
                        } else {

                            $day = random_int(trim($day[0]), trim($day[1]));
                            //$day = $day[2];
                        }
                        $dateStimated = Carbon::createFromDate($referencedStimatedDate->year, $referencedStimatedDate->month, $day);

                        // Check if date is a holiday or weekend, and adjust if necessary
                        $dateStimated = $this->holidayChecker->getNextBusinessDay($dateStimated);

                        $mondayController->changeColumnValue($boardId, $item['id'], $dateColumnId, $dateStimated->format('Y-m-d'));
                    } catch (\Exception $e) {
                        $this->error("Failed to update date for item {$item['id']}: " . $e->getMessage());
                    }
                }

                break;
            }
        }
    }

    /**
     * Process maintenance recurring service
     *
     * @param Client $contact
     * @param string $contact_internal_id
     * @param string $contact_name
     * @param Carbon $now
     * @param int $templateBoardId
     * @param string $dateColumnId
     * @param string $frequencyColumnId
     * @param array $product
     * @return void
     */
    private function processMaintenanceService(Client $contact, string $contact_internal_id, string $contact_name, Carbon $now, int $templateBoardId, string $dateColumnId, string $frequencyColumnId, array $product): void
    {
        $this->info("Processing maintenance service for client: {$contact_internal_id}");
        $mondayController = new MondayController();

        // Determine maintenance plan type from tags
        $planType = $this->getMaintenancePlanType($product);
        if (!$planType) {
            $this->error("No valid maintenance plan type found for client {$contact_internal_id}");
            return;
        }

        // Find the template board
        $template = Boards::find($templateBoardId);
        if (!$template) {
            $this->error("Template board not found for maintenance services");
            return;
        }

        // Get all template groups and find matching one for the plan type
        $response = $mondayController->getGroupsOfBoard($template->id);
        $responseData = $response->getData(true);

        $groups = $responseData[0]['groups'];
        $groupId = null;

        foreach ($groups as $group) {
            // Check if group title contains the plan type (case insensitive)
            if (stripos($group['title'], $planType) !== false) {
                $groupId = $group['id'];
                break;
            }
        }

        if (!$groupId) {
            $this->error("No matching template group found for maintenance plan: {$planType}");
            return;
        }

        // Find client's maintenance board
        $mondayBoard = Boards::where('internal_id', $contact->internal_id)
            ->whereRaw("name REGEXP ?", ["^0*{$contact_internal_id}_.*mantenimiento$"])->first();

        if (!$mondayBoard) {
            $this->error("Client maintenance board not found for {$contact_internal_id}");
            return;
        }

        // Create new month group or find existing one
        $nextMonth = $now->copy()->firstOfMonth()->addMonth();
        $groupName = ucfirst("{$nextMonth->translatedFormat('F Y')}");

        // Check if group already exists
        $existingGroups = $mondayController->getGroupsOfBoard($mondayBoard->id);
        $existingGroupsData = $existingGroups->getData(true);
        $destinationGroupId = null;

        foreach ($existingGroupsData[0]['groups'] as $group) {
            if ($group['title'] == $groupName) {
                $destinationGroupId = $group['id'];
                break;
            }
        }

        // Create new group if not exists
        if (!$destinationGroupId) {
            $response = $mondayController->createGroup($mondayBoard->id, $groupName);
            if (!isset($response[0]['data']['create_group']['id'])) {
                $this->error("Failed to create group for {$contact_internal_id}");
                return;
            }
            $destinationGroupId = $response[0]['data']['create_group']['id'];
        }

        // Duplicate template group
        $response = $mondayController->duplicateGroup($template->id, $groupId, $contact_name);
        if (!isset($response[0]['data']['duplicate_group']['id'])) {
            $this->error("Failed to duplicate group for {$contact_internal_id}");
            return;
        }

        $newGroupId = $response[0]['data']['duplicate_group']['id'];

        // Get all items from the duplicated group
        $response = $mondayController->getItemsOfGroup($template->id, $newGroupId);
        $items = $response[0]['data']['boards'][0]['groups'][0]['items_page']['items'] ?? [];

        // Process and move each item based on frequency
        foreach ($items as $item) {
            $this->processMaintenanceItem($mondayController, $item, $mondayBoard->id, $destinationGroupId, $dateColumnId, $frequencyColumnId, $nextMonth);
        }

        // Cleanup: delete the temporary duplicated group
        $mondayController->deleteGroup($template->id, $newGroupId);
    }

    /**
     * Determine the maintenance plan type from product tags or name
     *
     * @param array $product
     * @return string|null
     */
    private function getMaintenancePlanType(array $product): ?string
    {
        $validTypes = ['starter', 'standar', 'avanzado', 'custom', 'personalizado'];

        // First check in tags
        if (!empty($product['tags'])) {
            $tags = is_array($product['tags']) ? $product['tags'] : explode(',', $product['tags']);

            foreach ($tags as $tag) {
                $tag = strtolower(trim($tag));
                if (in_array($tag, $validTypes)) {
                    return $tag === 'personalizado' ? 'custom' : $tag;
                }
            }
        }

        // If not found in tags, check in product name
        if (!empty($product['name'])) {
            $name = strtolower($product['name']);

            foreach ($validTypes as $type) {
                if (str_contains($name, $type)) {
                    return $type === 'personalizado' ? 'custom' : $type;
                }
            }
        }

        return null;
    }

    /**
     * Process and move maintenance item based on frequency
     *
     * @param MondayController $mondayController
     * @param array $item
     * @param int $boardId
     * @param string $groupId
     * @param string $dateColumnId
     * @param string $frequencyColumnId
     * @param Carbon $nextMonth
     * @return void
     */
    private function processMaintenanceItem(MondayController $mondayController, array $item, int $boardId, string $groupId, string $dateColumnId, string $frequencyColumnId, Carbon $nextMonth): void
    {
        $frequency = null;

        // Extract the frequency from the item
        foreach ($item['column_values'] as $column) {
            if ($column['id'] == $frequencyColumnId && !empty($column['text'])) {
                $frequency = strtolower(trim($column['text']));
                break;
            }
        }

        if (!$frequency) {
            return; // Skip items without frequency
        }

        // Move the item to the client's board
        $response = $mondayController->moveItemToBoard($boardId, $groupId, $item['id'])[0];

        if (!isset($response['data']['move_item_to_board']['id'])) {
            $this->error("Failed to move item {$item['id']} to board {$boardId}");
            return;
        }

        $newItemId = $response['data']['move_item_to_board']['id'];

        // Set dates based on frequency
        switch ($frequency) {
            case 'diario':
                // For daily items, we need to create copies for each business day
                $this->processDailyItems($mondayController, $boardId, $groupId, $newItemId, $item['name'], $dateColumnId, $nextMonth);
                // Delete the original item as we created copies
                $mondayController->deleteItem($boardId, $newItemId);
                break;

            case 'semanal':
                // Weekly items - create one for each week
                $this->processWeeklyItems($mondayController, $boardId, $newItemId, $dateColumnId, $nextMonth, 1, $groupId);
                break;

            case 'bisemanal':
                // Biweekly items - create items for 1st and 3rd weeks
                $this->processWeeklyItems($mondayController, $boardId, $newItemId, $dateColumnId, $nextMonth, 2, $groupId);
                break;

            case 'mensual':
                // Monthly items go on the last Wednesday of the month
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                // Create notification item
                $this->createNotificationItem($mondayController, $boardId, $groupId, $newItemId, $dateColumnId, $date);
                break;

            case 'bimensual':
                // Every two months - if it's an even month
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                if ($nextMonth->month % 2 == 0) {
                    $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                } else {
                    // Delete the item for odd months
                    $mondayController->deleteItem($boardId, $newItemId);
                }
                break;

            case 'trimestral':
                // Every three months - if it's a quarter month (Jan, Apr, Jul, Oct)
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                if ($nextMonth->month % 3 == 1) {
                    $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                } else {
                    // Delete the item for non-quarter months
                    $mondayController->deleteItem($boardId, $newItemId);
                }
                break;

            case 'semestral':
                // Every six months - if it's January or July
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                if ($nextMonth->month == 1 || $nextMonth->month == 7) {
                    $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                } else {
                    // Delete the item for other months
                    $mondayController->deleteItem($boardId, $newItemId);
                }
                break;

            case 'anual':
                // Yearly - if it's January
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                if ($nextMonth->month == 1) {
                    $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                } else {
                    // Delete the item for other months
                    $mondayController->deleteItem($boardId, $newItemId);
                }
                break;

            default:
                // For unknown frequencies, default to the last Wednesday
                $date = $this->getLastWednesdayOfMonth($nextMonth);
                $mondayController->changeColumnValue($boardId, $newItemId, $dateColumnId, $date->format('Y-m-d'));
                break;
        }
    }

    /**
     * Process weekly or biweekly maintenance items
     *
     * @param MondayController $mondayController
     * @param int $boardId
     * @param string $itemId
     * @param string $dateColumnId
     * @param Carbon $month
     * @param int $interval
     * @param $groupId
     * @return void
     */
    private function processWeeklyItems(MondayController $mondayController, int $boardId, string $itemId, string $dateColumnId, Carbon $month, int $interval, $groupId): void
    {
        // Get number of weeks in the month
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        // Set first date to the first Wednesday
        $currentDate = $this->getWednesdayOfFirstWeek($month);
        $firstItem = true;

        while ($currentDate->month == $month->month) {
            if ($firstItem) {
                // Update the first item
                $lastModifiedItem = $mondayController->changeColumnValue($boardId, $itemId, $dateColumnId, $currentDate->format('Y-m-d'))[0];
                $lastModifiedItemName = $lastModifiedItem['data']['change_simple_column_value']['name'];
                // Create notification item for the first task
                $this->createNotificationItem($mondayController, $boardId, $groupId, $itemId, $dateColumnId, $currentDate);
                $firstItem = false;
            } else {
                // Duplicate the item for subsequent weeks
                $dupResponse = $mondayController->duplicateItem($boardId, $itemId)[0];
                if (isset($dupResponse['data']['duplicate_item']['id'])) {
                    $newId = $dupResponse['data']['duplicate_item']['id'];
                    $mondayController->changeColumnValue($boardId, $newId, $dateColumnId, $currentDate->format('Y-m-d'));
                    if (isset($lastModifiedItemName)) {
                        // Create notification item for each duplicated task
                        $mondayController->changeColumnValue($boardId, $newId, 'name', $lastModifiedItemName);

                    }
                    // Create notification item for each duplicated task
                    $this->createNotificationItem($mondayController, $boardId, $groupId, $newId, $dateColumnId, $currentDate);
                }
            }

            // Move to next week or skip based on interval
            $currentDate->addWeeks($interval);
        }
    }

    /**
     * Process daily maintenance items by creating an item for each business day
     *
     * @param MondayController $mondayController
     * @param int $boardId
     * @param string $groupId
     * @param string $itemId
     * @param string $itemName
     * @param string $dateColumnId
     * @param Carbon $month
     * @return void
     */
    private function processDailyItems(MondayController $mondayController, int $boardId, string $groupId, string $itemId, $itemName, string $dateColumnId, Carbon $month): void
    {
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();
        $currentDate = $startDate->copy();
        $firstItem = true;

        while ($currentDate <= $endDate) {
            // Check if it's a business day (not holiday and not weekend)
            if (!$this->holidayChecker->isHoliday($currentDate) && !$currentDate->isWeekend()) {
                if ($firstItem) {
                    // Update the first item
                    $mondayController->changeColumnValue($boardId, $itemId, $dateColumnId, $currentDate->format('Y-m-d'));
                    // Create notification item for the first task
                    $this->createNotificationItem($mondayController, $boardId, $groupId, $itemId, $dateColumnId, $currentDate);
                    $firstItem = false;
                } else {
                    // Duplicate the item for subsequent days
                    $dupResponse = $mondayController->duplicateItem($boardId, $itemId)[0];
                    if (isset($dupResponse['data']['duplicate_item']['id'])) {
                        $newId = $dupResponse['data']['duplicate_item']['id'];
                        $mondayController->changeColumnValue($boardId, $newId, $dateColumnId, $currentDate->format('Y-m-d'));
                        // Create notification item for each duplicated task
                        $this->createNotificationItem($mondayController, $boardId, $groupId, $newId, $dateColumnId, $currentDate);
                    }
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Create a notification item to remind about upcoming maintenance
     *
     * @param MondayController $mondayController
     * @param int $boardId
     * @param string $groupId
     * @param string $relatedItemId
     * @param string $dateColumnId
     * @param Carbon $maintenanceDate
     * @return void
     */
    private function createNotificationItem(MondayController $mondayController, int $boardId, string $groupId, string $relatedItemId, string $dateColumnId, Carbon $maintenanceDate): void
    {
        // Prepare notification date (2 days before maintenance)
        $notificationDate = $maintenanceDate->copy()->subDays(2);

        // Make sure notification date is a business day
        $notificationDate = $this->holidayChecker->getPreviousBusinessDay($notificationDate);

        // If notification date is in the past, skip creating it
        /*if ($notificationDate < Carbon::today()) {
            return;
        }*/

        // Check if we've already created a notification for this date
        $dateKey = $boardId . '_' . $notificationDate->format('Y-m-d');
        if (in_array($dateKey, $this->notificationDatesCreated)) {
            return; // Skip if notification for this date already exists
        }

        // Get the group ID if not provided
        if (!$groupId) {
            $response = $mondayController->getItemById($relatedItemId)[0];
            if (isset($response['data']['items'][0]['group']['id'])) {
                $groupId = $response['data']['items'][0]['group']['id'];
            } else {
                return; // Can't create notification without group ID
            }
        }

        // Create a new item for notification
        $response = $mondayController->createItem($boardId, $groupId, "Avisar al cliente de mantenimiento")[0];
        if (!isset($response['data']['create_item']['id'])) {
            $this->error("Failed to create notification item for maintenance task");
            return;
        }

        $notificationItemId = $response['data']['create_item']['id'];

        // Set the notification date
        $mondayController->changeColumnValue($boardId, $notificationItemId, $dateColumnId, $notificationDate->format('Y-m-d'));

        // Mark this date as having a notification
        $this->notificationDatesCreated[] = $dateKey;
    }

    /**
     * Get the previous business day
     *
     * @param Carbon $date
     * @return Carbon
     */
    private function getPreviousBusinessDay(Carbon $date): Carbon
    {
        $prevDay = $date->copy();

        // Go back until we find a business day
        while ($prevDay->isWeekend() || $this->holidayChecker->isHoliday($prevDay)) {
            $prevDay->subDay();
        }

        return $prevDay;
    }

    /**
     * Get the Wednesday of the first week of the month
     *
     * @param Carbon $month
     * @return Carbon
     */
    private function getWednesdayOfFirstWeek(Carbon $month): Carbon
    {
        $date = $month->copy()->startOfMonth();

        // Find the first Wednesday (day 3) of the month
        while ($date->dayOfWeek != Carbon::WEDNESDAY) {
            $date->addDay();
        }

        // Ensure it's a business day
        return $this->holidayChecker->getNextBusinessDay($date);
    }

    /**
     * Get the last Wednesday of the month
     *
     * @param Carbon $month
     * @return Carbon
     */
    private function getLastWednesdayOfMonth(Carbon $month): Carbon
    {
        $date = $month->copy()->endOfMonth();

        // Find the last Wednesday (day 3) of the month
        while ($date->dayOfWeek != Carbon::WEDNESDAY) {
            $date->subDay();
        }

        // Ensure it's a business day
        return $this->holidayChecker->getNextBusinessDay($date);
    }
}
