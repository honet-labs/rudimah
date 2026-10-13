<?php

namespace App\Core\MFA;

use App\Core\Identity\Models\User;
use App\Core\MFA\Models\MfaChallenge;
use App\Core\MFA\Models\MfaMethod;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class MfaManager
{
    public function requiresMfa(User $user, string $role): bool
    {
        $privilegedRoles = config('rudimah.mfa.privileged_roles', ['owner', 'admin']);
        if (in_array(strtolower($role), $privilegedRoles) || $user->is_super_admin) {
            return true;
        }

        return $user->mfaMethods()->where('is_verified', true)->exists();
    }

    public function createOtpChallenge(User $user, string $type = 'email'): MfaChallenge
    {
        // Generate random CSPRNG 6-digit code
        $rawCode = (string) random_int(100000, 999999);

        return MfaChallenge::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'challenge_type' => $type,
            'code_hash' => Hash::make($rawCode),
            'attempts' => 0,
            'max_attempts' => config('rudimah.mfa.max_attempts', 5),
            'expires_at' => now()->addSeconds(config('rudimah.mfa.otp_ttl_seconds', 300)),
        ]);
    }

    public function verifyChallenge(MfaChallenge $challenge, string $providedCode): bool
    {
        if (!$challenge->isValid()) {
            return false;
        }

        $challenge->increment('attempts');

        if (Hash::check($providedCode, $challenge->code_hash)) {
            $challenge->update(['consumed_at' => now()]);
            return true;
        }

        return false;
    }
}
