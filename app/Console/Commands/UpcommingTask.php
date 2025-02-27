<?php

namespace App\Console\Commands;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Models\Boards;
use Illuminate\Console\Command;

class UpcommingTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:upcomming {time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get upcomming task in next {time} ';

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

        // Instanciar el controlador de Slack
        $slackController = new SlackController();
        $mondayController = new MondayController();

        $time = $this->argument('time');
        $rules = [
            [
                "column_id" => "date", // ID de la columna de fecha
                "operator" => "greater_than",
                "compare_value" => ["UPCOMING"]
            ],
            [
                "column_id" => "date", // ID de la columna de fecha
                "operator" => "is_not_empty",
                "compare_value" => "null"
            ]
        ];

//        $boardsId = Boards::all()->pluck('id')->toArray();
        // boards where name not contains Subelementos
        $boardsId = Boards::where('name', 'not like', '%Subelementos%')->pluck('id')->toArray();
        $boardsId = [1695568080];
        $this->info('Boards: ' . count($boardsId));
        $report = '*Tareas para ' . $time . " días*\n\n";
        foreach ($boardsId as $boardId) {
            //get all tasks with cursor

            do {
                $cursor = null;
                $tasks = $mondayController->getItemsByBoard($boardId, null, $cursor, null, $rules)->getData();
                $cursor = $tasks->data->boards[0]->items_page->cursor;

                if ($cursor === "null") {
                    $cursor = null;
                }

                try {
                    $board = $tasks->data->boards[0]->name;
                    $boardUrl = $tasks->data->boards[0]->url;
                    $tasks = $tasks->data->boards[0]->items_page;
                    $tasks = $tasks->items;
                } catch (\Exception $e) {
                    $this->info('Error en el tablero: ' . $boardId . ' ' . $e->getMessage());
                    continue;
                }
                if (count($tasks) == 0) {
                    $this->info('No hay tareas en el tablero: ' . $board . ' - ' . $boardId);
                    continue;
                }
                $report .= 'Tablero: <' . $boardUrl . "|$board>\n";
                $this->info($board);
                foreach ($tasks as $task) {
                    $report .= "\t- <$task->url|$task->name> (<"/*.$mondayController->getUser($task->)*/ . ">)\n";
                    $this->info($task->name);
                }
            } while ($cursor);
        }
//        dd($report);
        $slackController->chat_post_message('C08DSE9SC3Z', $report);
//        dd($mondayController->getItemsByBoard(1709501863, null, null, null, $rules));

        return 0;
    }
}
