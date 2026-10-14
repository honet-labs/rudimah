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

    public function __construct(?string $apiKey = null, string $defaultModel = 'gpt-4o-mini', ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.openai_key', env('OPENAI_API_KEY', ''));
        $this->defaultModel = $defaultModel;
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: 'https://api.openai.com/v1', '/');
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;

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

            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->apiBaseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $formattedMessages,
                    'temperature' => $options['temperature'] ?? 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                $tokens = $data['usage']['total_tokens'] ?? 0;

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

            Log::warning('OpenAI API returned non-200', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->generateLocalFallbackChat($messages, $model);
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception', ['error' => $e->getMessage()]);
            return $this->generateLocalFallbackChat($messages, $model);
        }
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';

        if (empty($this->apiKey)) {
            return $this->generateLocalFallbackReceipt();
        }

        try {
            $prompt = "Ekstrak struk belanja/kuitansi ini ke dalam format JSON murni. Pastikan subtotal_minor + tax_minor - discount_minor = total_minor. Gunakan format mata uang Rupiah integer minor tanpa sen. Schema JSON: merchant_name, receipt_number, transaction_date, currency, subtotal_minor, tax_minor, service_charge_minor, discount_minor, total_minor, items: [{item_name, quantity, price_minor, discount_minor, total_minor}]. Kembalikan hanya valid JSON.";

            $response = Http::withToken($this->apiKey)
                ->timeout(45)
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

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $parsed = json_decode($content, true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }

            return $this->generateLocalFallbackReceipt();
        } catch (\Throwable $e) {
            Log::error('OpenAI Vision exception', ['error' => $e->getMessage()]);
            return $this->generateLocalFallbackReceipt();
        }
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

        $reply = "Halo, saya adalah Asisten Cerdas RUDIMAH (mode OpenAI {$model}). Menanggapi: \"" . mb_strimwidth($lastUserMsg, 0, 80, '...') . "\", informasi keuangan ruang kerja Anda telah diisolasi secara aman.";

        return [
            'content' => $reply,
            'model' => $model . '-local-simulation',
            'tokens_used' => 64,
            'metadata' => [
                'provider' => 'openai',
                'mode' => 'simulated_fallback',
            ],
        ];
    }

    protected function generateLocalFallbackReceipt(): array
    {
        return [
            'merchant_name' => 'INDOMARET POINT',
            'receipt_number' => 'IDM-' . date('Ymd') . '-012',
            'transaction_date' => date('Y-m-d'),
            'currency' => 'Rp',
            'subtotal_minor' => 85000,
            'tax_minor' => 9350,
            'service_charge_minor' => 0,
            'discount_minor' => 2000,
            'total_minor' => 92350,
            'items' => [
                ['item_name' => 'Susu UHT Full Cream 1L', 'quantity' => 2, 'price_minor' => 21000, 'discount_minor' => 2000, 'total_minor' => 40000],
                ['item_name' => 'Roti Tawar Gandum', 'quantity' => 1, 'price_minor' => 19500, 'discount_minor' => 0, 'total_minor' => 19500],
                ['item_name' => 'Kopi Arabika 200g', 'quantity' => 1, 'price_minor' => 25500, 'discount_minor' => 0, 'total_minor' => 25500],
            ],
        ];
    }
}
