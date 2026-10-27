<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;

    public function __construct(?string $apiKey = null, ?string $defaultModel = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.gemini_key', env('GEMINI_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: config('rudimah.ai.gemini_model', 'gemini-1.5-flash');
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API Google Gemini belum dikonfigurasi di Pusat Konfigurasi AI Platform.');
        }

        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemInstruction = ['parts' => [['text' => $msg['content']]]];
            } else {
                $contents[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? 0.7),
                'maxOutputTokens' => 2048,
            ],
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(45)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('Gemini API exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Gagal menghubungi Google Gemini API: ' . $e->getMessage());
        }

        if ($response->successful()) {
            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;

            if (empty(trim($text))) {
                throw new \RuntimeException("Model Google Gemini ({$model}) mengembalikan respon kosong.");
            }

            return [
                'content' => $text,
                'model' => $model,
                'tokens_used' => (int) $tokens,
                'metadata' => [
                    'provider' => 'gemini',
                    'finish_reason' => $data['candidates'][0]['finishReason'] ?? 'STOP',
                ],
            ];
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        Log::warning('Gemini API call returned non-200', ['status' => $status, 'body' => $errBody]);

        throw new \RuntimeException("Permintaan ke Google Gemini gagal (HTTP {$status}): {$errBody}");
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Kunci API Google Gemini belum dikonfigurasi untuk pemindaian kuitansi OCR.');
        }

        $systemPrompt = "Ekstrak struk belanja/kuitansi ini ke dalam format JSON murni tanpa markdown wrapper. Satuan Rupiah integer tanpa sen.
Format JSON wajib:
{
  \"merchant_name\": \"string\",
  \"receipt_number\": \"string\",
  \"transaction_date\": \"YYYY-MM-DD\",
  \"currency\": \"Rp\",
  \"subtotal_minor\": 0,
  \"tax_minor\": 0,
  \"service_charge_minor\": 0,
  \"discount_minor\": 0,
  \"total_minor\": 0,
  \"items\": [
    {
      \"item_name\": \"string\",
      \"quantity\": 1,
      \"price_minor\": 0,
      \"discount_minor\": 0,
      \"total_minor\": 0
    }
  ]
}";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(60)->post($endpoint, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $fileBase64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Gemini Vision exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Gagal menghubungi model Vision Google Gemini: ' . $e->getMessage());
        }

        if ($response->successful()) {
            $jsonText = $response->json('candidates.0.content.parts.0.text');
            $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', trim($jsonText));
            $parsed = json_decode($cleanJson, true);
            if (is_array($parsed)) {
                return $this->normalizeReceiptData($parsed);
            }
            throw new \RuntimeException('Respon Gemini Vision tidak menghasilkan struktur JSON kuitansi yang valid.');
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        throw new \RuntimeException("Pemindaian OCR Google Gemini gagal (HTTP {$status}): {$errBody}");
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
