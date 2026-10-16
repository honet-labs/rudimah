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
}
