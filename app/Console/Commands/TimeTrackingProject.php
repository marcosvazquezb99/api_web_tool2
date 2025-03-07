<?php

namespace App\Console\Commands;

use App\Http\Controllers\BoardsController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TimeTrackingReportController;
use App\Models\SlackChannel;
use Illuminate\Console\Command;

class TimeTrackingProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'time-tracking:active-boards {channel=C083ATGUVGB} ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Informe de rendimiento de uno o más proyectos en específico';

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
        $slackController = new SlackController();
        $mondayController = new MondayController();
        $timeTrackingReportController = new TimeTrackingReportController($mondayController);
        $channel_id = $this->argument('channel');
//        $channel_id = 'C083ATGUVGB';
        $boardsController = new BoardsController();
        $activeBoards = $boardsController->getActiveBoards()->getData();

        foreach ($activeBoards as $board) {
            $report = "------------------ Tablero: *" . $board->name . "* ------------------\n";
            $mondaySummary = $timeTrackingReportController->processMondayData(null, null, [$board->id]);


            $lastInteraction = $this->getLatestTime($mondaySummary);
            $this->info('Last interaction: ' . $lastInteraction . ' from board: ' . $board->name);
            // si la última interacción es mayor a 5 días
            if ($lastInteraction->diffInDays() > 5) {
                $report .= "*No se han registrado actividades en este tablero en los últimos días.*\n";
                try {
                    $slackChannel = SlackChannel::where('monday_board_id', $board->id)->first();
                    $slackController->chat_post_message(
                        $slackChannel->id,
                        "No se han registrado actividades en el tablero <$board->url|$board->name> desde $lastInteraction.");
                } catch (\Exception $e) {
                    $report .= "*No se ha podido enviar el reporte a Slack.*\n";
                }
            }

            $report .= $timeTrackingReportController->toReport($mondaySummary, 'simple');
            $slackController->chat_post_message($channel_id, $report);
        }
        return 0;
    }

    public function getLatestTime($request)
    {
        $data = $request; // Get the data from the request

        $latestTime = null;

        foreach ($data as $user) {
            foreach ($user['tableros'] as $board) {
                foreach ($board as $task) {
                    $startTime = \Carbon\Carbon::parse($task['startTime']);
                    $endTime = \Carbon\Carbon::parse($task['endTime']);

                    if ($latestTime === null || $endTime->greaterThan($latestTime)) {
                        $latestTime = $endTime;
                    }
                }
            }
        }
        return $latestTime;
    }
}
