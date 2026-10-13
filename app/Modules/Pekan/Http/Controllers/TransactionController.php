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

        $query = FinanceTransaction::with(['account', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $transactions = $query->paginate(15);

        return Inertia::render('Pekan/Transactions/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
            'transactions' => $transactions->items(),
            'totalCount' => $transactions->total(),
        ]);
    }

    public function create(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $accounts = FinanceAccount::where('is_active', true)->get();
        $categories = FinanceCategory::where('is_active', true)->get();

        return Inertia::render('Pekan/Transactions/Create', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|string',
            'category_id' => 'nullable|string',
            'type' => 'required|in:income,expense,transfer',
            'amount_minor' => 'required|integer|min:1',
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:255',
            'merchant_name' => 'nullable|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        $items = $request->input('items', []);

        $this->transactionService->createTransaction($validated, $items);

        $tenant = TenantContext::get();
        return redirect("/t/{$tenant->slug}/apps/pekan/transactions")
            ->with('status', 'Transaksi berhasil disimpan.');
    }

    public function show(string $tenantSlug, string $id): Response
    {
        $tenant = TenantContext::get();
        $transaction = FinanceTransaction::with(['account', 'category', 'items', 'attachments'])
            ->findOrFail($id);

        return Inertia::render('Pekan/Transactions/Detail', [
            'user' => auth()->user(),
            'tenant' => $tenant,
            'apps' => AppRegistry::all(),
            'transaction' => $transaction,
        ]);
    }

    public function destroy(string $tenantSlug, string $id): RedirectResponse
    {
        $transaction = FinanceTransaction::findOrFail($id);
        $transaction->delete();

        $tenant = TenantContext::get();
        return redirect("/t/{$tenant->slug}/apps/pekan/transactions")
            ->with('status', 'Transaksi berhasil dihapus.');
    }
}
