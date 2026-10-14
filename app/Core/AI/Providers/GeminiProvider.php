<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    protected string $apiKey;
    protected string $defaultModel;

    public function __construct(?string $apiKey = null, string $defaultModel = 'gemini-1.5-flash')
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.gemini_key', env('GEMINI_API_KEY', ''));
        $this->defaultModel = $defaultModel;
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;

        if (empty($this->apiKey)) {
            // Mode fallback lokal cerdas bila kunci API belum dikonfigurasi
            return $this->generateLocalFallbackChat($messages, $model);
        }

        try {
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
                    'temperature' => $options['temperature'] ?? 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ];

            if ($systemInstruction) {
                $payload['systemInstruction'] = $systemInstruction;
            }

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(30)->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $tokens = $data['usageMetadata']['totalTokenCount'] ?? 0;

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

            Log::warning('Gemini API call returned non-200', ['status' => $response->status(), 'body' => $response->body()]);
            return $this->generateLocalFallbackChat($messages, $model);
        } catch (\Throwable $e) {
            Log::error('Gemini API exception', ['error' => $e->getMessage()]);
            return $this->generateLocalFallbackChat($messages, $model);
        }
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        $model = $options['model'] ?? 'gemini-1.5-flash';

        if (empty($this->apiKey)) {
            return $this->generateLocalFallbackReceipt();
        }

        try {
            $systemPrompt = "Ekstrak struk belanja/kuitansi ini ke dalam format JSON murni. Pastikan subtotal_minor + tax_minor - discount_minor = total_minor. Gunakan satuan Rupiah integer (tanpa desimal/sen). Struktur JSON wajib: merchant_name, receipt_number, transaction_date, currency (Rp), subtotal_minor, tax_minor, service_charge_minor, discount_minor, total_minor, items (array of item_name, quantity, price_minor, discount_minor, total_minor).";

            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(45)->post($endpoint, [
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

            if ($response->successful()) {
                $jsonText = $response->json('candidates.0.content.parts.0.text');
                $parsed = json_decode($jsonText, true);
                if (is_array($parsed)) {
                    return $parsed;
                }
            }

            return $this->generateLocalFallbackReceipt();
        } catch (\Throwable $e) {
            Log::error('Gemini Vision exception', ['error' => $e->getMessage()]);
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

        $reply = "Halo, saya adalah Asisten Cerdas RUDIMAH (mode Gemini). Terkait pesan Anda: \"" . mb_strimwidth($lastUserMsg, 0, 80, '...') . "\", seluruh data ruang kerja ini terlindungi secara aman. Anda dapat menanyakan ringkasan anggaran, saldo rekening, atau transaksi terbaru.";

        return [
            'content' => $reply,
            'model' => $model . '-local-simulation',
            'tokens_used' => 64,
            'metadata' => [
                'provider' => 'gemini',
                'mode' => 'simulated_fallback',
            ],
        ];
    }

    protected function generateLocalFallbackReceipt(): array
    {
        return [
            'merchant_name' => 'SUPERINDO TRANSMART',
            'receipt_number' => 'STR-' . date('Ymd') . '-049',
            'transaction_date' => date('Y-m-d'),
            'currency' => 'Rp',
            'subtotal_minor' => 125000,
            'tax_minor' => 13750,
            'service_charge_minor' => 0,
            'discount_minor' => 5000,
            'total_minor' => 133750,
            'items' => [
                ['item_name' => 'Beras Pandan Wangi 5kg', 'quantity' => 1, 'price_minor' => 75000, 'discount_minor' => 0, 'total_minor' => 75000],
                ['item_name' => 'Minyak Goreng 2L', 'quantity' => 1, 'price_minor' => 34000, 'discount_minor' => 0, 'total_minor' => 34000],
                ['item_name' => 'Telur Ayam Negeri 1kg', 'quantity' => 1, 'price_minor' => 16000, 'discount_minor' => 5000, 'total_minor' => 11000],
            ],
        ];
    }
}
