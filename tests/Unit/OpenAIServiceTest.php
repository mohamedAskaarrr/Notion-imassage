<?php

namespace Tests\Unit;

use App\Services\OpenAIService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Mockery;
use PHPUnit\Framework\TestCase;

class OpenAIServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_parse_intent_returns_correct_structure(): void
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'action' => 'create_page',
                            'title' => 'Meeting with John',
                            'content' => 'Meeting notes',
                            'properties' => [],
                        ]),
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->with('POST', 'https://api.openai.com/v1/chat/completions', Mockery::any())
            ->andReturn(new Response(200, [], json_encode($mockResponse)));

        $service = $this->createService($httpClient);
        $result = $service->parseIntent('Create a note about my meeting with John');

        $this->assertIsArray($result);
        $this->assertEquals('create_page', $result['action']);
        $this->assertEquals('Meeting with John', $result['title']);
        $this->assertEquals('Meeting notes', $result['content']);
        $this->assertIsArray($result['properties']);
    }

    public function test_parse_intent_throws_on_invalid_json(): void
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'not valid json {{{',
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($mockResponse)));

        $service = $this->createService($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON response from OpenAI/');

        $service->parseIntent('Some message');
    }

    public function test_parse_intent_throws_on_http_error(): void
    {
        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andThrow(new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('POST', 'https://api.openai.com/v1/chat/completions')
            ));

        $service = $this->createService($httpClient);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Failed to call OpenAI API/');

        $service->parseIntent('Some message');
    }

    public function test_parse_intent_handles_add_to_database_action(): void
    {
        $mockResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'action' => 'add_to_database',
                            'title' => 'Fix the login bug',
                            'content' => '',
                            'properties' => ['priority' => 'high'],
                        ]),
                    ],
                ],
            ],
        ];

        $httpClient = Mockery::mock(ClientInterface::class);
        $httpClient->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($mockResponse)));

        $service = $this->createService($httpClient);
        $result = $service->parseIntent('Add a task: Fix the login bug, priority high');

        $this->assertEquals('add_to_database', $result['action']);
        $this->assertEquals('Fix the login bug', $result['title']);
        $this->assertEquals(['priority' => 'high'], $result['properties']);
    }

    private function createService(ClientInterface $httpClient): OpenAIService
    {
        return new class($httpClient) extends OpenAIService {
            public function __construct(ClientInterface $httpClient)
            {
                // bypass parent constructor that calls config()
                // Use reflection to set the readonly property and private fields
                $reflection = new \ReflectionClass(OpenAIService::class);
                
                $httpClientProp = $reflection->getProperty('httpClient');
                $httpClientProp->setAccessible(true);
                $httpClientProp->setValue($this, $httpClient);

                $apiKeyProp = $reflection->getProperty('apiKey');
                $apiKeyProp->setAccessible(true);
                $apiKeyProp->setValue($this, 'test_api_key');

                $modelProp = $reflection->getProperty('model');
                $modelProp->setAccessible(true);
                $modelProp->setValue($this, 'gpt-4');
            }
        };
    }
}
