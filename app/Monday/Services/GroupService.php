<?php

namespace App\Monday\Services;

use App\Monday\MondayClient;

class GroupService
{
    protected $client;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Método para obtener los grupos de un tablero específico
     */
    public function getGroupsOfBoard($boardId)
    {
        $query = <<<GRAPHQL
        {
            boards(ids: ["$boardId"]) {
                groups {
                    title
                    id
                }
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);
        if ($response['status'] === 200) {
//            dd($response);
            return $response[0]['data']['boards'];
        }
        return [];
    }

    /**
     * Método para duplicar un grupo de un tablero
     */
    public function duplicateGroup(string $boardId, string $groupId, bool $addToTop = true)
    {
        $query = <<<GRAPHQL
        mutation {
            duplicate_group (board_id: $boardId, group_id: "$groupId", add_to_top: true) {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para actualizar el título de un grupo
     */
    public function updateGroupTitle(string $boardId, string $groupId, string $title)
    {
        $query = <<<GRAPHQL
        mutation {
            update_group (board_id: $boardId, group_id: "$groupId", group_attribute: title, new_value: "$title") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para crear un grupo en un tablero
     */
    public function createGroup(string $boardId, string $name)
    {
        $query = <<<GRAPHQL
        mutation {
            create_group (board_id: $boardId, group_name: "$name") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para eliminar un grupo de un tablero
     */
    public function deleteGroup(string $boardId, string $groupId)
    {
        $query = <<<GRAPHQL
        mutation {
            delete_group (board_id: $boardId, group_id: "$groupId") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }
}
