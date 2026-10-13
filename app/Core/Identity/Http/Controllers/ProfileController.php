<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
