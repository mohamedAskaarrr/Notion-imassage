<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use App\Services\NotionService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly NotionService $notionService,
        private readonly TelegramBotService $telegramBot,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $messageText = $request->input('message.text', '');
        $chatId      = $request->input('message.chat.id');

        if (empty($messageText) || empty($chatId)) {
            return new JsonResponse(['ok' => true]);
        }

        try {
            $parsedIntent = $this->geminiService->parseIntent($messageText);
            $result       = $this->executeNotionAction($parsedIntent);
            $replyMessage = $this->buildReplyMessage($parsedIntent, $result);
        } catch (\Exception $e) {
            $replyMessage = 'Sorry, I encountered an error processing your request. Please try again.';
        }

        $this->telegramBot->sendMessage($chatId, $replyMessage);

        return new JsonResponse(['ok' => true]);
    }

    private function executeNotionAction(array $parsedIntent): array
    {
        $database = $parsedIntent['database'] ?? 'tasks';

        return match ($parsedIntent['action'] ?? 'unknown') {
            'create_page'     => $this->notionService->createPage($parsedIntent, $database),
            'add_to_database' => $this->notionService->addToDatabase($parsedIntent, $database),
            'update_page'     => $this->notionService->updatePage(
                $parsedIntent['properties']['page_id'] ?? '',
                $parsedIntent['properties'] ?? []
            ),
            'query_database'  => $this->notionService->queryDatabase(
                $parsedIntent['properties']['filter'] ?? [],
                $database
            ),
            default => ['status' => 'unknown_action'],
        };
    }

    private function buildReplyMessage(array $parsedIntent, array $result): string
    {
        $action   = $parsedIntent['action']   ?? 'unknown';
        $title    = $parsedIntent['title']    ?? 'Untitled';
        $database = $parsedIntent['database'] ?? 'tasks';
        $dbLabel  = ucfirst($database);

        return match ($action) {
            'create_page'     => "✅ Page '{$title}' created in Notion successfully.",
            'add_to_database' => "✅ Entry '{$title}' added to your {$dbLabel} database.",
            'update_page'     => "✅ Notion page updated successfully.",
            'query_database'  => "🔍 Found " . count($result['results'] ?? []) . " results in your {$dbLabel} database.",
            default           => "⚠️ I couldn't understand the action. Please try rephrasing your request.",
        };
    }
}
