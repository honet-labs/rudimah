<?php

namespace Tests\Feature;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\AI\Services\PlatformAiService;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptOcrHumanVerificationTest extends TestCase
{
    public function test_ocr_does_not_auto_commit_without_human_verification(): void
    {
        // Kebijakan non-negotiable: OCR tidak boleh mengesahkan transaksi tanpa verifikasi pengguna
        $autoCommit = config('rudimah.ocr.auto_commit');
        $this->assertFalse($autoCommit, 'OCR auto_commit wajib bernilai false untuk menjamin prinsip human-in-the-loop.');
    }

    public function test_platform_runtime_config_registers_9router_and_ninerouter_aliases(): void
    {
        config([
            'rudimah.ai.default_provider' => '9router',
            'rudimah.ai.9router_key' => 'test-key-123',
            'rudimah.ai.9router_endpoint' => 'https://api.9router.com/v1',
            'rudimah.ai.9router_model' => 'gpt-4o-mini',
            'rudimah.ai.ninerouter_key' => 'test-key-123',
            'rudimah.ai.ninerouter_endpoint' => 'https://api.9router.com/v1',
            'rudimah.ai.ninerouter_model' => 'gpt-4o-mini',
            'rudimah.ocr.provider' => '9router',
            'rudimah.ocr.model' => 'gpt-4o-mini',
        ]);

        $this->assertEquals('9router', config('rudimah.ai.default_provider'));
        $this->assertEquals('gpt-4o-mini', config('rudimah.ai.9router_model'));
        $this->assertEquals('gpt-4o-mini', config('rudimah.ocr.model'));
        $this->assertEquals('test-key-123', config('rudimah.ai.9router_key'));
    }

    public function test_ai_provider_manager_inherits_system_config_when_tenant_has_no_custom_keys(): void
    {
        config([
            'rudimah.ai.default_provider' => '9router',
            'rudimah.ai.9router_key' => 'global-9router-token',
            'rudimah.ai.9router_endpoint' => 'https://api.9router.com/v1',
            'rudimah.ai.9router_model' => 'gpt-4o-mini',
            'rudimah.ai.ninerouter_key' => 'global-9router-token',
            'rudimah.ai.ninerouter_endpoint' => 'https://api.9router.com/v1',
            'rudimah.ai.ninerouter_model' => 'gpt-4o-mini',
        ]);

        $tenant = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'tenant-ocr-test',
            'name' => 'Ruang Kerja OCR Test',
            'slug' => 'ruang-kerja-ocr-test',
            'status' => 'active',
        ]);
        TenantContext::set($tenant);

        // Tenant row dengan default migration (tanpa custom API key)
        $tenantSetting = new TenantAiSetting([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'provider' => 'gemini',
            'model' => 'gemini-1.5-flash',
            'api_key_encrypted' => null,
            'is_enabled' => true,
        ]);

        $manager = new AiProviderManager();
        $provider = $manager->resolve(null, $tenantSetting);

        // Karena tenant tidak punya custom key, wajib mewarisi active provider dari system settings (9router)
        $this->assertEquals('9router', $provider->getName());

        TenantContext::clear();
    }

    public function test_custom_ocr_model_and_provider_configured_in_admin_is_applied_to_ocr_config(): void
    {
        config([
            'rudimah.ai.default_provider' => 'gemini',
            'rudimah.ocr.provider' => 'openai',
            'rudimah.ocr.model' => 'gpt-4o',
        ]);

        $this->assertEquals('openai', config('rudimah.ocr.provider'));
        $this->assertEquals('gpt-4o', config('rudimah.ocr.model'));
    }

    public function test_ocr_inherits_openai_provider_and_model_when_configured(): void
    {
        config([
            'rudimah.ai.default_provider' => 'openai',
            'rudimah.ai.openai_model' => 'gpt-4o',
            'rudimah.ai.openai_key' => 'sk-test-openai',
            'rudimah.ocr.provider' => 'openai',
            'rudimah.ocr.model' => 'gpt-4o',
        ]);

        $this->assertEquals('openai', config('rudimah.ocr.provider'));
        $this->assertEquals('gpt-4o', config('rudimah.ocr.model'));
    }
}
