<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceTransaction;
use App\Modules\Pekan\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $query = FinanceTransaction::with(['category', 'items'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                  ->orWhere('merchant_name', 'like', '%' . $search . '%')
                  ->orWhere('id', 'like', '%' . $search . '%');
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        return Inertia::render('Pekan/Transactions/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->getActiveTenants() : [],
            'apps' => AppRegistry::all(),
            'transactions' => $transactions->items(),
            'totalCount' => $transactions->total(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', 'all'),
            ],
        ]);
    }

    public function create(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $categories = FinanceCategory::where('is_active', true)->get();

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Transactions/Create', [
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
        $validated = $request->validate([
            'account_id' => 'nullable|string',
            'category_id' => 'nullable|string',
            'type' => 'required|in:income,expense,transfer,savings',
            'amount_minor' => 'required|integer|min:1',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'merchant_name' => 'nullable|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'images' => 'nullable|array',
        ]);

        if (empty($validated['account_id'])) {
            $defaultAccount = FinanceAccount::first();
            $validated['account_id'] = $defaultAccount?->id;
        }

        $items = $request->input('items', []);
        $images = $request->input('images');

        $this->transactionService->createTransaction($validated, $items, $images);

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;
        return redirect("/t/{$slug}/apps/pekan/transactions")
            ->with('status', 'Transaksi berhasil disimpan.');
    }

    public function show(Request $request, ?string $tenantSlug = null, ?string $id = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        $resolvedId = $id ?: $request->route('id') ?: $tenantSlug;
        $transaction = FinanceTransaction::with(['category', 'items', 'attachments'])
            ->findOrFail($resolvedId);

        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Transactions/Detail', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'transaction' => $transaction,
        ]);
    }

    public function edit(Request $request, ?string $tenantSlug = null, ?string $id = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();
        $resolvedId = $id ?: $request->route('id') ?: $tenantSlug;
        $transaction = FinanceTransaction::with(['category', 'items'])->findOrFail($resolvedId);

        $categories = FinanceCategory::where('is_active', true)->get();
        $tenants = $user ? $user->getActiveTenants() : [];

        return Inertia::render('Pekan/Transactions/Edit', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'categories' => $categories,
            'transaction' => $transaction,
        ]);
    }

    public function update(Request $request, ?string $tenantSlug = null, ?string $id = null): RedirectResponse
    {
        $resolvedId = $id ?: $request->route('id') ?: $tenantSlug;
        $transaction = FinanceTransaction::findOrFail($resolvedId);

        $validated = $request->validate([
            'category_id' => 'nullable|string',
            'type' => 'required|in:income,expense,transfer,savings',
            'amount_minor' => 'required|integer|min:1',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'merchant_name' => 'nullable|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        $items = $request->input('items', []);
        $this->transactionService->updateTransaction($transaction, $validated, $items);

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug ?: session('current_tenant_slug');
        return redirect("/t/{$slug}/apps/pekan/transactions/{$transaction->id}")
            ->with('status', 'Transaksi berhasil diperbarui.');
    }

    public function destroy(Request $request, ?string $tenantSlug = null, ?string $id = null): RedirectResponse
    {
        $resolvedId = $id ?: $request->route('id') ?: $tenantSlug;
        $transaction = FinanceTransaction::findOrFail($resolvedId);
        $tenantId = $transaction->tenant_id;
        $transaction->delete();

        $this->transactionService->syncActiveBudgets($tenantId);

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug ?: session('current_tenant_slug');
        return redirect("/t/{$slug}/apps/pekan/transactions")
            ->with('status', 'Transaksi berhasil dihapus.');
    }
}

