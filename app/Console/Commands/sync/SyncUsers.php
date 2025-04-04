<?php

namespace App\Console\Commands\sync;

use App\Services\Sync\UserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize users from Monday.com and Slack with local database';

    /**
     * The user sync service
     *
     * @var UserSyncService
     */
    protected $userSyncService;

    /**
     * Create a new command instance.
     *
     * @param UserSyncService $userSyncService
     * @return void
     */
    public function __construct(UserSyncService $userSyncService)
    {
        parent::__construct();
        $this->userSyncService = $userSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->userSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->userSyncService->syncAll();

        return $result['success'] ? 0 : 1;
    }
}
