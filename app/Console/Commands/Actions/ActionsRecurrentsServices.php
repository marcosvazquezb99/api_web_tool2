<?php

namespace App\Console\Commands\Actions;

use App\Http\Actions\HolidayCheckerAction;
use App\Http\Controllers\Holded\DocumentsHoldedController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\ServiceController;
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
    private $holidays = [];

    /**
     * Holiday checker action
     *
     * @var HolidayCheckerAction
     */
    private $holidayChecker;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HolidayCheckerAction $holidayChecker = null)
    {
        parent::__construct();
        Carbon::setLocale('es');
        $services = ServiceController::types;

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
        $due = $now->copy()->addDays(-35);
        $documentsHoldedController = new DocumentsHoldedController();
        $documents = $documentsHoldedController->getDocuments('invoice', $due, $now);

        //RRSS
        $rrssColumnDateId = 'date';
        $rrssColumnStimatedDateId = 'texto__1';

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
                        // dd('RRSS');
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
    private function processSocialMediaService($contact, $contact_internal_id, $contact_name, $now, $dateColumnId, $estimatedDateColumnId)
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
//        dd($responseData);
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
            ->where('name', 'like', $contact_internal_id . "_%interno)")->first();

        if (!$mondayBoard) {
            $this->error("Client board not found for {$contact_internal_id}");
            return;
        }

        // Create new month group
        $nextMonth = $now->copy()->addMonth();
        $groupName = "{$contact->internal_id}_{$nextMonth->translatedFormat('F Y')}";
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
            $this->moveAndUpdateItem($mondayController, $item, $mondayBoard->id, $destinationGroupId, $estimatedDateColumnId, $dateColumnId, $now);
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
     * @param Carbon $now
     * @return void
     */
    private function moveAndUpdateItem($mondayController, $item, $boardId, $groupId, $estimatedDateColumnId, $dateColumnId, $now)
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
                        $dateStimated = Carbon::createFromDate($now->year, $now->month, $day);

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
}
