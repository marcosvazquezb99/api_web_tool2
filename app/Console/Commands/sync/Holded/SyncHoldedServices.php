<?php

namespace App\Console\Commands\sync\Holded;

use App\Services\Sync\Holded\HoldedServiceSyncService;
use Illuminate\Console\Command;

class SyncHoldedServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holded-services';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los servicios desde Holded a la base de datos local';

    /**
     * The service sync service
     *
     * @var HoldedServiceSyncService
     */
    protected $serviceSyncService;

    /**
     * Create a new command instance.
     *
     * @param HoldedServiceSyncService $serviceSyncService
     * @return void
     */
    public function __construct(HoldedServiceSyncService $serviceSyncService)
    {
        parent::__construct();
        $this->serviceSyncService = $serviceSyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Configure the output callback
        $this->serviceSyncService->setOutputCallback(function ($message, $type = 'info') {
            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }
        });

        // Run the sync
        $result = $this->serviceSyncService->syncServices();

        // Create progress bar for visual feedback
        if (!empty($result['data']['services'])) {
            $progress = $this->output->createProgressBar(count($result['data']['services']));
            $progress->start();
            $progress->finish();
            $this->newLine(2);
        }

        return $result['success'] ? 0 : 1;
    }
}
