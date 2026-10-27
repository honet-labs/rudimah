<?php

namespace App\Core\AI;

use App\Core\AI\Contracts\AiProviderInterface;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\AI\Providers\ClaudeProvider;
use App\Core\AI\Providers\GeminiProvider;
use App\Core\AI\Providers\HermesProvider;
use App\Core\AI\Providers\LocalMockProvider;
use App\Core\AI\Providers\NineRouterProvider;
use App\Core\AI\Providers\OpenAiProvider;
use App\Core\AI\Providers\OpenClawProvider;
use App\Core\Tenancy\TenantContext;

class AiProviderManager
{
    /**
     * Resolves an AI Provider based on tenant setting or explicit name.
     */
    public function resolve(?string $providerName = null, ?TenantAiSetting $tenantSetting = null): AiProviderInterface
    {
        \App\Core\AI\Services\PlatformAiService::applyRuntimeConfig();

        if (!$tenantSetting && TenantContext::id()) {
            try {
                $tenantSetting = TenantAiSetting::where('tenant_id', TenantContext::id())->first();
            } catch (\Throwable) {
                // Ignore if table not yet migrated during bootstrap
            }
        }

        $defaultProvider = config('rudimah.ai.default_provider', 'gemini');
        $provider = $providerName ?: ($tenantSetting?->provider ?: $defaultProvider);

        $apiKey = $tenantSetting?->api_key;
        $endpoint = $tenantSetting?->api_endpoint;
        $defaultModel = $tenantSetting?->model;

        // Fallback ke pengaturan global platform jika ruang kerja tidak mendefinisikan kunci kustom
        if (empty($apiKey)) {
            $apiKey = match (strtolower($provider)) {
                'gemini' => config('rudimah.ai.gemini_key'),
                'openai' => config('rudimah.ai.openai_key'),
                'claude' => config('rudimah.ai.claude_key'),
                '9router', 'ninerouter' => config('rudimah.ai.ninerouter_key'),
                'hermes' => config('rudimah.ai.hermes_key'),
                'openclaw' => config('rudimah.ai.openclaw_key'),
                default => null,
            };
        }

        if (empty($endpoint)) {
            $endpoint = match (strtolower($provider)) {
                'gemini' => config('rudimah.ai.gemini_endpoint'),
                'openai' => config('rudimah.ai.openai_endpoint'),
                'claude' => config('rudimah.ai.claude_endpoint'),
                '9router', 'ninerouter' => config('rudimah.ai.ninerouter_endpoint', 'https://api.9router.com/v1'),
                'hermes' => config('rudimah.ai.hermes_endpoint'),
                'openclaw' => config('rudimah.ai.openclaw_endpoint'),
                default => null,
            };
        }

        if (empty($defaultModel)) {
            $defaultModel = match (strtolower($provider)) {
                'gemini' => config('rudimah.ai.gemini_model', 'gemini-1.5-flash'),
                'openai' => config('rudimah.ai.openai_model', 'gpt-4o-mini'),
                'claude' => config('rudimah.ai.claude_model', 'claude-3-5-sonnet-20241022'),
                '9router', 'ninerouter' => config('rudimah.ai.ninerouter_model', 'high-models-agent'),
                'hermes' => 'hermes-3-llama-3.1-8b',
                'openclaw' => 'openclaw-agent-v1',
                default => null,
            };
        }

        return match (strtolower($provider)) {
            'gemini' => new GeminiProvider($apiKey, $defaultModel ?: 'gemini-1.5-flash'),
            'openai' => new OpenAiProvider($apiKey, $defaultModel ?: 'gpt-4o-mini', $endpoint),
            'claude' => new ClaudeProvider($apiKey, $defaultModel ?: 'claude-3-5-sonnet-20241022', $endpoint),
            '9router', 'ninerouter' => new NineRouterProvider($apiKey, $defaultModel ?: 'high-models-agent', $endpoint),
            'hermes' => new HermesProvider($apiKey, $defaultModel ?: 'hermes-3-llama-3.1-8b', $endpoint),
            'openclaw' => new OpenClawProvider($apiKey, $defaultModel ?: 'openclaw-agent-v1', $endpoint),
            'mock', 'local' => new LocalMockProvider(),
            default => new NineRouterProvider($apiKey, $defaultModel ?: 'high-models-agent', $endpoint),
        };
    }
}
