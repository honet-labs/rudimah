<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        $categories = FinanceCategory::orderBy('category_type')
            ->orderBy('name')
            ->get();

        $accounts = FinanceAccount::orderBy('name')->get();

        return Inertia::render('Pekan/Settings/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->getActiveTenants() : [],
            'apps' => AppRegistry::all(),
            'categories' => $categories,
            'accounts' => $accounts,
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }
}
