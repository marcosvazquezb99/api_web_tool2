<?php

namespace App\Console\Commands\sync;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Models\User;
use Illuminate\Console\Command;

class SyncUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $this->syncMondayUsers();
        $this->syncSlackUsers();


        return 0;
    }


    public function syncSlackUsers()
    {
        $slackController = new SlackController();
        $cursor = '';


        do {
            $slackUsers = $slackController->users_list($cursor, 100)->getData();
//            dd($slackUsers);
            $cursor = $slackUsers->response_metadata->next_cursor;
            $slackUsers = $slackUsers->members;
            //filtrar solo los usuarios no eliminados y que tengan email
            $slackUsers = array_filter($slackUsers, function ($user) {
                return $user->deleted == false && isset($user->profile->email) && $user->is_bot == false;
            });

            // Create a new user on table if not exists one by one using model Users
            foreach ($slackUsers as $slackUser) {
                $user_id = $slackUser->id;
                $name = $slackUser->real_name;
                $password = '$2y$10$cRXJ/RKxxKGf0lpMA2fNkumENLvRmviz70mTt2URW6LBGBRsEGJGy';
//                dd($slackUser->profile);
                $email = $slackUser->profile->email;
                $user = User::firstOrCreate(['email' => $email],
                    [
                        'name' => $name,
                        'password' => $password,
                        'email' => $email
                    ]);
                if ($user->slack_user_id == null) {
                    $user->slack_user_id = $user_id;
                    $user->save();
                }
            }
            $this->info('Page ' . $cursor . ' done');
//            $slackUsers = $slackController->getUsers($page,2)->getData();
        } while ($cursor != '');
    }

    public function syncMondayUsers()
    {
        $mondayController = new MondayController();
        $page = 1;

        do {
            $mondayUsers = $mondayController->getUsers($page, 100)->getData();
            // Create a new user on table if not exists one by one using model Users
            foreach ($mondayUsers as $mondayUser) {
                $user_id = $mondayUser->id;
                $name = $mondayUser->name;
                $password = '$2y$10$cRXJ/RKxxKGf0lpMA2fNkumENLvRmviz70mTt2URW6LBGBRsEGJGy';
                $email = $mondayUser->email;
                $user = User::firstOrCreate(['email' => $email],
                    [
                        'name' => $name,
                        'password' => $password,
                        'email' => $email
                    ]);
                if ($user->monday_user_id == null) {
                    $user->monday_user_id = $user_id;
                    $user->save();
                }
            }
            $this->info('Page ' . $page . ' done ' . count($mondayUsers));
            $page++;
        } while (count($mondayUsers) > 0);
    }
}
