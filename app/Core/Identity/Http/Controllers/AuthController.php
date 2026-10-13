<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuthController
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Cek apakah user wajib MFA
            if ($user->mfaMethods()->where('is_verified', true)->exists()) {
                return redirect()->route('mfa.challenge');
            }

            return redirect()->intended('/workspaces');
        }

        return back()->with('error', 'Kombinasi email dan kata sandi tidak cocok.');
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userId = (string) Str::uuid();
        $user = User::create([
            'id' => $userId,
            'email' => $validated['email'],
            'full_name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        // Buat tenant default personal
        $tenantId = (string) Str::uuid();
        $tenantSlug = Str::slug($validated['name'] . ' Personal');
        $tenant = Tenant::create([
            'id' => $tenantId,
            'code' => $tenantSlug,
            'name' => $validated['name'] . ' Personal',
            'slug' => $tenantSlug,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
        ]);

        TenantMembership::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Auth::login($user);

        return redirect("/t/{$tenant->slug}/apps/pekan/dashboard");
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
