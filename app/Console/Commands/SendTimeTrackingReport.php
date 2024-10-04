<?php

namespace App\Console\Commands;

use App\Http\Controllers\SlackController;
use App\Http\Controllers\TimeTrackingReportController;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTimeTrackingReport extends Command
{
    // Nombre y descripción del command
    protected $signature = 'time-tracking:send-report {days=7} {label=Reporte de tiempos}';
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
        $days = $this->argument('days');
        $label = $this->argument('label');
        // Instanciar el controlador de reportes de Monday
        $timeTrackingReportController = new TimeTrackingReportController();
        $fromDate = Carbon::now()->subDays($days); // Obtener fecha límite
        $now = Carbon::now(); // Obtener fecha actual
        //crear titulo del reporte
        $report = "*$label*\n\n";
        // Crear el encabezado del reporte
        $report .= "Desde el *{$fromDate->format('d/m/Y')}* hasta el *{$now->format('d/m/Y')}*:\n\n";
        // Generar el reporte
        $report .= $timeTrackingReportController->generateReport($days);

        // Instanciar el controlador de Slack
        $slackController = new SlackController();

        // Enviar el reporte al canal de Slack (sustituye #general por tu canal)
        $response = $slackController->chat_post_message('C07PF06HF46', $report);

        // Mostrar un mensaje en la consola si fue exitoso
        if ($response == 200) {
            $this->info('El reporte diario ha sido enviado exitosamente a Slack');
        } else {
            $this->error('Error al enviar el reporte a Slack: ' . $response['error']);
        }
    }
}
