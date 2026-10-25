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

            // Verifikasi otentikasi dan keanggotaan pengguna pada ruang kerja (fail-closed)
            $user = $request->user();
            if (!$user) {
                abort(401, 'Silakan masuk terlebih dahulu untuk mengakses ruang kerja ini.');
            }

            if (!$user->is_active) {
                abort(403, 'Akun pengguna Anda telah dinonaktifkan oleh Administrator.');
            }

            $membership = TenantMembership::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$membership && !$user->is_super_admin) {
                abort(403, 'Akses ditolak. Anda bukan anggota aktif dari ruang kerja ini.');
            }

            $tenant->role = $membership ? $membership->role : ($user->is_super_admin ? 'admin' : 'member');

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
