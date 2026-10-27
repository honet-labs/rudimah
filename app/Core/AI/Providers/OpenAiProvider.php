<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;
    protected string $apiBaseUrl;

    public function __construct(?string $apiKey = null, ?string $defaultModel = null, ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.openai_key', env('OPENAI_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: config('rudimah.ai.openai_model', 'gpt-4o-mini');
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.openai_endpoint', env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1')), '/');
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API OpenAI belum dikonfigurasi di Pusat Konfigurasi AI Platform.');
        }

        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages,
            'temperature' => (float) ($options['temperature'] ?? 0.7),
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'stream' => false,
        ];

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->apiBaseUrl}/chat/completions", $payload);
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi layanan OpenAI ({$this->apiBaseUrl}): " . $e->getMessage());
        }

        if ($response->successful()) {
            $data = $response->json();

            if (!empty($data['error'])) {
                $errMsg = is_array($data['error'])
                    ? ($data['error']['message'] ?? json_encode($data['error']))
                    : (string) $data['error'];
                throw new \RuntimeException("OpenAI melaporkan kesalahan: {$errMsg}");
            }

            $content = '';

            if (isset($data['choices'][0]['message']['content'])) {
                $rawContent = $data['choices'][0]['message']['content'];
                if (is_array($rawContent)) {
                    $parts = [];
                    foreach ($rawContent as $part) {
                        if (is_string($part)) {
                            $parts[] = $part;
                        } elseif (is_array($part) && isset($part['text'])) {
                            $parts[] = $part['text'];
                        }
                    }
                    $content = implode("\n", $parts);
                } else {
                    $content = (string) $rawContent;
                }
            }

            if (empty(trim($content)) && !empty($data['choices'][0]['message']['reasoning_content'])) {
                $content = (string) $data['choices'][0]['message']['reasoning_content'];
            }

            if (empty(trim($content)) && !empty($data['choices'][0]['message']['reasoning'])) {
                $content = (string) $data['choices'][0]['message']['reasoning'];
            }

            if (empty(trim($content)) && !empty($data['choices'][0]['text'])) {
                $content = (string) $data['choices'][0]['text'];
            }

            if (empty(trim($content)) && !empty($data['choices'][0]['delta']['content'])) {
                $content = (string) $data['choices'][0]['delta']['content'];
            }

            if (empty(trim($content)) && !empty($data['choices'][0]['message']['refusal'])) {
                $content = (string) $data['choices'][0]['message']['refusal'];
            }

            $tokens = $data['usage']['total_tokens'] ?? 0;

            if (empty(trim($content))) {
                $finishReason = $data['choices'][0]['finish_reason'] ?? 'tidak diketahui';
                $preview = mb_strimwidth($response->body(), 0, 300, '...');
                Log::warning('OpenAI empty response body', [
                    'finish_reason' => $finishReason,
                    'body' => $preview,
                ]);
                throw new \RuntimeException("Model OpenAI ({$model}) mengembalikan respon kosong (finish_reason: {$finishReason}). Respons mentah: {$preview}");
            }

            return [
                'content' => $content,
                'model' => $model,
                'tokens_used' => (int) $tokens,
                'metadata' => [
                    'provider' => 'openai',
                    'finish_reason' => $data['choices'][0]['finish_reason'] ?? 'stop',
                ],
            ];
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        Log::warning('OpenAI API returned non-200', ['status' => $status, 'body' => $errBody]);

        throw new \RuntimeException("Permintaan ke OpenAI gagal (HTTP {$status}): {$errBody}");
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API OpenAI belum dikonfigurasi untuk pemindaian kuitansi OCR.');
        }

        $prompt = 'Ekstrak data kuitansi atau struk belanja berikut ke dalam format JSON murni tanpa markdown wrapper:
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
}
Semua nominal berupa integer tanpa sen. Kembalikan hanya valid JSON.';

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->apiBaseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$fileBase64}",
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI Vision exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi model Vision OpenAI: " . $e->getMessage());
        }

        if ($response->successful()) {
            $content = $response->json('choices.0.message.content');
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                return $this->normalizeReceiptData($parsed);
            }
            throw new \RuntimeException('Respon OpenAI Vision tidak menghasilkan JSON kuitansi yang valid.');
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        throw new \RuntimeException("Pemindaian OCR OpenAI gagal (HTTP {$status}): {$errBody}");
    }

    protected function normalizeReceiptData(array $raw): array
    {
        $items = [];
        if (!empty($raw['items']) && is_array($raw['items'])) {
            foreach ($raw['items'] as $it) {
                $qty = (int) ($it['quantity'] ?? 1);
                $price = (int) ($it['price_minor'] ?? ($it['price'] ?? 0));
                $disc = (int) ($it['discount_minor'] ?? ($it['discount'] ?? 0));
                $total = (int) ($it['total_minor'] ?? ($it['total'] ?? (($qty * $price) - $disc)));
                $items[] = [
                    'item_name' => (string) ($it['item_name'] ?? $it['name'] ?? 'Item'),
                    'quantity' => max(1, $qty),
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
}
