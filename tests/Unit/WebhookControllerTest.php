<?php

namespace Tests\Unit;

use App\Http\Controllers\WebhookController;
use App\Services\GeminiService;
use App\Services\NotionService;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

class WebhookControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRequest(string $text = '', int $chatId = 123456789): Request
    {
        $body = ['message' => ['text' => $text, 'chat' => ['id' => $chatId]]];

        $request = Request::create('/api/webhook/telegram', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body));
        $request->setJson(new \Symfony\Component\HttpFoundation\ParameterBag($body));

        return $request;
    }

    public function test_handle_returns_ok_json_on_valid_request(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('parseIntent')
            ->once()
            ->with('Add task: deploy hotfix')
            ->andReturn([
                'action'     => 'add_to_database',
                'database'   => 'tasks',
                'title'      => 'Deploy hotfix',
                'content'    => '',
                'properties' => [],
            ]);

        $notion = Mockery::mock(NotionService::class);
        $notion->shouldReceive('addToDatabase')
            ->once()
            ->with(Mockery::any(), 'tasks')
            ->andReturn(['object' => 'page', 'id' => 'page-123']);

        $telegramBot = Mockery::mock(TelegramBotService::class);
        $telegramBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) {
                return $chatId === 123456789
                    && str_contains($text, 'Deploy hotfix')
                    && str_contains($text, 'Tasks');
            });

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest('Add task: deploy hotfix'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }

    public function test_handle_routes_add_to_ideas_database(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('parseIntent')
            ->once()
            ->andReturn([
                'action'     => 'add_to_database',
                'database'   => 'ideas',
                'title'      => 'Build a habit tracker',
                'content'    => '',
                'properties' => [],
            ]);

        $notion = Mockery::mock(NotionService::class);
        $notion->shouldReceive('addToDatabase')
            ->once()
            ->with(Mockery::any(), 'ideas')
            ->andReturn(['object' => 'page', 'id' => 'page-456']);

        $telegramBot = Mockery::mock(TelegramBotService::class);
        $telegramBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) {
                return str_contains($text, 'Ideas');
            });

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest('Save idea: build a habit tracker'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_returns_ok_json_on_query_action(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('parseIntent')
            ->once()
            ->andReturn([
                'action'     => 'query_database',
                'database'   => 'tasks',
                'title'      => '',
                'content'    => '',
                'properties' => ['filter' => []],
            ]);

        $notion = Mockery::mock(NotionService::class);
        $notion->shouldReceive('queryDatabase')
            ->once()
            ->with([], 'tasks')
            ->andReturn(['results' => [['id' => '1'], ['id' => '2']]]);

        $telegramBot = Mockery::mock(TelegramBotService::class);
        $telegramBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) {
                return str_contains($text, '2 results');
            });

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest('Show all my tasks'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }

    public function test_handle_returns_ok_json_on_update_action(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('parseIntent')
            ->once()
            ->andReturn([
                'action'     => 'update_page',
                'database'   => 'tasks',
                'title'      => '',
                'content'    => '',
                'properties' => ['page_id' => 'page-abc', 'status' => 'Done'],
            ]);

        $notion = Mockery::mock(NotionService::class);
        $notion->shouldReceive('updatePage')
            ->once()
            ->with('page-abc', ['page_id' => 'page-abc', 'status' => 'Done'])
            ->andReturn(['object' => 'page', 'id' => 'page-abc']);

        $telegramBot = Mockery::mock(TelegramBotService::class);
        $telegramBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) {
                return str_contains($text, 'updated successfully');
            });

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest('Update page page-abc set status to Done'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_returns_ok_with_error_message_on_exception(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('parseIntent')
            ->once()
            ->andThrow(new \RuntimeException('Gemini API error'));

        $notion = Mockery::mock(NotionService::class);

        $telegramBot = Mockery::mock(TelegramBotService::class);
        $telegramBot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text) {
                return str_contains($text, 'error');
            });

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest('Some message'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }

    public function test_handle_returns_ok_when_message_text_is_empty(): void
    {
        $gemini      = Mockery::mock(GeminiService::class);
        $notion      = Mockery::mock(NotionService::class);
        $telegramBot = Mockery::mock(TelegramBotService::class);

        $gemini->shouldNotReceive('parseIntent');
        $telegramBot->shouldNotReceive('sendMessage');

        $controller = new WebhookController($gemini, $notion, $telegramBot);
        $response   = $controller->handle($this->makeRequest(''));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }
}
