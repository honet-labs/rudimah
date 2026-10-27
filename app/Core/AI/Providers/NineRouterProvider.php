<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NineRouterProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;
    protected string $apiBaseUrl;

    public function __construct(?string $apiKey = null, ?string $defaultModel = null, ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.ninerouter_key', env('NINEROUTER_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: config('rudimah.ai.ninerouter_model', 'high-models-agent');
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.ninerouter_endpoint', env('NINEROUTER_API_ENDPOINT', 'https://api.9router.com/v1')), '/');
    }

    public function getName(): string
    {
        return '9router';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = !empty($options['model']) ? $options['model'] : $this->defaultModel;
        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;

        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'), '/');
        }

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Token akses / API Key 9Router belum dikonfigurasi di Pusat Konfigurasi AI Platform.');
        }

        $formattedMessages = [];
        foreach ($messages as $msg) {
            $formattedMessages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $url = $this->buildChatCompletionsUrl($endpoint);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(45)
                ->post($url, [
                    'model' => $model,
                    'messages' => $formattedMessages,
                    'temperature' => (float) ($options['temperature'] ?? 0.7),
                ]);
        } catch (\Throwable $e) {
            Log::error('9Router API connection exception', ['endpoint' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi endpoint 9Router ({$url}): " . $e->getMessage());
        }

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $tokens = (int) ($data['usage']['total_tokens'] ?? 0);

            if (empty(trim($content))) {
                throw new \RuntimeException("Model 9Router ({$model}) mengembalikan respon kosong.");
            }

            return [
                'content' => $content,
                'model' => $model,
                'tokens_used' => $tokens,
                'metadata' => [
                    'provider' => '9router',
                    'endpoint' => $endpoint,
                ],
            ];
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        Log::warning('9Router API returned non-200', [
            'status' => $status,
            'endpoint' => $url,
            'body' => $errBody,
        ]);

        throw new \RuntimeException("Permintaan ke 9Router ({$url}) gagal (HTTP {$status}): {$errBody}");
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = !empty($options['model']) ? $options['model'] : $this->defaultModel;
        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;

        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'), '/');
        }

        if (empty($this->apiKey)) {
            throw new \RuntimeException('Token akses 9Router belum dikonfigurasi untuk pemindaian kuitansi OCR.');
        }

        $url = $this->buildChatCompletionsUrl($endpoint);
        $prompt = 'Ekstrak data kuitansi atau struk belanja berikut ke dalam format JSON murni tanpa markdown wrapper. Format wajib:
{
  "merchant_name": "Nama Toko / Tempat Belanja",
  "receipt_number": "Nomor Struk / Kuitansi",
  "transaction_date": "YYYY-MM-DD",
  "currency": "Rp",
  "subtotal_minor": 0,
  "tax_minor": 0,
  "service_charge_minor": 0,
  "discount_minor": 0,
  "total_minor": 0,
  "items": [
    {
      "item_name": "Nama Barang / Jasa",
      "quantity": 1,
      "price_minor": 0,
      "discount_minor": 0,
      "total_minor": 0
    }
  ]
}
Catatan: Semua nilai nominal berupa integer dalam satuan Rupiah (tanpa sen). Pastikan total_minor = subtotal_minor + tax_minor + service_charge_minor - discount_minor.';

        $dataUrl = "data:{$mimeType};base64,{$fileBase64}";

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('9Router OCR API connection exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi layanan OCR 9Router ({$url}): " . $e->getMessage());
        }

        if ($response->successful()) {
            $data = $response->json();
            $rawText = $data['choices'][0]['message']['content'] ?? '';
            $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', trim($rawText));
            if (preg_match('/\{[\s\S]*\}/', $cleanJson, $matches)) {
                $parsed = json_decode($matches[0], true);
                if (is_array($parsed)) {
                    return $this->normalizeReceiptData($parsed);
                }
            }
            throw new \RuntimeException('Respon model OCR 9Router tidak berisi format JSON kuitansi yang valid.');
        }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        throw new \RuntimeException("Pemindaian OCR 9Router gagal (HTTP {$status}): {$errBody}");
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

    protected function buildChatCompletionsUrl(string $endpoint): string
    {
        $clean = rtrim($endpoint, '/');
        if (empty($clean)) {
            $clean = rtrim(config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'), '/');
        }
        if (str_ends_with($clean, '/chat/completions')) {
            return $clean;
        }
        if (str_ends_with($clean, '/v1')) {
            return "{$clean}/chat/completions";
        }
        return "{$clean}/v1/chat/completions";
    }
}
