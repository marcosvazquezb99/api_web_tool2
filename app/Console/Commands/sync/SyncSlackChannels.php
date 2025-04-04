<?php

namespace App\Console\Commands\sync;

use App\Services\Sync\SlackChannelSyncService;
use Illuminate\Console\Command;

class SyncSlackChannels extends Command
{
    protected $signature = 'sync:slack-channels';
    protected $description = 'Synchronize Slack channels with the database and map to Monday.com boards';

    /**
     * The slack channel sync service
     *
     * @var SlackChannelSyncService
     */
    protected $slackChannelSyncService;

    /**
     * Create a new command instance.
     *
     * @param SlackChannelSyncService $slackChannelSyncService
     * @return void
     */
    public function __construct(SlackChannelSyncService $slackChannelSyncService)
    {
        parent::__construct();
        $this->slackChannelSyncService = $slackChannelSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->slackChannelSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->slackChannelSyncService->sync();

        return $result['success'] ? 0 : 1;
    }
}
