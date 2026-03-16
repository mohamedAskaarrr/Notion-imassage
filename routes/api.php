<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/telegram', [WebhookController::class, 'handle'])
    ->middleware('verify.telegram');
