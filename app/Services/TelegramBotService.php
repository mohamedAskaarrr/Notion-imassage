<?php

namespace App\Services;

use GuzzleHttp\ClientInterface;

class TelegramBotService
{
    private string $botToken;

    public function __construct(private readonly ClientInterface $httpClient)
    {
        $this->botToken = config('services.telegram.bot_token', '');
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        if (empty($this->botToken)) {
            return;
        }

        try {
            $this->httpClient->request(
                'POST',
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                ['json' => ['chat_id' => $chatId, 'text' => $text]]
            );
        } catch (\Throwable) {
            // Non-critical: a failed reply should not crash the webhook handler
        }
    }
}
