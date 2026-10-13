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

        $recentTransactions = FinanceTransaction::with(['account', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $activeBudgets = FinanceBudget::with('category')
            ->where('status', 'active')
            ->get();

        $activeSavings = FinanceSavings::where('status', 'active')->get();

        return Inertia::render('Pekan/Dashboard', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
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
