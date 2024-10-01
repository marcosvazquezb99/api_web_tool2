<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;

class TimeTrackingReportController extends Controller
{
    protected $client;
    protected $mondayToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN'); // Añade tu token en el archivo .env
    }

    /**
     * Obtener los datos de Monday.com mediante GraphQL.
     */
    public function getMondayData()
    {
        $query = <<<'GRAPHQL'
        {
            boards {
                name
                items_page(limit: 500) {
                    items {
                        name
                        updated_at
                        column_values {
                            ... on PeopleValue {
                                persons_and_teams {
                                    id
                                    kind
                                }
                            }
                            ... on TimeTrackingValue {
                                history {
                                    started_user_id
                                    ended_user_id
                                    started_at
                                    ended_at
                                    manually_entered_end_date
                                    manually_entered_end_time
                                    manually_entered_start_date
                                    manually_entered_start_time
                                }
                                running
                                started_at
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->client->post('https://api.monday.com/v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->mondayToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $query,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Obtener el nombre del usuario por ID desde Monday.com.
     */
    public function getUserName($userId)
    {
        $query = <<<GRAPHQL
        {
            users(ids: [$userId]) {
                id
                name
                email
            }
        }
        GRAPHQL;

        $response = $this->client->post('https://api.monday.com/v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->mondayToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'query' => $query,
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['data']['users'][0]['name'] ?? 'Desconocido';
    }

    /**
     * Procesar los datos obtenidos de Monday.com y generar el reporte desde una fecha basada en días atrás.
     */
    public function processMondayData($daysAgo)
    {
        $data = $this->getMondayData();
        $fromDate = Carbon::now()->subDays($daysAgo); // Obtener fecha límite
        $usersData = []; // Agrupar las actividades por usuario

        // Iteramos sobre los tableros
        foreach ($data['data']['boards'] as $board) {
            foreach ($board['items_page']['items'] as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {
                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];

                            // Convertir a fechas de Carbon
                            $startTimeCarbon = Carbon::parse($startTime);
                            $endTimeCarbon = Carbon::parse($endTime);

                            // Si las fechas de inicio están dentro del rango de días
                            if ($startTimeCarbon->greaterThanOrEqualTo($fromDate)) {
                                $manuallyEntered = $record['manually_entered_start_date'] || $record['manually_entered_end_time'] ? 'Sí' : 'No';

                                // Obtener nombres de usuario
                                $startedUserName = $this->getUserName($record['started_user_id']);
                                $endedUserName = $this->getUserName($record['ended_user_id']);

                                // Solo procesar si es el mismo usuario quien inicia y termina
                                if ($record['started_user_id'] === $record['ended_user_id']) {
                                    $userId = $record['started_user_id'];

                                    // Crear entrada para el usuario si no existe
                                    if (!isset($usersData[$userId])) {
                                        $usersData[$userId] = [
                                            'name' => $startedUserName,
                                            'tableros' => []
                                        ];
                                    }

                                    // Crear entrada para el tablero si no existe
                                    if (!isset($usersData[$userId]['tableros'][$board['name']])) {
                                        $usersData[$userId]['tableros'][$board['name']] = [];
                                    }

                                    // Calcular la duración
                                    $duration = (strtotime($endTime) - strtotime($startTime)) / (60 * 60);
                                    $usersData[$userId]['tableros'][$board['name']][] = [
                                        'tarea' => $item['name'],
                                        'duracion' => number_format($duration, 2),
                                        'manual' => $manuallyEntered
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $usersData;
    }


    /**
     * Generar el reporte de horas trabajadas basado en días atrás.
     */
    public function generateReport($daysAgo)
    {
        $usersData = $this->processMondayData($daysAgo);
        $report = '';
        $globalHours = 0;

        foreach ($usersData as $userId => $user) {
            $report .= "Usuario: {$user['name']}\n";

            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                $report .= "  Tablero: $tablero:\n";

                foreach ($actividades as $actividad) {
                    $report .= "    Actividad: {$actividad['tarea']}\n";
                    $report .= "      Tiempo: {$actividad['duracion']} horas\n";
                    $report .= "      Ingresado manualmente: {$actividad['manual']}\n";
                    $totalHours += $actividad['duracion'];
                }

                $report .= "  Total de horas trabajadas en $tablero: " . number_format($totalHours, 2) . " horas\n";
                $globalHours += $totalHours;
            }

            $report .= "  Total de horas trabajadas por {$user['name']}: " . number_format($globalHours, 2) . " horas\n\n";
        }

        return $report;
    }

    /**
     * Enviar el reporte diario por correo electrónico.
     */
    public function sendDailyEmailReport()
    {
        $emailAddress = 'jose@somospecesvoladores.com';
        $subject = 'Reporte diario de tiempos del equipo';
        $message = $this->generateReport();
//dd($message);
        // Enviar correo usando la función Mail de Laravel
        SlackController::class->chat_post_message('C07PF06HF46', $message);

        return response()->json(['message' => 'Reporte enviado exitosamente']);
    }
}
