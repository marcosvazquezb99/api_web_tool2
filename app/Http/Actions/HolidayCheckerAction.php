<?php

namespace App\Http\Actions;

use App\Models\Holidays;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class HolidayCheckerAction
{
    /**
     * The country code to check holidays for
     *
     * @var string
     */
    protected $countryCode;

    /**
     * Cache duration in minutes
     *
     * @var int
     */
    protected $cacheDuration = 1440; // 24 hours

    /**
     * Create a new action instance
     *
     * @param string $countryCode The country code (default: ES-MD for Madrid, Spain)
     */
    public function __construct(string $countryCode = 'ES')
    {
        $this->countryCode = $countryCode;
    }

    /**
     * Check if a date is a holiday
     *
     * @param Carbon|string $date The date to check
     * @return bool
     */
    public function isHoliday($date)
    {
        if (!$date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $dateString = $date->format('Y-m-d');
        $year = $date->year;

        // Get holidays from database with caching
        $cacheKey = "holidays_{$this->countryCode}_{$year}";
        $holidays = Cache::remember($cacheKey, $this->cacheDuration, function () use ($year) {
            return Holidays::where('year', $year)
                ->where('country_code', $this->countryCode)
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();
        });

        // Add Madrid-specific holidays
        $madridHolidays = $this->getMadridHolidays($year);
        $allHolidays = array_merge($holidays, $madridHolidays);

        return in_array($dateString, $allHolidays);
    }

    /**
     * Get Madrid-specific holidays
     *
     * @param int $year
     * @return array
     */
    private function getMadridHolidays(int $year): array
    {
        // Get Madrid-specific holidays from database
        $madridHolidays = Cache::remember("madrid_holidays_{$year}", $this->cacheDuration, function () use ($year) {
            return Holidays::where('year', $year)
                ->where('country_code', 'ES')
                ->where(function ($query) {
                    $query->orWhereJsonContains('counties', 'ES-MD');
                })
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();
        });

        // Madrid specific holidays (as a fallback if not in the database)
        $fallbackMadridHolidays = [
            "$year-05-02", // Fiesta de la Comunidad de Madrid
            "$year-05-15", // San Isidro
            "$year-11-09", // Nuestra Señora de la Almudena
        ];

        return array_unique(array_merge($madridHolidays, $fallbackMadridHolidays));
    }

    /**
     * Get the next business day if date is a weekend or holiday
     *
     * @param Carbon|string $date The date to check
     * @return Carbon
     */
    public function getNextBusinessDay($date)
    {
        if (!$date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        $newDate = $date->copy();

        // Keep advancing the date until we find a business day
        while ($newDate->isWeekend() || $this->isHoliday($newDate)) {
            $newDate->addDay();
        }

        return $newDate;
    }

    /**
     * Set the country code
     *
     * @param string $countryCode
     * @return $this
     */
    public function forCountry(string $countryCode)
    {
        $this->countryCode = $countryCode;
        return $this;
    }
}
