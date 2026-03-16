<?php

namespace App\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class GeminiService
{
    private string $apiKey;
    private string $model;

    public function __construct(private readonly ClientInterface $httpClient)
    {
        $this->apiKey = config('services.gemini.api_key', '');
        $this->model  = config('services.gemini.model', 'gemini-1.5-flash');
    }

    public function parseIntent(string $message): array
    {
        $systemPrompt = <<<'PROMPT'
You are an AI assistant that parses user messages and extracts intent for Notion operations.
Analyze the user's message and return a JSON object with the following fields:
- action: one of "create_page", "add_to_database", "update_page", "query_database"
- database: one of "tasks" (for tasks, to-dos, action items) or "ideas" (for ideas, notes, thoughts, concepts)
- title: the title or name of the item (string)
- content: the main content or description (string)
- properties: additional properties as key-value pairs (object)

Examples:
- "Add task: fix login bug, priority high" -> {"action": "add_to_database", "database": "tasks", "title": "Fix login bug", "content": "", "properties": {"priority": "high"}}
- "Save idea: build a habit tracker app" -> {"action": "add_to_database", "database": "ideas", "title": "Build a habit tracker app", "content": "", "properties": {}}
- "Show all my tasks" -> {"action": "query_database", "database": "tasks", "title": "", "content": "", "properties": {}}
- "List my ideas" -> {"action": "query_database", "database": "ideas", "title": "", "content": "", "properties": {}}

Return ONLY valid JSON, no additional text or markdown.
PROMPT;

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->apiKey
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => [
                    'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents'           => [['parts' => [['text' => $message]]]],
                    'generationConfig'   => [
                        'temperature'     => 0.2,
                        'maxOutputTokens' => 500,
                    ],
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

            // Strip markdown code fences that Gemini sometimes wraps JSON in
            $rawText = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
            $rawText = preg_replace('/\s*```$/', '', $rawText);

            $parsed = json_decode(trim($rawText), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Gemini: ' . json_last_error_msg());
            }

            return [
                'action'     => $parsed['action']     ?? 'unknown',
                'database'   => $parsed['database']   ?? 'tasks',
                'title'      => $parsed['title']      ?? '',
                'content'    => $parsed['content']    ?? '',
                'properties' => $parsed['properties'] ?? [],
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to call Gemini API: ' . $e->getMessage(), 0, $e);
        }
    }
}
