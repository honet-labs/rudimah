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
            $body = $payload['body'] ?? $request->input('body', '');
            $senderName = $payload['_data']['notifyName'] ?? $payload['notifyName'] ?? null;

            if (empty($from) || empty(trim($body))) {
                return response()->json(['status' => 'ignored_empty_payload']);
            }

            // Abaikan pesan broadcast status/story WhatsApp
            if (str_contains($from, 'status@broadcast')) {
                return response()->json(['status' => 'ignored_status_broadcast']);
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
            if (!$message || empty($message['text'])) {
                return response()->json(['ok' => true, 'status' => 'no_text_message']);
            }

            $chatId = (string) ($message['chat']['id'] ?? '');
            $text = (string) ($message['text'] ?? '');
            $senderName = $message['from']['first_name'] ?? ($message['from']['username'] ?? null);

            if (empty($chatId) || empty(trim($text))) {
                return response()->json(['ok' => true, 'status' => 'empty_chat_or_text']);
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
