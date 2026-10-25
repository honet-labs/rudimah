<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceBudget;
use App\Modules\Pekan\Models\FinanceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $budgets = FinanceBudget::with('category')->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Budgets/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'budgets' => $budgets,
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $categories = FinanceCategory::where('category_type', 'expense')
            ->where('is_active', true)
            ->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Budgets/Create', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, ?string $tenantSlug = null): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'required|string',
            'amount_limit_minor' => 'required|integer|min:1',
            'period' => 'required|in:weekly,monthly,yearly',
            'start_date' => 'required|date',
            'alert_threshold_pct' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $budget = new FinanceBudget();
        $budget->id = (string) Str::uuid();
        $budget->tenant_id = $tenant->id;
        $budget->name = $validated['name'];
        $budget->category_id = $validated['category_id'];
        $budget->amount_limit_minor = $validated['amount_limit_minor'];
        $budget->spent_amount_minor = 0;
        $budget->currency = 'Rp';
        $budget->period = $validated['period'];
        $budget->start_date = $validated['start_date'];
        $budget->alert_threshold_pct = $validated['alert_threshold_pct'];
        $budget->status = 'active';
        $budget->notes = $validated['notes'] ?? null;
        $budget->created_by = auth()->id();
        $budget->save();

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/budgets")
            ->with('status', 'Anggaran belanja berhasil dibuat.');
    }
}
