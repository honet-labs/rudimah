<?php

namespace App\Core\AI;

use App\Core\AI\Contracts\AiProviderInterface;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\AI\Providers\GeminiProvider;
use App\Core\AI\Providers\HermesProvider;
use App\Core\AI\Providers\LocalMockProvider;
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
        if (!$tenantSetting && TenantContext::id()) {
            try {
                $tenantSetting = TenantAiSetting::where('tenant_id', TenantContext::id())->first();
            } catch (\Throwable) {
                // Ignore if table not yet migrated during bootstrap
            }
        }

        $provider = $providerName ?: ($tenantSetting?->provider ?: config('rudimah.ai.default_provider', 'gemini'));
        $apiKey = $tenantSetting?->api_key;
        $endpoint = $tenantSetting?->api_endpoint;
        $defaultModel = $tenantSetting?->model;

        return match (strtolower($provider)) {
            'gemini' => new GeminiProvider($apiKey, $defaultModel ?: 'gemini-1.5-flash'),
            'openai' => new OpenAiProvider($apiKey, $defaultModel ?: 'gpt-4o-mini', $endpoint),
            'hermes' => new HermesProvider($apiKey, $defaultModel ?: 'hermes-3-llama-3.1-8b', $endpoint),
            'openclaw' => new OpenClawProvider($apiKey, $defaultModel ?: 'openclaw-agent-v1', $endpoint),
            'mock', 'local' => new LocalMockProvider(),
            default => new GeminiProvider($apiKey, $defaultModel ?: 'gemini-1.5-flash'),
        };
    }
}
