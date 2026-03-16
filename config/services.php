<?php

return [
    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram' => [
        'bot_token'    => env('TELEGRAM_BOT_TOKEN'),
        'secret_token' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    'notion' => [
        'api_key'           => env('NOTION_API_KEY'),
        'tasks_database_id' => env('NOTION_TASKS_DATABASE_ID'),
        'ideas_database_id' => env('NOTION_IDEAS_DATABASE_ID'),
        'version'           => env('NOTION_VERSION', '2022-06-28'),
    ],
];
