<?php

namespace App\Console\Commands\sync;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    protected $description = 'Synchronize users from Monday.com and Slack with local database';

    /**
     * Default hashed password for new users
     *
     * @var string
     */
    private $defaultPassword = '$2y$10$cRXJ/RKxxKGf0lpMA2fNkumENLvRmviz70mTt2URW6LBGBRsEGJGy';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info('Starting user synchronization...');

            $this->syncMondayUsers();
            $this->syncSlackUsers();

            $this->info('User synchronization completed successfully.');
            return 0;
        } catch (Exception $e) {
            $this->error('Error during user synchronization: ' . $e->getMessage());
            Log::error('User sync error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 1;
        }
    }

    /**
     * Synchronize users from Slack
     */
    public function syncSlackUsers()
    {
        $this->info('Synchronizing Slack users...');
        $slackController = new SlackController();
        $cursor = '';
        $totalUsers = 0;

        do {
            try {
                $response = $slackController->users_list($cursor, 100)->getData();
                $cursor = $response->response_metadata->next_cursor;
                $slackUsers = $response->members;

                // Filter active, non-bot users with email
                $slackUsers = array_filter($slackUsers, function ($user) {
                    return !$user->deleted && isset($user->profile->email) && !$user->is_bot;
                });

                $batchCount = $this->processUserBatch($slackUsers, 'slack');
                $totalUsers += $batchCount;

                $this->info("Processed {$batchCount} Slack users. Next cursor: " . ($cursor ?: 'none'));
            } catch (Exception $e) {
                $this->error('Error fetching Slack users: ' . $e->getMessage());
                Log::error('Slack sync error', ['error' => $e->getMessage()]);
                break;
            }
        } while ($cursor != '');

        $this->info("Completed Slack synchronization. Total users processed: {$totalUsers}");
    }

    /**
     * Synchronize users from Monday.com
     */
    public function syncMondayUsers()
    {
        $this->info('Synchronizing Monday.com users...');
        $mondayController = new MondayController();
        $page = 1;
        $totalUsers = 0;

        do {
            try {
                $mondayUsers = $mondayController->getUsers($page, 100)->getData();
                $batchCount = $this->processUserBatch($mondayUsers, 'monday');
                $totalUsers += $batchCount;

                $this->info("Processed {$batchCount} Monday.com users on page {$page}");
                $page++;
            } catch (Exception $e) {
                $this->error('Error fetching Monday.com users: ' . $e->getMessage());
                Log::error('Monday sync error', ['error' => $e->getMessage()]);
                break;
            }
        } while ($batchCount > 0);

        $this->info("Completed Monday.com synchronization. Total users processed: {$totalUsers}");
    }

    /**
     * Process a batch of users from either Slack or Monday
     *
     * @param array $users Users to process
     * @param string $source Source of users ('slack' or 'monday')
     * @return int Number of users processed
     */
    private function processUserBatch($users, $source)
    {
        $count = 0;
        foreach ($users as $externalUser) {
            try {
                if ($source === 'slack') {
                    $userId = $externalUser->id;
                    $name = $externalUser->real_name;
                    $email = $externalUser->profile->email;
                    $idField = 'slack_user_id';
                } else {
                    $userId = $externalUser->id;
                    $name = $externalUser->name;
                    $email = $externalUser->email;
                    $idField = 'monday_user_id';
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => $this->defaultPassword,
                        'email' => $email
                    ]
                );

                if ($user->$idField === null) {
                    $user->$idField = $userId;
                    $user->save();
                }

                $count++;
            } catch (Exception $e) {
                Log::warning("Failed to process user", [
                    'source' => $source,
                    'error' => $e->getMessage(),
                    'user' => json_encode($externalUser)
                ]);
            }
        }

        return $count;
    }
}
