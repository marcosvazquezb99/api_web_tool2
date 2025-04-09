<?php

namespace App\Services\Monday;

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
        if (count($rules) > 0 && is_null($cursor)) {
            $queryParams .= ', query_params:{ rules: [';
            if (count($rules)) {
                foreach ($rules as $key => $value) {
                    $queryParams .= '{column_id: "' . $value['column_id'] . '", operator: ' . $value['operator'] . ', compare_value: ' . json_encode($value['compare_value']) . '}';
                    if ($key < count($rules) - 1) {
                        $queryParams .= ',';
                    }
                }
            }
            $queryParams .= ']}';
        }
        if ($cursor === null) {
            $cursor = 'null';
        } else {
            $cursor = '"' . $cursor . '"';
        }
        $query = <<<GRAPHQL
        {
            boards(limit: 1, ids: ["$boardId"]) {
                name
                url
                items_page(limit: $limit, cursor: $cursor$queryParams ) {
                    items {
                        id
                        url
                        name

                        updated_at
                        column_values {
                            id
                            text
                            value
                            type
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
    public function changeSimpleColumnValue(string $boardId, string $itemId, string $columnId, string $value)
    {
        $query = <<<GRAPHQL
        mutation {
            change_simple_column_value (board_id: $boardId, item_id: "$itemId", column_id: "$columnId", value: "$value") {
                id
                name
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


    /**
     * Método para duplicar un item en un tablero
     * @param string $boardId
     * @param string $itemId
     * @param string|null $groupId
     * @return array
     */
    public function duplicateItem(string $boardId, string $itemId, string $groupId = null): array
    {
        $query = <<<GRAPHQL
        mutation {
            duplicate_item (board_id: "$boardId", item_id: "$itemId", with_updates: true) {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para crear un item en un tablero
     * @param string $boardId
     * @param string $groupId
     * @param string $itemName
     * @param array $columnValues
     * @return array
     */
    public function createItem(string $boardId, string $groupId, string $itemName, array $columnValues): array
    {
        $columnValuesData = '';
        if (count($columnValues) > 0) {

            foreach ($columnValues as $columnId => $value) {
                $columnValuesData .= "{\"$columnId\", \"$value\"},";
            }
            $columnValuesData = rtrim($columnValuesData, ',');
            $columnValuesData = ", column_values: [$columnValuesData]";
        }


        $query = <<<GRAPHQL
        mutation {
            create_item (board_id: "$boardId", group_id: "$groupId", item_name: "$itemName" $columnValuesData) {
                id
            }
        }
        GRAPHQL;
        //dd($query);

        return $this->client->query($query);
    }

    /**
     * Método para eliminar un item de un tablero
     * @param string $boardId
     * @param string $itemId
     * @return array
     */
    public function deleteItem(string $boardId, string $itemId): array
    {
        $query = <<<GRAPHQL
        mutation {
            delete_item (board_id: "$boardId", item_id: "$itemId") {
                id
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }
}
