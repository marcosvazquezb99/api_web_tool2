<?php

namespace App\Console\Commands\sync\Monday;

use App\Http\Controllers\MondayController;
use App\Models\Boards;
use App\Models\SlackChannel;
use Illuminate\Console\Command;

class SyncBoards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:boards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncronize boards from monday.com to local database';

    /**
     * Cache para almacenar resultados de consultas de canales de Slack
     */
    protected $slackChannelCache = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $mondayController = new MondayController();
        $page = 1;
        $totalProcessed = 0;

        $this->info('Iniciando sincronización de tableros de Monday.com');

        $boards = $mondayController->getBoards($page, 100)->getData();

        while (count($boards) > 0) {
            $this->info("Procesando página {$page} - {$this->line(count($boards))} tableros encontrados");

            // Crear una barra de progreso para los tableros de la página actual
            $progressBar = $this->output->createProgressBar(count($boards));
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - Procesando: %message%');
            $progressBar->start();

            // Create a new board on table if not exists one by one using model Boards
            foreach ($boards as $board) {
                $progressBar->setMessage($board->name);
                $this->processBoard($board);
                $progressBar->advance();
                $totalProcessed++;
            }

            $progressBar->finish();
            $this->newLine();
            $this->info("Página {$page} completada. Total de tableros procesados: {$totalProcessed}");
            $this->newLine();

            $page++;
            $boards = $mondayController->getBoards($page, 100)->getData();

            if (count($boards) > 0) {
                $this->info("Esperando 3 segundos antes de procesar la siguiente página...");
                sleep(3);
            }
        }

        $this->info("Sincronización completada. Total de tableros procesados: {$totalProcessed}");
        return 0;
    }

    /**
     * Process a single board record
     *
     * @param object $board
     * @return void
     */
    private function processBoard($board)
    {
        $board_id = $board->id;
        $name = $board->name;
        $boardUrl = $board->url;
        $internal_client_id = $this->extractInternalClientId($name);

        $board = Boards::updateOrCreate(['id' => $board_id],
            [
                'name' => $name,
                'id' => $board_id,
                'url' => $boardUrl,
                'channel_id' => $this->getSlackChannelId($name),
                'internal_id' => $internal_client_id
            ]);
        $board->name = $name;
        $board->save();
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
