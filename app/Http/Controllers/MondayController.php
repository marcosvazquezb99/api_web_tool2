<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class MondayController extends Controller
{
    protected $client;
    protected $mondayToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN'); // Asegúrate de agregar tu token en el archivo .env
    }

    /**
     * Método para realizar una petición GraphQL a la API de Monday.com
     */
    public function query(Request $request)
    {
        // Validar que la consulta GraphQL esté presente en la solicitud
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['error' => 'GraphQL query no proporcionada'], 400);
        }

        // Hacer la solicitud POST a la API de Monday con el query GraphQL
        try {
            $response = $this->client->post('https://api.monday.com/v2', [
                'headers' => [
                    'Authorization' => $this->mondayToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            // Decodificar y devolver la respuesta de la API de Monday
            $body = json_decode($response->getBody(), true);
            return response()->json($body);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al realizar la consulta', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Método para obtener la información de los tableros (boards)
     */
    public function getBoards()
    {
        $query = <<<'GRAPHQL'
        {
            boards(limit:500) {
                id
                url
                name
                state
                workspace_id
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        $response = $this->query(new Request(['query' => $query]));
        return response()->json($response->original['data']['boards']);
    }

    /**
     * Método para obtener los elementos (items) de un tablero específico
     */
    public function getItemsByBoard($boardId)
    {
        $query = <<<GRAPHQL
        {
            boards (ids: $boardId) {
                items {
                    id
                    name
                    column_values {
                        id
                        text
                    }
                }
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        return $this->query(new Request(['query' => $query]));
    }

    /**
     * Método para duplicar un tablero en monday.com a taves de la api
     *
     */
    public function duplicateBoard(Request $request)
    {
        $boardId = $request->input('boardId');
        $boardName = $request->input('boardName');
        $query = <<<GRAPHQL
        mutation {
            duplicate_board (board_id:$boardId, duplicate_type: duplicate_board_with_pulses_and_updates, board_name: $boardName ) {
                board {
                    id
                    name
                }
            }
        }
        GRAPHQL;

        // Llamar al método query para realizar la petición GraphQL
        return $this->query(new Request(['query' => $query]));
    }
}
