<?php

namespace App\Core\Identity\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
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

        $mailConfigured = !empty(config('rudimah.notifications.mail.host', env('MAIL_HOST'))) && env('MAIL_MAILER', 'smtp') !== 'log';
        $telegramConfigured = !empty(config('rudimah.notifications.telegram.bot_token', env('TELEGRAM_BOT_TOKEN')));
        $whatsappConfigured = !empty(config('rudimah.notifications.whatsapp.waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL'))));

        return Inertia::render('Admin/Dashboard', [
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ],
            'tenant' => null,
            'systemStats' => [
                'totalTenants' => $totalTenants,
                'totalUsers' => $totalUsers,
                'dbEngine' => 'MariaDB 11 (InnoDB)',
                'redisConnected' => true,
                'appVersion' => '1.0.0-rudimah',
            ],
            'notificationChannels' => [
                'mail' => [
                    'name' => 'Email (SMTP)',
                    'configured' => $mailConfigured,
                    'detail' => $mailConfigured ? config('rudimah.notifications.mail.host', env('MAIL_HOST')) : 'Belum diatur (MAIL_HOST)',
                ],
                'telegram' => [
                    'name' => 'Telegram Bot API',
                    'configured' => $telegramConfigured,
                    'detail' => $telegramConfigured ? 'Token Terpasang' : 'Belum diatur (TELEGRAM_BOT_TOKEN)',
                ],
                'whatsapp' => [
                    'name' => 'WhatsApp (WAHA)',
                    'configured' => $whatsappConfigured,
                    'detail' => $whatsappConfigured ? 'Endpoint Terpasang' : 'Belum diatur (WAHA_API_URL)',
                ],
            ],
            'tenantsList' => $tenantsList,
            'usersList' => $usersList,
            'appsList' => $appsList,
        ]);
    }
}
