<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Monday\MondayClient;
use App\Services\Monday\UserService;
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
     * Verificar el webhook cuando Monday.com lo configura por primera vez
     * Implementa el "challenge" que Monday envía para verificar la URL
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyWebhook(Request $request)
    {
        Log::info('Monday Webhook verification', ['params' => $request->all()]);

        // Monday envía un parámetro "challenge" en la solicitud GET de verificación
        if ($request->has('challenge')) {
            // Devolver el mismo valor de "challenge" para verificar el webhook
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        return response()->json(['status' => 'error', 'message' => 'No challenge provided'], 400);
    }

    /**
     * Manejar las notificaciones de cambios de fecha entrantes de Monday
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleDateChangeWebhook(Request $request)
    {
        Log::info('Monday Date Change Webhook received', ['payload' => $request->all()]);

        // Verificar el tipo de evento
        $event = $request->input('event');
        if (!$event || !isset($event['type'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid event data'], 400);
        }

        // Verificar si es un cambio de valor de columna
        if ($event['type'] === 'update_column_value') {
            return $this->handleDateColumnChange($request);
        }

        return response()->json(['status' => 'ignored', 'message' => 'Event type not supported for date change webhook']);
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleDateColumnChange(Request $request)
    {
        $event = $request->input('event');
        $columnId = $event['columnId'];
        $boardId = $event['boardId'];
        $pulseId = $event['pulseId'];
        $userId = $event['userId'];
        $newValue = $event['value']['date'] ?? '';
        $previousValue = $event['previousValue']['date'] ?? '';
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
            'status' => 'ongoing',
            'category' => 'change_date',
            'external_id' => "{$boardId}_{$pulseId}_{$columnId}",
            'additional_data' => json_encode([
                'board_id' => $boardId,
                'item_id' => $pulseId,
                'column_id' => $columnId,
                'user_id' => $userId,
                'new_value' => $newValue,
                'previous_value' => $previousValue,
                'item_name' => $itemInfo->name,
                'board_name' => $itemInfo->board_name,
            ])
        ]);

        // Enviar notificación a Slack
        $this->sendSlackNotification($user, $itemInfo, $newValue, $eventRecord->id, $previousValue);

        return response()->json(['status' => 'success', 'message' => 'Date change processed']);
    }

    /**
     * Verificar si la columna es de tipo fecha
     * @param string $boardId
     * @param string $columnId
     * @return bool
     */
    private function isDateColumn($boardId, $columnId)
    {
        // Consultar las columnas del tablero para verificar si es de tipo fecha
        $query = "query { boards(ids: [{$boardId}]) { columns { id, type } } }";
        $response = $this->mondayClient->query($query);

        if (isset($response['data']['boards'][0]['columns'])) {
            foreach ($response['data']['boards'][0]['columns'] as $column) {
                if ($column['id'] === $columnId && ($column['type'] === 'date' || $column['type'] === 'timeline')) {
                    return true;
                }
            }
        }

        // Fallback: verificar si el ID de la columna contiene 'date'
        return (stripos($columnId, 'date') !== false);
    }

    /**
     * Obtener información del ítem (pulse) de Monday
     */
    private function getItemInfo($boardId, $pulseId)
    {
        // Consultar información del item a través de la API de Monday
        $query = <<<GRAPHQL
            {
                items (ids: ["$pulseId"]) {
                    name
                    url
                    board {
                        id
                        url
                        name
                    }
                }
            }
        GRAPHQL;
        $response = $this->mondayClient->query($query);
        if (isset($response[0]['data']['items'][0])) {
            $item = $response[0]['data']['items'][0];

            return (object)[
                'name' => $item['name'],
                'item_url' => $item['url'],
                'board_name' => $item['board']['name'],
                'board_id' => $item['board']['id'],
                'board_url' => $item['board']['url'],
            ];
        }

        return null;
    }

    /**
     * Enviar notificación a Slack con bloques interactivos
     */
    private function sendSlackNotification($user, $itemInfo, $newValue, $eventId, $previousValue)
    {
        // Buscar el ID de Slack del usuario
        $slackUserId = $this->findSlackUserId($user['email']);

        if (!$slackUserId) {
            Log::warning('No Slack user found for Monday user', ['monday_user' => $user]);
            return;
        }

        // Formatear la fecha para mostrarla
        if ($newValue != '') {
            $newValue = date('d-m-Y', strtotime($newValue));
        } else {
            $newValue = 'Sin especificar';
        }

        if ($previousValue != '') {
            $previousValue = date('d-m-Y', strtotime($previousValue));
        } else {
            $previousValue = 'Sin especificar';
        }


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
                    "text" => "Has cambiado una fecha en el ítem *<$itemInfo->item_url|$itemInfo->name>* en el tablero *<$itemInfo->board_url|$itemInfo->board_name>*."
                ]
            ],
            [
                "type" => "section",
                "fields" => [
                    [
                        "type" => "mrkdwn",
                        "text" => "*Nueva fecha:* $newValue} \n*Fecha anterior:* $previousValue"
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
