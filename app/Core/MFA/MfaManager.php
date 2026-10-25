<?php

namespace App\Core\MFA;

use App\Core\Identity\Models\User;
use App\Core\MFA\Models\MfaChallenge;
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

    public function getActiveChallenge(User $user, ?string $type = null): ?MfaChallenge
    {
        $query = MfaChallenge::where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('attempts', '<', config('rudimah.mfa.max_attempts', 5))
            ->where('expires_at', '>', now())
            ->latest('created_at');

        if ($type) {
            $query->where('challenge_type', $type);
        }

        return $query->first();
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

    /**
     * Verifikasi kode 6-digit terhadap seluruh metode MFA terverifikasi milik pengguna (TOTP atau OTP Challenge).
     */
    public function verifyUserCode(User $user, string $providedCode): bool
    {
        $cleanCode = trim($providedCode);
        if (strlen($cleanCode) !== 6 || !ctype_digit($cleanCode)) {
            return false;
        }

        // 1. Verifikasi TOTP jika pengguna memiliki metode TOTP aktif
        $totpMethods = $user->mfaMethods()
            ->where('method_type', 'totp')
            ->where('is_verified', true)
            ->get();

        foreach ($totpMethods as $method) {
            $secret = $method->secret_encrypted;
            if (!empty($secret)) {
                try {
                    $decryptedSecret = \Illuminate\Support\Facades\Crypt::decryptString($secret);
                } catch (\Throwable) {
                    $decryptedSecret = $secret;
                }

                if ($this->verifyTotpCode($decryptedSecret, $cleanCode)) {
                    $method->update(['last_used_at' => now()]);
                    return true;
                }
            }
        }

        // 2. Verifikasi terhadap tantangan OTP aktif (email, telegram, whatsapp)
        $activeChallenge = $this->getActiveChallenge($user);
        if ($activeChallenge && $this->verifyChallenge($activeChallenge, $cleanCode)) {
            $user->mfaMethods()
                ->where('method_type', $activeChallenge->challenge_type)
                ->where('is_verified', true)
                ->update(['last_used_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Verifikasi kode TOTP berbasis standar RFC 6238 dengan toleransi pergeseran waktu (drift).
     */
    public function verifyTotpCode(string $secret, string $code, int $window = 1): bool
    {
        $cleanedSecret = strtoupper(str_replace(' ', '', $secret));
        $binaryKey = $this->base32Decode($cleanedSecret) ?? $cleanedSecret;
        $currentTimeSlice = (int) floor(time() / 30);

        for ($drift = -$window; $drift <= $window; $drift++) {
            $expectedCode = $this->calculateTotp($binaryKey, $currentTimeSlice + $drift);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kalkulasi token TOTP untuk time slice tertentu per RFC 4226 / RFC 6238.
     */
    public function calculateTotp(string $binaryKey, int $timeSlice): string
    {
        $timeData = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeData, $binaryKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Dekode string Base32 RFC 4648 ke byte biner.
     */
    public function base32Decode(string $base32): ?string
    {
        $base32 = strtoupper(rtrim($base32, '='));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $pos = strpos($alphabet, $base32[$i]);
            if ($pos === false) {
                return null;
            }
            $binary .= sprintf('%05b', $pos);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
            $bytes .= chr(bindec(substr($binary, $i, 8)));
        }

        return $bytes;
    }
}
