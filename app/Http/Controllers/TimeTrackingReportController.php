<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Client;

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
     * Obtener los datos de Monday.com mediante GraphQL con paginación.
     */
    public function getMondayData()
    {
        $allBoards = []; // Para almacenar todos los tableros
        $page = 1; // Comenzamos en la primera página
        $hasMoreBoards = true; // Bandera para seguir solicitando más tableros

        while ($hasMoreBoards) {
            // Construimos la consulta GraphQL para los tableros, con paginación usando 'page'
            $query = <<<GRAPHQL
        {
            boards(limit: 250, page: $page) {
                name
                items_page(limit: 500) {
                    items {
                        id
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
                    cursor # Cursor para paginar los items
                }
            }
        }
        GRAPHQL;

            // Hacemos la solicitud a la API de Monday
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
            $boards = $data['data']['boards'];

            // Si recibimos menos de 250 boards, significa que no hay más boards
            if (count($boards) < 250) {
                $hasMoreBoards = false;
            }

            // Agregamos los tableros obtenidos
            foreach ($boards as $board) {
                $boardItems = [];
                $itemsCursor = null;

                // Mientras haya items para paginar dentro del tablero, seguimos obteniéndolos
                do {
                    $itemsPage = $board['items_page'];

                    // Agregamos los items de esta página
                    $boardItems = array_merge($boardItems, $itemsPage['items']);

                    // Actualizamos el cursor de los items
                    $itemsCursor = $itemsPage['cursor'];

                    // Si hay más items (cursor), hacemos otra solicitud para paginar
                    if ($itemsCursor) {
                        $queryItemsCursor = <<<GRAPHQL
                    {
                        boards(limit: 1, page: $page) {
                            name
                            items_page(limit: 500, cursor: "{$itemsCursor}") {
                                items {
                                    id
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
                                cursor # Cursor para paginar los items
                            }
                        }
                    }
                    GRAPHQL;

                        $itemsResponse = $this->client->post('https://api.monday.com/v2', [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $this->mondayToken,
                                'Content-Type' => 'application/json',
                            ],
                            'json' => [
                                'query' => $queryItemsCursor,
                            ],
                        ]);

                        $itemsData = json_decode($itemsResponse->getBody(), true);
                        $itemsPage = $itemsData['data']['boards'][0]['items_page'];
                    }

                } while ($itemsCursor); // Repetir mientras haya más items (cursor) para este tablero

                // Añadir los items del tablero actual
                $board['items_page']['items'] = $boardItems;
                $allBoards[] = $board;
            }

            // Incrementar el número de página para la siguiente solicitud
            $page++;

        }

        return $allBoards; // Devolver todos los tableros con sus items paginados
    }


    /**
     * Formatear el cursor para su uso en la consulta GraphQL.
     * @param string|null $cursor
     * @return string
     */
    private function formatCursor($cursor)
    {
        return $cursor ? "\"$cursor\"" : 'null';
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
     * Procesar los datos obtenidos de Monday.com y generar el reporte basado en un rango de fechas.
     *
     * @param string $fromDate Fecha de inicio en formato 'YYYY-MM-DD'.
     * @param string|null $toDate Fecha de fin en formato 'YYYY-MM-DD'. Si es null, se asume la fecha actual.
     * @return array Datos agrupados por usuario y tablero.
     */
    public function processMondayData($fromDate, $toDate = null)
    {
        // Si no se proporciona $toDate, se asume la fecha actual
        $toDate = $toDate ? Carbon::parse($toDate)->endOfDay() : Carbon::now()->endOfDay();
        $fromDate = Carbon::parse($fromDate)->startOfDay(); // Asegurar que la fecha de inicio es al principio del día

        $data = $this->getMondayData();
//        dd($data);
        $usersData = []; // Agrupar las actividades por usuario

        // Iteramos sobre los tableros
        foreach ($data as $board) {
            foreach ($board['items_page']['items'] as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {

                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];

                            // Convertir a fechas de Carbon
                            $startTimeCarbon = Carbon::parse($startTime);
                            $endTimeCarbon = Carbon::parse($endTime);

                            // Si las fechas de inicio y fin están dentro del rango

                            if ($endTime !== false && $startTimeCarbon->between($fromDate, $toDate) && $endTimeCarbon->between($fromDate, $toDate)) {
                                $manuallyEntered = !empty($record['manually_entered_start_date']) || !empty($record['manually_entered_end_time']) ? 'Sí' : 'No';

                                // Obtener nombres de usuario
                                $startedUserName = $this->getUserName($record['started_user_id']);
                                $endedUserName = $this->getUserName($record['ended_user_id']);

                                // Solo procesar si es el mismo usuario quien inicia y termina
//                                if ($record['started_user_id'] === $record['ended_user_id']) {
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
//                                }
                            }
                        }
                    }
                }
            }
        }

        return $usersData;
    }


    /**
     * Generar el reporte de horas trabajadas basado en un rango de fechas.
     *
     * @param string $fromDate Fecha de inicio en formato 'YYYY-MM-DD'.
     * @param string|null $toDate Fecha de fin en formato 'YYYY-MM-DD'.
     * Si solo se proporciona uno, o ambos son iguales, se calculará para solo ese día.
     */
    public function generateReport(string $fromDate, $type, string $toDate = null)
    {
        // Si no se proporciona $toDate o si ambos son iguales, procesar solo para $fromDate
        if (is_null($toDate) || $fromDate === $toDate) {
            $toDate = $fromDate; // Si no hay toDate, tratamos ambos como el mismo día
        }

        // Procesar los datos de Monday con el rango de fechas
        $usersData = $this->processMondayData($fromDate, $toDate);
        $report = '';

        foreach ($usersData as $user) {
            $report .= "Usuario: *{$user['name']}*\n";
            $globalHours = 0;
            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                $report .= "  Tablero: *$tablero*";

                $report .= ":\n";
                foreach ($actividades as $actividad) {
//                    dd($actividad);
                    try {
                        if ($type != 'simple') {
                            $report .= "    Actividad: *{$actividad['tarea']}* - ";
                            $report .= "Tiempo: " . gmdate('H:i', $actividad['duracion'] * 3600) . " horas - ";
                            $report .= "Manual: {$actividad['manual']}\n";
                        }
                        $totalHours += $actividad['duracion'];
                    } catch (\Exception $e) {
                        error_log($e->getMessage() . ' Error en la generación del reporte' . $actividad->toArray());
                    }

                }

                $report .= " - Total de horas: " . gmdate('H:i', $totalHours * 3600) . " horas\n";
                $globalHours += $totalHours;
            }

            $report .= "  Total de horas trabajadas por {$user['name']}: " . gmdate('H:i', $globalHours * 3600) . " horas\n";
            $report .= "*----------------------------------------*\n\n";
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
//        $message = $this->generateReport();
//dd($message);
        // Enviar correo usando la función Mail de Laravel
//        SlackController::class->chat_post_message('C07PF06HF46', $message);

//        return response()->json(['message' => 'Reporte enviado exitosamente']);
    }
}
