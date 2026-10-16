<?php

namespace App\Core\Notifications\Services;

use App\Core\Identity\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlatformNotificationService
{
    /**
     * Dapatkan konfigurasi seluruh kanal notifikasi (Email SMTP, WAHA, Telegram).
     * Nilai rahasia (password, token, api key) disamarkan untuk keamanan tampilan frontend.
     */
    public static function getSettings(): array
    {
        try {
            $dbSettings = SystemSetting::whereIn('group', ['mail', 'telegram', 'whatsapp'])
                ->pluck('value', 'key');
        } catch (\Throwable) {
            $dbSettings = collect();
        }

        // 1. Email (SMTP)
        $mailHost = $dbSettings->get('mail_host', env('MAIL_HOST', ''));
        $mailPort = (int) $dbSettings->get('mail_port', env('MAIL_PORT', 587));
        $mailUsername = $dbSettings->get('mail_username', env('MAIL_USERNAME', ''));
        $mailRawPassword = $dbSettings->get('mail_password', env('MAIL_PASSWORD', ''));
        $mailEncryption = $dbSettings->get('mail_encryption', env('MAIL_ENCRYPTION', 'tls'));
        $mailFromAddress = $dbSettings->get('mail_from_address', env('MAIL_FROM_ADDRESS', 'noreply@rudimah.id'));
        $mailFromName = $dbSettings->get('mail_from_name', env('MAIL_FROM_NAME', 'RUDIMAH Notifikasi'));
        $mailEnabled = filter_var($dbSettings->get('mail_enabled', !empty($mailHost) && env('MAIL_MAILER', 'smtp') !== 'log'), FILTER_VALIDATE_BOOLEAN);

        // 2. Telegram Bot
        $telegramRawToken = $dbSettings->get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $telegramUsername = $dbSettings->get('telegram_bot_username', env('TELEGRAM_BOT_USERNAME', ''));
        $telegramEnabled = filter_var($dbSettings->get('telegram_enabled', !empty($telegramRawToken)), FILTER_VALIDATE_BOOLEAN);

        // 3. WhatsApp (WAHA)
        $wahaApiUrl = $dbSettings->get('waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL', '')));
        $wahaRawApiKey = $dbSettings->get('waha_api_key', env('WAHA_API_KEY', env('WHATSAPP_API_KEY', '')));
        $wahaSession = $dbSettings->get('waha_session', env('WAHA_SESSION', 'default'));
        $wahaEnabled = filter_var($dbSettings->get('waha_enabled', !empty($wahaApiUrl)), FILTER_VALIDATE_BOOLEAN);

        return [
            'mail' => [
                'enabled' => $mailEnabled,
                'host' => $mailHost,
                'port' => $mailPort ?: 587,
                'username' => $mailUsername,
                'password' => '', // Jangan kirim plaintext ke browser
                'has_password' => !empty($mailRawPassword),
                'encryption' => $mailEncryption ?: 'tls',
                'from_address' => $mailFromAddress ?: 'noreply@rudimah.id',
                'from_name' => $mailFromName ?: 'RUDIMAH Notifikasi',
                'is_configured' => !empty($mailHost) && !empty($mailFromAddress),
            ],
            'telegram' => [
                'enabled' => $telegramEnabled,
                'bot_token' => '', // Jangan kirim plaintext ke browser
                'has_bot_token' => !empty($telegramRawToken),
                'bot_username' => $telegramUsername,
                'is_configured' => !empty($telegramRawToken),
            ],
            'whatsapp' => [
                'enabled' => $wahaEnabled,
                'waha_api_url' => $wahaApiUrl,
                'waha_api_key' => '', // Jangan kirim plaintext ke browser
                'has_api_key' => !empty($wahaRawApiKey),
                'waha_session' => $wahaSession ?: 'default',
                'is_configured' => !empty($wahaApiUrl),
            ],
        ];
    }

    /**
     * Simpan konfigurasi notifikasi dari form admin.
     */
    public static function saveSettings(array $data): void
    {
        // 1. Simpan Mail Settings
        if (isset($data['mail']) && is_array($data['mail'])) {
            $m = $data['mail'];
            SystemSetting::set('mail_enabled', !empty($m['enabled']) ? '1' : '0', 'mail');
            SystemSetting::set('mail_host', trim($m['host'] ?? ''), 'mail');
            SystemSetting::set('mail_port', (string) ((int) ($m['port'] ?? 587)), 'mail');
            SystemSetting::set('mail_username', trim($m['username'] ?? ''), 'mail');
            SystemSetting::set('mail_encryption', trim($m['encryption'] ?? 'tls'), 'mail');
            SystemSetting::set('mail_from_address', trim($m['from_address'] ?? 'noreply@rudimah.id'), 'mail');
            SystemSetting::set('mail_from_name', trim($m['from_name'] ?? 'RUDIMAH Notifikasi'), 'mail');

            // Simpan password hanya jika diisi dan bukan placeholder masking
            if (!empty($m['password']) && $m['password'] !== '******') {
                SystemSetting::set('mail_password', $m['password'], 'mail', true);
            }
        }

        // 2. Simpan Telegram Settings
        if (isset($data['telegram']) && is_array($data['telegram'])) {
            $t = $data['telegram'];
            SystemSetting::set('telegram_enabled', !empty($t['enabled']) ? '1' : '0', 'telegram');
            SystemSetting::set('telegram_bot_username', trim($t['bot_username'] ?? ''), 'telegram');

            if (!empty($t['bot_token']) && $t['bot_token'] !== '******') {
                SystemSetting::set('telegram_bot_token', trim($t['bot_token']), 'telegram', true);
            }
        }

        // 3. Simpan WhatsApp (WAHA) Settings
        if (isset($data['whatsapp']) && is_array($data['whatsapp'])) {
            $w = $data['whatsapp'];
            SystemSetting::set('waha_enabled', !empty($w['enabled']) ? '1' : '0', 'whatsapp');
            SystemSetting::set('waha_api_url', rtrim(trim($w['waha_api_url'] ?? ''), '/'), 'whatsapp');
            SystemSetting::set('waha_session', trim($w['waha_session'] ?? 'default'), 'whatsapp');

            if (!empty($w['waha_api_key']) && $w['waha_api_key'] !== '******') {
                SystemSetting::set('waha_api_key', trim($w['waha_api_key']), 'whatsapp', true);
            }
        }

        // Terapkan ke konfigurasi runtime aplikasi
        static::applyRuntimeConfig();
    }

    /**
     * Terapkan konfigurasi basis data ke dalam runtime Laravel (config()).
     */
    public static function applyRuntimeConfig(): void
    {
        try {
            $settings = SystemSetting::whereIn('group', ['mail', 'telegram', 'whatsapp'])
                ->pluck('value', 'key');

            $mailHost = $settings->get('mail_host', env('MAIL_HOST', ''));
            $mailPort = (int) $settings->get('mail_port', env('MAIL_PORT', 587));
            $mailUsername = $settings->get('mail_username', env('MAIL_USERNAME', ''));
            $mailPassword = $settings->get('mail_password', env('MAIL_PASSWORD', ''));
            $mailEncryption = $settings->get('mail_encryption', env('MAIL_ENCRYPTION', 'tls'));
            $mailFromAddress = $settings->get('mail_from_address', env('MAIL_FROM_ADDRESS', 'noreply@rudimah.id'));
            $mailFromName = $settings->get('mail_from_name', env('MAIL_FROM_NAME', 'RUDIMAH Notifikasi'));
            $mailEnabled = filter_var($settings->get('mail_enabled', !empty($mailHost) && env('MAIL_MAILER', 'smtp') !== 'log'), FILTER_VALIDATE_BOOLEAN);

            $telegramToken = $settings->get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
            $telegramUsername = $settings->get('telegram_bot_username', env('TELEGRAM_BOT_USERNAME', ''));
            $telegramEnabled = filter_var($settings->get('telegram_enabled', !empty($telegramToken)), FILTER_VALIDATE_BOOLEAN);

            $wahaUrl = $settings->get('waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL', '')));
            $wahaKey = $settings->get('waha_api_key', env('WAHA_API_KEY', env('WHATSAPP_API_KEY', '')));
            $wahaSession = $settings->get('waha_session', env('WAHA_SESSION', 'default'));
            $wahaEnabled = filter_var($settings->get('waha_enabled', !empty($wahaUrl)), FILTER_VALIDATE_BOOLEAN);

            if (!empty($mailHost)) {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $mailHost,
                    'mail.mailers.smtp.port' => $mailPort ?: 587,
                    'mail.mailers.smtp.encryption' => ($mailEncryption === 'none' || empty($mailEncryption)) ? null : $mailEncryption,
                    'mail.mailers.smtp.username' => $mailUsername,
                    'mail.mailers.smtp.password' => $mailPassword,
                    'mail.from.address' => $mailFromAddress ?: 'noreply@rudimah.id',
                    'mail.from.name' => $mailFromName ?: 'RUDIMAH Notifikasi',
                ]);
            }

            config([
                'rudimah.notifications.mail.enabled' => $mailEnabled,
                'rudimah.notifications.mail.host' => $mailHost,
                'rudimah.notifications.mail.port' => $mailPort,
                'rudimah.notifications.mail.from_address' => $mailFromAddress,

                'rudimah.notifications.telegram.enabled' => $telegramEnabled,
                'rudimah.notifications.telegram.bot_token' => $telegramToken,
                'rudimah.notifications.telegram.bot_username' => $telegramUsername,

                'rudimah.notifications.whatsapp.enabled' => $wahaEnabled,
                'rudimah.notifications.whatsapp.waha_api_url' => $wahaUrl,
                'rudimah.notifications.whatsapp.waha_api_key' => $wahaKey,
                'rudimah.notifications.whatsapp.waha_session' => $wahaSession,
            ]);
        } catch (\Throwable $e) {
            // Tangkap graceful saat migrasi belum dijalankan
            Log::debug('[PlatformNotificationService] Belum dapat menerapkan konfigurasi runtime: ' . $e->getMessage());
        }
    }

    /**
     * Uji coba pengiriman email via SMTP yang terkonfigurasi.
     */
    public static function testEmail(string $recipientEmail): array
    {
        static::applyRuntimeConfig();

        $mailHost = config('mail.mailers.smtp.host');
        if (empty($mailHost)) {
            return [
                'success' => false,
                'message' => 'Host SMTP (mail_host) belum diisi. Silakan simpan pengaturan terlebih dahulu.',
            ];
        }

        try {
            Mail::raw(
                "Halo,\n\nIni adalah pesan konfirmasi pengujian pengiriman email notifikasi dari Platform RUDIMAH (Ruang Digital Manajemen Harian).\n\nKanal Email (SMTP) Anda pada host {$mailHost} telah berhasil terhubung dan siap melayani seluruh ruang kerja.\n\nWaktu uji coba: " . now()->toIso8601String() . "\n\nSalam,\nTim Administrator RUDIMAH",
                function ($message) use ($recipientEmail) {
                    $message->to($recipientEmail)
                        ->subject('[RUDIMAH] Uji Coba Pengiriman Notifikasi Email Berhasil');
                }
            );

            return [
                'success' => true,
                'message' => "Email uji coba berhasil dikirim ke {$recipientEmail} melalui host {$mailHost}.",
            ];
        } catch (\Throwable $e) {
            Log::error('[RUDIMAH] Gagal uji coba kirim email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Uji coba koneksi Bot Telegram menggunakan endpoint getMe.
     */
    public static function testTelegram(): array
    {
        $token = SystemSetting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Token Bot Telegram belum dikonfigurasi.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok'] ?? false) {
                    $username = $data['result']['username'] ?? 'unknown';
                    $botName = $data['result']['first_name'] ?? 'Bot';
                    return [
                        'success' => true,
                        'message' => "Koneksi Telegram Berhasil! Terhubung ke bot @{$username} ({$botName}).",
                    ];
                }
            }

            $desc = $response->json('description') ?? $response->body();
            return [
                'success' => false,
                'message' => "Telegram menolak token bot: {$desc}",
            ];
        } catch (\Throwable $e) {
            Log::error('[RUDIMAH] Gagal uji koneksi Telegram: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal menghubungi server Telegram: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Uji coba koneksi WhatsApp WAHA endpoint.
     */
    public static function testWhatsApp(?string $targetPhone = null): array
    {
        $url = rtrim(SystemSetting::get('waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL', ''))), '/');
        $apiKey = SystemSetting::get('waha_api_key', env('WAHA_API_KEY', env('WHATSAPP_API_KEY', '')));
        $session = SystemSetting::get('waha_session', env('WAHA_SESSION', 'default'));

        if (empty($url)) {
            return [
                'success' => false,
                'message' => 'Alamat URL Endpoint WAHA belum dikonfigurasi.',
            ];
        }

        try {
            $client = Http::timeout(10);
            if (!empty($apiKey)) {
                $client = $client->withHeaders(['X-Api-Key' => $apiKey]);
            }

            // Jika ada targetPhone, coba kirim pesan uji coba
            if (!empty($targetPhone)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $targetPhone);
                if (str_starts_with($cleanPhone, '08')) {
                    $cleanPhone = '628' . substr($cleanPhone, 2);
                }
                $chatId = $cleanPhone . '@c.us';

                $sendRes = $client->post("{$url}/api/sendText", [
                    'session' => $session,
                    'chatId' => $chatId,
                    'text' => "[RUDIMAH] Uji Coba Pengiriman Notifikasi WhatsApp berhasil terhubung melalui sesi '{$session}'.\nWaktu: " . now()->toIso8601String(),
                ]);

                if ($sendRes->successful()) {
                    return [
                        'success' => true,
                        'message' => "Pesan uji coba WhatsApp berhasil dikirim ke {$cleanPhone}.",
                    ];
                }
            }

            // Cek endpoint sesi atau versi WAHA
            $response = $client->get("{$url}/api/sessions");

            if ($response->successful()) {
                $sessions = $response->json();
                $sessionNames = is_array($sessions) ? collect($sessions)->pluck('name')->implode(', ') : 'aktif';
                return [
                    'success' => true,
                    'message' => "Koneksi WAHA Berhasil! Server aktif di {$url}. Sesi terdeteksi: [{$sessionNames}].",
                ];
            }

            // Cek alternatif ping /api/server/version
            $versionRes = $client->get("{$url}/api/server/version");
            if ($versionRes->successful()) {
                $version = $versionRes->json('version') ?? 'Online';
                return [
                    'success' => true,
                    'message' => "Koneksi WAHA Berhasil! Server aktif versi {$version} di {$url}.",
                ];
            }

            return [
                'success' => false,
                'message' => "Server WAHA merespons kode status {$response->status()}: " . $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('[RUDIMAH] Gagal uji koneksi WAHA: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal menghubungi server WAHA: ' . $e->getMessage(),
            ];
        }
    }
}
