<?php

namespace App\Console\Commands;

use App\Services\VannaApiService;
use Illuminate\Console\Command;

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
