<?php

namespace Database\Seeders;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Administrator
        $adminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@rudimah.id');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD', 'AdminRUDIMAH2026!');
        $adminId = (string) Str::uuid();

        $admin = User::firstOrNew(['email' => $adminEmail]);
        if (!$admin->exists) {
            $admin->id = $adminId;
            $admin->full_name = 'Administrator RUDIMAH';
            $admin->password = $adminPassword;
            $admin->is_active = true;
            $admin->is_super_admin = true;
            $admin->save();
        }

        // 2. Buat Tenant Default
        $tenantId = (string) Str::uuid();
        $tenant = Tenant::firstOrCreate(
            ['code' => 'keluarga-mandiri'],
            [
                'id' => $tenantId,
                'name' => 'Keluarga Mandiri',
                'slug' => 'keluarga-mandiri',
                'status' => 'active',
                'timezone' => 'Asia/Jakarta',
            ]
        );

        // 3. Buat Membership
        TenantMembership::firstOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $admin->id],
            [
                'id' => (string) Str::uuid(),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]
        );

        // 4. Buat Rekening Default PEKAN
        $accounts = [
            ['name' => 'BCA Utama', 'type' => 'bank', 'balance' => 15500000],
            ['name' => 'Dompet Tunai', 'type' => 'cash', 'balance' => 415000],
            ['name' => 'Gopay / OVO', 'type' => 'ewallet', 'balance' => 120000],
        ];

        foreach ($accounts as $acc) {
            FinanceAccount::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $acc['name']],
                [
                    'id' => (string) Str::uuid(),
                    'account_type' => $acc['type'],
                    'currency' => 'IDR',
                    'opening_balance_minor' => $acc['balance'],
                    'current_balance_minor' => $acc['balance'],
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
        }

        // 5. Buat Kategori Default PEKAN
        $categories = [
            ['name' => 'Gaji Pokok', 'type' => 'income'],
            ['name' => 'Bonus & Tunjangan', 'type' => 'income'],
            ['name' => 'Belanja Dapur', 'type' => 'expense'],
            ['name' => 'Transportasi', 'type' => 'expense'],
            ['name' => 'Tagihan Listrik & Air', 'type' => 'expense'],
            ['name' => 'Pendidikan', 'type' => 'expense'],
            ['name' => 'Hiburan', 'type' => 'expense'],
        ];

        foreach ($categories as $cat) {
            FinanceCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $cat['name']],
                [
                    'id' => (string) Str::uuid(),
                    'category_type' => $cat['type'],
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
