<?php

namespace App\Http\Controllers\Holded;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class HoldedController extends Controller
{
    public const url = 'https://api.holded.com/api';

    /**
     * Make a request to Holded API
     *
     * @param string $method HTTP method
     * @param string $url API endpoint
     * @param array $body Request body
     * @return array|JsonResponse
     */
    protected function holdedRequest(string $method, string $url, array $body = [])
    {
        $client = new Client();
        $data = [
            'headers' => [
                'key' => env('HOLDED_API_TOKEN'),
            ],
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $data['body'] = json_encode($body);
        }

        try {
            $response = $client->request($method, $url, $data);
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Holded API error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }

    /**
     * Build URL with parameters
     *
     * @param string $endpoint Base endpoint
     * @param array $params URL parameters
     * @return string
     */
    protected function buildUrl(string $endpoint, array $params = []): string
    {
        $url = $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }
}
