<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NotionService
{
    /**
     * Notion API endpoint
     */
    private const NOTION_API = 'https://api.notion.com/v1';

    /**
     * Notion API version header
     */
    private const NOTION_VERSION = '2022-06-28';

    /**
     * Execute a command in Notion (create_task, list_tasks, save_idea)
     *
     * @param array $command The structured command from AI
     * @return array Result with message and optional data
     */
    public function executeCommand(array $command): array
    {
        try {
            $action = $command['action'] ?? null;

            // Route to appropriate method based on action
            return match($action) {
                'create_task' => $this->createTask($command),
                'list_tasks' => $this->listTasks($command),
                'save_idea' => $this->saveIdea($command),
                default => [
                    'message' => 'Unknown action: ' . $action,
                    'success' => false
                ]
            };

        } catch (Exception $e) {
            Log::error('NotionService Error', [
                'message' => $e->getMessage(),
                'command' => $command
            ]);

            return [
                'message' => 'Error executing command: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }

    /**
     * Create a new task in Notion
     *
     * @param array $command Command containing title and optional due date
     * @return array Result with message
     */
    private function createTask(array $command): array
    {
        $title = $command['title'] ?? null;
        $due = $command['due'] ?? null;

        if (!$title) {
            return [
                'message' => 'Task title is required',
                'success' => false
            ];
        }

        try {
            $databaseId = $this->normalizeNotionId(config('services.notion.database_tasks'));
            $token = config('services.notion.token');

            if (!$databaseId || !$token) {
                throw new Exception('Notion credentials not configured');
            }

            // Prepare the request payload for creating a page in Notion
            $payload = [
                'parent' => [
                    'database_id' => $databaseId
                ],
                'properties' => [
                    'Name' => [
                        'title' => [
                            [
                                'text' => [
                                    'content' => $title
                                ]
                            ]
                        ]
                    ],
                    'Status' => [
                        'status' => [
                            'name' => 'Not started'
                        ]
                    ]
                ]
            ];

            // Add due date if provided
            if ($due) {
                $payload['properties']['Due Date'] = [
                    'date' => [
                        'start' => $this->normalizeDateString($due),
                        'time_zone' => 'UTC'
                    ]
                ];
            }

            // Make request to Notion API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Notion-Version' => self::NOTION_VERSION,
                'Content-Type' => 'application/json',
            ])->post(self::NOTION_API . '/pages', $payload);

            if (!$response->successful()) {
                Log::error('Notion API Error creating task', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new Exception('Failed to create task in Notion');
            }

            return [
                'message' => "Task '{$title}' created successfully!" . ($due ? " Due: {$due}" : ""),
                'success' => true,
                'data' => [
                    'task_id' => $response->json()['id'] ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error creating task', [
                'error' => $e->getMessage(),
                'title' => $title
            ]);

            return [
                'message' => 'Error creating task: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }

    /**
     * List all tasks from Notion database
     *
     * @param array $command Command (no parameters needed)
     * @return array Result with message and tasks data
     */
    private function listTasks(array $command): array
    {
        try {
            $databaseId = $this->normalizeNotionId(config('services.notion.database_tasks'));
            $token = config('services.notion.token');

            if (!$databaseId || !$token) {
                throw new Exception('Notion credentials not configured');
            }

            // Make request to Notion API to query database
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Notion-Version' => self::NOTION_VERSION,
                'Content-Type' => 'application/json',
            ])->post(self::NOTION_API . '/databases/' . $databaseId . '/query', [
                'page_size' => 100
            ]);

            if (!$response->successful()) {
                Log::error('Notion API Error listing tasks', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new Exception('Failed to fetch tasks from Notion');
            }

            $responseData = $response->json();
            $results = $responseData['results'] ?? [];

            // Format tasks for display
            $tasks = [];
            foreach ($results as $page) {
                $properties = $page['properties'] ?? [];
                $name = $this->extractPropertyText($properties, 'Name');
                $status = $this->extractPropertyStatus($properties, 'Status');
                $due = $this->extractPropertyDate($properties, 'Due Date');

                if ($name) {
                    $tasks[] = [
                        'id' => $page['id'],
                        'title' => $name,
                        'status' => $status,
                        'due' => $due
                    ];
                }
            }

            // Create response message
            if (empty($tasks)) {
                $message = "You have no tasks yet.";
            } else {
                $message = "You have " . count($tasks) . " task(s):\n";
                foreach ($tasks as $task) {
                    $message .= "• " . $task['title'];
                    if ($task['due']) {
                        $message .= " (Due: " . $task['due'] . ")";
                    }
                    $message .= "\n";
                }
            }

            return [
                'message' => $message,
                'success' => true,
                'data' => [
                    'tasks' => $tasks,
                    'count' => count($tasks)
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error listing tasks', [
                'error' => $e->getMessage()
            ]);

            return [
                'message' => 'Error fetching tasks: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }

    /**
     * Save an idea/note to Notion
     *
     * @param array $command Command containing content and optional category
     * @return array Result with message
     */
    private function saveIdea(array $command): array
    {
        $content = $command['content'] ?? null;
        $category = $command['category'] ?? 'General';

        if (!$content) {
            return [
                'message' => 'Idea content is required',
                'success' => false
            ];
        }

        try {
            $databaseId = $this->normalizeNotionId(config('services.notion.database_ideas'));
            $token = config('services.notion.token');

            if (!$databaseId || !$token) {
                // If ideas database is not configured, return error
                throw new Exception('Ideas database not configured');
            }

            // Prepare the request payload for creating a page in Notion
            $payload = [
                'parent' => [
                    'database_id' => $databaseId
                ],
                'properties' => [
                    'Name' => [
                        'title' => [
                            [
                                'text' => [
                                    'content' => $content
                                ]
                            ]
                        ]
                    ],
                    'Category' => [
                        'select' => [
                            'name' => $category
                        ]
                    ],
                    'Created' => [
                        'date' => [
                            'start' => now()->toDateString()
                        ]
                    ]
                ]
            ];

            // Make request to Notion API
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$token}",
                'Notion-Version' => self::NOTION_VERSION,
                'Content-Type' => 'application/json',
            ])->post(self::NOTION_API . '/pages', $payload);

            if (!$response->successful()) {
                Log::error('Notion API Error saving idea', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new Exception('Failed to save idea in Notion');
            }

            return [
                'message' => "Idea saved successfully! 💡",
                'success' => true,
                'data' => [
                    'idea_id' => $response->json()['id'] ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error saving idea', [
                'error' => $e->getMessage(),
                'content' => $content
            ]);

            return [
                'message' => 'Error saving idea: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }

    /**
     * Extract text from a Notion property
     *
     * @param array $properties The properties object from Notion
     * @param string $propertyName The property to extract from
     * @return string|null
     */
    private function extractPropertyText(array $properties, string $propertyName): ?string
    {
        $property = $properties[$propertyName] ?? null;

        if (!$property) {
            return null;
        }

        // Handle title property
        if ($property['type'] === 'title') {
            $title = $property['title'][0]['text']['content'] ?? null;
            return $title;
        }

        // Handle rich_text property
        if ($property['type'] === 'rich_text') {
            $text = $property['rich_text'][0]['text']['content'] ?? null;
            return $text;
        }

        return null;
    }

    /**
     * Extract status from a Notion property
     *
     * @param array $properties The properties object from Notion
     * @param string $propertyName The property to extract from
     * @return string|null
     */
    private function extractPropertyStatus(array $properties, string $propertyName): ?string
    {
        $property = $properties[$propertyName] ?? null;

        if (!$property || $property['type'] !== 'status') {
            return null;
        }

        return $property['status']['name'] ?? null;
    }

    /**
     * Extract date from a Notion property
     *
     * @param array $properties The properties object from Notion
     * @param string $propertyName The property to extract from
     * @return string|null
     */
    private function extractPropertyDate(array $properties, string $propertyName): ?string
    {
        $property = $properties[$propertyName] ?? null;

        if (!$property || $property['type'] !== 'date') {
            return null;
        }

        return $property['date']['start'] ?? null;
    }

    /**
     * Convert natural language date to ISO 8601 format
     *
     * @param string $dateString Natural language date like "tomorrow", "today", etc.
     * @return string ISO 8601 formatted date
     */
    private function normalizeDateString(string $dateString): string
    {
        $dateString = strtolower(trim($dateString));

        // Handle specific cases
        $today = now();

        return match($dateString) {
            'today' => $today->toDateString(),
            'tomorrow' => $today->addDay()->toDateString(),
            'next monday' => $today->next('Monday')->toDateString(),
            'next tuesday' => $today->next('Tuesday')->toDateString(),
            'next wednesday' => $today->next('Wednesday')->toDateString(),
            'next thursday' => $today->next('Thursday')->toDateString(),
            'next friday' => $today->next('Friday')->toDateString(),
            'next saturday' => $today->next('Saturday')->toDateString(),
            'next sunday' => $today->next('Sunday')->toDateString(),
            default => $dateString // Return as-is if not a recognized pattern
        };
    }

    /**
     * Normalize a Notion ID to UUID format.
     * Accepts both 32-char IDs and dashed UUIDs.
     *
     * @param string|null $id
     * @return string|null
     */
    private function normalizeNotionId(?string $id): ?string
    {
        if (!$id) {
            return $id;
        }

        $clean = str_replace('-', '', trim($id));

        if (strlen($clean) !== 32) {
            return trim($id);
        }

        return substr($clean, 0, 8) . '-'
            . substr($clean, 8, 4) . '-'
            . substr($clean, 12, 4) . '-'
            . substr($clean, 16, 4) . '-'
            . substr($clean, 20, 12);
    }
}
