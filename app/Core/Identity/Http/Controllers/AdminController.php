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

        $mailConfigured = !empty(config('rudimah.notifications.mail.host', env('MAIL_HOST'))) && env('MAIL_MAILER', 'smtp') !== 'log';
        $telegramConfigured = !empty(config('rudimah.notifications.telegram.bot_token', env('TELEGRAM_BOT_TOKEN')));
        $whatsappConfigured = !empty(config('rudimah.notifications.whatsapp.waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL'))));

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
        ]);
    }
}
