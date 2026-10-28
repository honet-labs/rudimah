<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\Identity\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlatformAiService
{
    /**
     * Dapatkan konfigurasi seluruh penyedia AI platform (Gemini, OpenAI, Claude, 9Router).
     * Nilai kunci/token API disamarkan demi keamanan tampilan peramban.
     */
    public static function getSettings(): array
    {
        try {
            $dbSettings = SystemSetting::where('group', 'ai')->pluck('value', 'key');
        } catch (\Throwable) {
            $dbSettings = collect();
        }

        $activeProvider = $dbSettings->get('ai_default_provider', config('rudimah.ai.default_provider', env('AI_DEFAULT_PROVIDER', 'gemini')));
        $isEnabled = filter_var($dbSettings->get('ai_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

        // 1. Google Gemini
        $geminiRawKey = $dbSettings->get('ai_gemini_key', env('GEMINI_API_KEY', ''));
        $geminiEndpoint = $dbSettings->get('ai_gemini_endpoint', 'https://generativelanguage.googleapis.com');
        $geminiModel = $dbSettings->get('ai_gemini_model', 'gemini-1.5-flash');

        // 2. OpenAI
        $openaiRawKey = $dbSettings->get('ai_openai_key', env('OPENAI_API_KEY', ''));
        $openaiEndpoint = $dbSettings->get('ai_openai_endpoint', 'https://api.openai.com/v1');
        $openaiModel = $dbSettings->get('ai_openai_model', 'gpt-4o-mini');

        // 3. Anthropic Claude
        $claudeRawKey = $dbSettings->get('ai_claude_key', env('CLAUDE_API_KEY', ''));
        $claudeEndpoint = $dbSettings->get('ai_claude_endpoint', 'https://api.anthropic.com/v1');
        $claudeModel = $dbSettings->get('ai_claude_model', 'claude-3-5-sonnet-20241022');

        // 4. 9Router AI Gateway
        $routerRawKey = $dbSettings->get('ai_9router_key', env('NINEROUTER_API_KEY', ''));
        $routerEndpoint = $dbSettings->get('ai_9router_endpoint', 'https://api.9router.com/v1');
        $routerModel = $dbSettings->get('ai_9router_model', 'gpt-4o-mini');

        return [
            'active_provider' => $activeProvider ?: 'gemini',
            'is_enabled' => $isEnabled,
            'providers' => [
                'gemini' => [
                    'name' => 'Google Gemini',
                    'api_key' => '',
                    'has_api_key' => !empty($geminiRawKey),
                    'api_endpoint' => $geminiEndpoint ?: 'https://generativelanguage.googleapis.com',
                    'model' => $geminiModel ?: 'gemini-1.5-flash',
                    'is_configured' => !empty($geminiRawKey),
                ],
                'openai' => [
                    'name' => 'OpenAI',
                    'api_key' => '',
                    'has_api_key' => !empty($openaiRawKey),
                    'api_endpoint' => $openaiEndpoint ?: 'https://api.openai.com/v1',
                    'model' => $openaiModel ?: 'gpt-4o-mini',
                    'is_configured' => !empty($openaiRawKey),
                ],
                'claude' => [
                    'name' => 'Anthropic Claude',
                    'api_key' => '',
                    'has_api_key' => !empty($claudeRawKey),
                    'api_endpoint' => $claudeEndpoint ?: 'https://api.anthropic.com/v1',
                    'model' => $claudeModel ?: 'claude-3-5-sonnet-20241022',
                    'is_configured' => !empty($claudeRawKey),
                ],
                '9router' => [
                    'name' => '9Router AI Gateway',
                    'api_key' => '',
                    'has_api_key' => !empty($routerRawKey),
                    'api_endpoint' => $routerEndpoint ?: 'https://api.9router.com/v1',
                    'model' => $routerModel ?: 'gpt-4o-mini',
                    'is_configured' => !empty($routerRawKey),
                ],
            ],
        ];
    }

    /**
     * Simpan konfigurasi pengaturan AI platform dari Administrator Control Panel.
     */
    public static function saveSettings(array $data): void
    {
        if (isset($data['active_provider'])) {
            SystemSetting::set('ai_default_provider', trim($data['active_provider']), 'ai');
        }

        if (isset($data['is_enabled'])) {
            SystemSetting::set('ai_enabled', !empty($data['is_enabled']) ? '1' : '0', 'ai');
        }

        $providers = ['gemini', 'openai', 'claude', '9router'];
        foreach ($providers as $p) {
            if (isset($data[$p]) && is_array($data[$p])) {
                $cfg = $data[$p];

                if (isset($cfg['api_endpoint'])) {
                    SystemSetting::set("ai_{$p}_endpoint", rtrim(trim($cfg['api_endpoint']), '/'), 'ai');
                }

                if (isset($cfg['model']) && !empty($cfg['model'])) {
                    SystemSetting::set("ai_{$p}_model", trim($cfg['model']), 'ai');
                }

                if (!empty($cfg['api_key']) && $cfg['api_key'] !== '******') {
                    SystemSetting::set("ai_{$p}_key", trim($cfg['api_key']), 'ai', true);
                }
            }
        }

        static::applyRuntimeConfig();
    }

    /**
     * Terapkan konfigurasi basis data ke dalam konfigurasi runtime Laravel.
     */
    public static function applyRuntimeConfig(): void
    {
        try {
            $settings = SystemSetting::where('group', 'ai')->pluck('value', 'key');

            $activeProvider = $settings->get('ai_default_provider', config('rudimah.ai.default_provider', env('AI_DEFAULT_PROVIDER', 'gemini')));
            
            $geminiKey = $settings->get('ai_gemini_key', env('GEMINI_API_KEY', ''));
            $geminiEndpoint = $settings->get('ai_gemini_endpoint', 'https://generativelanguage.googleapis.com');
            $geminiModel = $settings->get('ai_gemini_model', 'gemini-1.5-flash');

            $openaiKey = $settings->get('ai_openai_key', env('OPENAI_API_KEY', ''));
            $openaiEndpoint = $settings->get('ai_openai_endpoint', 'https://api.openai.com/v1');
            $openaiModel = $settings->get('ai_openai_model', 'gpt-4o-mini');

            $claudeKey = $settings->get('ai_claude_key', env('CLAUDE_API_KEY', ''));
            $claudeEndpoint = $settings->get('ai_claude_endpoint', 'https://api.anthropic.com/v1');
            $claudeModel = $settings->get('ai_claude_model', 'claude-3-5-sonnet-20241022');

            $routerKey = $settings->get('ai_9router_key', env('NINEROUTER_API_KEY', ''));
            $routerEndpoint = $settings->get('ai_9router_endpoint', 'https://api.9router.com/v1');
            $routerModel = $settings->get('ai_9router_model', 'high-models-agent');

            $activeOcrModel = match ($activeProvider) {
                '9router' => $routerModel ?: 'high-models-agent',
                'openai' => $openaiModel ?: 'gpt-4o-mini',
                'claude' => $claudeModel ?: 'claude-3-5-sonnet-20241022',
                default => $geminiModel ?: 'gemini-1.5-flash',
            };

            config([
                'rudimah.ai.default_provider' => $activeProvider,
                'rudimah.ai.gemini_key' => $geminiKey,
                'rudimah.ai.gemini_endpoint' => $geminiEndpoint,
                'rudimah.ai.gemini_model' => $geminiModel,
                'rudimah.ai.openai_key' => $openaiKey,
                'rudimah.ai.openai_endpoint' => $openaiEndpoint,
                'rudimah.ai.openai_model' => $openaiModel,
                'rudimah.ai.claude_key' => $claudeKey,
                'rudimah.ai.claude_endpoint' => $claudeEndpoint,
                'rudimah.ai.claude_model' => $claudeModel,
                'rudimah.ai.ninerouter_key' => $routerKey,
                'rudimah.ai.ninerouter_endpoint' => $routerEndpoint,
                'rudimah.ai.ninerouter_model' => $routerModel,
                'rudimah.ocr.provider' => $activeProvider,
                'rudimah.ocr.model' => $activeOcrModel,
            ]);
        } catch (\Throwable $e) {
            Log::debug('[PlatformAiService] Belum dapat menerapkan konfigurasi runtime: ' . $e->getMessage());
        }
    }

    /**
     * Uji coba koneksi ke penyedia AI yang dikonfigurasi.
     */
    public static function testConnection(string $provider, ?string $endpoint = null, ?string $key = null, ?string $model = null): array
    {
        static::applyRuntimeConfig();

        $manager = new AiProviderManager();

        try {
            // Ambil kredensial jika tidak diberikan di form
            $dbKey = $key;
            if (empty($dbKey) || $dbKey === '******') {
                $dbKey = SystemSetting::get("ai_{$provider}_key", config("rudimah.ai.{$provider}_key", ''));
                if (empty($dbKey) && ($provider === '9router' || $provider === 'ninerouter')) {
                    $dbKey = SystemSetting::get('ai_9router_key', config('rudimah.ai.ninerouter_key', ''));
                }
            }

            if (empty($dbKey)) {
                return [
                    'success' => false,
                    'message' => "Kunci/Token API untuk penyedia {$provider} belum diisi atau tersimpan di sistem.",
                ];
            }

            $activeEndpoint = $endpoint ?: SystemSetting::get("ai_{$provider}_endpoint");
            if (($provider === '9router' || $provider === 'ninerouter') && empty($activeEndpoint)) {
                $activeEndpoint = SystemSetting::get('ai_9router_endpoint', config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'));
            }

            $activeModel = $model ?: SystemSetting::get("ai_{$provider}_model");
            if (empty($activeModel)) {
                $activeModel = match (strtolower($provider)) {
                    '9router', 'ninerouter' => config('rudimah.ai.ninerouter_model', 'high-models-agent'),
                    'openai' => config('rudimah.ai.openai_model', 'gpt-4o-mini'),
                    'claude' => config('rudimah.ai.claude_model', 'claude-3-5-sonnet-20241022'),
                    default => config('rudimah.ai.gemini_model', 'gemini-1.5-flash'),
                };
            }

            // Resolusi provider langsung dengan kredensial aktif
            $instance = match (strtolower($provider)) {
                'gemini' => new \App\Core\AI\Providers\GeminiProvider($dbKey, $activeModel ?: 'gemini-1.5-flash'),
                'openai' => new \App\Core\AI\Providers\OpenAiProvider($dbKey, $activeModel ?: 'gpt-4o-mini', $activeEndpoint),
                'claude' => new \App\Core\AI\Providers\ClaudeProvider($dbKey, $activeModel ?: 'claude-3-5-sonnet-20241022', $activeEndpoint),
                '9router', 'ninerouter' => new \App\Core\AI\Providers\NineRouterProvider($dbKey, $activeModel ?: 'high-models-agent', $activeEndpoint),
                default => $manager->resolve($provider),
            };

            $messages = [
                ['role' => 'user', 'content' => 'Halo! Ini adalah tes verifikasi koneksi dari platform RUDIMAH. Jawab dalam 1 kalimat singkat.'],
            ];

            $startTime = microtime(true);
            $response = $instance->chat($messages, [
                'model' => $activeModel,
                'api_endpoint' => $activeEndpoint,
                'max_tokens' => 2048,
            ]);
            $latencyMs = round((microtime(true) - $startTime) * 1000);

            if (!empty($response['content'])) {
                $sampleReply = mb_strimwidth($response['content'], 0, 150, '...');
                return [
                    'success' => true,
                    'latency_ms' => $latencyMs,
                    'provider' => $instance->getName(),
                    'model' => $response['model'] ?? $activeModel,
                    'message' => "Koneksi ke {$instance->getName()} berhasil ({$latencyMs}ms)! Respon model: \"{$sampleReply}\"",
                ];
            }

            return [
                'success' => false,
                'message' => "Koneksi ke {$provider} tidak menghasilkan konten teks valid.",
            ];
        } catch (\Throwable $e) {
            Log::error("[PlatformAiService] Gagal uji koneksi {$provider}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Gagal terhubung ke {$provider}: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Mendeteksi dan mengambil daftar model AI yang tersedia dari penyedia (OpenAI, 9Router, Gemini, Claude).
     *
     * @return array{success: bool, provider: string, models: array<array{id: string, name: string, description?: string}>, message: string}
     */
    public static function fetchAvailableModels(string $provider, ?string $endpoint = null, ?string $key = null): array
    {
        static::applyRuntimeConfig();

        $provider = strtolower(trim($provider));
        if ($provider === 'ninerouter') {
            $provider = '9router';
        }

        // Ambil kredensial jika tidak diberikan di form
        $dbKey = $key;
        if (empty($dbKey) || $dbKey === '******' || str_contains($dbKey, '••••')) {
            $dbKey = SystemSetting::get("ai_{$provider}_key", config("rudimah.ai.{$provider}_key", ''));
            if (empty($dbKey) && $provider === '9router') {
                $dbKey = SystemSetting::get('ai_9router_key', config('rudimah.ai.ninerouter_key', ''));
            }
        }

        if (empty($dbKey)) {
            return [
                'success' => false,
                'provider' => $provider,
                'models' => [],
                'message' => "Kunci/Token API untuk penyedia {$provider} belum diisi atau tersimpan di sistem.",
            ];
        }

        $activeEndpoint = $endpoint ?: SystemSetting::get("ai_{$provider}_endpoint");
        if ($provider === '9router' && empty($activeEndpoint)) {
            $activeEndpoint = SystemSetting::get('ai_9router_endpoint', config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'));
        }

        try {
            return match ($provider) {
                'gemini' => static::fetchGeminiModels($dbKey, $activeEndpoint),
                'openai' => static::fetchOpenAiModels($dbKey, $activeEndpoint),
                '9router' => static::fetchNineRouterModels($dbKey, $activeEndpoint),
                'claude' => static::fetchClaudeModels($dbKey, $activeEndpoint),
                default => [
                    'success' => false,
                    'provider' => $provider,
                    'models' => [],
                    'message' => "Penyedia {$provider} tidak mendukung deteksi model otomatis.",
                ],
            };
        } catch (\Throwable $e) {
            Log::error("[PlatformAiService] Gagal deteksi model {$provider}: " . $e->getMessage());
            return [
                'success' => false,
                'provider' => $provider,
                'models' => [],
                'message' => "Gagal mendeteksi model dari {$provider}: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Mengambil model yang didukung dari Google Gemini API.
     */
    protected static function fetchGeminiModels(string $apiKey, ?string $endpoint): array
    {
        $baseEndpoint = !empty($endpoint) ? rtrim($endpoint, '/') : 'https://generativelanguage.googleapis.com';
        if (str_contains($baseEndpoint, '/v1beta/models') || str_contains($baseEndpoint, '/v1/models')) {
            $url = "{$baseEndpoint}?key={$apiKey}";
        } elseif (str_contains($baseEndpoint, '/v1beta') || str_contains($baseEndpoint, '/v1')) {
            $url = "{$baseEndpoint}/models?key={$apiKey}";
        } else {
            $url = "{$baseEndpoint}/v1beta/models?key={$apiKey}";
        }

        $response = Http::timeout(15)->get($url);

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("Google Gemini API ({$response->status()}): {$msg}");
        }

        $data = $response->json('models') ?? [];
        $models = [];

        foreach ($data as $item) {
            $methods = $item['supportedGenerationMethods'] ?? [];
            if (!in_array('generateContent', $methods, true)) {
                continue;
            }

            $rawName = $item['name'] ?? '';
            $id = str_replace('models/', '', $rawName);
            $displayName = $item['displayName'] ?? $id;

            $models[] = [
                'id' => $id,
                'name' => "{$displayName} ({$id})",
                'description' => $item['description'] ?? 'Model Google Gemini',
            ];
        }

        usort($models, function ($a, $b) {
            $priority = ['gemini-1.5-flash' => 1, 'gemini-1.5-pro' => 2, 'gemini-2.0-flash' => 3, 'gemini-1.5-flash-8b' => 4];
            $pA = $priority[$a['id']] ?? 99;
            $pB = $priority[$b['id']] ?? 99;
            return $pA <=> $pB;
        });

        return [
            'success' => true,
            'provider' => 'gemini',
            'models' => $models,
            'message' => 'Berhasil mendeteksi ' . count($models) . ' model Gemini yang tersedia.',
        ];
    }

    /**
     * Mengambil model chat yang didukung dari OpenAI API.
     */
    protected static function fetchOpenAiModels(string $apiKey, ?string $endpoint): array
    {
        $url = static::buildModelsUrl($endpoint ?: 'https://api.openai.com/v1');

        $response = Http::withToken($apiKey)->timeout(15)->get($url);

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("OpenAI API ({$response->status()}): {$msg}");
        }

        $data = $response->json('data') ?? [];
        $chatModels = [];
        $otherModels = [];

        foreach ($data as $item) {
            $id = $item['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            // Abaikan model non-chat seperti audio, embedding, whisper, dall-e, moderation, tts
            if (preg_match('/^(text-embedding|audio|whisper|dall-e|tts|text-moderation|davinci|babbage)/i', $id)) {
                continue;
            }

            $entry = [
                'id' => $id,
                'name' => $id,
                'description' => 'Model OpenAI',
            ];

            if (preg_match('/^(gpt-4o|o1|o3|chatgpt-4o|gpt-4|gpt-3\.5)/i', $id)) {
                $chatModels[] = $entry;
            } else {
                $otherModels[] = $entry;
            }
        }

        usort($chatModels, function ($a, $b) {
            $rank = ['gpt-4o-mini' => 1, 'gpt-4o' => 2, 'chatgpt-4o-latest' => 3, 'o1-mini' => 4, 'o1-preview' => 5, 'gpt-4-turbo' => 6];
            $rA = $rank[$a['id']] ?? 50;
            $rB = $rank[$b['id']] ?? 50;
            return $rA <=> $rB;
        });

        $finalModels = array_merge($chatModels, $otherModels);

        return [
            'success' => true,
            'provider' => 'openai',
            'models' => $finalModels,
            'message' => 'Berhasil mendeteksi ' . count($finalModels) . ' model OpenAI yang tersedia.',
        ];
    }

    /**
     * Mengambil model yang didukung dari 9Router AI Gateway.
     */
    protected static function fetchNineRouterModels(string $apiKey, ?string $endpoint): array
    {
        $url = static::buildModelsUrl($endpoint ?: 'https://api.9router.com/v1');

        $response = Http::withToken($apiKey)->timeout(15)->get($url);

        if (!$response->successful()) {
            $msg = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException("9Router Gateway ({$response->status()}): {$msg}");
        }

        $data = $response->json('data') ?? [];
        $models = [];

        foreach ($data as $item) {
            $id = $item['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            $models[] = [
                'id' => $id,
                'name' => $id,
                'description' => 'Model 9Router AI Gateway',
            ];
        }

        usort($models, function ($a, $b) {
            if ($a['id'] === 'high-models-agent') return -1;
            if ($b['id'] === 'high-models-agent') return 1;
            if (str_starts_with($a['id'], 'gpt-4o-mini')) return -1;
            if (str_starts_with($b['id'], 'gpt-4o-mini')) return 1;
            return strcasecmp($a['id'], $b['id']);
        });

        return [
            'success' => true,
            'provider' => '9router',
            'models' => $models,
            'message' => 'Berhasil mendeteksi ' . count($models) . ' model dari 9Router Gateway.',
        ];
    }

    /**
     * Mengambil model yang didukung dari Anthropic Claude API atau katalog resmi Claude.
     */
    protected static function fetchClaudeModels(string $apiKey, ?string $endpoint): array
    {
        $url = static::buildModelsUrl($endpoint ?: 'https://api.anthropic.com/v1');
        $models = [];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(15)->get($url);

            if ($response->successful()) {
                $data = $response->json('data') ?? [];
                foreach ($data as $item) {
                    $id = $item['id'] ?? '';
                    if (empty($id)) {
                        continue;
                    }
                    $name = $item['display_name'] ?? $id;
                    $models[] = [
                        'id' => $id,
                        'name' => "{$name} ({$id})",
                        'description' => 'Model Anthropic Claude',
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::info('[PlatformAiService] Katalog /models Claude tidak merespons, beralih ke daftar resmi: ' . $e->getMessage());
        }

        if (empty($models)) {
            $models = [
                ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet (Rekomendasi Utama)', 'description' => 'Model cerdas rekomendasi'],
                ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku (Cepat & Hemat)', 'description' => 'Model cepat dan hemat biaya'],
                ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus (Penalaran Kompleks)', 'description' => 'Model penalaran kompleks'],
                ['id' => 'claude-3-sonnet-20240229', 'name' => 'Claude 3 Sonnet', 'description' => 'Model seimbang versi sebelumnya'],
                ['id' => 'claude-3-haiku-20240307', 'name' => 'Claude 3 Haiku', 'description' => 'Model ringan versi sebelumnya'],
            ];
        }

        return [
            'success' => true,
            'provider' => 'claude',
            'models' => $models,
            'message' => 'Berhasil memuat ' . count($models) . ' model Claude yang kompatibel.',
        ];
    }

    /**
     * Membangun URL endpoint /models yang valid dan toleran terhadap variasi path.
     */
    protected static function buildModelsUrl(string $endpoint): string
    {
        $clean = rtrim($endpoint, '/');
        if (str_ends_with($clean, '/models')) {
            return $clean;
        }
        if (str_ends_with($clean, '/v1')) {
            return "{$clean}/models";
        }
        return "{$clean}/v1/models";
    }
}

