<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\TelegramController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// WhatsApp webhook endpoint from Twilio
// This receives incoming WhatsApp messages and replies back
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'handleWebhook'])
    ->withoutMiddleware(['api'])
    ->name('whatsapp.webhook');

// Telegram webhook endpoint
// This receives incoming Telegram bot messages
Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook'])
    ->name('telegram.webhook');
