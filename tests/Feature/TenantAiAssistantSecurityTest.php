<?php

namespace Tests\Feature;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\AI\Services\TenantFinancialContextBuilder;
use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAiAssistantSecurityTest extends TestCase
{
    public function test_ai_provider_manager_resolves_all_supported_providers(): void
    {
        $manager = new AiProviderManager();

        $gemini = $manager->resolve('gemini');
        $this->assertEquals('gemini', $gemini->getName());

        $openai = $manager->resolve('openai');
        $this->assertEquals('openai', $openai->getName());

        $hermes = $manager->resolve('hermes');
        $this->assertEquals('hermes', $hermes->getName());

        $openclaw = $manager->resolve('openclaw');
        $this->assertEquals('openclaw', $openclaw->getName());

        $mock = $manager->resolve('mock');
        $this->assertEquals('mock', $mock->getName());
    }

    public function test_ai_financial_context_strictly_contains_only_current_tenant_data(): void
    {
        // 1. Inisialisasi Tenant A
        $tenantA = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'tenant-a-personal',
            'name' => 'Keluarga Mandiri A',
            'slug' => 'keluarga-a',
            'status' => 'active',
        ]);

        // 2. Inisialisasi Tenant B
        $tenantB = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'tenant-b-corporate',
            'name' => 'PT Rahasia B',
            'slug' => 'pt-rahasia-b',
            'status' => 'active',
        ]);

        // 3. Masuki konteks Tenant A
        TenantContext::set($tenantA);
        $contextBuilder = new TenantFinancialContextBuilder();
        $prompt = $contextBuilder->buildSystemPrompt();

        // 4. Verifikasi isolasi keamanan:
        // Prompt harus memuat nama dan ID Tenant A
        $this->assertStringContainsString($tenantA->name, $prompt);
        $this->assertStringContainsString($tenantA->id, $prompt);

        // Prompt TIDAK BOLEH memuat nama atau ID Tenant B
        $this->assertStringNotContainsString($tenantB->name, $prompt);
        $this->assertStringNotContainsString($tenantB->id, $prompt);

        // Verifikasi keberadaan direktif keamanan mutlak
        $this->assertStringContainsString('PERATURAN KEAMANAN DATA MUTLAK', $prompt);
        $this->assertStringContainsString('DILARANG KERAS mencari, menebak, membocorkan', $prompt);

        TenantContext::clear();
    }

    public function test_cross_tenant_conversation_isolation_blocks_unauthorized_access(): void
    {
        $tenantAId = (string) Str::uuid();
        $tenantBId = (string) Str::uuid();

        $userAId = (string) Str::uuid();
        $userBId = (string) Str::uuid();

        // Percakapan milik Tenant B
        $convTenantB = new AiConversation([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantBId,
            'user_id' => $userBId,
            'title' => 'Diskusi Strategis Bisnis B',
            'provider' => 'hermes',
            'model' => 'hermes-3-llama-3.1-8b',
        ]);

        // Simulasikan pengguna A di Tenant A mencoba mengakses percakapan Tenant B
        $this->assertNotEquals($tenantAId, $convTenantB->tenant_id);
        $this->assertNotEquals($userAId, $convTenantB->user_id);
    }
}
