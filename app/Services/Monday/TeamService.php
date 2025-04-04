<?php

namespace App\Services\Monday;

class TeamService
{
    protected $client;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get all teams from Monday.com
     *
     * @return array
     */
    public function getTeams(): array
    {
        $query = <<<GRAPHQL
        {
            teams {
                id
                name
                picture_url
                users {
                    id
                    name
                    email
                    photo_thumb_small
                }
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);

        if (isset($response['data']['teams'])) {
            return $response['data']['teams'];
        }

        return [];
    }

    /**
     * Get a specific team by ID
     *
     * @param string $teamId
     * @return array|null
     */
    public function getTeam(string $teamId): ?array
    {
        $query = <<<GRAPHQL
        {
            teams(ids: [$teamId]) {
                id
                name
                picture_url
                users {
                    id
                    name
                    email
                    photo_thumb_small
                }
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);

        if (isset($response['data']['teams'][0])) {
            return $response['data']['teams'][0];
        }

        return null;
    }

    /**
     * Get teams with their users formatted for the web project form
     *
     * @return array
     */
    public function getTeamsWithUsers(): array
    {
        $teams = $this->getTeams();
        $formattedTeams = [];

        foreach ($teams as $team) {
            $teamName = $team['name'];

            foreach ($team['users'] as $user) {
                $formattedTeams[] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'team' => $teamName,
                    'email' => $user['email'] ?? '',
                    'photo' => $user['photo_thumb_small'] ?? ''
                ];
            }
        }

        return $formattedTeams;
    }
}
