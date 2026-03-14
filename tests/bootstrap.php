<?php

require_once __DIR__.'/../vendor/autoload.php';

// Create a minimal Laravel application for testing
$app = new \Illuminate\Foundation\Application(
    dirname(__DIR__)
);

// Bind config repository with test values
$app->singleton('config', function () {
    return new \Illuminate\Config\Repository([
        'app' => [
            'name' => 'NotionWhatsAppAssistant',
            'env' => 'testing',
            'key' => 'base64:MMo75gYMsGnq0jOy0RMXfVHQSgmLV9g9hL1qkTVe9Zs=',
            'debug' => true,
            'url' => 'http://localhost',
            'timezone' => 'UTC',
            'locale' => 'en',
            'fallback_locale' => 'en',
            'cipher' => 'AES-256-CBC',
        ],
        'services' => [
            'twilio' => ['auth_token' => '', 'account_sid' => '', 'from' => ''],
            'openai' => ['api_key' => '', 'model' => 'gpt-4'],
            'notion' => ['api_key' => '', 'database_id' => '', 'version' => '2022-06-28'],
        ],
    ]);
});

// Bind response factory
$app->singleton(
    \Illuminate\Contracts\Http\Kernel::class,
    \Illuminate\Foundation\Http\Kernel::class
);

// Set as the global app instance
\Illuminate\Container\Container::setInstance($app);
\Illuminate\Support\Facades\Facade::setFacadeApplication($app);
