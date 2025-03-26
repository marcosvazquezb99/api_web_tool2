<?php

namespace App\Console\Commands;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TimeTrackingReportController;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTimeTrackingReport extends Command
{
    // Nombre y descripción del comando
    protected $signature = 'time-tracking:send-report {days=7} {label=Reporte de tiempos} {tipo=completo} {channel=C07PF06HF46} ';
    protected $description = 'Enviar el reporte diario de tiempos del equipo desde Monday.com';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        // Instanciar el controlador de Slack
        $slackController = new SlackController();

        $days = $this->argument('days');
        $label = $this->argument('label');
        $channel = $this->argument('channel');
        $tipo = $this->argument('tipo');

        // Instanciar el controlador de reportes de Monday
        $timeTrackingReportController = new TimeTrackingReportController(new MondayController());

        // Obtener la fecha límite (días atrás) y la fecha actual
        $fromDate = Carbon::now()->subDays($days)->startOfDay(); // Desde hace "X" días
        $now = Carbon::now()->endOfDay(); // Fecha actual

        // Crear título del reporte
        $report = "*$label*\n\n";

        // Crear el encabezado del reporte con las fechas formateadas
        $report .= "Desde el *{$fromDate->setTimezone('Europe/Madrid')->format('d/m/Y H:i')}* hasta el *{$now->setTimezone('Europe/Madrid')->format('d/m/Y H:i')}*:\n\n";
        $this->info('Generando reporte: Procesando datos de Monday.com');
        $usersData = $timeTrackingReportController->processMondayData($fromDate, $now->toDateString());
        $this->info('Generando reporte: Finalización de procesado de datos de Monday.com');
        $this->info('Extrayendo ids de los tableros');


        $this->info('Enviando reporte general a slack');

        $report .= $timeTrackingReportController->toReport($usersData, $tipo);

        // subir nivel de actividades
        $response = $slackController->chat_post_message($channel, $report);

        if ($tipo == 'completo') {
            $this->info('Enviando reporte de cada proyecto a slack');
            // Ordenar actividades de los usuarios por fecha
            foreach ($usersData as &$user) {
                $user['actividades'] = [];
                foreach ($user['tableros'] as $key => $tablero) {
                    $user['actividades'] = array_merge($user['actividades'] ?? [], $tablero);
                }
                usort($user['actividades'], function ($a, $b) {
                    if ($a['startTime']->eq($b['startTime'])) {
                        return 0;
                    }
                    return $a['startTime']->lt($b['startTime']) ? -1 : 1;
                });
                unset($user['tableros']);

            }
            //info de tablero y fecha de cada actividad

            $report = '';
            unset($user);
            $report .= '*Actividades de los usuarios cronologicamente*' . "\n";
            $channel_crono = 'C08FULSS7HR';
            foreach ($usersData as $user) {
                $userDisplayName = $user['slack_user_id'] ? "<@{$user['slack_user_id']}>" : $user['name'];
                $report .= "\tUsuario: $userDisplayName\n";
                foreach ($user['actividades'] as $actividad) {
                    $manual = $actividad['manual'] ? '*' : '';
                    $report .= "\t\t $manual" . $actividad['startTime']->setTimezone('Europe/Madrid')->format('d-m-Y H:i')
                        . " - {$actividad['endTime']->setTimezone('Europe/Madrid')->format('H:i')}"
                        . " - <{$actividad['boardUrl']}|{$actividad['boardName']}>" .
                        " - <{$actividad['tareaUrl']}|{$actividad['tarea']}> \n";
                }
            }
            $response = $slackController->chat_post_message($channel_crono, $report);
        }


        // Mostrar un mensaje en la consola si fue exitoso
        if ($response == 200) {
            $this->info('El reporte diario ha sido enviado exitosamente a Slack');
        } else {
            $this->error('Error al enviar el reporte a Slack');
        }
    }
}
