<?php

namespace App\Monday\Services;

use App\Models\User;
use App\Monday\MondayClient;

class UserService
{
    protected $client;
    protected $limit = 25;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Método para obtener usuarios de Monday
     */
    public function getUsers($page = 1, $limit = null)
    {
        $limit = $limit ?? $this->limit;

        $query = <<<GRAPHQL
        {
            users(limit: $limit, page:$page) {
                id
                name
                email
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);
        if ($response['status'] === 200) {
            return $response['data']['data']['users'];
        }
        return [];
    }

    /**
     * Método para obtener un usuario específico
     */
    public function getUser($userId, $bypassLocal = false)
    {
        $user = null;

        if (!$bypassLocal) {
            $user = User::where('monday_user_id', $userId)->first();
        }
        if (!$user) {
            $response = $this->getUserRequest($userId);
            if (isset($response['data']['data']['users'][0])) {
                return $response['data']['data']['users'][0];
            }
            return null;
        } else {
            return $user;
        }
    }

    /**
     * Método para obtener información de un usuario a través de la API
     */
    protected function getUserRequest($userId)
    {
        $query = <<<GRAPHQL
        {
            users(ids: [$userId]) {
                id
                name
                email
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }
}
