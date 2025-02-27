<?php

namespace App\Console\Commands\Sync;

use App\Http\Controllers\SlackController;
use App\Models\Boards;
use App\Models\SlackChannel;
use Illuminate\Console\Command;

// Asegúrate de tener un modelo para tu tabla

class SyncSlackChannels extends Command
{
    protected $signature = 'sync:slack-channels';
    protected $description = 'Sincroniza los canales de Slack con la base de datos.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 1. Obtener los canales de Slack (aquí debes usar la API de Slack)
        $slackChannels = $this->getSlackChannels();

        // 2. Sincronizar con la base de datos
        foreach ($slackChannels as $channel) {
            SlackChannel::updateOrCreate(
                ['id' => $channel['id']], // Criteria to find an existing record
                [                           // Data to update or create
                    'slack_channel_name' => $channel['name'],
                    'monday_board_id' => $channel['monday_board_id'],
                ])->save();
        }

        $this->info('Canales de Slack sincronizados correctamente.');
    }

    private function getSlackChannels()
    {
        // Aquí debes implementar la lógica para obtener los canales de Slack
        // Puedes usar la API de Slack directamente o alguna librería que te facilite la tarea
        // Este es solo un ejemplo, debes adaptarlo a tu configuración
        $slackController = new SlackController();
        $result = $slackController->conversations_list('exclude_archived=true&types=private_channel')->getData();
//        dd($result->getData()->channels);
        $channels = [];
        foreach ($result->channels as $channel) {

            $channels[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'monday_board_id' => $this->getMondayBoardId($channel->name) // Función para obtener el ID de Monday.com
            ];
        }

        return $channels;
    }

    private function getMondayBoardId($slackChannelName)
    {
        // Aquí debes implementar la lógica para obtener el ID del tablero de Monday.com
        // a partir del ID del canal de Slack. Esto dependerá de cómo tengas configurada
        // la relación entre ambos.
        // Este es solo un ejemplo, debes adaptarlo a tu configuración.

        // Ejemplo: buscar en una tabla de relación
        $relation = Boards::where('name', $slackChannelName)
            ->first();
        if (!$relation) {
            // Si no se encuentra, puedes devolver null o lanzar una excepción
            // dependiendo de cómo quieras manejarlo
            // Divide el nombre del canal de Slack por "_"
            $parts = explode('_', $slackChannelName);

            // Toma el primer elemento del array resultante (índice 0)
            $firstPart = $parts[0];
            $relation = Boards::where('name', 'like', $firstPart . '_%')->first();
        }
        //dd($relation->id);

        return $relation ? $relation->id : null;
    }
}
