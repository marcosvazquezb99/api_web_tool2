<?php

namespace App\Console\Commands;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TimeTrackingReportController;
use App\Models\Boards;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpcommingTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:upcomming {time=TODAY} {channel=C08DSE9SC3Z}';

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
        $time = strtoupper($this->argument('time'));
        $channel = $this->argument('channel');
        switch ($time) {
            case 'TODAY':
                $date = Carbon::today();
                break;
            case 'YESTERDAY':
                $date = Carbon::yesterday();
                break;
            case 'TOMORROW';
                $date = Carbon::tomorrow();
                if ($date->isSaturday()) {
                    $date->addDays(2); // Add 2 days to get to Monday
                }
                break;
        }

        // Instanciar el controlador de Slack
        $slackController = new SlackController();
        $mondayController = new MondayController();
        $tasksByUser = [];
        $timeTrackingReportController = new TimeTrackingReportController($mondayController);

        $rules = [
            [
                "column_id" => "date", // ID de la columna de fecha
                "operator" => "any_of",
                "compare_value" => ["EXACT", "{$date->format('Y-m-d')}"]
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
//        $boardsId = [1383274320];
        $this->info('Boards: ' . count($boardsId));
        $report = '*Tareas para ' . $date->format('d/m/y') . "*\n\n";
        foreach ($boardsId as $boardId) {
            //get all tasks with cursor

            $cursor = null;
            do {
                $tasks = $mondayController->getItemsByBoard($boardId, null, $cursor, null, $rules)[0];

                try {
                    $cursor = $tasks['data']['boards'][0]['items_page']['cursor'];
                    if ($cursor === "null") {
                        $cursor = null;
                    }

                    $board = $tasks['data']['boards'][0];
                    $boardName = $board['name'];
                    $boardUrl = $board['url'];
                    $tasks = $board['items_page']['items'];
                    //$tasks = $tasks->items;
                } catch (\Exception $e) {
                    $this->info('Error en el tablero: ' . $boardId . ' ' . $e->getMessage());
                    continue;
                }
                if (count($tasks) == 0) {
                    // $this->info('No hay tareas en el tablero: ' . $boardName . ' - ' . $boardId);
                    continue;
                }
                //$report .= 'Tablero: <' . $boardUrl . "|$board>\n";
//                $this->info($boardName);
                $allUsers = [];
                foreach ($tasks as $task) {
                    $user = null;
                    $hours_estimate = null;
                    foreach ($task['column_values'] as $column_value) {
                        if (isset($column_value['persons_and_teams']) && count($column_value['persons_and_teams'])) {
                            //     $this->info($column_value->persons_and_teams[0]->id);


                            $user = $mondayController->getUser($column_value['persons_and_teams'][0]['id']);
//                            dd($user);
                        }
                        if (isset($column_value['column']['title'])
                            && $column_value['column']['title'] == "Fecha prevista") {
                            $date = $column_value['text'];
                        } elseif (isset($column_value['column']['title'])
                            && $column_value['column']['title'] == "Horas programadas") {
                            //dd($task);
                            $hours_estimate = $column_value['number'] ?? null;

                        }


                    }

//                    $this->info($user->name);
                    $tasksByUser[$user->monday_user_id ?? 'Sin asignar'][] = [
                        'task' => $task,
                        'user' => $user,
                        'board' => $board,
                        'hours_estimate' => $hours_estimate,
                        'date' => $date ?? null,
                    ];
//                    $report .= "\t- <$task->url|$task->name> (".$slackController->formatDisplayUser($user) . " - $hours_estimate - $date)\n";

                    //$this->info($task->name);
                }
            } while ($cursor);
        }

        foreach ($tasksByUser as $user) {
            //dd($user[0]["user"]);
            $report .= "Usuario {$slackController->formatDisplayUser($user[0]["user"])}\n";
            $hour_counter = 0;
            foreach ($user as $task) {
//dd($task['date']);
                $report .= "\t- <{$task['task']['url']}|{$task['task']['name']}> ({$timeTrackingReportController->formatTime($task['hours_estimate'])} - {$task['date']}) - <{$task['board']['url']}|{$task['board']['name']}>\n";
//                try {
//                    dd($hour_counter);
                $hour_counter += $task['hours_estimate'];
                /*}catch (\Exception $exception){
                    dd($task,$task['board']->url, $exception);
                }*/

            }

            $report .= "Horas totales: {$this->formatTime($hour_counter)}\n\n";

        }
        $slackController->chat_post_message($channel, $report);

        return 0;
    }

    private function formatTime($time)
    {
        try {
            $hours = floor($time);
            $minutes = ($time - $hours) * 60;
//            dd($time);
            return sprintf("%02d:%02d", $hours, $minutes);
        } catch (\Exception $e) {
            dd($e);
        }
    }
}
