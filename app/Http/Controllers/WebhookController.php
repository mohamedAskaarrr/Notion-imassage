<?php

namespace App\Http\Controllers;

use App\Services\NotionService;
use App\Services\OpenAIService;
use App\Services\TwilioSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(
        private readonly TwilioSignatureValidator $twilioValidator,
        private readonly OpenAIService $openAIService,
        private readonly NotionService $notionService,
    ) {}

    public function handle(Request $request): Response
    {
        if (!$this->twilioValidator->validate($request)) {
            return new Response('Forbidden', 403);
        }

        $messageBody = $request->input('Body', '');

        try {
            $parsedIntent = $this->openAIService->parseIntent($messageBody);
            $result = $this->executeNotionAction($parsedIntent);
            $replyMessage = $this->buildReplyMessage($parsedIntent, $result);
        } catch (\Exception $e) {
            $replyMessage = 'Sorry, I encountered an error processing your request. Please try again.';
        }

        $twiml = $this->buildTwimlResponse($replyMessage);

        return new Response($twiml, 200, ['Content-Type' => 'application/xml']);
    }

    private function executeNotionAction(array $parsedIntent): array
    {
        return match ($parsedIntent['action'] ?? 'unknown') {
            'create_page' => $this->notionService->createPage($parsedIntent),
            'add_to_database' => $this->notionService->addToDatabase($parsedIntent),
            'update_page' => $this->notionService->updatePage(
                $parsedIntent['properties']['page_id'] ?? '',
                $parsedIntent['properties'] ?? []
            ),
            'query_database' => $this->notionService->queryDatabase(
                $parsedIntent['properties']['filter'] ?? []
            ),
            default => ['status' => 'unknown_action'],
        };
    }

    private function buildReplyMessage(array $parsedIntent, array $result): string
    {
        $action = $parsedIntent['action'] ?? 'unknown';
        $title = $parsedIntent['title'] ?? 'Untitled';

        return match ($action) {
            'create_page' => "✅ Page '{$title}' created in Notion successfully.",
            'add_to_database' => "✅ Entry '{$title}' added to Notion database.",
            'update_page' => "✅ Notion page updated successfully.",
            'query_database' => "🔍 Found " . count($result['results'] ?? []) . " results in Notion.",
            default => "⚠️ I couldn't understand the action. Please try rephrasing your request.",
        };
    }

    private function buildTwimlResponse(string $message): string
    {
        $escapedMessage = htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>\n    <Message>{$escapedMessage}</Message>\n</Response>";
    }
}
