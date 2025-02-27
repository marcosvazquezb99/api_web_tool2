<?php

namespace App\Console\Commands\sync;

use App\Http\Controllers\MondayController;
use App\Models\Boards;
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

        $boards = $mondayController->getBoards($page, 100)->getData();

        while (count($boards) > 0) {
            // Create a new board on table if not exists one by one using model Boards
            foreach ($boards as $board) {
                $board_id = $board->id;
                $name = $board->name;
                $boardUrl = $board->url;
//                dd($boardUrl);
                $board = Boards::updateOrCreate(['id' => $board_id],
                    [
                        'name' => $name,
                        'id' => $board_id,
                        'url' => $boardUrl
                    ]);
                $board->name = $name;
                $board->save();
            }
            $this->info('Page ' . $page . ' done');


            $page++;
            $boards = $mondayController->getBoards($page, 100)->getData();
            sleep(3);
        }
        return 0;
    }
}
