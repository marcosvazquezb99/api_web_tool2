<?php

namespace App\Monday\Services;

use App\Monday\MondayClient;

class TimeTrackingService
{
    protected $client;
    protected $userService;
    protected $itemService;

    public function __construct(MondayClient $client, UserService $userService, ItemService $itemService)
    {
        $this->client = $client;
        $this->userService = $userService;
        $this->itemService = $itemService;
    }

    /**
     * Método para obtener resumen de time tracking de un tablero
     */
    public function getTimeTrakingMondayBoardSummary($boardId, $fromDate = null, $toDate = null)
    {
        $columns = 'TimeTrackingValue,PeopleValue';
        $cursor = null;
        $usersData = [];

        do {
            $boardResponse = $this->itemService->getItemsByBoard($boardId, $columns, $cursor);

            if ($boardResponse['status'] !== 200) {
                return ['error' => 'Error al obtener los items del tablero'];
            }

            $board = $boardResponse['data'];
            $items = $board['data']['boards'][0]['items_page']['items'];
            $cursor = $board['data']['boards'][0]['items_page']['cursor'];

            if ($cursor === 'null') {
                $cursor = null;
            }

            $board_name = $board['data']['boards'][0]['name'];

            foreach ($items as $item) {
                foreach ($item['column_values'] as $column) {
                    if (!empty($column['history'])) {
                        foreach ($column['history'] as $record) {
                            $startTime = $record['started_at'] ?? $record['manually_entered_start_date'];
                            $endTime = $record['ended_at'] ?? $record['manually_entered_end_date'];
                            $manuallyEntered = !empty($record['manually_entered_start_date']) || !empty($record['manually_entered_end_time']) ? 'Sí' : 'No';

                            $user = $this->userService->getUser($record['started_user_id']);

                            if ($user && isset($user['email'])) {
                                $userId = $record['started_user_id'];
                                $startedUserName = $user['name'];

                                // Crear entrada para el usuario si no existe
                                if (!isset($usersData[$userId])) {
                                    $usersData[$userId] = [
                                        'name' => $startedUserName,
                                        'slack_user_id' => $user['slack_user_id'] ?? null,
                                        'tableros' => []
                                    ];
                                }

                                // Calcular la duración
                                if ($endTime) {
                                    $duration = (strtotime($endTime) - strtotime($startTime)) / (60 * 60);
                                    if ($duration < 0) {
                                        continue;
                                    }

                                    if (!isset($usersData[$userId]['tableros'][$board_name][$item['name']])) {
                                        $usersData[$userId]['tableros'][$board_name][$item['name']] = [
                                            'tarea' => $item['name'],
                                            'duracion' => (float)number_format($duration, 2),
                                            'manual' => $manuallyEntered
                                        ];
                                    } else {
                                        $usersData[$userId]['tableros'][$board_name][$item['name']]['duracion'] += (float)number_format($duration, 2);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } while ($cursor !== null);

        return $usersData;
    }

    /**
     * Generar el reporte de horas trabajadas
     */
    public function generateTimeTrackingReport($usersData)
    {
        $report = '';
        $globalTotalHours = 0;

        foreach ($usersData as $user) {
            $report .= "Usuario: *{$user['name']}*\n";
            $totalUserHours = 0;

            foreach ($user['tableros'] as $tablero => $actividades) {
                $totalHours = 0;
                $report .= "  Tablero: *$tablero*";
                $report .= ":\n";

                foreach ($actividades as $actividad) {
                    try {
                        $report .= "    Actividad: *{$actividad['tarea']}* - ";
                        $report .= "Tiempo: " . gmdate('H:i', $actividad['duracion'] * 3600) . " horas - ";
                        $report .= "Manual: {$actividad['manual']}\n";
                        $totalHours += $actividad['duracion'];
                    } catch (\Exception $e) {
                        error_log($e->getMessage() . ' Error en la generación del reporte');
                    }
                }

                $report .= " - Total de horas: " . gmdate('H:i', $totalHours * 3600) . " horas\n";
                $totalUserHours += $totalHours;
                $globalTotalHours += $totalHours;
            }

            $report .= "  Total de horas trabajadas por {$user['name']}: " . gmdate('H:i', $totalUserHours * 3600) . " horas\n";
            $report .= "*----------------------------------------*\n\n";
        }

        $report .= "Total de horas: " . gmdate('H:i', $globalTotalHours * 3600) . " horas\n";

        return $report;
    }
}
