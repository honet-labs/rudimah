<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\Identity\Models\SystemSetting;
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
                '9router' => $routerModel,
                'openai' => $openaiModel,
                'claude' => $claudeModel,
                default => $geminiModel,
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
}
