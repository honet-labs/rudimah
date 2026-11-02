<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceBudget;
use App\Modules\Pekan\Models\FinanceCategory;
use Illuminate\Http\JsonResponse;
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
        foreach ($budgets as $budget) {
            if (!$budget->is_past) {
                $budget->recalculateSpentAmount();
            }
        }

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

    public function transactions(Request $request, string $tenantSlug, string $id): JsonResponse
    {
        $budgetId = $request->route('id') ?: $id;
        $budget = FinanceBudget::with('category')->findOrFail($budgetId);

        $transactions = $budget->getTransactionsQuery()
            ->with(['category', 'account', 'items'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get();

        [$startDate, $endDate] = $budget->getDateRange();

        return response()->json([
            'success' => true,
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'category_name' => $budget->category_name,
                'amount_limit_minor' => (int) $budget->amount_limit_minor,
                'spent_amount_minor' => (int) $budget->spent_amount_minor,
                'progress_percent' => (float) $budget->progress_percent,
                'period_label' => $budget->period_label,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'transactions' => $transactions,
            'total_count' => $transactions->count(),
            'total_spent_minor' => (int) $transactions->sum('amount_minor'),
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
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'alert_threshold_pct' => 'required|integer|min:1|max:100',
            'status' => 'nullable|in:active,archived',
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
        $budget->end_date = !empty($validated['end_date']) ? $validated['end_date'] : null;
        $budget->alert_threshold_pct = $validated['alert_threshold_pct'];
        $budget->status = $validated['status'] ?? 'active';
        $budget->notes = $validated['notes'] ?? null;
        $budget->created_by = auth()->id();
        $budget->save();

        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/budgets")
            ->with('status', 'Anggaran belanja berhasil dibuat.');
    }

    public function edit(Request $request, string $tenantSlug, string $id): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        $budgetId = $request->route('id') ?: $id;
        $budget = FinanceBudget::findOrFail($budgetId);

        $categories = FinanceCategory::where('category_type', 'expense')
            ->where('is_active', true)
            ->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Budgets/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'categories' => $categories,
            'budget' => $budget,
        ]);
    }

    public function update(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $budgetId = $request->route('id') ?: $id;
        $budget = FinanceBudget::findOrFail($budgetId);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => 'required|string',
            'amount_limit_minor' => 'required|integer|min:1',
            'period' => 'required|in:weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'alert_threshold_pct' => 'required|integer|min:1|max:100',
            'status' => 'nullable|in:active,archived',
            'notes' => 'nullable|string|max:500',
        ]);

        $budget->update([
            'name' => $validated['name'],
            'category_id' => $validated['category_id'],
            'amount_limit_minor' => $validated['amount_limit_minor'],
            'period' => $validated['period'],
            'start_date' => $validated['start_date'],
            'end_date' => !empty($validated['end_date']) ? $validated['end_date'] : null,
            'alert_threshold_pct' => $validated['alert_threshold_pct'],
            'status' => $validated['status'] ?? $budget->status,
            'notes' => $validated['notes'] ?? null,
        ]);

        $budget->recalculateSpentAmount(true);

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/budgets")
            ->with('status', 'Anggaran belanja berhasil diperbarui.');
    }

    public function destroy(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $budgetId = $request->route('id') ?: $id;
        $budget = FinanceBudget::findOrFail($budgetId);
        $budget->delete();

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/budgets")
            ->with('status', 'Anggaran belanja berhasil dihapus.');
    }
}

