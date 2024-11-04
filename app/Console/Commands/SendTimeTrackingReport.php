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
        // Obtener los argumentos del comando
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

        // Generar el reporte con las fechas correctas
        $report .= $timeTrackingReportController->generateReport($fromDate->toDateString(), $tipo, $now->toDateString());

        // Instanciar el controlador de Slack
        $slackController = new SlackController();

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
