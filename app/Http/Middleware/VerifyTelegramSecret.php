<?php

namespace App\Http\Middleware;

use App\Services\TelegramSecretValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramSecret
{
    public function __construct(private readonly TelegramSecretValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->validator->validate($request)) {
            return response('Forbidden: Invalid Telegram secret token.', 403);
        }

        return $next($request);
    }
}
