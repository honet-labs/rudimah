<?php

namespace App\Modules\App1\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class App1Controller
{
    public function index(Request $request): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        return Inertia::render('App1/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->memberships()->with('tenant')->get()->pluck('tenant') : [],
            'apps' => AppRegistry::all(),
        ]);
    }
}
