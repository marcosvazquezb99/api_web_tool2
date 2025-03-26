<?php

namespace App\Console\Commands\sync;

use App\Http\Controllers\HolidaysController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * The holidays controller instance.
     */
    protected $holidaysController;

    /**
     * Create a new command instance.
     *
     * @param HolidaysController $holidaysController
     * @return void
     */
    public function __construct(HolidaysController $holidaysController)
    {
        parent::__construct();
        $this->holidaysController = $holidaysController;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $country = $this->option('country');
        $region = $this->option('region');
        $year = $this->option('year') ?: date('Y');
        $yearsToSync = $this->option('years');

        $this->info("Starting holiday synchronization for {$country}" . ($region ? "-{$region}" : ""));

        $successCount = 0;
        $errorCount = 0;

        // Create a progress bar
        $yearRange = range($year, $year + $yearsToSync - 1);
        $bar = $this->output->createProgressBar(count($yearRange));
        $bar->start();

        foreach ($yearRange as $yearToSync) {
            $this->syncHolidaysForYear($country, $yearToSync, $successCount, $errorCount);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // If region specified, try to sync specific regional holidays
        if ($region) {
            $this->info("Checking for specific holidays for region: {$region}");
            // This is where you would add region-specific logic if needed
            // Currently, the API handles regions through the counties field
        }

        $this->info("Holiday synchronization completed: {$successCount} successful, {$errorCount} errors");

        return Command::SUCCESS;
    }

    /**
     * Sync holidays for a specific year and country
     *
     * @param string $country
     * @param int $year
     * @param int &$successCount
     * @param int &$errorCount
     * @return void
     */
    private function syncHolidaysForYear($country, $year, &$successCount, &$errorCount)
    {
        try {
            // Create a request object to pass to the controller
            $request = new Request([
                'year' => $year,
                'country_code' => $country,
            ]);

            // Call the controller method to sync holidays
            $response = $this->holidaysController->syncHolidays($request);
            $data = json_decode($response->getContent(), true);

            if ($data['success']) {
                $successCount++;
                $this->info("\nSuccessfully synced {$data['count']} holidays for {$country} {$year}");
            } else {
                $errorCount++;
                $this->error("\nError syncing holidays for {$country} {$year}: {$data['message']}");
                Log::error("Holiday sync error for {$country} {$year}: {$data['message']}");
            }
        } catch (\Exception $e) {
            $errorCount++;
            $this->error("\nException syncing holidays for {$country} {$year}: {$e->getMessage()}");
            Log::error("Holiday sync exception for {$country} {$year}: {$e->getMessage()}");
        }
    }
}
