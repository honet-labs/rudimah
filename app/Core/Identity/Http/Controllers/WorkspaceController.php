<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $memberships = $user->memberships()->with('tenant')->get();

        $tenants = $memberships->map(function ($m) {
            $t = $m->tenant;
            $t->role = $m->role;
            return $t;
        });

        return Inertia::render('WorkspaceSelector', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $user = $request->user();
        $baseSlug = Str::slug($validated['name']) ?: 'ruang-kerja';
        $slug = $baseSlug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->orWhere('code', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $tenantId = (string) Str::uuid();
        $tenant = Tenant::create([
            'id' => $tenantId,
            'code' => $slug,
            'name' => $validated['name'],
            'slug' => $slug,
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

        return redirect("/t/{$tenant->slug}/apps/pekan/dashboard");
    }

    /**
     * Peluncur cerdas akses modul aplikasi.
     * Mengarahkan pengguna ke ruang kerja aktif atau default tanpa menimbulkan error 404.
     */
    public function launchApp(Request $request, string $appSlug = 'pekan'): RedirectResponse
    {
        $user = $request->user();
        $targetSlug = $appSlug ?: 'pekan';

        // 1. Cek riwayat tenant aktif di session
        $tenant = null;
        $sessionTenantSlug = session('current_tenant_slug');
        if ($sessionTenantSlug) {
            $tenant = Tenant::where('slug', $sessionTenantSlug)
                ->where('status', 'active')
                ->first();
        }

        // 2. Jika tidak ada di session, gunakan keanggotaan aktif pertama milik pengguna
        if (!$tenant && $user) {
            $membership = $user->memberships()
                ->where('status', 'active')
                ->with('tenant')
                ->first();

            if ($membership && $membership->tenant && $membership->tenant->status === 'active') {
                $tenant = $membership->tenant;
            }
        }

        // 3. Jika pengguna adalah super admin dan belum punya keanggotaan khusus, gunakan tenant aktif pertama platform
        if (!$tenant && $user?->is_super_admin) {
            $tenant = Tenant::where('status', 'active')->first();
        }

        // 4. Jika tenant ditemukan, simpan ke session dan arahkan ke dashboard modul tersebut
        if ($tenant) {
            session(['current_tenant_slug' => $tenant->slug]);
            return redirect("/t/{$tenant->slug}/apps/{$targetSlug}");
        }

        // 5. Jika sama sekali belum ada tenant aktif terdaftar, arahkan ke halaman pembuatan ruang kerja
        return redirect()->route('workspaces.index');
    }
}

