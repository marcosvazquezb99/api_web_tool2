<?php

namespace App\Console\Commands;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class changeDateReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:date-changes {channel_id? : The Slack channel ID to send the report to} {days_back=7 : Number of days back to include in the report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a report of date changes for Monday tasks within specified days and send it to a Slack channel';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $channelId = $this->argument('channel_id') ?? env('SLACK_DEFAULT_REPORT_CHANNEL');
        $daysBack = (int)$this->argument('days_back');

        if (!$channelId) {
            $this->error('No Slack channel ID provided. Please provide a channel ID as an argument or set SLACK_DEFAULT_REPORT_CHANNEL in your environment.');
            return 1;
        }

        $this->info('Generating date change report for the last ' . $daysBack . ' days...');

        // Query for date change events status completed or ongoing get sql query
        $cutoffDate = Carbon::now()->subDays($daysBack)->startOfDay();
        $events = Event::where(function ($query) {
            $query->where('status', 'completed')
                ->orWhere('status', 'ongoing');
        })
//                      ->where('category', 'date_change')
            ->where('source', 'monday')
            ->where('created_at', '>=', $cutoffDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $slackController = new SlackController();

        if ($events->isEmpty()) {
            $this->info('No date change events found in the last ' . $daysBack . ' days.');

            // Send message to Slack
            $slackController = new SlackController();
            $slackController->chat_post_message($channelId, "No date change events were found in the system for the last " . $daysBack . " days.");

            return 0;
        }
        foreach ($events as &$event) {
            $event->board_id = explode('_', $event->external_id)[0];
        }


        // Group events by board is in additional_data
        $boardGroups = $events->groupBy(
            function ($event) {
                $additionalData = json_decode($event->additional_data, true) ?: [];
                return $additionalData['board_id'] ?? null;
            }
        );
        $mondayController = new MondayController();

        // Prepare the report
        $report = "*Cambio de alcances desde {$cutoffDate->translatedFormat('d/m/y')}*\n\n";

        foreach ($boardGroups as $boardId => $boardEvents) {
            // Get board information
            $boardInfo = $mondayController->getBoardById($boardId)->getData()[0] ?? null;
            $boardName = $boardInfo ? $boardInfo->name : "Board ID: $boardId";

            $report .= "*Board: <$boardInfo->url|$boardName>*\n";

            unset($event);
            foreach ($boardEvents as $event) {
                $additionalData = json_decode($event->additional_data, true) ?: [];
                $userId = $additionalData['user_id'] ?? null;
                $reason = $additionalData['reason'] ?? 'No reason provided';
                $itemId = $event->external_id;
                $itemId = explode('_', $itemId)[1];
//                $itemId = $event->item_id;

                // Try to get item information
                $itemInfo = null;
                try {
                    $itemInfo = $mondayController->itemService->getItemById($itemId)[0]['data']['items'][0];
                    $itemInfo = (object)$itemInfo;
                } catch (\Exception $e) {
                    // Item might not exist anymore
                }

                $itemName = $itemInfo && isset($itemInfo->name) ? $itemInfo->name : "Item ID: $itemId";

                $newValue = $additionalData['new_value'] ?? null;



                // Get user who made the change
                $userInfo = null;
                if ($userId) {
                    try {
                        $userInfo = $mondayController->getUser($userId);
                    } catch (\Exception $e) {
                        // User might not exist anymore
                    }
                }

                $userName = $slackController->formatDisplayUser($userInfo);

                // Add to report
                $report .= "• Task: *<$itemInfo->url|$itemName>*\n";
                $report .= "  Changed by: *$userName* to $newValue\n";
                $report .= "  Reason: _\"$reason\"_\n\n";
            }

            $report .= "\n";
        }

        // Send the report to Slack
        $response = $slackController->chat_post_message($channelId, $report);

        if ($response == 200) {
            $this->info('Report successfully sent to Slack channel.');
        } else {
            $this->error('Failed to send report to Slack channel.');
        }

        return 0;
    }
}
