<?php

namespace App\Core\Notifications\Services;

use App\Core\AI\Models\AiConversation;
use App\Core\AI\Services\TenantAssistantService;
use App\Core\Identity\Models\ExternalChatPairing;
use App\Core\Identity\Models\ExternalChatSession;
use App\Core\Identity\Models\SystemSetting;
use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalChatGatewayService
{
    protected TenantAssistantService $assistantService;

    public function __construct(TenantAssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    /**
     * Buat kode hubung (pairing code) 6 digit sekali pakai (berlaku 15 menit).
     */
    public function generatePairingCode(User $user, Tenant $tenant, string $channel = 'any'): ExternalChatPairing
    {
        // Bersihkan kode usang yang sudah kedaluwarsa milik pengguna
        ExternalChatPairing::where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('expires_at', '<', now())
                    ->orWhereNotNull('used_at');
            })
            ->delete();

        // Buat kode 6 digit numerik unik berformat RD-XXXXXX
        do {
            $digits = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $code = "RD-{$digits}";
        } while (ExternalChatPairing::where('code', $code)->exists());

        return ExternalChatPairing::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'code' => $code,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * Tangani pesan masuk dari WhatsApp (WAHA) atau Telegram Bot.
     */
    public function handleIncomingMessage(
        string $channel,
        string $channelUserId,
        string $messageText,
        ?string $senderName = null
    ): ?string {
        $trimmedText = trim($messageText);
        if (empty($trimmedText)) {
            return null;
        }

        $channelLabel = $channel === 'whatsapp' ? 'WhatsApp' : 'Telegram';

        // 1. Cari sesi tautan yang sudah aktif untuk nomor/chat ID ini
        $session = ExternalChatSession::with(['user', 'tenant'])
            ->where('channel', $channel)
            ->where('channel_user_id', $channelUserId)
            ->first();

        // 2. Jika belum memiliki sesi aktif, periksa apakah pesan adalah kode pairing
        if (!$session) {
            return $this->handleUnauthenticatedMessage($channel, $channelUserId, $trimmedText, $senderName);
        }

        // 3. Sesi ditemukan: Verifikasi apakah akun pengguna masih aktif
        if (!$session->user || !$session->user->is_active) {
            $session->delete();
            return "[RUDIMAH] Akses Ditolak\n\nAkun Anda telah dinonaktifkan oleh administrator. Tautan chat ini otomatis dihentikan.";
        }

        // Perbarui stempel waktu interaksi terakhir
        $session->update(['last_interaction_at' => now()]);

        // Tetapkan konteks runtime pengguna dan tenant aktif
        auth()->setUser($session->user);
        TenantContext::set($session->tenant);

        // 4. Periksa apakah pesan merupakan perintah bawaan (Built-in Commands)
        $lowerText = strtolower($trimmedText);
        $commandParts = explode(' ', $trimmedText, 2);
        $command = strtolower($commandParts[0]);
        $argument = isset($commandParts[1]) ? trim($commandParts[1]) : '';

        if ($command === '/help' || $command === '/bantuan') {
            return $this->buildHelpMessage($session);
        }

        if ($command === '/status' || $command === '/whoami') {
            return $this->buildStatusMessage($session, $channelLabel);
        }

        if ($command === '/tenant' || $command === '/switch') {
            return $this->handleTenantSwitch($session, $argument);
        }

        if ($command === '/logout' || $command === '/putus' || $command === '/disconnect') {
            $session->delete();
            return "[RUDIMAH] Tautan Berhasil Diputuskan\n\nNomor/Akun {$channelLabel} Anda tidak lagi terhubung ke akun {$session->user->full_name}. Untuk menghubungkan kembali di lain waktu, buat kode hubung baru melalui menu Profil di web aplikasi RUDIMAH.";
        }

        // 5. Pesan Normal: Teruskan ke AI Assistant RUDIMAH
        return $this->processAssistantMessage($session, $trimmedText);
    }

    /**
     * Tangani pesan dari pengirim yang belum terotentikasi.
     */
    protected function handleUnauthenticatedMessage(
        string $channel,
        string $channelUserId,
        string $text,
        ?string $senderName
    ): string {
        $channelLabel = $channel === 'whatsapp' ? 'WhatsApp' : 'Telegram';

        // Normalisasi teks untuk deteksi kode pairing (contoh: "RD-123456" atau "123456" atau "rd 123456")
        $normalizedCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $text));
        if (str_starts_with($normalizedCode, 'RD') && strlen($normalizedCode) === 8) {
            $searchCode = 'RD-' . substr($normalizedCode, 2);
        } elseif (strlen($normalizedCode) === 6 && ctype_digit($normalizedCode)) {
            $searchCode = "RD-{$normalizedCode}";
        } else {
            $searchCode = strtoupper(trim($text));
        }

        $pairing = ExternalChatPairing::with(['user', 'tenant'])
            ->where('code', $searchCode)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pairing && $pairing->user && $pairing->tenant) {
            // Tandai kode pairing sudah digunakan
            $pairing->update(['used_at' => now()]);

            // Hapus sesi lama jika ada konflik
            ExternalChatSession::where('channel', $channel)
                ->where('channel_user_id', $channelUserId)
                ->delete();

            // Buat sesi aktif baru
            $newSession = ExternalChatSession::create([
                'id' => (string) Str::uuid(),
                'channel' => $channel,
                'channel_user_id' => $channelUserId,
                'user_id' => $pairing->user_id,
                'tenant_id' => $pairing->tenant_id,
                'metadata' => [
                    'sender_name' => $senderName,
                    'paired_at' => now()->toIso8601String(),
                ],
                'last_interaction_at' => now(),
            ]);

            return "[RUDIMAH] Tautan Berhasil Terhubung!\n\n"
                . "Halo, {$pairing->user->full_name}.\n"
                . "Nomor {$channelLabel} ini telah berhasil dihubungkan ke Ruang Kerja: {$pairing->tenant->name}.\n\n"
                . "Asisten Cerdas RUDIMAH siap membantu Anda:\n"
                . "- Kirimkan catatan transaksi, kuitansi, atau pertanyaan keuangan harian.\n"
                . "- Ketik /status untuk memeriksa ruang kerja yang aktif.\n"
                . "- Ketik /tenant untuk melihat daftar atau berpindah ruang kerja.\n"
                . "- Ketik /logout untuk memutuskan tautan chat ini kapan saja.";
        }

        // Jika bukan kode yang valid, berikan panduan otentikasi
        return "[RUDIMAH] Akun Belum Terhubung\n\n"
            . "Nomor/Akun {$channelLabel} ini belum dihubungkan ke akun RUDIMAH manapun.\n\n"
            . "Cara Menghubungkan Akun:\n"
            . "1. Buka peramban dan login ke web RUDIMAH.\n"
            . "2. Buka menu Profil > Integrasi Chat (WhatsApp & Telegram).\n"
            . "3. Pilih Ruang Kerja Anda, lalu klik 'Buat Kode Hubung'.\n"
            . "4. Balas obrolan ini dengan kode yang muncul (contoh: RD-123456) dalam waktu 15 menit.";
    }

    /**
     * Proses pesan melalui TenantAssistantService.
     */
    protected function processAssistantMessage(ExternalChatSession $session, string $userMessageText): string
    {
        try {
            // Ambil percakapan AI aktif untuk sesi ini, atau buat baru
            $conversation = null;
            if ($session->conversation_id) {
                $conversation = AiConversation::where('id', $session->conversation_id)
                    ->where('tenant_id', $session->tenant_id)
                    ->where('user_id', $session->user_id)
                    ->first();
            }

            if (!$conversation) {
                $channelTitle = $session->channel === 'whatsapp' ? 'WhatsApp' : 'Telegram';
                $conversation = $this->assistantService->startConversation("Asisten {$channelTitle}");
                $session->update(['conversation_id' => $conversation->id]);
            }

            // Kirim pesan ke asisten AI
            $responseMessage = $this->assistantService->sendMessage($conversation, $userMessageText);

            return $responseMessage->content;
        } catch (\Throwable $e) {
            Log::error("[ExternalChatGateway] Gagal memproses pesan AI: " . $e->getMessage(), [
                'session_id' => $session->id,
                'channel' => $session->channel,
            ]);

            return "Maaf, terjadi kendala saat memproses permintaan Anda melalui Asisten AI: " . $e->getMessage();
        }
    }

    /**
     * Tangani perintah /tenant atau /switch untuk melihat dan mengganti ruang kerja.
     */
    protected function handleTenantSwitch(ExternalChatSession $session, string $argument): string
    {
        $memberships = $session->user->memberships()
            ->with('tenant')
            ->get()
            ->filter(fn ($m) => $m->tenant && $m->status === 'active' && $m->tenant->status === 'active')
            ->values();

        if ($memberships->isEmpty()) {
            return "[RUDIMAH] Anda belum terdaftar pada ruang kerja aktif manapun.";
        }

        // Jika ada argumen (nomor urut atau slug tenant)
        if (!empty($argument)) {
            $targetTenant = null;

            if (is_numeric($argument)) {
                $index = ((int) $argument) - 1;
                $targetTenant = $memberships->get($index)?->tenant;
            } else {
                $slug = strtolower($argument);
                $targetTenant = $memberships->first(fn ($m) => strtolower($m->tenant->slug) === $slug || strtolower($m->tenant->name) === $slug)?->tenant;
            }

            if ($targetTenant) {
                $session->update([
                    'tenant_id' => $targetTenant->id,
                    'conversation_id' => null, // Reset percakapan agar terisolasi di tenant baru
                ]);

                return "[RUDIMAH] Ruang Kerja Dialihkan\n\n"
                    . "Ruang kerja aktif Anda kini: {$targetTenant->name} ({$targetTenant->slug}).\n"
                    . "Seluruh pencatatan dan perintah AI berikutnya akan diterapkan pada ruang kerja ini.";
            }

            return "[RUDIMAH] Ruang kerja '{$argument}' tidak ditemukan dalam daftar ruang kerja Anda.\nKetik /tenant untuk melihat daftar pilihan.";
        }

        // Tampilkan daftar ruang kerja
        $list = "[RUDIMAH] Daftar Ruang Kerja Anda:\n\n";
        foreach ($memberships as $idx => $m) {
            $num = $idx + 1;
            $isCurrent = ($m->tenant_id === $session->tenant_id) ? " [Sedang Aktif]" : "";
            $list .= "{$num}. {$m->tenant->name} ({$m->tenant->slug}){$isCurrent}\n";
        }

        $list .= "\nUntuk beralih ke ruang kerja lain, balas dengan:\n"
            . "/switch <nomor>\n"
            . "Contoh: /switch 2";

        return $list;
    }

    /**
     * Format informasi status akun dan tenant.
     */
    protected function buildStatusMessage(ExternalChatSession $session, string $channelLabel): string
    {
        return "[RUDIMAH] Status Sesi Terhubung\n\n"
            . "Pengguna: {$session->user->full_name}\n"
            . "Email: {$session->user->email}\n"
            . "Ruang Kerja Aktif: {$session->tenant->name} ({$session->tenant->slug})\n"
            . "Kanal Integrasi: {$channelLabel}\n"
            . "Interaksi Terakhir: " . ($session->last_interaction_at ? $session->last_interaction_at->format('d M Y H:i') : 'Baru saja') . "\n\n"
            . "Perintah Navigasi:\n"
            . "- /tenant : Lihat & ganti ruang kerja\n"
            . "- /help : Bantuan perintah\n"
            . "- /logout : Putuskan koneksi chat ini";
    }

    /**
     * Format panduan bantuan.
     */
    protected function buildHelpMessage(ExternalChatSession $session): string
    {
        return "[RUDIMAH] Panduan Asisten Chat\n\n"
            . "Ruang Kerja Saat Ini: {$session->tenant->name}\n\n"
            . "Perintah Sistem:\n"
            . "- /status : Cek info akun & ruang kerja yang sedang aktif\n"
            . "- /tenant : Tampilkan daftar ruang kerja Anda\n"
            . "- /switch <nomor> : Beralih ke ruang kerja lain\n"
            . "- /logout : Putuskan tautan chat ini dari akun Anda\n\n"
            . "Penggunaan AI Assistant:\n"
            . "Ketik secara bebas dalam Bahasa Indonesia untuk mencatat pengeluaran, pemasukan, atau mengecek informasi keuangan.\n"
            . "Contoh:\n"
            . "'Beli bensin pertalite 2.5 liter total 25 ribu di SPBU Pertamina'\n"
            . "'Sarapan lontong sayur 15 ribu pakai kas tunai'";
    }

    /**
     * Kirimkan balasan pesan keluar ke WhatsApp (WAHA) atau Telegram Bot.
     */
    public function sendReply(string $channel, string $channelUserId, string $text): bool
    {
        try {
            if ($channel === 'telegram') {
                return $this->sendTelegramText($channelUserId, $text);
            }

            if ($channel === 'whatsapp') {
                return $this->sendWhatsAppText($channelUserId, $text);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("[ExternalChatGateway] Gagal mengirim balasan ke {$channel}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim pesan ke Telegram Bot API.
     */
    protected function sendTelegramText(string $chatId, string $text): bool
    {
        $token = SystemSetting::get('telegram_bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        if (empty($token)) {
            Log::warning("[ExternalChatGateway] Token bot Telegram belum dikonfigurasi.");
            return false;
        }

        $res = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return $res->successful() && ($res->json('ok') ?? false);
    }

    /**
     * Kirim pesan ke WAHA (WhatsApp HTTP API).
     */
    protected function sendWhatsAppText(string $chatId, string $text): bool
    {
        $url = rtrim(SystemSetting::get('waha_api_url', env('WAHA_API_URL', env('WHATSAPP_API_URL', ''))), '/');
        $apiKey = SystemSetting::get('waha_api_key', env('WAHA_API_KEY', env('WHATSAPP_API_KEY', '')));
        $session = SystemSetting::get('waha_session', env('WAHA_SESSION', 'default'));

        if (empty($url)) {
            Log::warning("[ExternalChatGateway] URL endpoint WAHA belum dikonfigurasi.");
            return false;
        }

        $client = Http::timeout(10);
        if (!empty($apiKey)) {
            $client = $client->withHeaders(['X-Api-Key' => $apiKey]);
        }

        $res = $client->post("{$url}/api/sendText", [
            'session' => $session,
            'chatId' => $chatId,
            'text' => $text,
        ]);

        return $res->successful();
    }
}
