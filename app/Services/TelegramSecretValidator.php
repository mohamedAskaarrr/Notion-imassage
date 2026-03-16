<?php

namespace App\Services;

use Illuminate\Http\Request;

class TelegramSecretValidator
{
    private string $secretToken;

    public function __construct()
    {
        $this->secretToken = config('services.telegram.secret_token', '');
    }

    public function validate(Request $request): bool
    {
        if (empty($this->secretToken)) {
            return false;
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (empty($header)) {
            return false;
        }

        return hash_equals($this->secretToken, $header);
    }
}
