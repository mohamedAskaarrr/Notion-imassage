<?php

namespace App\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class NotionService
{
    private string $apiKey;
    private string $tasksDatabaseId;
    private string $ideasDatabaseId;
    private string $notionVersion;

    public function __construct(private readonly ClientInterface $httpClient)
    {
        $this->apiKey          = config('services.notion.api_key', '');
        $this->tasksDatabaseId = config('services.notion.tasks_database_id', '');
        $this->ideasDatabaseId = config('services.notion.ideas_database_id', '');
        $this->notionVersion   = config('services.notion.version', '2022-06-28');
    }

    private function getHeaders(): array
    {
        return [
            'Authorization'  => 'Bearer ' . $this->apiKey,
            'Content-Type'   => 'application/json',
            'Notion-Version' => $this->notionVersion,
        ];
    }

    private function resolveDatabaseId(string $database): string
    {
        return match ($database) {
            'ideas' => $this->ideasDatabaseId,
            default => $this->tasksDatabaseId,
        };
    }

    public function createPage(array $parsedIntent, string $database = 'tasks'): array
    {
        $title      = $parsedIntent['title']      ?? 'Untitled';
        $content    = $parsedIntent['content']    ?? '';
        $properties = $parsedIntent['properties'] ?? [];

        $pageProperties = [
            'title' => [
                'title' => [
                    ['text' => ['content' => $title]],
                ],
            ],
        ];

        foreach ($properties as $key => $value) {
            if (is_string($value)) {
                $pageProperties[$key] = [
                    'rich_text' => [
                        ['text' => ['content' => $value]],
                    ],
                ];
            }
        }

        $body = [
            'parent'     => ['database_id' => $this->resolveDatabaseId($database)],
            'properties' => $pageProperties,
        ];

        if (!empty($content)) {
            $body['children'] = [
                [
                    'object'    => 'block',
                    'type'      => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [
                            ['type' => 'text', 'text' => ['content' => $content]],
                        ],
                    ],
                ],
            ];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.notion.com/v1/pages', [
                'headers' => $this->getHeaders(),
                'json'    => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to create Notion page: ' . $e->getMessage(), 0, $e);
        }
    }

    public function addToDatabase(array $parsedIntent, string $database = 'tasks'): array
    {
        return $this->createPage($parsedIntent, $database);
    }

    public function updatePage(string $pageId, array $properties): array
    {
        if (empty($pageId)) {
            throw new \InvalidArgumentException('Page ID is required for update operation');
        }

        $pageProperties = [];
        // 'page_id' identifies the page, 'filter' is used for queries;
        // neither should be stored as a Notion property value.
        $reservedKeys = ['page_id', 'filter'];
        foreach ($properties as $key => $value) {
            if (in_array($key, $reservedKeys, true)) {
                continue;
            }
            if (is_string($value)) {
                $pageProperties[$key] = [
                    'rich_text' => [
                        ['text' => ['content' => $value]],
                    ],
                ];
            }
        }

        try {
            $response = $this->httpClient->request('PATCH', "https://api.notion.com/v1/pages/{$pageId}", [
                'headers' => $this->getHeaders(),
                'json'    => ['properties' => $pageProperties],
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to update Notion page: ' . $e->getMessage(), 0, $e);
        }
    }

    public function queryDatabase(array $filter = [], string $database = 'tasks'): array
    {
        $databaseId = $this->resolveDatabaseId($database);
        $body       = [];

        if (!empty($filter)) {
            $body['filter'] = $filter;
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                "https://api.notion.com/v1/databases/{$databaseId}/query",
                [
                    'headers' => $this->getHeaders(),
                    'json'    => $body,
                ]
            );

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to query Notion database: ' . $e->getMessage(), 0, $e);
        }
    }
}
