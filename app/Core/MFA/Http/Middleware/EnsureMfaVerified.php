<?php

namespace App\Core\MFA\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaVerified
{
    /**
     * Pastikan pengguna yang memiliki MFA aktif telah menyelesaikan verifikasi sebelum mengakses area aplikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Cek apakah akun pengguna memiliki metode MFA yang aktif dan terverifikasi
        $hasActiveMfa = $user->mfaMethods()->where('is_verified', true)->exists();

        if ($hasActiveMfa) {
            $isVerified = (bool) $request->session()->get('mfa_verified', false);

            if (!$isVerified) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Otentikasi multi-faktor (MFA) diperlukan sebelum dapat melanjutkan.',
                    ], 403);
                }

                return redirect()->route('mfa.challenge');
            }
        }

        return $next($request);
    }
}
