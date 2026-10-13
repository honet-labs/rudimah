<?php

namespace Tests\Feature;

use App\Core\Identity\Models\User;
use App\Core\MFA\MfaManager;
use Illuminate\Support\Str;
use Tests\TestCase;

class MfaAssuranceTest extends TestCase
{
    public function test_privileged_roles_require_mfa(): void
    {
        $mfaManager = new MfaManager();

        $ownerUser = new User([
            'id' => (string) Str::uuid(),
            'email' => 'owner@test.local',
            'full_name' => 'Owner Ruang Kerja',
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        // Peran owner wajib MFA
        $this->assertTrue($mfaManager->requiresMfa($ownerUser, 'owner'));

        // Peran admin wajib MFA
        $this->assertTrue($mfaManager->requiresMfa($ownerUser, 'admin'));

        // Super admin platform wajib MFA
        $superAdmin = new User([
            'id' => (string) Str::uuid(),
            'email' => 'super@test.local',
            'full_name' => 'Super Admin',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $this->assertTrue($mfaManager->requiresMfa($superAdmin, 'viewer'));
    }
}
