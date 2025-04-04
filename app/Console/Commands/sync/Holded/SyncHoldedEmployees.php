<?php

namespace App\Console\Commands\sync\Holded;

use App\Services\Sync\Holded\HoldedEmployeeSyncService;
use Illuminate\Console\Command;

class SyncHoldedEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize holded employees from holded to local database';

    /**
     * The employee sync service
     *
     * @var HoldedEmployeeSyncService
     */
    protected $employeeSyncService;

    /**
     * Create a new command instance.
     *
     * @param HoldedEmployeeSyncService $employeeSyncService
     * @return void
     */
    public function __construct(HoldedEmployeeSyncService $employeeSyncService)
    {
        parent::__construct();
        $this->employeeSyncService = $employeeSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->employeeSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->employeeSyncService->syncEmployees();

        // Create progress bar for visual feedback if there are employees
        if (!empty($result['data']['employees'])) {
            $progress = $this->output->createProgressBar(count($result['data']['employees']));
            $progress->start();
            $progress->finish();
            $this->newLine(2);
        }

        return $result['success'] ? 0 : 1;
    }
}
