<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;

class ResolveTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = $request->route('tenantSlug') ?? $request->route('tenant');

        if ($tenantSlug) {
            $tenant = Tenant::where(function ($query) use ($tenantSlug) {
                $query->where('slug', $tenantSlug)
                    ->orWhere('code', $tenantSlug);
            })->where('status', 'active')->first();

            if (!$tenant) {
                abort(404, 'Ruang kerja tidak ditemukan atau telah dinonaktifkan.');
            }

            // Jika ada sesi pengguna, verifikasi keanggotaan tenant
            $user = $request->user();
            if ($user) {
                $membership = TenantMembership::where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();

                if (!$membership && !$user->is_super_admin) {
                    abort(403, 'Anda bukan anggota aktif dari ruang kerja ini.');
                }

                $tenant->role = $membership ? $membership->role : ($user->is_super_admin ? 'admin' : 'member');
            } else {
                $tenant->role = 'viewer';
            }

            TenantContext::set($tenant);
            session(['current_tenant_slug' => $tenant->slug]);
        }

        try {
            return $next($request);
        } finally {
            // Hindari tenant context bleeding antar request atau worker
            TenantContext::clear();
        }
    }
}
