<?php

namespace App\Console\Commands;

use App\Http\Controllers\BoardsController;
use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Http\Controllers\TimeTrackingReportController;
use Illuminate\Console\Command;

class TimeTrackingProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'time-tracking:active-boards';

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
        $timeTrackingReportController = new TimeTrackingReportController();
        $channel_id = 'C083ATGUVGB';
        $boardsController = new BoardsController();
        $activeBoards = $boardsController->getActiveBoards()->getData();
        foreach ($activeBoards as $board) {
            $report = "--------------------------------------------------\nTablero: " . $board->name . "\n";
            $mondaySummary = $mondayController->getTimeTrakingMondayBoardSummary($board->id);
            //convert $mondaySummary to array
//            dd($mondaySummary);
            $report .= $timeTrackingReportController->toReport($mondaySummary, 'simple');
            $slackController->chat_post_message($channel_id, $report);
//            $slackController->getTimeTrackingMondayBoardSummary($board->board_id, $channel_id);
        }
        return 0;
    }
}
