<?php

use App\Core\Notifications\Http\Controllers\ChatWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'healthy',
        'platform' => 'RUDIMAH',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Webhook integrasi obrolan WAHA & Telegram Bot
Route::post('/webhook/whatsapp', [ChatWebhookController::class, 'handleWhatsApp']);
Route::post('/webhook/telegram', [ChatWebhookController::class, 'handleTelegram']);

