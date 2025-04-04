<?php

namespace App\Console\Commands\sync\Monday;

use App\Services\Sync\Monday\BoardSyncService;
use Illuminate\Console\Command;

class SyncBoards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:boards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize boards from monday.com to local database';

    /**
     * The board sync service
     *
     * @var BoardSyncService
     */
    protected $boardSyncService;

    /**
     * Create a new command instance.
     *
     * @param BoardSyncService $boardSyncService
     * @return void
     */
    public function __construct(BoardSyncService $boardSyncService)
    {
        parent::__construct();
        $this->boardSyncService = $boardSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->boardSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Create a progress indicator for a better UX
        $this->output->newLine();

        // Run the sync with a delay of 3 seconds between pages
        $result = $this->boardSyncService->syncBoards(3);

        return $result['success'] ? 0 : 1;
    }
}
