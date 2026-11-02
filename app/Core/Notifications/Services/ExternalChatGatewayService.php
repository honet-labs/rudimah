<?php

namespace App\Core\Notifications\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Services\PlatformAiService;
use App\Core\AI\Services\TenantAssistantService;
use App\Core\Identity\Models\ExternalChatPairing;
use App\Core\Identity\Models\ExternalChatSession;
use App\Core\Identity\Models\SystemSetting;
use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Services\TransactionService;
use App\Shared\Helpers\MoneyHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalChatGatewayService
{
    protected TenantAssistantService $assistantService;
    protected ?TransactionService $transactionService;
    protected ?AiProviderManager $providerManager;

    public function __construct(
        TenantAssistantService $assistantService,
        ?TransactionService $transactionService = null,
        ?AiProviderManager $providerManager = null
    ) {
        $this->assistantService = $assistantService;
        $this->transactionService = $transactionService ?: app(TransactionService::class);
        $this->providerManager = $providerManager ?: app(AiProviderManager::class);
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

            return $this->formatChatAssistantResponse($responseMessage);
        } catch (\Throwable $e) {
            Log::error("[ExternalChatGateway] Gagal memproses pesan AI: " . $e->getMessage(), [
                'session_id' => $session->id,
                'channel' => $session->channel,
            ]);

            return "Maaf, terjadi kendala saat memproses permintaan Anda melalui Asisten AI: " . $e->getMessage();
        }
    }

    /**
     * Tangani pesan media gambar (kuitansi/struk belanja) dari WhatsApp atau Telegram.
     */
    public function handleIncomingMedia(
        string $channel,
        string $channelUserId,
        string $fileBase64,
        string $mimeType,
        ?string $caption = null,
        ?string $senderName = null
    ): ?string {
        $channelLabel = $channel === 'whatsapp' ? 'WhatsApp' : 'Telegram';

        // 1. Cari sesi tautan yang sudah aktif
        $session = ExternalChatSession::with(['user', 'tenant'])
            ->where('channel', $channel)
            ->where('channel_user_id', $channelUserId)
            ->first();

        if (!$session) {
            return $this->handleUnauthenticatedMessage($channel, $channelUserId, '', $senderName);
        }

        if (!$session->user || !$session->user->is_active) {
            $session->delete();
            return "[RUDIMAH] Akses Ditolak\n\nAkun Anda telah dinonaktifkan oleh administrator.";
        }

        $session->update(['last_interaction_at' => now()]);
        auth()->setUser($session->user);
        TenantContext::set($session->tenant);

        // 2. Ekstraksi OCR Struk melalui AI Vision Provider aktif
        try {
            PlatformAiService::applyRuntimeConfig();
            $platformSettings = PlatformAiService::getSettings();
            $activePlatformProvider = $platformSettings['active_provider'] ?? config('rudimah.ai.default_provider', 'gemini');
            $activeDetails = $platformSettings['providers'][$activePlatformProvider] ?? [];
            $modelName = $activeDetails['model'] ?? '';
            $endpoint = $activeDetails['api_endpoint'] ?? null;

            $provider = $this->providerManager->resolve($activePlatformProvider);
            $extracted = $provider->extractReceipt($fileBase64, $mimeType, [
                'model' => $modelName,
                'api_endpoint' => $endpoint,
            ]);
        } catch (\Throwable $e) {
            Log::error("[ExternalChatGateway] Gagal OCR dari {$channel}: " . $e->getMessage());
            return "Maaf, pemindaian struk gagal: " . $e->getMessage() . "\n\nPastikan foto struk terlihat jelas, tidak buram, dan teks kuitansi terbaca.";
        }

        if (!is_array($extracted) || empty($extracted)) {
            return "Maaf, sistem tidak dapat menemukan rincian transaksi belanja yang valid dari foto struk ini.";
        }

        // 3. Catat transaksi pengeluaran ke buku kas ruang kerja
        try {
            $totalMinor = (int) ($extracted['total_minor'] ?? 0);
            $merchantName = $extracted['merchant_name'] ?? 'Toko';
            $date = !empty($extracted['transaction_date']) ? $extracted['transaction_date'] : now()->toDateString();
            $receiptNo = $extracted['receipt_number'] ?? null;

            $items = [];
            if (!empty($extracted['items']) && is_array($extracted['items'])) {
                foreach ($extracted['items'] as $it) {
                    $items[] = [
                        'item_name' => $it['item_name'] ?? 'Barang',
                        'quantity' => $it['quantity'] ?? 1,
                        'price_minor' => (int) ($it['price_minor'] ?? 0),
                        'total_minor' => (int) ($it['total_minor'] ?? 0),
                        'discount_minor' => 0,
                    ];
                }
            }

            $desc = "Belanja di {$merchantName}";
            if (!empty($caption)) {
                $desc .= " (" . trim($caption) . ")";
            }

            $this->transactionService->createTransaction([
                'type' => 'expense',
                'amount_minor' => $totalMinor,
                'merchant_name' => $merchantName,
                'receipt_number' => $receiptNo,
                'transaction_date' => $date,
                'description' => $desc,
                'notes' => "Dipindai dan dicatat otomatis via {$channelLabel}",
            ], $items);

            $totalFormatted = MoneyHelper::formatIdr($totalMinor);
            $itemLines = [];
            foreach ($items as $it) {
                $pStr = MoneyHelper::formatIdr((int) $it['price_minor']);
                $tStr = MoneyHelper::formatIdr((int) $it['total_minor']);
                $itemLines[] = "• {$it['item_name']} ({$it['quantity']}x @ {$pStr}): {$tStr}";
            }

            $res = "[RUDIMAH - Hasil Pindai Struk]\n\n"
                . "Foto struk berhasil dipindai dan dicatat ke pengeluaran ruang kerja {$session->tenant->name}:\n\n"
                . "Tempat: *{$merchantName}*\n"
                . "Tanggal: {$date}\n"
                . "Total Belanja: *{$totalFormatted}*\n";

            if (!empty($receiptNo)) {
                $res .= "No. Struk: {$receiptNo}\n";
            }

            if (!empty($itemLines)) {
                $res .= "\nRincian Barang:\n" . implode("\n", $itemLines) . "\n";
            }

            $res .= "\nTransaksi telah otomatis tersimpan ke buku kas.";
            return $res;
        } catch (\Throwable $e) {
            Log::error("[ExternalChatGateway] Gagal mencatat transaksi dari OCR {$channel}: " . $e->getMessage());
            return "Struk berhasil diekstraksi, namun terjadi kendala saat menyimpan transaksi: " . $e->getMessage();
        }
    }

    /**
     * Memformat balasan asisten AI agar mencantumkan rincian aksi (transaksi, tagihan, tabungan)
     * yang serupa dengan kartu tampilan pada antarmuka web.
     */
    protected function formatChatAssistantResponse($responseMessage): string
    {
        $content = trim($responseMessage->content);
        $metadata = $responseMessage->metadata ?? [];
        $extraBlocks = [];

        // 1. Rincian Transaksi yang Dicatat/Diperbarui/Dihapus
        if (!empty($metadata['recorded_transactions'])) {
            $txLines = [];
            foreach ($metadata['recorded_transactions'] as $tx) {
                $actLabel = match ($tx['action'] ?? 'created') {
                    'updated' => '[Diperbarui]',
                    'deleted' => '[Dihapus]',
                    default => ($tx['type'] ?? 'expense') === 'income' ? '[Pemasukan]' : '[Pengeluaran]',
                };
                $amtStr = MoneyHelper::formatIdr((int) ($tx['amount_minor'] ?? 0));
                $desc = $tx['description'] ?? 'Transaksi';
                $dateStr = !empty($tx['date']) ? " ({$tx['date']})" : '';
                $txLines[] = "• {$actLabel} {$desc}: *{$amtStr}*{$dateStr}";
            }
            if (!empty($txLines)) {
                $extraBlocks[] = "*Rincian Transaksi:*\n" . implode("\n", $txLines);
            }
        }

        // 2. Rincian Jadwal Tagihan
        if (!empty($metadata['recorded_reminders'])) {
            $remLines = [];
            foreach ($metadata['recorded_reminders'] as $rem) {
                $actLabel = match ($rem['action'] ?? 'created') {
                    'updated' => '[Diperbarui]',
                    'deleted' => '[Dihapus]',
                    default => '[Jadwal Baru]',
                };
                $amtStr = MoneyHelper::formatIdr((int) ($rem['amount_minor'] ?? 0));
                $title = $rem['title'] ?? 'Tagihan';
                $intervalStr = match ($rem['repeat_interval'] ?? 'monthly') {
                    'monthly' => 'Bulanan',
                    'weekly' => 'Mingguan',
                    'yearly' => 'Tahunan',
                    'daily' => 'Harian',
                    default => 'Sekali',
                };
                $dueStr = !empty($rem['due_date']) ? "Jatuh tempo: {$rem['due_date']}" : '';
                $remLines[] = "• {$actLabel} {$title}: *{$amtStr}* ({$intervalStr}, {$dueStr})";
            }
            if (!empty($remLines)) {
                $extraBlocks[] = "*Jadwal Tagihan & Pengingat:*\n" . implode("\n", $remLines);
            }
        }

        // 3. Rincian Tabungan
        if (!empty($metadata['recorded_savings'])) {
            $savLines = [];
            foreach ($metadata['recorded_savings'] as $sav) {
                $name = $sav['name'] ?? 'Tabungan';
                if (($sav['action'] ?? '') === 'deposited') {
                    $amtStr = MoneyHelper::formatIdr((int) ($sav['amount_minor'] ?? 0));
                    $currStr = MoneyHelper::formatIdr((int) ($sav['current_amount_minor'] ?? 0));
                    $targetStr = MoneyHelper::formatIdr((int) ($sav['target_amount_minor'] ?? 0));
                    $pct = $sav['progress_percent'] ?? 0;
                    $savLines[] = "• [Setoran] {$name}: *+{$amtStr}* (Terkumpul: {$currStr} / {$targetStr} - {$pct}%)";
                } elseif (($sav['action'] ?? '') === 'withdrawn') {
                    $amtStr = MoneyHelper::formatIdr((int) ($sav['amount_minor'] ?? 0));
                    $currStr = MoneyHelper::formatIdr((int) ($sav['current_amount_minor'] ?? 0));
                    $savLines[] = "• [Penarikan] {$name}: *-{$amtStr}* (Sisa: {$currStr})";
                } elseif (($sav['action'] ?? '') === 'deleted') {
                    $savLines[] = "• [Dihapus] Pos Tabungan: {$name}";
                } else {
                    $targetStr = MoneyHelper::formatIdr((int) ($sav['target_amount_minor'] ?? 0));
                    $targetDate = !empty($sav['target_date']) ? ", Target: {$sav['target_date']}" : '';
                    $savLines[] = "• [Target Baru] {$name}: Target *{$targetStr}*{$targetDate}";
                }
            }
            if (!empty($savLines)) {
                $extraBlocks[] = "*Pos Tabungan Impian:*\n" . implode("\n", $savLines);
            }
        }

        if (!empty($extraBlocks)) {
            $content .= "\n\n" . implode("\n\n", $extraBlocks);
        }

        return $content;
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
        return "[RUDIMAH] Panduan Asisten Chat (WhatsApp & Telegram)\n\n"
            . "Ruang Kerja Saat Ini: {$session->tenant->name}\n\n"
            . "Perintah Sistem:\n"
            . "- /status : Cek info akun & ruang kerja yang sedang aktif\n"
            . "- /tenant : Tampilkan daftar ruang kerja Anda\n"
            . "- /switch <nomor> : Beralih ke ruang kerja lain\n"
            . "- /logout : Putuskan tautan chat ini dari akun Anda\n\n"
            . "Fungsi AI Assistant (Sama Persis dengan Web RUDIMAH):\n"
            . "1. Catat Transaksi: Kirim teks seperti 'Beli bensin pertamax 35rb di SPBU Pertamina' atau 'Makan siang soto 25rb'.\n"
            . "2. Pindai Struk / Kuitansi: Kirim foto kuitansi/struk belanja untuk dipindai otomatis via OCR.\n"
            . "3. Jadwal Tagihan: 'tolong masukan ini ke tagihan, Tagihan VPS everera 235rb setiap tanggal 1'.\n"
            . "4. Pos Tabungan: 'buat target tabungan Beli Laptop 15 juta target 31 Des 2026' atau 'nabung 500rb ke tabungan Beli Laptop'.\n"
            . "5. Informasi Keuangan: 'berapa saldo kas saat ini?', 'ada tagihan apa saja?', atau 'ringkasan pengeluaran bulan ini'.";
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
