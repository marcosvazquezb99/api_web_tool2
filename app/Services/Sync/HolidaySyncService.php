<?php

namespace App\Services\Sync;

use App\Http\Controllers\HolidaysController;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HolidaySyncService
{
    /**
     * The holidays controller instance.
     */
    protected $holidaysController;

    /**
     * Output callback function for logging/display
     *
     * @var callable|null
     */
    private $outputCallback = null;

    /**
     * Create a new service instance.
     *
     * @param HolidaysController $holidaysController
     * @return void
     */
    public function __construct(HolidaysController $holidaysController)
    {
        $this->holidaysController = $holidaysController;
    }

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
     * Synchronize holidays for a country and year range
     *
     * @param string $country Country code (e.g., 'ES' for Spain)
     * @param string|null $region Region code (e.g., 'MD' for Madrid)
     * @param int|null $year Starting year (defaults to current year)
     * @param int $yearsToSync Number of years to sync (including the start year)
     * @return array Synchronization results
     */
    public function syncHolidays($country = 'ES', $region = null, $year = null, $yearsToSync = 1)
    {
        $year = $year ?: date('Y');

        $this->output("Starting holiday synchronization for {$country}" . ($region ? "-{$region}" : ""));

        $successCount = 0;
        $errorCount = 0;
        $results = [];

        // Create year range
        $yearRange = range($year, $year + $yearsToSync - 1);

        foreach ($yearRange as $yearToSync) {
            $yearResult = $this->syncHolidaysForYear($country, $yearToSync, $successCount, $errorCount);
            $results[$yearToSync] = $yearResult;
        }

        // If region specified, try to sync specific regional holidays
        if ($region) {
            $this->output("Checking for specific holidays for region: {$region}");
            // This is where you would add region-specific logic if needed
        }

        $this->output("Holiday synchronization completed: {$successCount} successful, {$errorCount} errors");

        return [
            'success' => $errorCount === 0,
            'message' => "Holiday synchronization completed: {$successCount} successful, {$errorCount} errors",
            'data' => [
                'country' => $country,
                'region' => $region,
                'years' => $yearRange,
                'results' => $results,
                'summary' => [
                    'successCount' => $successCount,
                    'errorCount' => $errorCount
                ]
            ]
        ];
    }

    /**
     * Sync holidays for a specific year and country
     *
     * @param string $country
     * @param int $year
     * @param int &$successCount
     * @param int &$errorCount
     * @return array Year-specific results
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
                $this->output("Successfully synced {$data['count']} holidays for {$country} {$year}");
                return [
                    'success' => true,
                    'count' => $data['count'],
                    'message' => "Successfully synced {$data['count']} holidays for {$country} {$year}"
                ];
            } else {
                $errorCount++;
                $this->output("Error syncing holidays for {$country} {$year}: {$data['message']}", 'error');
                Log::error("Holiday sync error for {$country} {$year}: {$data['message']}");
                return [
                    'success' => false,
                    'message' => "Error syncing holidays for {$country} {$year}: {$data['message']}"
                ];
            }
        } catch (Exception $e) {
            $errorCount++;
            $this->output("Exception syncing holidays for {$country} {$year}: {$e->getMessage()}", 'error');
            Log::error("Holiday sync exception for {$country} {$year}: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => "Exception syncing holidays for {$country} {$year}: {$e->getMessage()}"
            ];
        }
    }
}
