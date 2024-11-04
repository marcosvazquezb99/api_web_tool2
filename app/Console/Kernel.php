<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('time-tracking:send-report --days=0 --label="Reporte Diario" --tipo="simple" --channel"C07R3NTSV09"')->weekdays()->at('18:00');
        $schedule->command('time-tracking:send-report --days=0 --label="Reporte Diario" --tipo="completo"')->weekdays()->at('18:00');
        $schedule->command('time-tracking:send-report --days=5 --label="Reporte Semanal" ')->fridays()->at('18:00');
        $schedule->command('time-tracking:send-report --days=30 --label="Reporte mensual" ')->monthlyOn('30', '18:00');

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
