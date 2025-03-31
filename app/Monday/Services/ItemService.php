<?php

namespace App\Monday\Services;

use App\Monday\MondayClient;
use App\Monday\MondayDataDefinitions;

class ItemService
{
    protected $client;
    protected $limit = 25;
    protected $itemsData;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
        $this->itemsData = MondayDataDefinitions::getItemsData();
    }

    /**
     * Método para obtener los elementos (items) de un grupo específico
     */
    public function getItemsOfGroup($boardId, $groupId, $cursor = null, $limit = null)
    {
        $limit = $limit ?? $this->limit;

        if ($cursor === null) {
            $cursor = 'null';
        } else {
            $cursor = '"' . $cursor . '"';
        }

        $query = <<<GRAPHQL
        {
            boards(ids: ["$boardId"]) {
                groups(ids: ["$groupId"]) {
                    items_page {
                        items {
                            id
                            url
                            name
                            updated_at
                            column_values {
                                id
                                column {
                                    title
                                }
                                type
                                text
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para obtener los elementos (items) de un tablero específico
     */
    public function getItemsByBoard($boardId, $columns = null, $cursor = null, $limit = null, $rules = [])
    {
        $columnsData = '';
        $queryParams = '';

        // Obtener las columnas a solicitar
        if ($columns) {
            $columns = explode(',', $columns);
        } else {
            $columns = array_keys($this->itemsData);
        }

        // Crear la cadena de columnas a solicitar
        foreach ($columns as $column) {
            $columnsData .= $this->itemsData[$column] . "\n            column{title}";
        }

        // Obtener el límite de elementos (items) del tablero
        $limit = $limit ?? $this->limit;

        if ($cursor === null) {
            $cursor = 'null';
        } else {
            $cursor = '"' . $cursor . '"';
        }

        $queryParams .= 'rules: [';
        if (count($rules)) {
            foreach ($rules as $key => $value) {
                $queryParams .= '{column_id: "' . $value['column_id'] . '", operator: ' . $value['operator'] . ', compare_value: ' . json_encode($value['compare_value']) . '}';
                if ($key < count($rules) - 1) {
                    $queryParams .= ',';
                }
            }
        }
        $queryParams .= ']';

        $query = <<<GRAPHQL
        {
            boards(limit: 1, ids: ["$boardId"]) {
                name
                url
                items_page(limit: $limit, cursor: $cursor, query_params:{ $queryParams }) {
                    items {
                        id
                        url
                        name
                        updated_at
                        column_values {
                            $columnsData
                        }
                    }
                    cursor
                }
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para cambiar el valor de la columna de un item
     */
    public function changeColumnValue(string $boardId, string $itemId, string $columnId, string $value)
    {
        $query = <<<GRAPHQL
        mutation {
            change_simple_column_value (board_id: $boardId, item_id: "$itemId", column_id: "$columnId", value: "$value") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para mover un item de un tablero a otro
     */
    public function moveItemToBoard(string $boardId, string $groupId, string $itemId)
    {
        $query = <<<GRAPHQL
        mutation {
            move_item_to_board (board_id: $boardId, group_id: "$groupId", item_id: "$itemId") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para obtener un item por su id
     * @param string $itemId
     * @return array
     */
    public function getItemById(string $itemId): array
    {
        $query = <<<GRAPHQL
        {
            items(ids: ["$itemId"]) {
                id
                name
                url
                updated_at
                board{
                    id
                    name
                    url
                }
                column_values {
                    id
                    column {
                        title
                    }
                    type
                    text
                }
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }
}
