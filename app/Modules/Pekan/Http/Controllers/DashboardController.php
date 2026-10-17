<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceBudget;
use App\Modules\Pekan\Models\FinanceSavings;
use App\Modules\Pekan\Models\FinanceTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $incomeMinor = (int) FinanceTransaction::where('type', 'income')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount_minor');

        $expenseMinor = (int) FinanceTransaction::where('type', 'expense')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount_minor');

        $savingsMinor = (int) FinanceSavings::where('status', 'active')->sum('current_amount_minor');

        $recentTransactions = FinanceTransaction::with(['category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'category_name' => $tx->category?->name ?? 'Umum',
                    'type' => $tx->type,
                    'amount_minor' => $tx->amount_minor,
                    'currency' => $tx->currency ?? 'Rp',
                    'transaction_date' => $tx->transaction_date ? $tx->transaction_date->toDateString() : now()->toDateString(),
                    'description' => $tx->description ?? '-',
                    'created_at' => $tx->created_at ? $tx->created_at->toIso8601String() : now()->toIso8601String(),
                ];
            });

        $activeBudgets = FinanceBudget::with('category')
            ->where('status', 'active')
            ->get();

        $activeSavings = FinanceSavings::where('status', 'active')->get();

        $tenants = $user ? $user->memberships()->with('tenant')->get()->map(function ($m) {
            $t = $m->tenant;
            if ($t) {
                $t->role = $m->role;
            }
            return $t;
        })->filter()->values() : [];

        return Inertia::render('Pekan/Dashboard', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $tenants,
            'apps' => AppRegistry::all(),
            'summary' => [
                'incomeMinor' => $incomeMinor,
                'expenseMinor' => $expenseMinor,
                'netMinor' => $incomeMinor - $expenseMinor,
                'savingsMinor' => $savingsMinor,
            ],
            'recentTransactions' => $recentTransactions,
            'activeBudgets' => $activeBudgets,
            'activeSavings' => $activeSavings,
        ]);
    }
}
