<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $tenant = TenantContext::get() ?? $user->memberships()->first()?->tenant;

        return Inertia::render('Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ],
            'tenant' => $tenant,
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
        ]);

        $request->user()->update([
            'full_name' => $validated['name'],
        ]);

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'Kata sandi saat ini yang Anda masukkan tidak cocok.',
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return back()->with('status', 'Kata sandi Anda berhasil diperbarui.');
    }
}
