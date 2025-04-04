<?php

namespace App\Services\Sync;

use App\Http\Controllers\SlackController;
use App\Models\Boards;
use App\Models\SlackChannel;
use Exception;
use Illuminate\Support\Facades\Log;

class SlackChannelSyncService
{
    /**
     * Output callback function for logging/display
     *
     * @var callable|null
     */
    private $outputCallback = null;

    /**
     * Set output callback for messages
     *
     * @param callable $callback
     * @return $this
     */
    public function setOutputCallback(callable $callback)
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Send output message
     *
     * @param string $message
     * @param string $type info|error|warning
     * @return void
     */
    private function output($message, $type = 'info')
    {
        if (is_callable($this->outputCallback)) {
            call_user_func($this->outputCallback, $message, $type);
        }
    }

    /**
     * Synchronize Slack channels with database
     *
     * @return array Results of synchronization
     */
    public function sync()
    {
        try {
            $this->output('Starting Slack channel synchronization...');

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

            $this->output("Successfully synchronized {$count} Slack channels.");

            return [
                'success' => true,
                'message' => "Successfully synchronized {$count} Slack channels",
                'data' => [
                    'channelsCount' => $count,
                    'channels' => $slackChannels
                ]
            ];
        } catch (Exception $e) {
            $this->output('Error during Slack channel synchronization: ' . $e->getMessage(), 'error');
            Log::error('Slack channel sync error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error during Slack channel synchronization: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fetch channels from Slack API
     *
     * @return array List of channels with Monday.com board mappings
     */
    private function getSlackChannels()
    {
        $this->output('Fetching channels from Slack...');
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
                $this->output("Mapped channel '{$channel->name}' to Monday board ID: {$mondayBoardId}");
            } else {
                $this->output("No Monday board mapping found for channel: {$channel->name}", 'warning');
            }
        }

        $this->output('Retrieved ' . count($channels) . ' channels from Slack');
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
