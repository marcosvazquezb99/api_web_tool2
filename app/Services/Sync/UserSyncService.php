<?php

namespace App\Services\Sync;

use App\Http\Controllers\MondayController;
use App\Http\Controllers\SlackController;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

class UserSyncService
{
    /**
     * Default hashed password for new users
     *
     * @var string
     */
    private $defaultPassword = '$2y$10$cRXJ/RKxxKGf0lpMA2fNkumENLvRmviz70mTt2URW6LBGBRsEGJGy';

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
     * Synchronize users from both Monday.com and Slack
     *
     * @return array Results of the synchronization process
     */
    public function syncAll()
    {
        try {
            $this->output('Starting user synchronization...');

            $mondayResults = $this->syncMondayUsers();
            $slackResults = $this->syncSlackUsers();

            $results = [
                'success' => true,
                'message' => 'User synchronization completed successfully',
                'data' => [
                    'monday' => $mondayResults,
                    'slack' => $slackResults,
                    'total' => [
                        'processed' => $mondayResults['totalUsers'] + $slackResults['totalUsers']
                    ]
                ]
            ];

            $this->output('User synchronization completed successfully.');
            return $results;
        } catch (Exception $e) {
            $this->output('Error during user synchronization: ' . $e->getMessage(), 'error');
            Log::error('User sync error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Error during user synchronization: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Synchronize users from Slack
     *
     * @return array
     */
    public function syncSlackUsers()
    {
        $this->output('Synchronizing Slack users...');
        $slackController = new SlackController();
        $cursor = '';
        $totalUsers = 0;
        $results = [];

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

                $this->output("Processed {$batchCount} Slack users. Next cursor: " . ($cursor ?: 'none'));
            } catch (Exception $e) {
                $this->output('Error fetching Slack users: ' . $e->getMessage(), 'error');
                Log::error('Slack sync error', ['error' => $e->getMessage()]);
                break;
            }
        } while ($cursor != '');

        $this->output("Completed Slack synchronization. Total users processed: {$totalUsers}");

        return [
            'totalUsers' => $totalUsers,
            'lastCursor' => $cursor
        ];
    }

    /**
     * Synchronize users from Monday.com
     *
     * @return array
     */
    public function syncMondayUsers()
    {
        $this->output('Synchronizing Monday.com users...');
        $mondayController = new MondayController();
        $page = 1;
        $totalUsers = 0;

        do {
            try {
                $mondayUsers = $mondayController->getUsers($page, 100)->getData();
                $batchCount = $this->processUserBatch($mondayUsers, 'monday');
                $totalUsers += $batchCount;

                $this->output("Processed {$batchCount} Monday.com users on page {$page}");
                $page++;
            } catch (Exception $e) {
                $this->output('Error fetching Monday.com users: ' . $e->getMessage(), 'error');
                Log::error('Monday sync error', ['error' => $e->getMessage()]);
                break;
            }
        } while ($batchCount > 0);

        $this->output("Completed Monday.com synchronization. Total users processed: {$totalUsers}");

        return [
            'totalUsers' => $totalUsers,
            'lastPage' => $page - 1
        ];
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
