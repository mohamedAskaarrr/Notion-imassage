<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\NotionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Telegram Bot API base URL
     */
    private const TELEGRAM_API = 'https://api.telegram.org/bot';

    /**
     * Handle incoming Telegram webhook messages
     *
     * @param Request $request
     * @param AIService $aiService
     * @param NotionService $notionService
     * @return Response
     */
    public function handleWebhook(Request $request, AIService $aiService, NotionService $notionService): Response
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            Log::info('Telegram payload', $data);

            $message_text = trim((string) ($data['message']['text'] ?? ''));
            $chat_id = $data['message']['chat']['id'] ?? $data['chat_join_request']['chat']['id'] ?? null;

            Log::info('Telegram chat id', [
                'chat_id' => $chat_id
            ]);

            if (!$chat_id) {
                return response('ok', 200);
            }

            $replyText = 'Send me a message like "Add task finish MLH challenge tomorrow" or "Show my tasks".';
            $ruleMatched = false;

            if ($message_text !== '') {
                $message = strtolower($message_text);

                if (str_starts_with($message, 'add task')) {
                    $ruleMatched = true;
                    $title = trim(substr($message_text, strlen('add task')));
                    $result = $notionService->createTask($title);

                    Log::info('Telegram Notion result', [
                        'chat_id' => $chat_id,
                        'result' => $result,
                    ]);

                    $replyText = ($result['success'] ?? false)
                        ? '✅ Task added: ' . $title
                        : ($result['message'] ?? '❌ Failed to create task.');
                } elseif ($message === 'show my tasks') {
                    $ruleMatched = true;
                    $result = $notionService->listTasks();

                    Log::info('Telegram Notion result', [
                        'chat_id' => $chat_id,
                        'result' => $result,
                    ]);

                    if (($result['success'] ?? false) && !empty($result['data']['tasks'])) {
                        $lines = [];
                        foreach ($result['data']['tasks'] as $index => $task) {
                            $lines[] = ($index + 1) . '. ' . $task['title'];
                        }

                        $replyText = "📋 Your tasks:\n" . implode("\n", $lines);
                    } else {
                        $replyText = $result['message'] ?? 'Error fetching tasks.';
                    }
                } elseif (str_starts_with($message, 'save idea')) {
                    $ruleMatched = true;
                    $title = trim(substr($message_text, strlen('save idea')));
                    $result = $notionService->createIdea($title);

                    Log::info('Telegram Notion result', [
                        'chat_id' => $chat_id,
                        'result' => $result,
                    ]);

                    $replyText = ($result['success'] ?? false)
                        ? '💡 Idea saved: ' . $title
                        : ($result['message'] ?? '❌ Failed to save idea.');
                }

                if (!$ruleMatched) {
                    $command = $aiService->parseMessage($message_text);

                    Log::info('Telegram AI parsed command', [
                        'chat_id' => $chat_id,
                        'command' => $command,
                    ]);

                    if (!$command || !isset($command['action']) || $command['action'] === 'unknown') {
                        $replyText = "I didn't understand that. Try:\n- Add task buy groceries tomorrow\n- Show my tasks\n- Save idea build a habit tracker app";
                    } else {
                        $result = $notionService->executeCommand($command);

                        Log::info('Telegram Notion result', [
                            'chat_id' => $chat_id,
                            'result' => $result,
                        ]);

                        $replyText = $result['message'] ?? ('Message received: ' . $message_text);
                    }
                }
            }

            $botToken = config('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');

            if (!$botToken) {
                Log::error('Telegram bot token not configured');
                return response('ok', 200);
            }

            $response = Http::timeout(15)->post(
                self::TELEGRAM_API . $botToken . '/sendMessage',
                [
                    'chat_id' => $chat_id,
                    'text' => $replyText,
                ]
            );

            Log::info('Telegram response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram Webhook Error', [
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // Always return HTTP 200 so Telegram does not retry the update
        return response('ok', 200);
    }

    /**
     * Send a text message to a Telegram chat via the Bot API
     *
     * @param int|string $chatId  The recipient chat / user ID
     * @param string     $text    The message to send
     * @return void
     */
    private function sendTelegramMessage(int|string $chatId, string $text): void
    {
        $botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');

        if (!$botToken) {
            Log::error('Telegram bot token not configured');
            return;
        }

        $url = self::TELEGRAM_API . $botToken . '/sendMessage';

        $response = Http::post($url, [
            'chat_id' => $chatId,
            'text'    => $text,
        ]);

        if (!$response->successful()) {
            Log::error('Failed to send Telegram message', [
                'chat_id'  => $chatId,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
        } else {
            Log::info('Telegram message sent', [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);
        }
    }
}
