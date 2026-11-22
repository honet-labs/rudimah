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
        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;
        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.openai_endpoint', 'https://api.openai.com/v1'), '/');
        }

        $apiKey = !empty($this->apiKey) ? $this->apiKey : (string) config('rudimah.ai.openai_key', '');

        if (empty($apiKey)) {
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
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post("{$endpoint}/chat/completions", $payload);
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            throw new \RuntimeException("Gagal menghubungi layanan OpenAI ({$endpoint}): " . $e->getMessage());
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
        $model = $options['model'] ?? $this->defaultModel;
        if (empty($model)) {
            $model = 'gpt-4o-mini';
        }

        $endpoint = !empty($options['api_endpoint']) ? rtrim($options['api_endpoint'], '/') : $this->apiBaseUrl;
        if (empty($endpoint)) {
            $endpoint = rtrim(config('rudimah.ai.openai_endpoint', 'https://api.openai.com/v1'), '/');
        }

        $apiKey = !empty($this->apiKey) ? $this->apiKey : (string) config('rudimah.ai.openai_key', '');

        if (empty($apiKey)) {
            throw new \RuntimeException('Kunci API OpenAI belum dikonfigurasi di Pusat Konfigurasi AI Platform.');
        }

        $normalizedMime = strtolower(trim($mimeType));
        if ($normalizedMime === 'image/jpg' || $normalizedMime === 'image/pjpeg') {
            $normalizedMime = 'image/jpeg';
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

        $prompt .= \App\Modules\Pekan\Services\ReceiptDataNormalizer::instructions();
        $url = str_ends_with($endpoint, '/chat/completions') ? $endpoint : "{$endpoint}/chat/completions";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(80)
                ->retry(2, 500, static fn ($error) =>
                    $error instanceof \Illuminate\Http\Client\ConnectionException ||
                    ($error instanceof \Illuminate\Http\Client\RequestException && in_array($error->response->status(), [429, 502, 503, 504])), throw: false)
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$normalizedMime};base64,{$fileBase64}",
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'max_tokens' => 4096,
                    'stream' => false,
                ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI Vision exception', ['error' => $e->getMessage()]);
            if (str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout') || str_contains(strtolower($e->getMessage()), '28')) {
                throw new \RuntimeException("Pemindaian struk melebihi batas waktu (80 detik). Model '{$model}' pada OpenAI kemungkinan sedang mengalami antrian tinggi atau tidak mendukung vision.");
            }
            throw new \RuntimeException("Gagal menghubungi model Vision OpenAI: " . $e->getMessage());
        }

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['error'])) {
                $errMsg = is_array($data['error'])
                    ? ($data['error']['message'] ?? json_encode($data['error']))
                    : (string) $data['error'];
                throw new \RuntimeException("OpenAI melaporkan kesalahan: {$errMsg}");
            }

            $content = $data['choices'][0]['message']['content'] ?? '';
            $content = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/', '$1', trim((string) $content));
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                return \App\Modules\Pekan\Services\ReceiptDataNormalizer::normalize($parsed);
            }
            throw new \RuntimeException('Respon OpenAI Vision tidak menghasilkan JSON kuitansi yang valid.');
        }

        $status = $response->status();
        $errData = $response->json();
        $errMsg = $errData['error']['message'] ?? $errData['message'] ?? mb_strimwidth($response->body(), 0, 300, '...');

        if (
            str_contains(strtolower($errMsg), 'image') ||
            str_contains(strtolower($errMsg), 'vision') ||
            str_contains(strtolower($errMsg), 'not support') ||
            str_contains(strtolower($errMsg), 'unsupported')
        ) {
            throw new \RuntimeException("Model AI '{$model}' tidak mendukung fitur vision / pemindaian gambar (pesan OpenAI: {$errMsg}). Silakan pilih model yang mendukung vision di Administrasi Sistem.");
        }

        throw new \RuntimeException("Pemindaian OCR OpenAI gagal (HTTP {$status}): {$errMsg}");
    }

}
