<?php

namespace App\Core\MFA\Http\Controllers;

use App\Core\MFA\MfaManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MfaController
{
    public function __construct(
        protected MfaManager $mfaManager
    ) {}

    public function showChallenge(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Jika user sudah terverifikasi MFA atau tidak memiliki MFA aktif, arahkan langsung ke workspaces
        if ($request->session()->get('mfa_verified', false) || !$user->mfaMethods()->where('is_verified', true)->exists()) {
            return redirect()->intended('/workspaces');
        }

        return Inertia::render('Auth/MfaChallenge', [
            'challengeType' => session('mfa_challenge_type', 'totp'),
            'error' => session('error'),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $code = trim($request->input('code'));
        $isValid = $this->mfaManager->verifyUserCode($user, $code);

        if (!$isValid) {
            return back()->with('error', 'Kode autentikasi tidak valid atau telah kedaluwarsa.');
        }

        $request->session()->put('mfa_verified', true);
        $request->session()->forget('mfa_required');

        return redirect()->intended('/workspaces');
    }
}
