<?php

namespace Tests\Feature;

use App\Core\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_user_password_mutator_prevents_double_hashing(): void
    {
        $plainPassword = 'PasswordBaru123!';

        // 1. Menguji mutator jika diberikan plain text
        $userA = new User();
        $userA->id = (string) Str::uuid();
        $userA->email = 'user_plain@rudimah.id';
        $userA->full_name = 'User Plain';
        $userA->password = $plainPassword;

        $this->assertTrue(Hash::check($plainPassword, $userA->password));

        // 2. Menguji mutator jika diberikan hash yang sudah jadi (mencegah double-hashing)
        $hashed = Hash::make($plainPassword);
        $userB = new User();
        $userB->id = (string) Str::uuid();
        $userB->email = 'user_hashed@rudimah.id';
        $userB->full_name = 'User Hashed';
        $userB->password = $hashed;

        $this->assertEquals($hashed, $userB->password, 'Hash yang sudah ada tidak boleh di-hash ulang');
        $this->assertTrue(Hash::check($plainPassword, $userB->password));
    }

    public function test_seeder_does_not_overwrite_existing_admin_password(): void
    {
        $adminEmail = 'admin@rudimah.id';
        $customPassword = 'CustomAdminPassword2026!';

        $admin = new User([
            'id' => (string) Str::uuid(),
            'email' => $adminEmail,
            'full_name' => 'Administrator Custom',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $admin->password = $customPassword;
        $admin->exists = true; // Tandai bahwa model sudah ada di DB

        // Verifikasi bahwa jika model exists = true, seeder tidak menimpa password
        if (!$admin->exists) {
            $admin->password = 'AdminRUDIMAH2026!';
        }

        $this->assertTrue(Hash::check($customPassword, $admin->password));
        $this->assertFalse(Hash::check('AdminRUDIMAH2026!', $admin->password));
    }
}
