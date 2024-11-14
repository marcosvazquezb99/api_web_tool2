<?php

namespace App\Console\Commands;

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
        /*$slackController->timeTrackingMondayBoardSummaryWithBoardIds(['1623214457']);
        $this->info('Verificar en slack');*/
        // Obtener los argumentos del comando
        $slackController->timeTrackingMondayBoardSummaryWithBoardIds([
                "1699857255",
                "1693810807",
                "1693800504",
                "1685206840",
                "1652561334",
                "1542576910",
                "1383274320",
                "1667729854",
                "1657803522",
                "1651892951",
                "1643528562",
                "1623214457",
                "1597063527",
                "1664352227",
                "1664348119",
                "1661855098",
                "1418732497",
            ]
        );
        $days = $this->argument('days');
        $label = $this->argument('label');
        $channel = $this->argument('channel');
        $tipo = $this->argument('tipo');

        // Instanciar el controlador de reportes de Monday
        $timeTrackingReportController = new TimeTrackingReportController();

        // Obtener la fecha límite (días atrás) y la fecha actual
        $fromDate = Carbon::now()->subDays($days)->startOfDay(); // Desde hace "X" días
        $now = Carbon::now()->endOfDay(); // Fecha actual

        // Crear título del reporte
        $report = "*$label*\n\n";

        // Crear el encabezado del reporte con las fechas formateadas
        $report .= "Desde el *{$fromDate->format('d/m/Y H:i')}* hasta el *{$now->format('d/m/Y H:i')}*:\n\n";
        $this->info('Generando reporte: Procesando datos de Monday.com');
        $usersData = $timeTrackingReportController->processMondayData($fromDate, $now->toDateString());
//        dd($usersData);
        $this->info('Generando reporte: Finalización de procesado de datos de Monday.com');
        $this->info('Extrayendo ids de los tableros');
        //get all boards ids
        $boardsIds = [];
        foreach ($usersData as $user) {
            foreach ($user['tableros'] as $tablero => $actividades) {
                foreach ($actividades as $actividad) {
                    $boardsIds[] = $actividad['boardId'];
                }
            }
        }
//        dd(array_unique($boardsIds));
        $this->info('Enviando reporte de cada proyecto a slack');
        $slackController->timeTrackingMondayBoardSummaryWithBoardIds(array_unique($boardsIds));
        $this->info('Enviando reporte general a slack');

        $report .= $timeTrackingReportController->toReport($usersData, $tipo);

        // Generar el reporte con las fechas correctas
//        $report .= $timeTrackingReportController->generateReport($fromDate->toDateString(), $tipo, $now->toDateString());


        // Enviar el reporte al canal de Slack (sustituye 'C07PF06HF46' por el ID de tu canal)
        $response = $slackController->chat_post_message($channel, $report);

        // Mostrar un mensaje en la consola si fue exitoso
        if ($response == 200) {
            $this->info('El reporte diario ha sido enviado exitosamente a Slack');
        } else {
            $this->error('Error al enviar el reporte a Slack: ' . ($response['error'] ?? 'Error desconocido'));
        }
    }
}
