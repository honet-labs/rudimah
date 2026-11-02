<?php

namespace App\Core\Notifications\Http\Controllers;

use App\Core\Notifications\Services\ExternalChatGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatWebhookController
{
    protected ExternalChatGatewayService $gateway;

    public function __construct(ExternalChatGatewayService $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Webhook endpoint untuk WhatsApp (WAHA).
     */
    public function handleWhatsApp(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $payload = $request->input('payload', $data);

            // Hindari memproses pesan yang dikirim oleh bot sendiri
            $fromMe = $payload['fromMe'] ?? $request->input('fromMe', false);
            if ($fromMe) {
                return response()->json(['status' => 'ignored_self_message']);
            }

            $from = $payload['from'] ?? $request->input('from');
            $body = $payload['body'] ?? $payload['caption'] ?? $request->input('body', '');
            $senderName = $payload['_data']['notifyName'] ?? $payload['notifyName'] ?? null;

            if (empty($from)) {
                return response()->json(['status' => 'ignored_empty_from']);
            }

            // Abaikan pesan broadcast status/story WhatsApp
            if (str_contains($from, 'status@broadcast')) {
                return response()->json(['status' => 'ignored_status_broadcast']);
            }

            // 1. Cek apakah pesan berisi gambar / media (misal foto struk belanja)
            $hasMedia = $payload['hasMedia'] ?? $request->input('hasMedia', false);
            $media = $payload['media'] ?? $request->input('media', null);

            if ($hasMedia && is_array($media)) {
                $base64 = $media['data'] ?? '';
                $mimeType = $media['mimetype'] ?? 'image/jpeg';
                $url = $media['url'] ?? '';

                if (empty($base64) && !empty($url)) {
                    try {
                        $imgRes = \Illuminate\Support\Facades\Http::timeout(20)->get($url);
                        if ($imgRes->successful()) {
                            $base64 = base64_encode($imgRes->body());
                        }
                    } catch (\Throwable $e) {
                        Log::warning('[ChatWebhookController] Gagal mengunduh media WAHA: ' . $e->getMessage());
                    }
                }

                if (!empty($base64)) {
                    $reply = $this->gateway->handleIncomingMedia('whatsapp', $from, $base64, $mimeType, $body, $senderName);
                    if ($reply) {
                        $this->gateway->sendReply('whatsapp', $from, $reply);
                    }
                    return response()->json(['status' => 'processed_media']);
                }
            }

            if (empty(trim($body))) {
                return response()->json(['status' => 'ignored_empty_payload']);
            }

            $reply = $this->gateway->handleIncomingMessage('whatsapp', $from, $body, $senderName);
            if ($reply) {
                $this->gateway->sendReply('whatsapp', $from, $reply);
            }

            return response()->json(['status' => 'processed']);
        } catch (\Throwable $e) {
            Log::error('[ChatWebhookController] Error webhook WhatsApp: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook endpoint untuk Bot Telegram.
     */
    public function handleTelegram(Request $request): JsonResponse
    {
        try {
            $message = $request->input('message');
            if (!$message) {
                return response()->json(['ok' => true, 'status' => 'no_message']);
            }

            $chatId = (string) ($message['chat']['id'] ?? '');
            $text = (string) ($message['text'] ?? $message['caption'] ?? '');
            $senderName = $message['from']['first_name'] ?? ($message['from']['username'] ?? null);

            if (empty($chatId)) {
                return response()->json(['ok' => true, 'status' => 'empty_chat_id']);
            }

            // 1. Cek apakah pesan berisi foto struk/kuitansi
            $photos = $message['photo'] ?? null;
            if (!empty($photos) && is_array($photos)) {
                $largestPhoto = end($photos);
                $fileId = $largestPhoto['file_id'] ?? null;

                if ($fileId) {
                    $token = \App\Core\Identity\Models\SystemSetting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
                    if (!empty($token)) {
                        $fileRes = \Illuminate\Support\Facades\Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getFile?file_id={$fileId}");
                        if ($fileRes->successful() && !empty($fileRes->json('result.file_path'))) {
                            $filePath = $fileRes->json('result.file_path');
                            $downRes = \Illuminate\Support\Facades\Http::timeout(25)->get("https://api.telegram.org/file/bot{$token}/{$filePath}");
                            if ($downRes->successful()) {
                                $base64 = base64_encode($downRes->body());
                                $reply = $this->gateway->handleIncomingMedia('telegram', $chatId, $base64, 'image/jpeg', $text, $senderName);
                                if ($reply) {
                                    $this->gateway->sendReply('telegram', $chatId, $reply);
                                }
                                return response()->json(['ok' => true, 'status' => 'processed_media']);
                            }
                        }
                    }
                }
            }

            if (empty(trim($text))) {
                return response()->json(['ok' => true, 'status' => 'empty_text']);
            }

            $reply = $this->gateway->handleIncomingMessage('telegram', $chatId, $text, $senderName);
            if ($reply) {
                $this->gateway->sendReply('telegram', $chatId, $reply);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('[ChatWebhookController] Error webhook Telegram: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
