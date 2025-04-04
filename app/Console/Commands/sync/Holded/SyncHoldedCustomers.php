<?php

namespace App\Console\Commands\sync\Holded;

use App\Services\Sync\Holded\HoldedCustomerSyncService;
use Illuminate\Console\Command;

class SyncHoldedCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize holded customers from holded to local database';

    /**
     * The customer sync service
     *
     * @var HoldedCustomerSyncService
     */
    protected $customerSyncService;

    /**
     * Create a new command instance.
     *
     * @param HoldedCustomerSyncService $customerSyncService
     * @return void
     */
    public function __construct(HoldedCustomerSyncService $customerSyncService)
    {
        parent::__construct();
        $this->customerSyncService = $customerSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->customerSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->customerSyncService->syncCustomers();

        // Create progress bar for visual feedback if there are clients
        if (!empty($result['data']['clients'])) {
            $progress = $this->output->createProgressBar(count($result['data']['clients']));
            $progress->start();
            $progress->finish();
            $this->newLine(2);
        }

        return $result['success'] ? 0 : 1;
    }
}
