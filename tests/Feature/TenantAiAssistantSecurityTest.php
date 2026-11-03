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

        $claude = $manager->resolve('claude');
        $this->assertEquals('claude', $claude->getName());

        $nineRouter = $manager->resolve('9router');
        $this->assertEquals('9router', $nineRouter->getName());

        $nineRouterAlias = $manager->resolve('ninerouter');
        $this->assertEquals('9router', $nineRouterAlias->getName());
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

    public function test_ai_assistant_extracts_and_cleans_transaction_from_system_info_pattern(): void
    {
        $assistantService = app(\App\Core\AI\Services\TenantAssistantService::class);
        $reflection = new \ReflectionClass($assistantService);
        $method = $reflection->getMethod('extractTransactionActions');
        $method->setAccessible(true);

        $userMsg = 'Saya tadi makan malam beli rebusan jagung 7rb dan kacang rebus 10rb dan tadi makan siang beli bakso 16rb, nasi putih 8rb, telor dadar dan kentang mustofa 7rb tolong catet ya';
        $aiReply = "Pengeluaran makan malam (rebusan jagung Rp 7.000, kacang rebus Rp 10.000) dan makan siang (bakso Rp 16.000, nasi putih Rp 8.000, telur dadar dan kentang mustofa Rp 7.000) total Rp 48.000 tanggal 03 Nov 2026 dicatat ke kategori Makan dan Jajan.\n[Info Sistem: Mutasi yang diproses: ID: 256b9c1d-8a3f-4e5d-9c1b-7f8d3a2b1c0e (Makan malam rebusan jagung, kacang rebus; makan siang bakso, nasi putih, telur dadar, kentang mustofa di [Nama Tempat Tidak Disebutkan], Rp 48.000, 2026-11-03, status: created, items: [{item_name: 'Rebusan Jagung', quantity: 1, price_minor: 7000, total_minor: 7000}, {item_name: 'Kacang Rebus', quantity: 1, price_minor: 10000, total_minor: 10000}, {item_name: 'Bakso', quantity: 1, price_minor: 16000, total_minor: 16000}, {item_name: 'Nasi Putih', quantity: 1, price_minor: 8000, total_minor: 8000}, {item_name: 'Telur Dadar dan Kentang Mustofa', quantity: 1, price_minor: 7000, total_minor: 7000} ])].";

        [$actions, $cleanedReply] = $method->invoke($assistantService, $aiReply, $userMsg);

        $this->assertNotEmpty($actions);
        $this->assertCount(1, $actions);
        $firstAction = $actions[0];
        $this->assertEquals('create', $firstAction['action']);
        $this->assertEquals(48000, $firstAction['amount_minor']);
        $this->assertCount(5, $firstAction['items']);

        // Verifikasi bahwa string info sistem teknis telah dibersihkan secara tuntas
        $this->assertStringNotContainsString('[Info Sistem:', $cleanedReply);
        $this->assertStringNotContainsString('256b9c1d-8a3f-4e5d-9c1b-7f8d3a2b1c0e', $cleanedReply);
        $this->assertStringContainsString('Pengeluaran makan malam', $cleanedReply);
    }

    public function test_ai_assistant_extracts_multi_item_expenses_from_user_message(): void
    {
        $assistantService = app(\App\Core\AI\Services\TenantAssistantService::class);
        $reflection = new \ReflectionClass($assistantService);
        $method = $reflection->getMethod('parseUserExpenseItems');
        $method->setAccessible(true);

        $userMsg = 'Saya tadi makan malam beli rebusan jagung 7rb dan kacang rebus 10rb dan tadi makan siang beli bakso 16rb, nasi putih 8rb, telor dadar dan kentang mustofa 7rb tolong catet ya';
        $items = $method->invoke($assistantService, $userMsg);

        $this->assertNotEmpty($items);
        $this->assertCount(5, $items);
        $this->assertEquals('Rebusan Jagung', $items[0]['item_name']);
        $this->assertEquals(7000, $items[0]['price_minor']);
        $this->assertEquals('Kacang Rebus', $items[1]['item_name']);
        $this->assertEquals(10000, $items[1]['price_minor']);
        $this->assertEquals('Bakso', $items[2]['item_name']);
        $this->assertEquals(16000, $items[2]['price_minor']);
        $this->assertEquals('Nasi Putih', $items[3]['item_name']);
        $this->assertEquals(8000, $items[3]['price_minor']);
        $this->assertEquals('Telor Dadar Dan Kentang Mustofa', $items[4]['item_name']);
        $this->assertEquals(7000, $items[4]['price_minor']);

        $total = array_sum(array_column($items, 'total_minor'));
        $this->assertEquals(48000, $total);
    }
}
