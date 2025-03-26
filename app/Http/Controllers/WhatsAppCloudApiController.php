<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudApiController extends Controller
{
    protected $apiUrl = 'https://graph.facebook.com/v17.0/';
    protected $accessToken;
    protected $phoneNumberId;

    public function __construct()
    {
        $this->accessToken = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    /**
     * Verify webhook
     */
    public function verifyWebhook(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.webhook_token')) {
            return response($challenge, 200);
        }

        return response('Verification failed', 403);
    }

    /**
     * Handle webhook events
     */
    public function handleWebhook(Request $request)
    {
        Log::info('WhatsApp webhook received', ['payload' => $request->all()]);

        $data = $request->all();

        if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $message['from'];

            // Handle different types of messages
            if (isset($message['text'])) {
                $this->processTextMessage($from, $message['text']['body']);
            } elseif (isset($message['image'])) {
                $this->processImageMessage($from, $message['image']);
            }
            // Add more message type handlers as needed
        }

        return response('Webhook received', 200);
    }

    /**
     * Process incoming text messages
     */
    protected function processTextMessage($from, $text)
    {
        // Handle text message logic here
        // Example: send an auto-reply
        $this->sendTextMessage($from, "Thanks for your message: $text");
    }

    /**
     * Process incoming image messages
     */
    protected function processImageMessage($from, $image)
    {
        // Handle image message logic here
        $this->sendTextMessage($from, "Thanks for sending an image!");
    }

    /**
     * Send text message
     */
    public function sendTextMessage($to, $message)
    {
        $url = $this->apiUrl . $this->phoneNumberId . '/messages';

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

        Log::info('WhatsApp message sent', [
            'to' => $to,
            'message' => $message,
            'response' => $response->json()
        ]);

        return $response->json();
    }

    /**
     * Send image message
     */
    public function sendImageMessage($to, $imageUrl)
    {
        $url = $this->apiUrl . $this->phoneNumberId . '/messages';

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'image',
                'image' => [
                    'link' => $imageUrl
                ]
            ]);

        Log::info('WhatsApp image sent', [
            'to' => $to,
            'image' => $imageUrl,
            'response' => $response->json()
        ]);

        return $response->json();
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage($to, $templateName, $components = [])
    {
        $url = $this->apiUrl . $this->phoneNumberId . '/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => 'en_US'
                ]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = Http::withToken($this->accessToken)
            ->post($url, $payload);

        Log::info('WhatsApp template sent', [
            'to' => $to,
            'template' => $templateName,
            'response' => $response->json()
        ]);

        return $response->json();
    }
}
