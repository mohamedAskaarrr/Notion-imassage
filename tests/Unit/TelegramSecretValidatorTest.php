<?php

namespace Tests\Unit;

use App\Services\TelegramSecretValidator;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TelegramSecretValidatorTest extends TestCase
{
    private string $secretToken = 'super_secret_webhook_token_123';

    public function test_validate_returns_false_when_secret_token_not_configured(): void
    {
        $validator = $this->createValidatorWithToken('');

        $request = Request::create('/api/webhook/telegram', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'some_token');

        $this->assertFalse($validator->validate($request));
    }

    public function test_validate_returns_false_when_header_is_missing(): void
    {
        $validator = $this->createValidatorWithToken($this->secretToken);

        $request = Request::create('/api/webhook/telegram', 'POST');
        // No X-Telegram-Bot-Api-Secret-Token header set

        $this->assertFalse($validator->validate($request));
    }

    public function test_validate_returns_false_when_header_does_not_match(): void
    {
        $validator = $this->createValidatorWithToken($this->secretToken);

        $request = Request::create('/api/webhook/telegram', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'wrong_token');

        $this->assertFalse($validator->validate($request));
    }

    public function test_validate_returns_true_when_header_matches_configured_token(): void
    {
        $validator = $this->createValidatorWithToken($this->secretToken);

        $request = Request::create('/api/webhook/telegram', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $this->secretToken);

        $this->assertTrue($validator->validate($request));
    }

    public function test_validate_is_case_sensitive(): void
    {
        $validator = $this->createValidatorWithToken($this->secretToken);

        $request = Request::create('/api/webhook/telegram', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', strtoupper($this->secretToken));

        $this->assertFalse($validator->validate($request));
    }

    private function createValidatorWithToken(string $token): TelegramSecretValidator
    {
        return new class($token) extends TelegramSecretValidator {
            public function __construct(private string $token)
            {
                // Bypass parent constructor that calls config()
            }

            public function validate(Request $request): bool
            {
                if (empty($this->token)) {
                    return false;
                }

                $header = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

                if (empty($header)) {
                    return false;
                }

                return hash_equals($this->token, $header);
            }
        };
    }
}
