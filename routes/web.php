<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoint - no middleware
Route::get('/health', [HealthController::class, 'check']);

// WhatsApp webhook endpoint from Twilio
// Supports both /webhook/whatsapp and /api/whatsapp/webhook
Route::post('/webhook/whatsapp', [WhatsAppController::class, 'handleWebhook'])
    ->withoutMiddleware(['web'])
    ->name('whatsapp.webhook.legacy');

Route::post('/api/whatsapp/webhook', [WhatsAppController::class, 'handleWebhook'])
    ->withoutMiddleware(['web'])
    ->name('whatsapp.webhook');

// Browser check endpoint for the webhook URL
Route::get('/api/whatsapp/webhook', function () {
    return response()->json([
        'ok' => true,
        'message' => 'Webhook is live. Send POST requests to this URL (Twilio does this automatically).',
    ]);
});


