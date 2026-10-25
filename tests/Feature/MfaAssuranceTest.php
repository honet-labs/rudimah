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

    public function test_totp_calculation_and_verification(): void
    {
        $mfaManager = new MfaManager();
        $base32Secret = 'JBSWY3DPEHPK3PXP'; // Kunci base32 standar RFC

        $timeSlice = (int) floor(time() / 30);
        $binaryKey = $mfaManager->base32Decode($base32Secret);
        $this->assertNotNull($binaryKey);

        $validCode = $mfaManager->calculateTotp($binaryKey, $timeSlice);
        $this->assertEquals(6, strlen($validCode));
        $this->assertTrue(ctype_digit($validCode));

        // Verifikasi kode valid diterima
        $this->assertTrue($mfaManager->verifyTotpCode($base32Secret, $validCode));

        // Verifikasi kode sembarang yang salah ditolak
        $invalidCode = str_pad((string) (($validCode + 123456) % 1000000), 6, '0', STR_PAD_LEFT);
        if ($invalidCode === $validCode) {
            $invalidCode = '000000';
        }
        $this->assertFalse($mfaManager->verifyTotpCode($base32Secret, $invalidCode));
    }

    public function test_ensure_mfa_verified_middleware_protects_routes(): void
    {
        $middleware = new \App\Core\MFA\Http\Middleware\EnsureMfaVerified();

        // Pengguna tanpa MFA aktif diizinkan lanjut
        $userWithoutMfa = new User([
            'id' => (string) Str::uuid(),
            'email' => 'nomfa@test.local',
            'full_name' => 'User No MFA',
            'is_active' => true,
        ]);

        $request = \Illuminate\Http\Request::create('/workspaces', 'GET');
        $request->setUserResolver(fn() => $userWithoutMfa);

        $response = $middleware->handle($request, function () {
            return new \Symfony\Component\HttpFoundation\Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
