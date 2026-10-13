<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceSavings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $savings = FinanceSavings::where('status', 'active')->get();

        return Inertia::render('Pekan/Savings/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
            'savings' => $savings,
        ]);
    }
}
