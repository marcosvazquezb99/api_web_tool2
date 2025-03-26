<?php

namespace App\Console\Commands\sync;

use App\Http\Controllers\SlackController;
use App\Models\Boards;
use App\Models\SlackChannel;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSlackChannels extends Command
{
    protected $signature = 'sync:slack-channels';
    protected $description = 'Synchronize Slack channels with the database and map to Monday.com boards';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $this->info('Starting Slack channel synchronization...');

            $slackChannels = $this->getSlackChannels();
            $count = 0;

            foreach ($slackChannels as $channel) {
                SlackChannel::updateOrCreate(
                    ['id' => $channel['id']],
                    [
                        'slack_channel_name' => $channel['name'],
                        'monday_board_id' => $channel['monday_board_id'],
                    ]
                );
                $count++;
            }

            $this->info("Successfully synchronized {$count} Slack channels.");
            return 0;
        } catch (Exception $e) {
            $this->error('Error during Slack channel synchronization: ' . $e->getMessage());
            Log::error('Slack channel sync error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    /**
     * Fetch channels from Slack API
     *
     * @return array List of channels with Monday.com board mappings
     */
    private function getSlackChannels()
    {
        $this->info('Fetching channels from Slack...');
        $slackController = new SlackController();
        $result = $slackController->conversations_list('exclude_archived=true&types=private_channel')->getData();

        $channels = [];
        foreach ($result->channels as $channel) {
            $mondayBoardId = $this->getMondayBoardId($channel->name);

            $channels[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'monday_board_id' => $mondayBoardId
            ];

            if ($mondayBoardId) {
                $this->info("Mapped channel '{$channel->name}' to Monday board ID: {$mondayBoardId}");
            } else {
                $this->warn("No Monday board mapping found for channel: {$channel->name}");
            }
        }

        $this->info('Retrieved ' . count($channels) . ' channels from Slack');
        return $channels;
    }

    /**
     * Find corresponding Monday.com board ID for a Slack channel
     *
     * @param string $slackChannelName The Slack channel name
     * @return int|null Monday.com board ID or null if not found
     */
    private function getMondayBoardId($slackChannelName)
    {
        // Try exact match first
        $board = Boards::where('name', $slackChannelName)->first();

        if (!$board) {
            // Try matching by first segment (before underscore)
            $parts = explode('_', $slackChannelName);
            $firstPart = $parts[0];

            if ($firstPart) {
                $board = Boards::where('name', 'like', $firstPart . '_%')->first();
            }
        }

        return $board ? $board->id : null;
    }
}
