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

        //::::::::::::::::::::::::::    TIME TRACKING    :::::::::::::::::::::::::::::::::::::::::://
        $schedule->command('time-tracking:send-report 0 "Reporte Diario" "simple" "C07R3NTSV09"')->weekdays()->at('19:00');
        $schedule->command('time-tracking:send-report 30 "Reporte Diario" "simple" "C07R3NTSV09"')->lastDayOfMonth();
        $schedule->command('time-tracking:send-report 0 "Reporte Diario" "completo"')->weekdays()->at('19:00');
        $schedule->command('time-tracking:active-boards')->weekdays()->at('08:00');
//        $schedule->command('time-tracking:send-report 5 "Reporte Semanal" ')->fridays()->at('18:00');
//        $schedule->command('time-tracking:send-report 30 "Reporte mensual" ')->monthlyOn('30', '18:00');

        //::::::::::::::::::::::::::    SYNC    :::::::::::::::::::::::::::::::::::::::::://
        $schedule->command('sync:boards')->dailyAt('00:00');
        $schedule->command('sync:users')->dailyAt('00:00');
        $schedule->command('sync:slack-channels')->dailyAt('00:00');
        $schedule->command('sync:holded-employees')->dailyAt('00:00');
        $schedule->command('sync:holded-customers')->dailyAt('00:00');
        $schedule->command('sync:holded-services')->dailyAt('00:00');
        $schedule->command('sync:holidays')->dailyAt('00:00');

        //::::::::::::::::::::::::::    TASKS    :::::::::::::::::::::::::::::::::::::::::://
        $schedule->command('task:upcomming today C08GS9MT8N8')->weekdays()->at('08:00');
        $schedule->command('task:upcomming tomorrow C08HA45TP1T')->weekdays()->at('15:00');

        //::::::::::::::::::::::::::    CHANGES REPORT    :::::::::::::::::::::::::::::::::::::::::://
        $schedule->command('report:date-changes C08L6EHV204 0')->weekdays()->at('18:30');
        $schedule->command('report:date-changes C08L6EHV204 7')->mondays()->at('05:00');
        $schedule->command('report:date-changes C08L6EHV204 31')->lastDayOfMonth()->at('05:00');
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
