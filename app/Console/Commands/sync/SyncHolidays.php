<?php

namespace App\Console\Commands\sync;

use App\Services\Sync\HolidaySyncService;
use Illuminate\Console\Command;

class SyncHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:holidays
                            {--country=ES : The country code to sync holidays for (default: ES for Spain)}
                            {--region= : Specific region to focus on (e.g., MD for Madrid)}
                            {--year= : The year to sync holidays for (default: current year)}
                            {--years=1 : Number of years to sync (including specified year)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize holidays from external API for specified country and year. Example: php artisan sync:holidays --country=ES --region=MD --year=2023 --years=2';

    /**
     * The holiday sync service
     *
     * @var HolidaySyncService
     */
    protected $holidaySyncService;

    /**
     * Create a new command instance.
     *
     * @param HolidaySyncService $holidaySyncService
     * @return void
     */
    public function __construct(HolidaySyncService $holidaySyncService)
    {
        parent::__construct();
        $this->holidaySyncService = $holidaySyncService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get options
        $country = $this->option('country');
        $region = $this->option('region');
        $year = $this->option('year') ?: date('Y');
        $yearsToSync = $this->option('years');

        // Create a progress callback
        $yearRange = range($year, $year + $yearsToSync - 1);
        $bar = $this->output->createProgressBar(count($yearRange));
        $bar->start();

        // Configure the output callback
        $this->holidaySyncService->setOutputCallback(function ($message, $type = 'info') use ($bar) {
            // Pause the progress bar to output messages
            $bar->clear();

            if ($type === 'error') {
                $this->error($message);
            } elseif ($type === 'warning') {
                $this->warn($message);
            } else {
                $this->info($message);
            }

            // Redraw the progress bar
            $bar->display();
        });

        // Run the sync
        $result = $this->holidaySyncService->syncHolidays($country, $region, $year, $yearsToSync);

        $bar->finish();
        $this->newLine(2);

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
