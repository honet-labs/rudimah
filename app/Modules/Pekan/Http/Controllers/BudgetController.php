<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceBudget;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $budgets = FinanceBudget::with('category')->get();

        return Inertia::render('Pekan/Budgets/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
            'budgets' => $budgets,
        ]);
    }
}
