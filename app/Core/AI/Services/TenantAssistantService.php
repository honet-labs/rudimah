<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Services\TransactionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantAssistantService
{
    protected AiProviderManager $providerManager;
    protected TenantFinancialContextBuilder $contextBuilder;
    protected TransactionService $transactionService;

    public function __construct(
        AiProviderManager $providerManager,
        TenantFinancialContextBuilder $contextBuilder,
        TransactionService $transactionService
    ) {
        $this->providerManager = $providerManager;
        $this->contextBuilder = $contextBuilder;
        $this->transactionService = $transactionService;
    }

    /**
     * Memulai percakapan baru untuk tenant dan user aktif.
     */
    public function startConversation(string $title = 'Percakapan Baru'): AiConversation
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        if (!$tenantId || !$userId) {
            throw new \RuntimeException('Konteks ruang kerja dan pengguna wajib aktif.');
        }

        PlatformAiService::applyRuntimeConfig();

        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        $hasCustomKey = !empty($setting?->api_key);

        // Otomatis mewarisi dari Administrasi Sistem jika ruang kerja tidak menggunakan kunci kustom
        $activeProvider = $hasCustomKey
            ? ($setting->provider ?: config('rudimah.ai.default_provider', '9router'))
            : config('rudimah.ai.default_provider', '9router');

        $activeModel = $hasCustomKey
            ? ($setting->model ?: config("rudimah.ai.{$activeProvider}_model", 'high-models-agent'))
            : config("rudimah.ai.{$activeProvider}_model", 'high-models-agent');

        $conversation = AiConversation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $title,
            'provider' => $activeProvider,
            'model' => $activeModel,
        ]);

        AuditLogger::log('ai_conversation_created', 'ai_conversations', $conversation->id, null, [
            'provider' => $activeProvider,
            'model' => $activeModel,
        ]);

        return $conversation;
    }

    /**
     * Mengirim pesan ke AI Assistant dan mendapatkan respon dalam isolasi tenant.
     */
    public function sendMessage(AiConversation $conversation, string $userMessageText): AiMessage
    {
        $tenantId = TenantContext::id();
        $userId = auth()->id();

        // Verifikasi isolasi keamanan: percakapan wajib milik tenant dan user yang sedang aktif
        if ($conversation->tenant_id !== $tenantId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Percakapan ini berada di luar ruang kerja Anda.');
        }

        if ($conversation->user_id !== $userId && !auth()->user()?->is_super_admin) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Anda tidak memiliki akses ke percakapan ini.');
        }

        // Terapkan konfigurasi AI platform runtime sebelum pemrosesan
        PlatformAiService::applyRuntimeConfig();

        // 1. Simpan pesan pengguna
        $userMessage = AiMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenantId,
            'role' => 'user',
            'content' => $userMessageText,
        ]);

        // Perbarui judul percakapan bila masih default
        if ($conversation->title === 'Percakapan Baru') {
            $conversation->update([
                'title' => mb_strimwidth($userMessageText, 0, 40, '...'),
            ]);
        }

        // 2. Evaluasi Perlindungan Keamanan & Anti-Injeksi Prompt Cepat
        if ($this->isExplicitOutOfScopeOrInjection($userMessageText)) {
            $refusalText = "Maaf, sebagai Asisten Cerdas RUDIMAH, peran saya dikhususkan untuk membantu manajemen keuangan harian (PEKAN), pencatatan transaksi, pos anggaran, target tabungan, dan pengingat tagihan pada ruang kerja ini. Saya tidak dapat melayani permintaan penulisan kode program/skrip atau topik di luar manajemen keuangan platform RUDIMAH. Ada transaksi atau data keuangan yang ingin saya bantu catat?";

            return AiMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'tenant_id' => $tenantId,
                'role' => 'assistant',
                'content' => $refusalText,
                'metadata' => [
                    'guarded' => true,
                    'reason' => 'prompt_injection_or_out_of_scope',
                ],
            ]);
        }

        // 3. Ambil konfigurasi AI Ruang Kerja atau Platform Global
        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        if ($setting && !$setting->is_enabled) {
            return AiMessage::create([
                'id' => (string) Str::uuid(),
                'conversation_id' => $conversation->id,
                'tenant_id' => $tenantId,
                'role' => 'assistant',
                'content' => 'Layanan Asisten AI saat ini sedang dinonaktifkan oleh administrator ruang kerja Anda.',
                'metadata' => ['disabled' => true],
            ]);
        }

        // Tentukan provider, model, dan endpoint aktif (otomatis warisi dari Administrasi Sistem bila tidak ada kunci kustom)
        $hasCustomKey = !empty($setting?->api_key);
        $activeProvider = $hasCustomKey
            ? ($setting->provider ?: config('rudimah.ai.default_provider', '9router'))
            : config('rudimah.ai.default_provider', '9router');

        $activeModel = $hasCustomKey
            ? ($setting->model ?: config("rudimah.ai.{$activeProvider}_model", 'high-models-agent'))
            : config("rudimah.ai.{$activeProvider}_model", 'high-models-agent');

        $activeEndpoint = $hasCustomKey && !empty($setting?->api_endpoint)
            ? $setting->api_endpoint
            : config("rudimah.ai.{$activeProvider}_endpoint");

        // Jika percakapan lama memiliki provider yang berbeda atau belum terset, sinkronkan ke provider aktif
        if ($conversation->provider !== $activeProvider || empty($conversation->model)) {
            $conversation->update([
                'provider' => $activeProvider,
                'model' => $activeModel,
            ]);
        }

        // 3. Bangun prompt sistem dengan batasan ketat tenant dan konteks data riil
        $systemPrompt = $this->contextBuilder->buildSystemPrompt($setting?->system_instructions);

        // 4. Siapkan riwayat percakapan (maksimal 10 pesan terakhir)
        $pastMessages = AiMessage::where('conversation_id', $conversation->id)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse();

        $messagesPayload = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($pastMessages as $pm) {
            $messagesPayload[] = [
                'role' => $pm->role,
                'content' => $pm->content,
            ];
        }

        // 5. Panggil provider AI yang sesuai
        $provider = $this->providerManager->resolve($activeProvider, $setting);
        $options = [
            'model' => $activeModel,
            'temperature' => $setting?->temperature ?? 0.7,
            'api_endpoint' => $activeEndpoint,
        ];

        try {
            $response = $provider->chat($messagesPayload, $options);
            $replyContent = $response['content'] ?? '';
            $tokensUsed = (int) ($response['tokens_used'] ?? 0);
            $meta = array_merge($response['metadata'] ?? [], [
                'model' => $response['model'] ?? $activeModel,
                'tokens_used' => $tokensUsed,
                'tenant_verified' => $tenantId,
            ]);
        } catch (\Throwable $e) {
            Log::error("[TenantAssistantService] Gagal memproses respon AI ({$activeProvider}): " . $e->getMessage());
            $replyContent = "Terjadi kendala saat menghubungi layanan AI ({$activeProvider}): " . $e->getMessage();
            $tokensUsed = 0;
            $meta = [
                'error' => true,
                'provider' => $activeProvider,
                'model' => $activeModel,
                'error_detail' => $e->getMessage(),
            ];
        }

        if (empty(trim($replyContent))) {
            $replyContent = "Maaf, asisten tidak menghasilkan respon yang dapat ditampilkan. Silakan coba ajukan pertanyaan kembali.";
        }

        // 6. Deteksi dan Eksekusi Pencatatan Transaksi Nyata ke Database
        $recordedTransactions = [];
        [$parsedActions, $cleanedReply] = $this->extractTransactionActions($replyContent, $userMessageText);

        if (!empty($parsedActions)) {
            foreach ($parsedActions as $action) {
                try {
                    $amountMinor = (int) ($action['amount_minor'] ?? 0);
                    if ($amountMinor <= 0 && !empty($action['amount'])) {
                        $amountMinor = (int) round((float) $action['amount']);
                    }
                    if ($amountMinor <= 0) {
                        continue;
                    }

                    $type = strtolower($action['type'] ?? 'expense');
                    if (!in_array($type, ['income', 'expense', 'savings', 'transfer'])) {
                        $type = 'expense';
                    }

                    $tx = $this->transactionService->createTransaction([
                        'type' => $type,
                        'amount_minor' => $amountMinor,
                        'description' => $action['description'] ?? 'Transaksi via Asisten AI',
                        'category' => $action['category'] ?? null,
                        'transaction_date' => $action['date'] ?? now()->toDateString(),
                        'notes' => 'Dicatat secara otomatis melalui Asisten AI',
                    ]);

                    $recordedTransactions[] = [
                        'id' => $tx->id,
                        'type' => $tx->type,
                        'description' => $tx->description,
                        'amount_minor' => $tx->amount_minor,
                        'date' => (string) $tx->transaction_date,
                    ];
                } catch (\Throwable $e) {
                    Log::error('[TenantAssistantService] Gagal mencatat transaksi dari AI: ' . $e->getMessage());
                }
            }

            if (!empty($recordedTransactions)) {
                $meta['recorded_transactions'] = $recordedTransactions;
                $replyContent = $cleanedReply;
                if (empty(trim($replyContent))) {
                    $count = count($recordedTransactions);
                    $replyContent = "Transaksi berhasil dicatat ke dalam daftar mutasi ({$count} transaksi).";
                }
            }
        }

        // 7. Simpan respon asisten
        $assistantMessage = AiMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $conversation->id,
            'tenant_id' => $tenantId,
            'role' => 'assistant',
            'content' => $replyContent,
            'metadata' => array_merge($meta, [
                'tokens_used' => $tokensUsed,
            ]),
        ]);

        return $assistantMessage;
    }

    /**
     * Mengekstrak instruksi pencatatan transaksi baik dari blok JSON terstruktur
     * maupun dari pola baris item balasan teks AI (sebagai fallback).
     */
    protected function extractTransactionActions(string $replyContent, string $userMessageText): array
    {
        // 0. Cegah pencatatan mutasi transaksi jika pesan pengguna jelas berupa pertanyaan informasional (misal: "berapa tagihan...", "apa saja...", "info...", "daftar...")
        $isInformationalQuery = (bool) preg_match('/^\s*(berapa|apa|apakah|kapan|cek|info|lihat|tampilkan|daftar)\b/i', $userMessageText)
            && !preg_match('/\b(catat|masuk|simpan|input|buatkan mutasi|bayar)\b/i', $userMessageText);

        if ($isInformationalQuery) {
            // Bersihkan blok kode JSON action jika model AI keliru menyertakannya saat menjawab pertanyaan informasi
            $cleaned = preg_replace('/```(?:json:transaction_action|json)?\s*(\[\s*\{.*?\}\s*\]|\{\s*"type"\s*:.*?\}\s*)\s*```/is', '', $replyContent);
            return [[], trim($cleaned ?: $replyContent)];
        }

        // 1. Ekstraksi Primer: Blok ```json:transaction_action ... ``` atau ```json ... ```
        if (preg_match('/```(?:json:transaction_action|json)?\s*(\[\s*\{.*?\}\s*\])\s*```/is', $replyContent, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                return [$parsed, $cleaned];
            }
        }

        // 2. Ekstraksi Sekunder: Blok objek tunggal ```json:transaction_action { ... } ```
        if (preg_match('/```(?:json:transaction_action|json)?\s*(\{\s*"type"\s*:.*?\}\s*)\s*```/is', $replyContent, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                return [[$parsed], $cleaned];
            }
        }

        // 3. Ekstraksi Tertier: JSON array mentah tanpa markdown fence
        if (preg_match('/(\[\s*\{\s*"type"\s*:.*?"amount_minor"\s*:.*?\}\s*\])/is', $replyContent, $matches)) {
            $parsed = json_decode($matches[1], true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                return [$parsed, $cleaned];
            }
        }

        // 4. Ekstraksi Kuartener (Fallback cerdas): Jika pengguna meminta pencatatan ("masukin/catat/simpan/input")
        // dan balasan asisten merinci baris-baris "- Item: Rp XX.XXX"
        $isUserAskingToRecord = (bool) preg_match('/(masuk(?:in|kan)?|catat(?:kan)?|tambah(?:kan)?|simpan|input|buatkan mutasi)\b/i', $userMessageText);
        if ($isUserAskingToRecord) {
            $transactions = [];
            if (preg_match_all('/^[\s*•-]*([A-Za-z0-9\s\/\(\)]+?)\s*:\s*(?:Rp\.?|IDR)?\s*([\d\.,]+)/mi', $replyContent, $lineMatches, PREG_SET_ORDER)) {
                $isIncome = (bool) preg_match('/(pemasukan|gaji|income|transfer masuk)\b/i', $userMessageText);
                $type = $isIncome ? 'income' : 'expense';

                foreach ($lineMatches as $match) {
                    $itemDesc = trim($match[1]);
                    $rawNominal = $match[2];

                    $descLower = strtolower($itemDesc);
                    if (in_array($descLower, ['total', 'subtotal', 'sisa', 'saldo', 'anggaran', 'total hari ini', 'total pengeluaran', 'total pemasukan', 'catatan'])) {
                        continue;
                    }

                    $nominalDigits = preg_replace('/[^\d]/', '', $rawNominal);
                    $amountMinor = (int) $nominalDigits;

                    if ($amountMinor > 0 && strlen($itemDesc) >= 2) {
                        $transactions[] = [
                            'type' => $type,
                            'description' => $itemDesc,
                            'amount_minor' => $amountMinor,
                            'date' => now()->toDateString(),
                            'category' => null,
                        ];
                    }
                }

                if (!empty($transactions)) {
                    return [$transactions, $replyContent];
                }
            }
        }

        return [[], $replyContent];
    }

    /**
     * Mendeteksi upaya injeksi prompt atau permintaan yang jelas di luar domain keuangan.
     */
    protected function isExplicitOutOfScopeOrInjection(string $text): bool
    {
        $normalized = strtolower(trim($text));

        // 1. Pola eksplisit pembuatan/penulisan skrip atau kode program
        if (preg_match('/\b(buatkan|bikin|tulis|generate|create)\s+(script|skrip|koding|coding|code|fungsi|function|source\s*code|program|algoritma)\b/i', $normalized)) {
            // Kecuali jika jelas sedang meminta catat transaksi (misal: "buatkan catatan transaksi...", "catat pengeluaran...")
            if (!preg_match('/\b(transaksi|anggaran|budget|tabungan|pengingat|tagihan|catat|pemasukan|pengeluaran)\b/i', $normalized)) {
                return true;
            }
        }

        // 2. Pola skrip atau kode bahasa pemrograman spesifik (seperti "script login form dari python")
        if (preg_match('/\b(script|skrip|koding|kode)\b.*(?:\b(python|javascript|php|golang|java|html|css|bash|powershell|sql|react|vue|node)\b|c\+\+)/i', $normalized)) {
            return true;
        }

        // 3. Pola jailbreak / manipulasi peran / prompt injection eksplisit
        if (preg_match('/\b(ignore|abaikan)\s+(all|semua)?\s*(previous|prior|sebelumnya)?\s*(instructions|instruksi|rules|aturan)\b/i', $normalized)) {
            return true;
        }

        if (preg_match('/\b(developer\s*mode|dan\s*mode|jailbreak|unrestricted\s*mode|mode\s*pengembang|system\s*prompt)\b/i', $normalized)) {
            return true;
        }

        if (preg_match('/\b(kamu\s+sekarang\s+adalah|you\s+are\s+now|act\s+as|berperanlah\s+sebagai)\s+(bukan|programmer|hacker|bot|developer|ai\s+bebas|dan)\b/i', $normalized)) {
            return true;
        }

        return false;
    }
}

