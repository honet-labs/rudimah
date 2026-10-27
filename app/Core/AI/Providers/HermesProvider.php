<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HermesProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected string $defaultModel;
    protected string $apiBaseUrl;

    public function __construct(?string $apiKey = null, string $defaultModel = 'hermes-3-llama-3.1-8b', ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey ?: (string) config('rudimah.ai.hermes_key', env('HERMES_API_KEY', ''));
        $this->defaultModel = $defaultModel;
        $this->apiBaseUrl = rtrim($apiBaseUrl ?: config('rudimah.ai.hermes_endpoint', env('HERMES_API_ENDPOINT', 'http://localhost:11434/v1')), '/');
    }

    public function getName(): string
    {
        return 'hermes';
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->defaultModel;
        $endpoint = rtrim($options['api_endpoint'] ?? $this->apiBaseUrl, '/');

        try {
            $formattedMessages = [];
            foreach ($messages as $msg) {
                $formattedMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'],
                ];
            }

            $req = Http::timeout(40);
            if (!empty($this->apiKey)) {
                $req = $req->withToken($this->apiKey);
            }

            $response = $req->post("{$endpoint}/chat/completions", [
                'model' => $model,
                'messages' => $formattedMessages,
                'temperature' => $options['temperature'] ?? 0.6,
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
                        'provider' => 'hermes',
                        'endpoint' => $endpoint,
                    ],
                ];
            }

        $status = $response->status();
        $errBody = mb_strimwidth($response->body(), 0, 300, '...');
        Log::warning('Hermes API returned non-200', ['status' => $status, 'endpoint' => $endpoint, 'body' => $errBody]);

        throw new \RuntimeException("Permintaan ke Hermes ({$endpoint}) gagal (HTTP {$status}): {$errBody}");
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        throw new \RuntimeException('Hermes Agent difokuskan untuk penalaran teks dan belum mendukung pemindaian multimodal gambar.');
    }
}
