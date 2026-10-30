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

    public function __construct(?string $apiKey = null, ?string $defaultModel = null, ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.claude_key', env('CLAUDE_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: config('rudimah.ai.claude_model', 'claude-3-5-sonnet-20241022');
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.claude_endpoint', env('CLAUDE_API_ENDPOINT', 'https://api.anthropic.com/v1')), '/');
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = !empty($options['model']) ? $options['model'] : $this->defaultModel;
        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;

        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.claude_endpoint', 'https://api.anthropic.com/v1'), '/');
        }

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API Anthropic Claude belum dikonfigurasi di Pusat Konfigurasi AI Platform.');
        }

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

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(45)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Claude API exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi Anthropic Claude API ({$url}): " . $e->getMessage());
        }

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

            if (empty(trim($content))) {
                throw new \RuntimeException("Model Anthropic Claude ({$model}) mengembalikan respon kosong.");
            }

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

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        Log::warning('Anthropic Claude API error', [
            'status' => $status,
            'body' => $errBody,
        ]);

        throw new \RuntimeException("Permintaan ke Anthropic Claude gagal (HTTP {$status}): {$errBody}");
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = !empty($options['model']) ? $options['model'] : $this->defaultModel;
        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;

        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.claude_endpoint', 'https://api.anthropic.com/v1'), '/');
        }

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API Anthropic Claude belum dikonfigurasi untuk pemindaian kuitansi OCR.');
        }

        $url = $this->buildMessagesUrl($endpoint);
        $prompt = 'Ekstrak data kuitansi atau struk belanja berikut ke dalam format JSON murni tanpa markdown wrapper. Satuan Rupiah integer tanpa sen.
Format JSON wajib:
{
  "merchant_name": "string",
  "receipt_number": "string",
  "transaction_date": "YYYY-MM-DD",
  "currency": "Rp",
  "subtotal_minor": 0,
  "tax_minor": 0,
  "service_charge_minor": 0,
  "discount_minor": 0,
  "total_minor": 0,
  "items": [
    {
      "item_name": "string",
      "quantity": 1,
      "price_minor": 0,
      "discount_minor": 0,
      "total_minor": 0
    }
  ]
}';

        $payload = [
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
                                'media_type' => $mimeType,
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
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Claude Vision exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi model Vision Anthropic Claude: " . $e->getMessage());
        }

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
            if (preg_match('/\{[\s\S]*\}/', $cleanJson, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $this->normalizeReceiptData($parsed);
                }
            }
            throw new \RuntimeException('Respon Anthropic Claude tidak menghasilkan JSON kuitansi yang valid.');
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        throw new \RuntimeException("Pemindaian OCR Anthropic Claude gagal (HTTP {$status}): {$errBody}");
    }

    protected function normalizeReceiptData(array $raw): array
    {
        $items = [];
        if (!empty($raw['items']) && is_array($raw['items'])) {
            foreach ($raw['items'] as $it) {
                $rawQty = $it['quantity'] ?? 1;
                $qty = is_numeric($rawQty) ? (float) $rawQty : 1;
                if ($qty <= 0) {
                    $qty = 1;
                }
                $price = (int) ($it['price_minor'] ?? ($it['price'] ?? 0));
                $disc = (int) ($it['discount_minor'] ?? ($it['discount'] ?? 0));
                $total = (int) ($it['total_minor'] ?? ($it['total'] ?? round(($qty * $price) - $disc)));
                $items[] = [
                    'item_name' => (string) ($it['item_name'] ?? $it['name'] ?? 'Item'),
                    'quantity' => $qty,
                    'price_minor' => $price,
                    'discount_minor' => $disc,
                    'total_minor' => $total,
                ];
            }
        }

        $subtotal = (int) ($raw['subtotal_minor'] ?? ($raw['subtotal'] ?? 0));
        if ($subtotal === 0 && count($items) > 0) {
            $subtotal = array_sum(array_column($items, 'total_minor'));
        }
        $tax = (int) ($raw['tax_minor'] ?? ($raw['tax'] ?? 0));
        $service = (int) ($raw['service_charge_minor'] ?? ($raw['service_charge'] ?? 0));
        $discount = (int) ($raw['discount_minor'] ?? ($raw['discount'] ?? 0));
        $total = (int) ($raw['total_minor'] ?? ($raw['total'] ?? ($subtotal + $tax + $service - $discount)));

        return [
            'merchant_name' => (string) ($raw['merchant_name'] ?? $raw['store_name'] ?? 'Toko Belanja'),
            'receipt_number' => (string) ($raw['receipt_number'] ?? $raw['invoice_number'] ?? 'STR-' . date('Ymd-His')),
            'transaction_date' => (string) ($raw['transaction_date'] ?? $raw['date'] ?? date('Y-m-d')),
            'currency' => 'Rp',
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'service_charge_minor' => $service,
            'discount_minor' => $discount,
            'total_minor' => $total,
            'items' => $items,
        ];
    }

    protected function buildMessagesUrl(string $endpoint): string
    {
        $clean = rtrim($endpoint, '/');
        if (empty($clean)) {
            $clean = rtrim(config('rudimah.ai.claude_endpoint', 'https://api.anthropic.com/v1'), '/');
        }
        if (str_ends_with($clean, '/messages')) {
            return $clean;
        }
        if (str_ends_with($clean, '/v1')) {
            return "{$clean}/messages";
        }
        return "{$clean}/v1/messages";
    }
}
