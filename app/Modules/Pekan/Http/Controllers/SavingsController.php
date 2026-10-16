<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceSavings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $savings = FinanceSavings::where('status', 'active')->get();

        $tenants = $user ? $user->memberships()->with('tenant')->get()->map(function ($m) {
            $t = $m->tenant;
            if ($t) $t->role = $m->role;
            return $t;
        })->filter()->values() : [];

        return Inertia::render('Pekan/Savings/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'savings' => $savings,
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $tenants = $user ? $user->memberships()->with('tenant')->get()->map(function ($m) {
            $t = $m->tenant;
            if ($t) $t->role = $m->role;
            return $t;
        })->filter()->values() : [];

        return Inertia::render('Pekan/Savings/Create', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
        ]);
    }

    public function store(Request $request, ?string $tenantSlug = null): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'target_amount_minor' => 'required|integer|min:1',
            'current_amount_minor' => 'nullable|integer|min:0',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $savings = new FinanceSavings();
        $savings->id = (string) Str::uuid();
        $savings->tenant_id = $tenant->id;
        $savings->name = $validated['name'];
        $savings->target_amount_minor = $validated['target_amount_minor'];
        $savings->current_amount_minor = $validated['current_amount_minor'] ?? 0;
        $savings->currency = 'Rp';
        $savings->start_date = now()->toDateString();
        $savings->target_date = $validated['target_date'] ?? null;
        $savings->notes = $validated['notes'] ?? null;
        $savings->status = 'active';
        $savings->created_by = auth()->id();
        $savings->save();

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/savings")
            ->with('status', 'Pos tabungan impian berhasil dibuat.');
    }
}
