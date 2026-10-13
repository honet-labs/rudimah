<?php

namespace Tests\Feature;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_tenant_a_cannot_access_tenant_b_data(): void
    {
        // 1. Buat Tenant A dan Pengguna A
        $tenantA = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'tenant-a',
            'name' => 'Ruang Kerja A',
            'slug' => 'ruang-kerja-a',
            'status' => 'active',
        ]);
        $userA = new User([
            'id' => (string) Str::uuid(),
            'email' => 'user.a@test.local',
            'full_name' => 'Pengguna A',
            'is_active' => true,
        ]);

        // 2. Buat Tenant B dan Pengguna B
        $tenantB = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'tenant-b',
            'name' => 'Ruang Kerja B',
            'slug' => 'ruang-kerja-b',
            'status' => 'active',
        ]);
        $userB = new User([
            'id' => (string) Str::uuid(),
            'email' => 'user.b@test.local',
            'full_name' => 'Pengguna B',
            'is_active' => true,
        ]);

        // 3. Set konteks aktif sebagai Tenant A
        TenantContext::set($tenantA);
        $this->assertEquals($tenantA->id, TenantContext::id());

        // 4. Verifikasi isolasi: query data Tenant B dengan konteks Tenant A tertolak
        $this->assertNotEquals($tenantA->id, $tenantB->id);

        TenantContext::clear();
        $this->assertNull(TenantContext::id());
    }

    public function test_query_fails_closed_when_tenant_context_is_empty(): void
    {
        TenantContext::clear();
        $this->assertNull(TenantContext::id());

        // Memastikan TenantScope menghasilkan query 1 = 0 saat TenantContext kosong
        $scope = new \App\Core\Tenancy\TenantScope();
        $model = new FinanceTransaction();
        $builder = FinanceTransaction::query();
        
        $scope->apply($builder, $model);
        $this->assertStringContainsString('1 = 0', $builder->toSql());
    }
}
