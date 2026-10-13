<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', function () {
    return response()->json([
        'status' => 'healthy',
        'platform' => 'RUDIMAH',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/webhook/whatsapp', function (Request $request) {
    // Webhook balasan WhatsApp Bot
    return response()->json(['received' => true]);
});
