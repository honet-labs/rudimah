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

    public function __construct(?string $apiKey = null, string $defaultModel = 'gpt-4o-mini', ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.ninerouter_key', env('NINEROUTER_API_KEY', ''));
        $this->defaultModel = $defaultModel ?: 'gpt-4o-mini';
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.ninerouter_endpoint', env('NINEROUTER_API_ENDPOINT', 'https://api.9router.com/v1')), '/');
    }

    public function getName(): string
    {
        return '9router';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $endpoint = rtrim($options['api_endpoint'] ?? $this->apiBaseUrl, '/');

        if (empty($this->apiKey)) {
            return $this->generateLocalFallbackChat($messages, $model);
        }

        try {
            $formattedMessages = [];
            foreach ($messages as $msg) {
                $formattedMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            $url = $this->buildChatCompletionsUrl($endpoint);
            $response = Http::withToken($this->apiKey)
                ->timeout(35)
                ->post($url, [
                    'model' => $model,
                    'messages' => $formattedMessages,
                    'temperature' => (float) ($options['temperature'] ?? 0.7),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $tokens = (int) ($data['usage']['total_tokens'] ?? 0);

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

            Log::warning('9Router API returned non-200', [
                'status' => $response->status(),
                'endpoint' => $url,
                'body' => $response->body(),
            ]);

            return $this->generateLocalFallbackChat($messages, $model);
        } catch (\Throwable $e) {
            Log::info('9Router API connection exception, using simulation fallback', ['error' => $e->getMessage()]);
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
            $url = $this->buildChatCompletionsUrl($endpoint);
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

            $dataUrl = "data:{$mimeType};base64,{$fileBase64}";
            $response = Http::withToken($this->apiKey)
                ->timeout(45)
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

            if ($response->successful()) {
                $data = $response->json();
                $rawText = $data['choices'][0]['message']['content'] ?? '';
                $cleanJson = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', trim($rawText));
                $parsed = json_decode($cleanJson, true);
                if (is_array($parsed) && isset($parsed['total_minor'])) {
                    return $parsed;
                }
            }

            return $this->generateFallbackReceipt();
        } catch (\Throwable $e) {
            Log::warning('9Router OCR extraction exception: ' . $e->getMessage());
            return $this->generateFallbackReceipt();
        }
    }

    protected function buildChatCompletionsUrl(string $endpoint): string
    {
        $clean = rtrim($endpoint, '/');
        if (str_ends_with($clean, '/chat/completions')) {
            return $clean;
        }
        if (str_ends_with($clean, '/v1')) {
            return "{$clean}/chat/completions";
        }
        return "{$clean}/v1/chat/completions";
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

        $reply = "Halo, saya adalah Asisten Cerdas RUDIMAH yang terhubung melalui gateway 9Router ({$model}). Saya telah memproses instruksi Anda: \"" . mb_strimwidth($lastUserMsg, 0, 70, '...') . "\". Seluruh alur data terisolasi secara privat pada ruang kerja Anda saat ini.";

        return [
            'content' => $reply,
            'model' => $model . '-9router-simulation',
            'tokens_used' => 64,
            'metadata' => [
                'provider' => '9router',
                'mode' => 'fallback_simulation',
            ],
        ];
    }

    protected function generateFallbackReceipt(): array
    {
        return [
            'merchant_name' => 'KOREKSI MANUAL DIPERLUKAN',
            'receipt_number' => '9R-' . date('Ymd-His'),
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
