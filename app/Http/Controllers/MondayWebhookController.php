<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Monday\MondayClient;
use App\Monday\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MondayWebhookController extends Controller
{
    protected $mondayClient;
    protected $userService;
    protected $slackController;

    public function __construct()
    {
        $this->mondayClient = new MondayClient();
        $this->userService = new UserService($this->mondayClient);
        $this->slackController = new SlackController();
    }

    /**
     * Manejar las notificaciones entrantes de Monday
     */
    public function handleWebhook(Request $request)
    {
        Log::info('Monday Webhook received', ['payload' => $request->all()]);

        // Verificar el tipo de evento
        $event = $request->input('event');
        if ($event && isset($event['type']) && $event['type'] === 'change_column_value') {
            return $this->handleDateColumnChange($request);
        }

        return response()->json(['status' => 'ignored', 'message' => 'Event not supported']);
    }

    /**
     * Manejar cambios específicos en columnas de fecha
     */
    private function handleDateColumnChange(Request $request)
    {
        $event = $request->input('event');
        $columnId = $event['columnId'];
        $boardId = $event['boardId'];
        $pulseId = $event['pulseId'];
        $userId = $event['userId'];
        $newValue = $event['value'];

        // Verificar si la columna es de tipo fecha
        if (!$this->isDateColumn($boardId, $columnId)) {
            return response()->json(['status' => 'ignored', 'message' => 'Not a date column']);
        }

        // Obtener información del usuario que hizo el cambio
        $user = $this->userService->getUser($userId);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found']);
        }

        // Obtener información del item/pulse
        $itemInfo = $this->getItemInfo($boardId, $pulseId);
        if (!$itemInfo) {
            return response()->json(['status' => 'error', 'message' => 'Item not found']);
        }

        // Guardar el evento en la base de datos
        $eventRecord = Event::create([
            'title' => 'Cambio de fecha en Monday',
            'description' => "Usuario {$user['name']} cambió la fecha en el ítem {$itemInfo->name}",
            'start_date' => now(),
            'source' => 'monday',
            'external_id' => "{$boardId}_{$pulseId}_{$columnId}",
            'additional_data' => json_encode([
                'board_id' => $boardId,
                'item_id' => $pulseId,
                'column_id' => $columnId,
                'user_id' => $userId,
                'new_value' => $newValue,
                'item_name' => $itemInfo->name,
                'board_name' => $itemInfo->board_name,
            ])
        ]);

        // Enviar notificación a Slack
        $this->sendSlackNotification($user, $itemInfo, $newValue, $eventRecord->id);

        return response()->json(['status' => 'success', 'message' => 'Date change processed']);
    }

    /**
     * Verificar si la columna es de tipo fecha
     */
    private function isDateColumn($boardId, $columnId)
    {
        // En una implementación completa, aquí consultaríamos a Monday
        // Para simplificar, podemos verificar si el ID de la columna contiene 'date' o tiene un formato específico

        // Para propósitos de demostración, asumimos que es una columna de fecha
        return true;
    }

    /**
     * Obtener información del ítem (pulse) de Monday
     */
    private function getItemInfo($boardId, $pulseId)
    {
        // Consultar información del item a través de la API de Monday
        $query = "query { items(ids: [{$pulseId}]) { name board { id name } } }";
        $response = $this->mondayClient->query($query);

        if (isset($response['data']['items'][0])) {
            $item = $response['data']['items'][0];
            return (object)[
                'name' => $item['name'],
                'board_name' => $item['board']['name'],
                'board_id' => $item['board']['id'],
            ];
        }

        return null;
    }

    /**
     * Enviar notificación a Slack con bloques interactivos
     */
    private function sendSlackNotification($user, $itemInfo, $newValue, $eventId)
    {
        // Buscar el ID de Slack del usuario
        $slackUserId = $this->findSlackUserId($user['email']);

        if (!$slackUserId) {
            Log::warning('No Slack user found for Monday user', ['monday_user' => $user]);
            return;
        }

        // Formatear la fecha para mostrarla
        $formattedDate = date('Y-m-d', strtotime($newValue));

        // Crear los bloques para el mensaje de Slack
        $blocks = [
            [
                "type" => "header",
                "text" => [
                    "type" => "plain_text",
                    "text" => "🗓️ Actualización de fecha en Monday",
                    "emoji" => true
                ]
            ],
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => "Has cambiado una fecha en el ítem *{$itemInfo->name}* en el tablero *{$itemInfo->board_name}*."
                ]
            ],
            [
                "type" => "section",
                "fields" => [
                    [
                        "type" => "mrkdwn",
                        "text" => "*Nueva fecha:*\n{$formattedDate}"
                    ]
                ]
            ],
            [
                "type" => "input",
                "block_id" => "reason_block",
                "element" => [
                    "type" => "plain_text_input",
                    "action_id" => "reason_input",
                    "multiline" => true,
                    "placeholder" => [
                        "type" => "plain_text",
                        "text" => "Explica el motivo del cambio de fecha"
                    ]
                ],
                "label" => [
                    "type" => "plain_text",
                    "text" => "Motivo del cambio"
                ]
            ],
            [
                "type" => "actions",
                "elements" => [
                    [
                        "type" => "button",
                        "text" => [
                            "type" => "plain_text",
                            "text" => "Guardar motivo",
                            "emoji" => true
                        ],
                        "style" => "primary",
                        "value" => (string)$eventId,
                        "action_id" => "submit_date_change_reason"
                    ]
                ]
            ]
        ];

        // Enviar mensaje a Slack
        $this->slackController->chat_post_message($slackUserId, null, [
            'blocks' => json_encode($blocks)
        ]);
    }

    /**
     * Encontrar el ID de usuario de Slack basado en el email
     */
    private function findSlackUserId($email)
    {
        // En una implementación real, consultaríamos a la API de Slack o usaríamos una tabla de mapeo
        // Para esta demostración, usaremos un algoritmo simple

        // Consultar a la API de Slack para buscar el usuario por email
        $slackUsers = $this->slackController->users_list('', 1000, true)->getData();

        foreach ($slackUsers->members as $member) {
            if (isset($member->profile->email) && $member->profile->email === $email) {
                return $member->id;
            }
        }

        // Si no encontramos el usuario, enviamos a un canal predeterminado
        return "general";
    }
}
