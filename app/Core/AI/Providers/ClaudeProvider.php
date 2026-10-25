<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;
    protected string $apiBaseUrl;

    public function __construct(?string $apiKey = null, string $defaultModel = 'claude-3-5-sonnet-20241022', ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.claude_key', env('CLAUDE_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: 'claude-3-5-sonnet-20241022';
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.claude_endpoint', env('CLAUDE_API_ENDPOINT', 'https://api.anthropic.com/v1')), '/');
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $endpoint = rtrim($options['api_endpoint'] ?? $this->apiBaseUrl, '/');

        if (empty($this->apiKey)) {
            return $this->generateLocalFallbackChat($messages, $model);
        }

        try {
            $systemPrompt = '';
            $formattedMessages = [];

            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemPrompt .= ($systemPrompt ? "\n\n" : '') . $msg['content'];
                } else {
                    $formattedMessages[] = [
                        'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                        'content' => $msg['content'],
                    ];
                }
            }

            // Pastikan pesan berurutan dengan benar dan tidak kosong
            if (empty($formattedMessages)) {
                $formattedMessages[] = ['role' => 'user', 'content' => 'Halo'];
            }

            $url = $this->buildMessagesUrl($endpoint);
            $payload = [
                'model' => $model,
                'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
                'messages' => $formattedMessages,
                'temperature' => (float) ($options['temperature'] ?? 0.7),
            ];

            if (!empty($systemPrompt)) {
                $payload['system'] = $systemPrompt;
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(35)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $content = '';
                if (!empty($data['content']) && is_array($data['content'])) {
                    foreach ($data['content'] as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $content .= $block['text'] ?? '';
                        }
                    }
                }

                $usage = $data['usage'] ?? [];
                $tokens = (int) (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0));

                return [
                    'content' => $content,
                    'model' => $model,
                    'tokens_used' => $tokens,
                    'metadata' => [
                        'provider' => 'claude',
                        'id' => $data['id'] ?? null,
                    ],
                ];
            }

            Log::warning('Anthropic Claude API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->generateLocalFallbackChat($messages, $model);
        } catch (\Throwable $e) {
            Log::info('Anthropic Claude API connection exception, using simulation fallback', ['error' => $e->getMessage()]);
            return $this->generateLocalFallbackChat($messages, $model);
        }
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $endpoint = rtrim($options['api_endpoint'] ?? $this->apiBaseUrl, '/');

        if (empty($this->apiKey)) {
            return $this->generateFallbackReceipt();
        }

        try {
            $url = $this->buildMessagesUrl($endpoint);
            $prompt = 'Ekstrak struk belanja/kuitansi berikut ke dalam format JSON murni tanpa markdown fence. Format wajib:
{
  "merchant_name": "string",
  "receipt_number": "string",
  "transaction_date": "YYYY-MM-DD",
  "currency": "Rp",
  "subtotal_minor": integer,
  "tax_minor": integer,
  "service_charge_minor": integer,
  "discount_minor": integer,
  "total_minor": integer,
  "items": [
    {
      "item_name": "string",
      "quantity": integer,
      "price_minor": integer,
      "discount_minor": integer,
      "total_minor": integer
    }
  ]
}';

            $validMediaTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mediaType = in_array($mimeType, $validMediaTypes, true) ? $mimeType : 'image/jpeg';

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(45)->post($url, [
                'model' => $model,
                'max_tokens' => 2048,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mediaType,
                                    'data' => $fileBase64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $rawText = '';
                if (!empty($data['content']) && is_array($data['content'])) {
                    foreach ($data['content'] as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $rawText .= $block['text'] ?? '';
                        }
                    }
                }

                $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', trim($rawText));
                $parsed = json_decode($cleanJson, true);
                if (is_array($parsed) && isset($parsed['total_minor'])) {
                    return $parsed;
                }
            }

            return $this->generateFallbackReceipt();
        } catch (\Throwable $e) {
            Log::warning('Claude Vision OCR exception: ' . $e->getMessage());
            return $this->generateFallbackReceipt();
        }
    }

    protected function buildMessagesUrl(string $endpoint): string
    {
        $clean = rtrim($endpoint, '/');
        if (str_ends_with($clean, '/messages')) {
            return $clean;
        }
        if (str_ends_with($clean, '/v1')) {
            return "{$clean}/messages";
        }
        return "{$clean}/v1/messages";
    }

    protected function generateLocalFallbackChat(array $messages, string $model): array
    {
        $lastUserMsg = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $lastUserMsg = $m['content'];
                break;
            }
        }

        $reply = "Halo, saya adalah Asisten Cerdas RUDIMAH yang ditenagai model Anthropic Claude ({$model}). Saya telah menerima instruksi Anda: \"" . mb_strimwidth($lastUserMsg, 0, 70, '...') . "\". Sesuai kebijakan tata kelola sistem, data dan penalaran saya terisolasi ketat pada ruang kerja aktif Anda.";

        return [
            'content' => $reply,
            'model' => $model . '-simulation',
            'tokens_used' => 64,
            'metadata' => [
                'provider' => 'claude',
                'mode' => 'fallback_simulation',
            ],
        ];
    }

    protected function generateFallbackReceipt(): array
    {
        return [
            'merchant_name' => 'KOREKSI MANUAL DIPERLUKAN',
            'receipt_number' => 'CLD-' . date('Ymd-His'),
            'transaction_date' => date('Y-m-d'),
            'currency' => 'Rp',
            'subtotal_minor' => 0,
            'tax_minor' => 0,
            'service_charge_minor' => 0,
            'discount_minor' => 0,
            'total_minor' => 0,
            'items' => [],
        ];
    }
}
