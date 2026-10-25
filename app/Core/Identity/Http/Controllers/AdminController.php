<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Identity\Models\User;
use App\Core\Notifications\Services\PlatformNotificationService;
use App\Core\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
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
        $tenantsList = Tenant::withCount('memberships')->latest()->get()->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'status' => $t->status,
                'members_count' => $t->memberships_count,
                'created_at' => $t->created_at?->toDateString(),
            ];
        });

        $usersList = User::withCount('memberships')->latest()->limit(50)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'email' => $u->email,
                'is_super_admin' => (bool) $u->is_super_admin,
                'has_mfa' => (bool) $u->mfaMethods()->where('is_verified', true)->exists(),
                'workspaces_count' => $u->memberships_count,
                'created_at' => $u->created_at?->toDateString(),
            ];
        });

        $appsList = AppRegistry::all();
        $notificationSettings = PlatformNotificationService::getSettings();

        // Resolve ruang kerja aktif/default bagi admin untuk navigasi aplikasi yang valid
        $defaultTenant = null;
        $sessionTenantSlug = session('current_tenant_slug');
        if ($sessionTenantSlug) {
            $defaultTenant = Tenant::where('slug', $sessionTenantSlug)->where('status', 'active')->first();
        }

        if (!$defaultTenant) {
            $membership = $user->memberships()->where('status', 'active')->with('tenant')->first();
            if ($membership && $membership->tenant && $membership->tenant->status === 'active') {
                $defaultTenant = $membership->tenant;
            }
        }

        if (!$defaultTenant) {
            $defaultTenant = Tenant::where('status', 'active')->first();
        }

        $tenantPayload = $defaultTenant ? [
            'id' => $defaultTenant->id,
            'name' => $defaultTenant->name,
            'slug' => $defaultTenant->slug,
            'status' => $defaultTenant->status,
            'role' => 'admin',
        ] : null;

        return Inertia::render('Admin/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ],
            'tenant' => $tenantPayload,
            'status' => session('status'),
            'error' => session('error'),
            'systemStats' => [
                'totalTenants' => $totalTenants,
                'totalUsers' => $totalUsers,
                'dbEngine' => 'MariaDB 11 (InnoDB)',
                'redisConnected' => true,
                'appVersion' => '1.0.0-rudimah',
            ],
            'notificationSettings' => $notificationSettings,
            'tenantsList' => $tenantsList,
            'usersList' => $usersList,
            'appsList' => $appsList,
        ]);
    }

    /**
     * Simpan konfigurasi pengiriman notifikasi (Email SMTP, WAHA WhatsApp, Telegram).
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->is_super_admin) {
            abort(403, 'Hanya Administrator Platform yang memiliki izin mengubah konfigurasi sistem.');
        }

        $validated = $request->validate([
            'mail' => 'nullable|array',
            'mail.enabled' => 'nullable|boolean',
            'mail.host' => 'nullable|string|max:255',
            'mail.port' => 'nullable|integer|min:1|max:65535',
            'mail.username' => 'nullable|string|max:255',
            'mail.password' => 'nullable|string|max:255',
            'mail.encryption' => 'nullable|string|in:tls,ssl,none',
            'mail.from_address' => 'nullable|email|max:255',
            'mail.from_name' => 'nullable|string|max:255',

            'telegram' => 'nullable|array',
            'telegram.enabled' => 'nullable|boolean',
            'telegram.bot_token' => 'nullable|string|max:255',
            'telegram.bot_username' => 'nullable|string|max:255',
            'telegram.default_group' => 'nullable|string|max:255',

            'whatsapp' => 'nullable|array',
            'whatsapp.enabled' => 'nullable|boolean',
            'whatsapp.waha_api_url' => 'nullable|url|max:255',
            'whatsapp.waha_api_key' => 'nullable|string|max:255',
            'whatsapp.waha_session' => 'nullable|string|max:100',
            'whatsapp.default_group' => 'nullable|string|max:255',
        ]);

        PlatformNotificationService::saveSettings($validated);

        return back()->with('status', 'Konfigurasi notifikasi platform berhasil disimpan dan diterapkan untuk semua ruang kerja.');
    }

    /**
     * Jalankan uji coba pengiriman atau koneksi kanal notifikasi terpilih.
     */
    public function testNotification(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->is_super_admin) {
            abort(403, 'Hanya Administrator Platform yang memiliki izin menguji kanal notifikasi.');
        }

        $validated = $request->validate([
            'channel' => 'required|in:mail,telegram,whatsapp',
            'target' => 'nullable|string|max:255',
        ]);

        $channel = $validated['channel'];
        $target = $validated['target'] ?? null;

        if ($channel === 'mail') {
            $recipient = !empty($target) ? trim($target) : $user->email;
            $res = PlatformNotificationService::testEmail($recipient);
        } elseif ($channel === 'telegram') {
            $res = PlatformNotificationService::testTelegram($target);
        } else {
            $res = PlatformNotificationService::testWhatsApp($target);
        }

        if ($res['success']) {
            return back()->with('status', $res['message']);
        }

        return back()->with('error', $res['message']);
    }
}
