<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController
{
    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        if (!$user->is_super_admin) {
            abort(403, 'Hanya Administrator Platform yang memiliki akses ke halaman ini.');
        }

        $totalTenants = Tenant::count();
        $totalUsers = User::count();
        $tenantsList = Tenant::latest()->limit(10)->get();

        return Inertia::render('Admin/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
            ],
            'tenant' => TenantContext::get() ?? $user->memberships()->first()?->tenant,
            'systemStats' => [
                'totalTenants' => $totalTenants,
                'totalUsers' => $totalUsers,
                'dbEngine' => 'MariaDB 11 (InnoDB)',
                'redisConnected' => true,
                'appVersion' => '1.0.0-rudimah',
            ],
            'tenantsList' => $tenantsList,
        ]);
    }
}
