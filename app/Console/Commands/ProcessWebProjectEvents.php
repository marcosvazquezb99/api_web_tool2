<?php

namespace App\Console\Commands;

use App\Http\Controllers\SlackController;
use App\Models\Event;
use App\Services\Monday\WebProjectService;
use Illuminate\Console\Command;

class ProcessWebProjectEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:process-web-projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending web project creation events from Slack';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get pending web project creation events
        $events = Event::where('category', 'web_project_creation')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($events->isEmpty()) {
            $this->info('No pending web project events found.');
            return 0;
        }

        $this->info('Found ' . $events->count() . ' pending web project events.');
        $slackController = new SlackController();

        foreach ($events as $event) {
            $this->info('Processing event ID: ' . $event->id);

            try {
                // Extract data from the event
                $additionalData = json_decode($event->additional_data, true);
                $payload = $additionalData['payload'] ?? null;
                $channelId = $additionalData['channel_id'] ?? null;

                if (!$payload) {
                    $this->error('Invalid payload in event: ' . $event->id);
                    $event->status = 'failed';
                    $event->additional_data = json_encode([
                        ...$additionalData,
                        'error' => 'Invalid payload structure'
                    ]);
                    $event->save();
                    continue;
                }

                // Extract form data from the Slack payload
                $values = $payload['view']['state']['values'];
                $projectName = $values['project_name']['project_name_input']['value'] ?? null;
                $projectType = $values['project_type']['project_type_input']['selected_option']['value'] ?? null;

                if (!$projectName || !$projectType) {
                    throw new \Exception('Missing required project information');
                }

                // Extract phase dates
                $phases = [];
                foreach ($values as $blockId => $blockValues) {
                    if (strpos($blockId, 'phase_') === 0) {
                        $parts = explode('_', $blockId);
                        if (count($parts) >= 3) {
                            $phaseId = $parts[1];
                            $dateType = $parts[2]; // start or end

                            if (!isset($phases[$phaseId])) {
                                $phases[$phaseId] = [];
                            }

                            $actionId = "{$blockId}_input";
                            $dateValue = $blockValues[$actionId]['selected_date'] ?? null;
                            $phases[$phaseId][$dateType] = $dateValue;
                        }
                    }
                }

                // Extract team assignments
                $teamAssignments = [];
                foreach ($values as $blockId => $blockValues) {
                    if (strpos($blockId, 'team_') === 0) {
                        $team = str_replace('team_', '', $blockId);
                        $team = str_replace('_', ' ', $team);

                        $actionId = "{$blockId}_input";
                        $userId = $blockValues[$actionId]['selected_option']['value'] ?? null;

                        if ($userId) {
                            $teamAssignments[$team] = $userId;
                        }
                    }
                }

                // Create the project in Monday.com
                $webProjectService = new WebProjectService();
                $result = $webProjectService->createWebProject(
                    $projectName,
                    $projectType,
                    $phases,
                    $teamAssignments
                );

                if ($result['success']) {
                    // Update the event status
                    $event->status = 'completed';
                    $event->additional_data = json_encode([
                        ...$additionalData,
                        'result' => $result
                    ]);
                    $event->save();

                    // Send a success message to the Slack channel
                    $boardUrl = $result['board_url'] ?? '';
                    $successMessage = "✅ Proyecto web *{$projectName}* creado correctamente.\n";
                    $successMessage .= $boardUrl ? "🔗 <{$boardUrl}|Ver tablero en Monday.com>" : "";

                    if ($channelId) {
                        $slackController->chat_post_message($channelId, $successMessage);
                    }

                    $this->info('Successfully created web project: ' . $projectName);
                } else {
                    throw new \Exception($result['error'] ?? 'Unknown error creating web project');
                }

            } catch (\Exception $e) {
                $this->error('Error processing event ' . $event->id . ': ' . $e->getMessage());

                // Update the event with the error
                $additionalData = json_decode($event->additional_data, true);
                $event->status = 'failed';
                $event->additional_data = json_encode([
                    ...$additionalData,
                    'error' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ]);
                $event->save();

                // Notify the user of the failure
                $channelId = $additionalData['channel_id'] ?? null;
                if ($channelId) {
                    $slackController->chat_post_message(
                        $channelId,
                        "❌ Error al crear el proyecto web: " . $e->getMessage()
                    );
                }
            }
        }

        return 0;
    }
}
