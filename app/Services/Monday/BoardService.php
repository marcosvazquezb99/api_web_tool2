<?php

namespace App\Services\Monday;

class BoardService
{
    protected $client;
    protected $limit = 25;

    public function __construct(MondayClient $client)
    {
        $this->client = $client;
    }

    /**
     * Método para obtener la información de los tableros (boards)
     */
    public function getBoards($page = 1, $limit = null)
    {
        $limit = $limit ?? $this->limit;

        $query = <<<GRAPHQL
        {
            boards(limit: $limit, page:$page) {
                id
                url
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);
        if ($response['status'] === 200) {
            return $response[0]['data']['boards'];
        }
        return [];
    }

    /**
     * Método para obtener la información de un tablero por su id
     */
    public function getBoardById($boardId)
    {
        $query = <<<GRAPHQL
        {
            boards(ids: [$boardId]) {
                id
                name
                url
                state
                workspace_id
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);
        if ($response['status'] === 200) {
            return $response[0]['data']['boards'];
        }
        return [];
    }

    /**
     * Método para duplicar un tablero en monday.com
     */
    public function duplicateBoard($boardId, $boardName, $keepSubscribers = false)
    {

        if ($keepSubscribers) {
            $keepSubscribers = 'true';
        } else {
            $keepSubscribers = 'false';
        }

        $query = <<<GRAPHQL
        mutation {
            duplicate_board (board_id:$boardId, duplicate_type: duplicate_board_with_pulses_and_updates, board_name: "$boardName", keep_subscribers: $keepSubscribers) {
                board {
                    id
                    name
                    url
                }
            }
        }
        GRAPHQL;

        return $this->client->query($query);
    }

    /**
     * Método para obtener la información de los tableros por array de IDs
     */
    public function getBoardsByIds($boardIds)
    {
        $boardIdsStringified = '[';
        foreach ($boardIds as $boardId) {
            $boardIdsStringified .= '"' . $boardId . '",';
        }
        $boardIdsStringified = rtrim($boardIdsStringified, ',');
        $boardIdsStringified .= ']';

        $query = <<<GRAPHQL
        {
            boards(ids: $boardIdsStringified) {
                id
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        $response = $this->client->query($query);
        if ($response['status'] === 200) {
            return $response['data']['data']['boards'];
        }
        return [];
    }

    /**
     * Método para encontrar un tablero por client ID
     */
    public function findBoardIdByClientId($clientId)
    {
        $page = 1;
        $foundBoard = null;

        do {
            $boards = $this->getBoards($page);

            if (count($boards) > 0) {
                foreach ($boards as $board) {
                    $mondayClientId = explode('_', $board['name']);
                    if ($mondayClientId[0] === $clientId) {
                        $foundBoard = $board;
                        break;
                    }
                }

                if ($foundBoard) {
                    break;
                }

                $page++;
            } else {
                break;
            }
        } while ($page);

        return $foundBoard;
    }

    /**
     * Método para encontrar un tablero por nombre
     */
    public function findBoardIdByName($boardName)
    {
        $page = 1;
        $foundBoard = null;

        do {
            $boards = $this->getBoards($page);

            if (count($boards) > 0) {
                foreach ($boards as $board) {
                    if ($board['name'] === $boardName) {
                        $foundBoard = $board;
                        break;
                    }
                }

                if ($foundBoard) {
                    break;
                }

                $page++;
            } else {
                break;
            }
        } while ($page);

        return $foundBoard;
    }
}
