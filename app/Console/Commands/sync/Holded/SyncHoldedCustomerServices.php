<?php

namespace App\Console\Commands\sync\Holded;

use App\Services\Sync\Holded\HoldedCustomerServiceSyncService;
use Illuminate\Console\Command;

class SyncHoldedCustomerServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-customerservices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los servicios recurrentes de clientes desde Holded';

    /**
     * The customer service sync service
     *
     * @var HoldedCustomerServiceSyncService
     */
    protected $customerServiceSyncService;

    /**
     * Create a new command instance.
     *
     * @param HoldedCustomerServiceSyncService $customerServiceSyncService
     * @return void
     */
    public function __construct(HoldedCustomerServiceSyncService $customerServiceSyncService)
    {
        parent::__construct();
        $this->customerServiceSyncService = $customerServiceSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->customerServiceSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->customerServiceSyncService->syncCustomerServices();

        // Create progress bar for visual feedback if there are documents
        if (!empty($result['data']['documents'])) {
            $progress = $this->output->createProgressBar(count($result['data']['documents']));
            $progress->start();
            $progress->finish();
            $this->newLine(2);
        }

        return $result['success'] ? 0 : 1;
    }
}
