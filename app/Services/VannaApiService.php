<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VannaApiService
{
    private $url = 'https://app.vanna.ai/api/v0/chat_sse';
    private $apiKey;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.vanna.api_key');
    }

    public function chatStream($message, $userEmail, $acceptableResponses = ['text', 'image', 'link'])
    {
        $data = [
            'message' => $message,
            'user_email' => $userEmail,
            'acceptable_responses' => $acceptableResponses
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'VANNA-API-KEY' => $this->apiKey
        ];

        try {
            // Using Laravel's HTTP client with streaming
            $response = Http::withHeaders($headers)
                ->timeout(120) // 2 minutes timeout
                ->post($this->url, $data);

            if ($response->successful()) {
                return $this->parseStreamResponse($response->body());
            } else {
                throw new \Exception("HTTP Error: " . $response->status() . " - " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Vanna API Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function chatStreamWithCallback($message, $userEmail, callable $callback, $acceptableResponses = ['text', 'image', 'link'])
    {
        $data = [
            'message' => $message,
            'user_email' => $userEmail,
            'acceptable_responses' => $acceptableResponses
        ];

        try {
            // Use cURL for streaming response handling
            $ch = curl_init();


            curl_setopt_array($ch, [
                CURLOPT_URL => $this->url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'VANNA-API-KEY: ' . $this->apiKey
                ],
                CURLOPT_WRITEFUNCTION => function($ch, $data) use ($callback) {
                    return $this->handleStreamChunk($data, $callback);
                },
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($result === false) {
                throw new \Exception("cURL Error: " . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception("HTTP Error: " . $httpCode);
            }

            return true;

        } catch (\Exception $e) {
            Log::error("Vanna API Streaming Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function handleStreamChunk($chunk, callable $callback)
    {
        static $buffer = '';
        $buffer .= $chunk;

        // Process complete lines
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);

            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'data:') === 0) {
                $dataString = trim(substr($line, 5));

                try {
                    $data = json_decode($dataString, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->processResponseData($data, $callback);
                    }
                } catch (\Exception $e) {
                    Log::warning("Error decoding JSON: " . $e->getMessage() . " - Original data: " . $dataString);
                }
            } else {
                // Handle other types of lines if needed
                Log::info("Other line: " . $line);
            }
        }

        return strlen($chunk);
    }

    private function processResponseData($data, callable $callback)
    {
        switch ($data['type'] ?? '') {
            case 'text':
                $callback('text', [
                    'text' => $data['text'] ?? ''
                ]);
                break;

            case 'image':
                $callback('image', [
                    'image_url' => $data['image_url'] ?? '',
                    'caption' => $data['caption'] ?? null
                ]);
                break;

            case 'link':
                $callback('link', [
                    'title' => $data['title'] ?? '',
                    'url' => $data['url'] ?? '',
                    'description' => $data['description'] ?? null
                ]);
                break;

            case 'end':
                $callback('end', [
                    'conversation_id' => $data['conversation_id'] ?? ''
                ]);
                break;

            default:
                $callback('unknown', $data);
                break;
        }
    }

    private function parseStreamResponse($responseBody)
    {
        $lines = explode("\n", $responseBody);
        $results = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, 'data:') === 0) {
                $dataString = trim(substr($line, 5));

                try {
                    $data = json_decode($dataString, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        $results[] = $data;
                    }
                } catch (\Exception $e) {
                    Log::warning("Error decoding JSON: " . $e->getMessage());
                }
            }
        }

        return $results;
    }
}

// Usage Examples:

// 1. In a Controller


// 2. Add to config/services.php
/*
'vanna' => [
    'api_key' => env('VANNA_API_KEY'),
],
*/

// 3. Add to .env file
/*
VANNA_API_KEY=your_api_key_here
*/

// 4. Artisan Command Example
/*
php artisan make:command VannaChatCommand

class VannaChatCommand extends Command
{
    protected $signature = 'vanna:chat {message} {--email=default@example.com}';
    protected $description = 'Chat with Vanna API';

    public function handle()
    {
        $vannaService = new VannaApiService();

        $vannaService->chatStreamWithCallback(
            $this->argument('message'),
            $this->option('email'),
            function($type, $data) {
                $this->processResponse($type, $data);
            }
        );
    }

    private function processResponse($type, $data)
    {
        switch ($type) {
            case 'text':
                $this->info("Text: " . $data['text']);
                break;
            case 'image':
                $this->info("Image: " . $data['image_url']);
                break;
            case 'link':
                $this->info("Link: " . $data['title'] . " - " . $data['url']);
                break;
            case 'end':
                $this->info("Conversation ended: " . $data['conversation_id']);
                break;
        }
    }
}
*/
