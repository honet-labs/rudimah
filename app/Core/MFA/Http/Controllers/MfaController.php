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

    public function showChallenge(): Response
    {
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

        // Simulasikan verifikasi valid untuk code
        $request->session()->put('mfa_verified', true);

        return redirect()->intended('/workspaces');
    }
}
