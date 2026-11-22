<?php

namespace Tests\Feature;

use App\Core\AI\Services\TenantFinancialContextBuilder;
use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReceiptScan;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptWorkflowRegressionTest extends TestCase
{
    private Tenant $tenant;

    public function createApplication()
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('t', 32)),
            'app.env' => 'testing', 'session.driver' => 'array', 'cache.default' => 'array',
            'database.default' => 'platform',
            'database.connections.platform' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'database.connections.pekan' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'rudimah.ai.default_provider' => 'openai', 'rudimah.ai.openai_key' => 'test-only',
        ]);
        DB::purge('platform');
        DB::purge('pekan');
        // Explicitly disposable in-memory databases only; never invoke production migration commands.
        foreach (glob(database_path('migrations/*.php')) as $file) (require $file)->up();
        Http::preventStrayRequests();
        $user = User::create(['id' => (string) Str::uuid(), 'full_name' => 'Pengguna Uji',
            'email' => 'receipt@example.test', 'password' => 'test-password', 'is_active' => true]);
        $this->tenant = Tenant::create(['id' => (string) Str::uuid(), 'name' => 'Ruang Uji', 'code' => 'uji', 'slug' => 'uji', 'status' => 'active']);
        TenantMembership::create(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'status' => 'active', 'role' => 'owner']);
        $this->actingAs($user);
        TenantContext::set($this->tenant);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function payload(string $scanId): array
    {
        return ['scan_id' => $scanId, 'merchant_name' => 'Toko Uji', 'transaction_date' => '2026-11-03',
            'subtotal_minor' => 48500, 'discount_minor' => 3500, 'tax_minor' => 0, 'service_charge_minor' => 0,
            'total_minor' => 45000, 'items' => [['item_name' => 'Belanja', 'quantity' => 1, 'price_minor' => 48500, 'total_minor' => 48500]]];
    }

    private function scan(?string $tenantId = null): FinanceReceiptScan
    {
        return FinanceReceiptScan::create(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId ?? $this->tenant->id,
            'status' => 'pending_review', 'original_filename' => 'struk.jpg', 'mime_type' => 'image/jpeg',
            'images' => [['id' => 'proof', 'name' => 'struk.jpg', 'url' => '/uploads/receipts/proof.jpg']]]);
    }

    public function test_verified_total_date_and_attachment_survive_confirmation_and_duplicate_submit(): void
    {
        $scan = $this->scan();
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', $this->payload($scan->id))->assertSuccessful();
        TenantContext::set($this->tenant);
        $transaction = FinanceTransaction::firstOrFail();
        $this->assertSame(45000, $transaction->amount_minor);
        $this->assertSame(3500, $transaction->receipt_discount_minor);
        $this->assertSame('2026-11-03', $transaction->toArray()['transaction_date']);
        $this->assertSame('proof', $transaction->images[0]['id']);
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', $this->payload($scan->id))->assertUnprocessable();
        $this->assertSame(1, DB::connection('pekan')->table('finance_transactions')->count());
    }

    public function test_inconsistent_total_invalid_date_and_other_tenant_scan_are_rejected(): void
    {
        $scan = $this->scan();
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', array_replace($this->payload($scan->id), ['total_minor' => 48500]))
            ->assertUnprocessable()->assertJsonValidationErrors('total_minor');
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', array_replace($this->payload($scan->id), ['transaction_date' => '2026-02-31']))
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_date');
        TenantContext::set($this->tenant);
        $other = $this->scan((string) Str::uuid());
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', $this->payload($other->id))->assertNotFound();
        $this->assertSame(0, DB::connection('pekan')->table('finance_transactions')->count());
    }

    public function test_assistant_can_find_old_transactions_and_aggregate_all_pages_without_cross_tenant_data(): void
    {
        for ($i = 0; $i < 70; $i++) {
            FinanceTransaction::create(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id,
                'account_id' => (string) Str::uuid(), 'type' => 'expense', 'amount_minor' => 1000,
                'transaction_date' => '2026-01-03', 'input_date' => '2026-01-03', 'description' => 'Belanja Lama']);
        }
        FinanceTransaction::create(['id' => (string) Str::uuid(), 'tenant_id' => (string) Str::uuid(),
            'account_id' => (string) Str::uuid(), 'type' => 'expense', 'amount_minor' => 999999,
            'transaction_date' => '2026-01-03', 'input_date' => '2026-01-03', 'description' => 'RAHASIA_TENANT_B']);
        $context = app(TenantFinancialContextBuilder::class)->buildTransactionContext('transaksi "Belanja Lama" halaman 2');
        $this->assertStringContainsString('70 dari 70', $context);
        $this->assertStringContainsString('Rp 70.000', $context);
        $this->assertSame(20, substr_count($context, '"description":"Belanja Lama"'));
        $this->assertStringNotContainsString('RAHASIA_TENANT_B', $context);
        TenantContext::clear();
        $this->assertStringNotContainsString('Belanja Lama', app(TenantFinancialContextBuilder::class)->buildTransactionContext());
    }

    public function test_mobile_upload_is_saved_as_a_real_transaction_attachment(): void
    {
        $root = sys_get_temp_dir() . '/rudimah-upload-' . Str::uuid();
        mkdir($root, 0700, true);
        $this->app->usePublicPath($root);
        $file = UploadedFile::fake()->image('kamera.jpg', 200, 300);
        $this->post('/t/uji/apps/pekan/transactions', ['type' => 'expense', 'amount_minor' => 45000,
            'description' => 'Upload Mobile', 'transaction_date' => '2026-11-03', 'images' => [$file]])->assertRedirect();
        TenantContext::set($this->tenant);
        $transaction = FinanceTransaction::firstOrFail();
        $this->assertCount(1, $transaction->images);
        $path = $root . $transaction->images[0]['url'];
        $this->assertFileExists($path);
        $this->assertFileExists($file->getRealPath());
        unlink($path);
        rmdir(dirname($path));
        rmdir($root . '/uploads');
        rmdir($root);
    }

    public function test_vision_retries_transient_failure_and_preserves_raw_date_and_total(): void
    {
        $receipt = ['merchant_name' => 'Toko Uji', 'transaction_date_raw' => '03/11/26',
            'transaction_date' => '2026-03-11', 'subtotal_minor' => 48500, 'discount_minor' => 3500, 'total_minor' => 45000];
        Http::fake(['*' => Http::sequence()->push(['error' => ['message' => 'Unavailable']], 503)
            ->push(['choices' => [['message' => ['content' => json_encode($receipt)]]]], 200)]);
        $provider = new \App\Core\AI\Providers\OpenAiProvider('test-key', 'vision-test', 'https://vision.example.test/v1');
        $result = $provider->extractReceipt(base64_encode('fixture'), 'image/jpeg');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'][0]['text'], 'transaction_date_raw'));
        $this->assertSame('2026-11-03', $result['transaction_date']);
        $this->assertSame(45000, $result['total_minor']);
        $this->assertSame(0, DB::connection('pekan')->table('finance_transactions')->count());
    }

    public function test_edit_keeps_verified_receipt_total_discount_and_existing_proof(): void
    {
        $scan = $this->scan();
        $this->postJson('/t/uji/apps/pekan/receipt-scan/confirm', $this->payload($scan->id))->assertSuccessful();
        TenantContext::set($this->tenant);
        $transaction = FinanceTransaction::firstOrFail();
        $this->put('/t/uji/apps/pekan/transactions/' . $transaction->id, [
            'description' => 'Nama diperbaiki', 'type' => 'expense', 'amount_minor' => 45000,
            'transaction_date' => '2026-11-03', 'subtotal_minor' => 48500, 'receipt_discount_minor' => 3500,
            'items' => [], 'existing_images' => json_encode(['proof']),
        ])->assertRedirect();
        TenantContext::set($this->tenant);
        $transaction->refresh();
        $this->assertSame(45000, $transaction->amount_minor);
        $this->assertSame(3500, $transaction->receipt_discount_minor);
        $this->assertSame('proof', $transaction->images[0]['id']);
    }

    public function test_receipt_upload_returns_the_saved_proof_and_draft_without_committing_a_transaction(): void
    {
        $root = sys_get_temp_dir() . '/rudimah-ocr-' . Str::uuid();
        mkdir($root, 0700, true);
        $this->app->usePublicPath($root);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'merchant_name' => 'Toko OCR', 'transaction_date_raw' => '03/11/26', 'subtotal_minor' => 48500,
            'discount_minor' => 3500, 'total_minor' => 45000,
        ])]]]], 200)]);
        $source = UploadedFile::fake()->image('source.jpg', 200, 400);
        $this->post('/t/uji/apps/pekan/receipt-scan/upload', [
            'receipt_file' => new UploadedFile($source->getRealPath(), 'kamera-tanpa-ekstensi', 'image/jpeg', null, true),
        ], ['Accept' => 'application/json'])->assertSuccessful()
            ->assertJsonPath('extracted.total_minor', 45000)->assertJsonPath('extracted.transaction_date', '2026-11-03');
        TenantContext::set($this->tenant);
        $scan = FinanceReceiptScan::firstOrFail();
        $this->assertSame('pending_review', $scan->status);
        $this->assertSame(0, FinanceTransaction::count());
        $this->assertCount(1, $scan->images);
        $path = $root . $scan->images[0]['url'];
        $this->assertFileExists($path);
        unlink($path);
        rmdir(dirname($path));
        rmdir($root . '/uploads');
        rmdir($root);
    }

    public function test_correction_without_a_new_date_does_not_replace_the_original_date_with_today(): void
    {
        $service = app(\App\Core\AI\Services\TenantAssistantService::class);
        $this->assertSame('2026-11-03', $service->resolveTransactionDate('ubah nominal jadi 45000', '2026-11-19', \Carbon\Carbon::parse('2026-11-03')));
        $this->assertSame('2026-11-03', $service->resolveTransactionDate('ubah nominal jadi 45000', null, '2026-11-03'));
    }
}
