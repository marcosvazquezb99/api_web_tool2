<?php

namespace App\Services\Sync\Monday;

use App\Http\Controllers\MondayController;
use App\Models\Boards;
use App\Models\SlackChannel;
use Illuminate\Support\Facades\Log;

class BoardSyncService
{
    /**
     * Cache para almacenar resultados de consultas de canales de Slack
     */
    protected $slackChannelCache = [];

    /**
     * Output callback function for logging/display
     *
     * @var callable|null
     */
    private $outputCallback = null;

    /**
     * Set output callback for messages
     *
     * @param callable $callback
     * @return $this
     */
    public function setOutputCallback(callable $callback)
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Send output message
     *
     * @param string $message
     * @param string $type info|error|warning
     * @return void
     */
    private function output($message, $type = 'info')
    {
        if (is_callable($this->outputCallback)) {
            call_user_func($this->outputCallback, $message, $type);
        }
    }

    /**
     * Synchronize boards from Monday.com
     *
     * @param int $delayBetweenPages Seconds to wait between pages (to avoid API rate limits)
     * @return array Synchronization results
     */
    public function syncBoards($delayBetweenPages = 3)
    {
        try {
            $mondayController = new MondayController();
            $page = 1;
            $totalProcessed = 0;
            $processedBoards = [];

            $this->output('Iniciando sincronización de tableros de Monday.com');

            $boards = $mondayController->getBoards($page, 100)->getData();

            while (count($boards) > 0) {
                $this->output("Procesando página {$page} - " . count($boards) . " tableros encontrados");
                $pageResults = [];

                // Process each board on the current page
                foreach ($boards as $board) {
                    $result = $this->processBoard($board);
                    $pageResults[] = $result;
                    $totalProcessed++;

                    $this->output("Procesado: {$board->name}");
                }

                $processedBoards["page_{$page}"] = $pageResults;

                $this->output("Página {$page} completada. Total de tableros procesados: {$totalProcessed}");

                $page++;
                $boards = $mondayController->getBoards($page, 100)->getData();

                if (count($boards) > 0 && $delayBetweenPages > 0) {
                    $this->output("Esperando {$delayBetweenPages} segundos antes de procesar la siguiente página...");
                    sleep($delayBetweenPages);
                }
            }

            $this->output("Sincronización completada. Total de tableros procesados: {$totalProcessed}");

            return [
                'success' => true,
                'message' => "Sincronización completada. Total de tableros procesados: {$totalProcessed}",
                'data' => [
                    'totalBoards' => $totalProcessed,
                    'totalPages' => $page - 1,
                    'boards' => $processedBoards
                ]
            ];
        } catch (\Exception $e) {
            $this->output("Error en la sincronización: " . $e->getMessage(), 'error');
            Log::error("Board sync error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => "Error en la sincronización: " . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process a single board record
     *
     * @param object $board
     * @return array Board processing result
     */
    private function processBoard($board)
    {
        try {
            $board_id = $board->id;
            $name = $board->name;
            $boardUrl = $board->url;
            $internal_client_id = $this->extractInternalClientId($name);
            $channelId = $this->getSlackChannelId($name);

            $boardModel = Boards::updateOrCreate(['id' => $board_id],
                [
                    'name' => $name,
                    'id' => $board_id,
                    'url' => $boardUrl,
                    'channel_id' => $channelId,
                    'internal_id' => $internal_client_id
                ]);
            $boardModel->name = $name;
            $boardModel->save();

            return [
                'success' => true,
                'board_id' => $board_id,
                'name' => $name,
                'channel_id' => $channelId,
                'internal_id' => $internal_client_id
            ];
        } catch (\Exception $e) {
            Log::error("Error processing board: " . $e->getMessage(), [
                'board_id' => $board->id ?? 'unknown',
                'board_name' => $board->name ?? 'unknown'
            ]);

            return [
                'success' => false,
                'board_id' => $board->id ?? 'unknown',
                'name' => $board->name ?? 'unknown',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract internal client ID from board name
     *
     * @param string $name
     * @return int|null
     */
    private function extractInternalClientId($name)
    {
        $internal_client_id = last(explode(' ', explode('_', $name)[0]));
        return is_numeric($internal_client_id) ? $internal_client_id : null;
    }

    /**
     * Get the Slack channel ID related to a board name
     *
     * @param string $boardName
     * @return int|null
     */
    private function getSlackChannelId($boardName)
    {
        // Check if result is already cached
        if (isset($this->slackChannelCache[$boardName])) {
            return $this->slackChannelCache[$boardName];
        }

        // Buscar en la tabla de relación
        $relation = SlackChannel::where('slack_channel_name', $boardName)
            ->first();

        if (!$relation) {
            // Si no se encuentra una coincidencia exacta, intentar coincidencia parcial
            $parts = explode('_', $boardName);
            $firstPart = $parts[0];
            $relation = SlackChannel::where('slack_channel_name', 'like', $firstPart . '_%')->first();
        }

        // Cache the result to avoid redundant queries
        $result = $relation ? $relation->id : null;
        $this->slackChannelCache[$boardName] = $result;

        return $result;
    }
}
