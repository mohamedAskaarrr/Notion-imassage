<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parse_intent_returns_correct_structure(): void
    {
        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'action'     => 'add_to_database',
                                    'database'   => 'tasks',
                                    'title'      => 'Fix login bug',
                                    'content'    => '',
                                    'properties' => ['priority' => 'high'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->withArgs(function ($method, $url) {
                return $method === 'POST'
                    && str_contains($url, 'generativelanguage.googleapis.com')
                    && str_contains($url, 'gemini-1.5-flash');
            })
            ->andReturn(new Response(200, [], json_encode($geminiResponse)));

        $service = $this->createService($httpClient);
        $result  = $service->parseIntent('Add task: fix login bug, priority high');

        $this->assertIsArray($result);
        $this->assertEquals('add_to_database', $result['action']);
        $this->assertEquals('tasks', $result['database']);
        $this->assertEquals('Fix login bug', $result['title']);
        $this->assertEquals(['priority' => 'high'], $result['properties']);
    }

    public function test_parse_intent_handles_ideas_database(): void
    {
        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'action'     => 'add_to_database',
                                    'database'   => 'ideas',
                                    'title'      => 'Build a habit tracker app',
                                    'content'    => '',
                                    'properties' => [],
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($geminiResponse)));

        $service = $this->createService($httpClient);
        $result  = $service->parseIntent('Save idea: build a habit tracker app');

        $this->assertEquals('add_to_database', $result['action']);
        $this->assertEquals('ideas', $result['database']);
        $this->assertEquals('Build a habit tracker app', $result['title']);
    }

    public function test_parse_intent_strips_markdown_code_fences(): void
    {
        $jsonPayload = json_encode([
            'action'     => 'query_database',
            'database'   => 'tasks',
            'title'      => '',
            'content'    => '',
            'properties' => [],
        ]);

        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => "```json\n{$jsonPayload}\n```"],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($geminiResponse)));

        $service = $this->createService($httpClient);
        $result  = $service->parseIntent('Show all my tasks');

        $this->assertEquals('query_database', $result['action']);
        $this->assertEquals('tasks', $result['database']);
    }

    public function test_parse_intent_throws_on_invalid_json(): void
    {
        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [['text' => 'not valid json {{{']],
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($geminiResponse)));

        $service = $this->createService($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON response from Gemini/');

        $service->parseIntent('Some message');
    }

    public function test_parse_intent_throws_on_http_error(): void
    {
        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andThrow(new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('POST', 'https://generativelanguage.googleapis.com')
            ));

        $service = $this->createService($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to call Gemini API/');

        $service->parseIntent('Some message');
    }

    public function test_parse_intent_defaults_database_to_tasks_when_missing(): void
    {
        $geminiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'action'     => 'create_page',
                                    'title'      => 'Meeting notes',
                                    'content'    => 'Notes',
                                    'properties' => [],
                                    // 'database' key intentionally omitted
                                ]),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($geminiResponse)));

        $service = $this->createService($httpClient);
        $result  = $service->parseIntent('Create a note about the meeting');

        $this->assertEquals('tasks', $result['database']);
    }

    private function createService(ClientInterface $httpClient): GeminiService
    {
        return new class($httpClient) extends GeminiService {
            public function __construct(ClientInterface $httpClient)
            {
                $reflection = new \ReflectionClass(GeminiService::class);

                $prop = $reflection->getProperty('httpClient');
                $prop->setAccessible(true);
                $prop->setValue($this, $httpClient);

                $prop = $reflection->getProperty('apiKey');
                $prop->setAccessible(true);
                $prop->setValue($this, 'test_gemini_key');

                $prop = $reflection->getProperty('model');
                $prop->setAccessible(true);
                $prop->setValue($this, 'gemini-1.5-flash');
            }
        };
    }
}
