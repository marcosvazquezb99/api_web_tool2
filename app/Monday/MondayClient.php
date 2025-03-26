<?php

namespace App\Monday;

use GuzzleHttp\Client;

class MondayClient
{
    protected $client;
    protected $mondayToken;

    public function __construct()
    {
        $this->client = new Client();
        $this->mondayToken = env('MONDAY_API_TOKEN');
    }

    /**
     * Método para realizar una petición GraphQL a la API de Monday.com
     */
    public function query($query)
    {
        if (!$query) {
            return ['error' => 'GraphQL query no proporcionada', 'status' => 400];
        }

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

            return [json_decode($response->getBody(), true), 'status' => 200];
        } catch (\Exception $e) {
            return ['error' => 'Error al realizar la consulta', 'message' => $e->getMessage(), 'status' => 500];
        }
    }
}
