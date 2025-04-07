<?php

namespace App\Services\Monday;

class GroupService
{
    protected $client;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Método para obtener grupos de un tablero específico
     */
    public function getGroupsOfBoard($boardId)
    {
        $query = <<<GRAPHQL
        {
            boards(ids: ["$boardId"]) {
                groups {
                    id
                    title
                    color
                    position
                }
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para duplicar un grupo de un tablero
     * @param string $boardId
     * @param string $groupId
     * @param bool $addToTop
     * @return array
     */
    public function duplicateGroup(string $boardId, string $groupId, bool $addToTop = true): array
    {
        $query = <<<GRAPHQL
        mutation {
            duplicate_group (board_id: "$boardId", group_id: "$groupId", add_to_top: $addToTop) {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para actualizar el título de un grupo
     * @param string $boardId
     * @param string $groupId
     * @param string $title
     * @return array
     */
    public function updateGroupTitle(string $boardId, string $groupId, string $title): array
    {
        $query = <<<GRAPHQL
        mutation {
            update_group (board_id: "$boardId", group_id: "$groupId", group_attribute: title, new_value: "$title") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para crear un grupo en un tablero
     * @param string $boardId
     * @param string $name
     * @return array
     */
    public function createGroup(string $boardId, string $name): array
    {
        $query = <<<GRAPHQL
        mutation {
            create_group (board_id: "$boardId", group_name: "$name") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para eliminar un grupo de un tablero
     * @param string $boardId
     * @param string $groupId
     * @return array
     */
    public function deleteGroup(string $boardId, string $groupId): array
    {
        $query = <<<GRAPHQL
        mutation {
            delete_group (board_id: "$boardId", group_id: "$groupId") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Get items within a specific group
     * @param string $boardId
     * @param string $groupId
     * @return array
     */
    public function getItemsInGroup(string $boardId, string $groupId, $cursor = null): array
    {
        $cursor = $cursor ? "$cursor" : "null";
        $query = <<<GRAPHQL
        {
            boards(ids: ["$boardId"]) {
                groups(ids: ["$groupId"]) {
                    items_page (cursor: $cursor) {
                        items {
                            id
                            name
                            column_values {
                                id
                                text
                                column {
                                    id
                                    title
                                    type
                                }
                            }
                        }
                        cursor
                    }
                }
            }
        }
        GRAPHQL;
//        dd($query);

        return $this->client->query($query);
    }
}
