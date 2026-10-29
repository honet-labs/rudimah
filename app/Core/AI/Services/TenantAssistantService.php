<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceTransaction;
use App\Modules\Pekan\Services\TransactionService;
use App\Shared\Helpers\MoneyHelper;
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
            $content = $pm->content;
            if ($pm->role === 'assistant' && !empty($pm->metadata['recorded_transactions'])) {
                $txList = $pm->metadata['recorded_transactions'];
                $summaryList = [];
                foreach ($txList as $t) {
                    $desc = $t['description'] ?? 'Transaksi';
                    $id = $t['id'] ?? '';
                    $date = $t['date'] ?? '';
                    $merchant = !empty($t['merchant_name']) ? " di {$t['merchant_name']}" : '';
                    $amt = MoneyHelper::formatIdr((int) ($t['amount_minor'] ?? 0));
                    $act = $t['action'] ?? 'dicatat';
                    $summaryList[] = "ID: {$id} ({$desc}{$merchant}, {$amt}, {$date}, status: {$act})";
                }
                $content .= "\n[Info Sistem: Mutasi yang diproses: " . implode('; ', $summaryList) . "]";
            }

            $messagesPayload[] = [
                'role' => $pm->role,
                'content' => $content,
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

        // 6. Deteksi dan Eksekusi Tindakan Transaksi (Pencatatan, Pengubahan/Ralat, dan Penghapusan)
        $recordedTransactions = [];
        [$parsedActions, $cleanedReply] = $this->extractTransactionActions($replyContent, $userMessageText);

        $userMessageTrimmed = trim($userMessageText);
        $isCorrectionIntent = (bool) preg_match('/\b(salah|rubah|ubah|ralat|koreksi|ganti|edit|revisi|bukan\b.*?\btapi)\b/i', $userMessageTrimmed);
        $isDeleteIntent = (bool) preg_match('/\b(hapus|batalkan|delete)\s+(transaksi|mutasi|catatan)\b/i', $userMessageTrimmed);
        $isExplicitCreate = (bool) preg_match('/\b(catat|tambah|masuk(?:in|kan)?|simpan|input|buatkan|tadi\s+(?:makan|beli|minum|jajan|belanja)|beli|makan|bayar)\b/i', $userMessageTrimmed);

        if (!empty($parsedActions)) {
            foreach ($parsedActions as $action) {
                try {
                    $rawAction = strtolower($action['action'] ?? '');
                    if ($isDeleteIntent) {
                        $actionType = 'delete';
                    } elseif ($isCorrectionIntent && !$isExplicitCreate) {
                        $actionType = 'update';
                    } elseif ($isExplicitCreate && !$isCorrectionIntent) {
                        // Pengguna secara eksplisit meminta pencatatan baru, abaikan halusinasi 'update' dari AI
                        $actionType = 'create';
                    } elseif (!empty($rawAction)) {
                        $actionType = $rawAction;
                    } else {
                        $actionType = 'create';
                    }

                    $amountMinor = (int) ($action['amount_minor'] ?? 0);
                    if ($amountMinor <= 0 && !empty($action['amount'])) {
                        $amountMinor = (int) round((float) $action['amount']);
                    }

                    // Resolusi Nama Merchant / Tempat
                    $merchantName = $action['merchant_name'] ?? $action['merchant'] ?? $action['toko'] ?? $action['tempat'] ?? $action['store'] ?? null;
                    if (empty($merchantName)) {
                        $searchSource = ($action['description'] ?? '') . ' ' . $userMessageTrimmed;
                        if (preg_match('/\bdi\s+([A-Za-z0-9\s]{2,35}?)(?:\s+(?:sebesar|seharga|senilai|\d|rp|\,|$|\.))/i', $searchSource, $mMatch)) {
                            $candidate = trim($mMatch[1]);
                            if (!preg_match('/^(hari|sini|situ|sana|atas|bawah|dalam|luar|kemarin|tadi)$/i', $candidate)) {
                                $merchantName = ucwords($candidate);
                            }
                        }
                    }

                    // Resolusi Deskripsi Lengkap
                    $description = trim($action['description'] ?? '');
                    if (empty($description) || in_array(strtolower($description), ['transaksi', 'makan', 'makan siang', 'makan malam', 'makan pagi', 'pengeluaran'])) {
                        if (preg_match('/(?:makan\s+(?:siang|pagi|malam)?\s*(?:makai|pakai|menu)?\s*)([A-Za-z0-9\s]{2,40}?)(?:\s+(?:sebesar|seharga|senilai|\d|rp|di\b|$))/i', $userMessageTrimmed, $dMatch)) {
                            $dishName = trim($dMatch[1]);
                            if (!empty($dishName)) {
                                $prefix = preg_match('/makan\s+(siang|malam|pagi)/i', $userMessageTrimmed, $pm) ? ucwords($pm[0]) . ' ' : 'Makan ';
                                $description = $prefix . $dishName;
                            }
                        }
                    }
                    if (!empty($merchantName) && stripos($description, $merchantName) === false) {
                        $description .= ' di ' . $merchantName;
                    }
                    if (empty($description)) {
                        $description = 'Pengeluaran via Asisten AI';
                    }

                    // Ekstraksi / Pembentukan Baris Item Transaksi
                    $items = [];
                    $calculatedItemsTotal = 0;
                    if (!empty($action['items']) && is_array($action['items'])) {
                        foreach ($action['items'] as $item) {
                            $itemName = trim($item['item_name'] ?? $item['name'] ?? $item['deskripsi'] ?? '');
                            if (!empty($itemName)) {
                                $itemQty = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
                                $itemPrice = (int) ($item['price_minor'] ?? $item['price'] ?? 0);
                                if ($itemPrice <= 0 && $itemQty > 0 && count($action['items']) === 1) {
                                    $itemPrice = (int) round($amountMinor / $itemQty);
                                }
                                $itemTotal = (int) ($item['total_minor'] ?? ($itemPrice * $itemQty));
                                if ($itemTotal <= 0 && $itemPrice > 0) {
                                    $itemTotal = $itemPrice * $itemQty;
                                }
                                $calculatedItemsTotal += $itemTotal;
                                $items[] = [
                                    'item_name' => $itemName,
                                    'quantity' => $itemQty,
                                    'price_minor' => $itemPrice,
                                    'total_minor' => $itemTotal,
                                    'discount_minor' => 0,
                                ];
                            }
                        }
                    }

                    // Sinkronisasi nominal transaksi jika model mengembalikan daftar item
                    if ($calculatedItemsTotal > 0 && ($amountMinor <= 0 || $amountMinor < $calculatedItemsTotal)) {
                        $amountMinor = $calculatedItemsTotal;
                    }
                    if (empty($items) && $amountMinor > 0) {
                        $cleanItemName = $description;
                        if ($merchantName) {
                            $cleanItemName = trim(preg_replace('/\s+di\s+' . preg_quote($merchantName, '/') . '/i', '', $cleanItemName));
                        }
                        if (preg_match('/(?:makan\s+(?:siang|pagi|malam)?\s*(?:makai|pakai|menu)?\s*)([A-Za-z0-9\s]{2,35}?)(?:\s+(?:sebesar|seharga|senilai|\d|rp|di\b|$))/i', $userMessageTrimmed, $imMatch)) {
                            $candidateItem = trim($imMatch[1]);
                            if (!empty($candidateItem)) {
                                $cleanItemName = ucwords($candidateItem);
                            }
                        }
                        $items[] = [
                            'item_name' => $cleanItemName ?: 'Transaksi',
                            'quantity' => 1,
                            'price_minor' => $amountMinor,
                            'total_minor' => $amountMinor,
                            'discount_minor' => 0,
                        ];
                    }

                    // A. AKSI PENGHAPUSAN TRANSAKSI
                    if ($actionType === 'delete') {
                        $targetTx = null;
                        if (!empty($action['transaction_id'])) {
                            $targetTx = FinanceTransaction::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($action) {
                                    $q->where('id', $action['transaction_id'])
                                      ->orWhere('id', 'like', $action['transaction_id'] . '%');
                                })->first();
                        }
                        if (!$targetTx) {
                            $targetTx = $this->resolveLastRecordedTransaction($conversation->id, $tenantId, $description);
                        }
                        if ($targetTx) {
                            $deletedDesc = $targetTx->description;
                            $deletedMerchant = $targetTx->merchant_name;
                            $deletedAmount = $targetTx->amount_minor;
                            $deletedDate = $targetTx->transaction_date ? \Carbon\Carbon::parse($targetTx->transaction_date)->format('Y-m-d') : now()->toDateString();
                            $targetTx->delete();
                            $recordedTransactions[] = [
                                'id' => $targetTx->id,
                                'action' => 'deleted',
                                'type' => $targetTx->type,
                                'description' => $deletedDesc,
                                'merchant_name' => $deletedMerchant,
                                'amount_minor' => $deletedAmount,
                                'date' => $deletedDate,
                            ];
                        }
                        continue;
                    }

                    // B. AKSI PENGUBAHAN / KOREKSI / RALAT TRANSAKSI (UPDATE)
                    if ($actionType === 'update') {
                        $targetTx = null;
                        if (!empty($action['transaction_id'])) {
                            $targetTx = FinanceTransaction::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($action) {
                                    $q->where('id', $action['transaction_id'])
                                      ->orWhere('id', 'like', $action['transaction_id'] . '%');
                                })->first();
                        }
                        if (!$targetTx) {
                            $targetTx = $this->resolveLastRecordedTransaction($conversation->id, $tenantId, $description);
                        }

                        if ($targetTx) {
                            $updatePayload = [];
                            if (!empty($action['date'])) {
                                $updatePayload['transaction_date'] = $action['date'];
                            }
                            if ($amountMinor > 0) {
                                $updatePayload['amount_minor'] = $amountMinor;
                            }
                            if (!empty($description)) {
                                $updatePayload['description'] = $description;
                            }
                            if (!empty($merchantName)) {
                                $updatePayload['merchant_name'] = $merchantName;
                            }
                            if (!empty($action['category'])) {
                                $updatePayload['category'] = $action['category'];
                            }
                            if (!empty($action['type']) && in_array(strtolower($action['type']), ['income', 'expense', 'savings', 'transfer'])) {
                                $updatePayload['type'] = strtolower($action['type']);
                            }

                            $updatedTx = $this->transactionService->updateTransaction($targetTx, $updatePayload, $items);
                            $formattedDate = $updatedTx->transaction_date ? \Carbon\Carbon::parse($updatedTx->transaction_date)->format('Y-m-d') : now()->toDateString();

                            $recordedTransactions[] = [
                                'id' => $updatedTx->id,
                                'action' => 'updated',
                                'type' => $updatedTx->type,
                                'description' => $updatedTx->description,
                                'merchant_name' => $updatedTx->merchant_name,
                                'amount_minor' => $updatedTx->amount_minor,
                                'date' => $formattedDate,
                            ];
                            continue;
                        }
                        // Jika targetTx tidak ditemukan sama sekali, lanjut ke fallback create
                    }

                    // C. AKSI PENCATATAN TRANSAKSI BARU (CREATE)
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
                        'description' => $description,
                        'merchant_name' => $merchantName,
                        'category' => $action['category'] ?? null,
                        'transaction_date' => $action['date'] ?? now()->toDateString(),
                        'notes' => 'Dicatat secara otomatis melalui Asisten AI',
                    ], $items);

                    $formattedDate = $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d') : now()->toDateString();

                    $recordedTransactions[] = [
                        'id' => $tx->id,
                        'action' => 'created',
                        'type' => $tx->type,
                        'description' => $tx->description,
                        'merchant_name' => $tx->merchant_name,
                        'amount_minor' => $tx->amount_minor,
                        'date' => $formattedDate,
                    ];
                } catch (\Throwable $e) {
                    Log::error('[TenantAssistantService] Gagal mengeksekusi aksi transaksi dari AI: ' . $e->getMessage());
                }
            }

            if (!empty($recordedTransactions)) {
                $meta['recorded_transactions'] = $recordedTransactions;
                $replyContent = $cleanedReply;

                $hasCreate = collect($recordedTransactions)->contains('action', 'created');
                $hasUpdate = collect($recordedTransactions)->contains('action', 'updated');
                $hasDelete = collect($recordedTransactions)->contains('action', 'deleted');

                // Jika aksi adalah pencatatan baru (create), pastikan tidak ada teks salah "Deskripsi diperbarui" atau "diperbarui"
                if ($hasCreate && !$hasUpdate) {
                    $replyContent = preg_replace('/(?:Deskripsi|Transaksi)\s+diperbarui\s*:/i', 'Transaksi berhasil dicatat:', $replyContent);
                    $replyContent = preg_replace('/berhasil\s+diperbarui/i', 'berhasil dicatat', $replyContent);
                }

                // Bersihkan kalimat matematika janggal/halusinasi jika ada (seperti "Total dua transaksi Rp 30.000 untuk hari ini")
                $replyContent = preg_replace('/\s*Total\s+(?:dua|tiga|\d+)\s+transaksi\s+Rp\s*[\d\.,]+\s+untuk\s+hari\s+ini\.?/i', '', $replyContent);

                if ($hasUpdate) {
                    $replyContent = preg_replace('/Transaksi\s+dicatat\s*:/i', 'Transaksi berhasil diperbarui:', $replyContent);
                    if (empty(trim($replyContent))) {
                        $replyContent = "Transaksi berhasil diperbarui di dalam sistem.";
                    }
                } elseif ($hasDelete) {
                    $replyContent = preg_replace('/Transaksi\s+dicatat\s*:/i', 'Transaksi berhasil dihapus:', $replyContent);
                    if (empty(trim($replyContent))) {
                        $replyContent = "Transaksi berhasil dihapus dari daftar mutasi.";
                    }
                } elseif (empty(trim($replyContent))) {
                    $firstTx = $recordedTransactions[0];
                    $nominalFormatted = 'Rp ' . number_format($firstTx['amount_minor'] ?? 0, 0, ',', '.');
                    $replyContent = "Transaksi '{$firstTx['description']}' sebesar {$nominalFormatted} telah berhasil dicatat ke dalam sistem.";
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
        // 0. Cegah pencatatan mutasi transaksi jika pesan pengguna jelas berupa pertanyaan informasional atau keluhan/pertanyaan klarifikasi
        $isInformationalQuery = (bool) preg_match('/^\s*(berapa|apa|apakah|kapan|cek|info|lihat|tampilkan|daftar)\b/i', $userMessageText)
            && !preg_match('/\b(catat|masuk|simpan|input|buatkan mutasi|bayar)\b/i', $userMessageText);

        $isNegatedOrQuestion = (bool) preg_match('/\b(belum|jangan|tidak|bukan|kok|kenapa|mengapa)\s+(?:pernah\s+)?(?:di)?(?:catat|simpan|masuk|input|perbarui|update)\b/i', $userMessageText)
            && !preg_match('/\b(tolong\s+catat|sekarang\s+catat|catat\s+ulang|masukkan\s+ulang|buatkan\s+baru)\b/i', $userMessageText);

        if ($isInformationalQuery || $isNegatedOrQuestion) {
            // Bersihkan blok kode JSON action jika model AI keliru menyertakannya saat menjawab pertanyaan informasi atau klarifikasi
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
        $isUserAskingToRecord = !$isNegatedOrQuestion && (bool) preg_match('/(masuk(?:in|kan)?|catat(?:kan)?|tambah(?:kan)?|simpan|input|buatkan mutasi)\b/i', $userMessageText);
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

    /**
     * Mencari transaksi sasaran yang baru saja dicatat pada percakapan ini
     * atau transaksi mutasi paling baru di ruang kerja (sebagai target update/koreksi).
     */
    protected function resolveLastRecordedTransaction(string $conversationId, string $tenantId, ?string $description = null): ?FinanceTransaction
    {
        // 1. Cari dari pesan asisten paling baru di percakapan ini yang memiliki metadata recorded_transactions
        $lastAssistantWithTx = AiMessage::where('conversation_id', $conversationId)
            ->where('tenant_id', $tenantId)
            ->where('role', 'assistant')
            ->whereNotNull('metadata')
            ->orderBy('created_at', 'desc')
            ->get()
            ->first(function ($msg) {
                return !empty($msg->metadata['recorded_transactions']);
            });

        if ($lastAssistantWithTx && !empty($lastAssistantWithTx->metadata['recorded_transactions'])) {
            $recentTxs = $lastAssistantWithTx->metadata['recorded_transactions'];
            $lastTxMeta = end($recentTxs);
            if (!empty($lastTxMeta['id'])) {
                $found = FinanceTransaction::where('tenant_id', $tenantId)->find($lastTxMeta['id']);
                if ($found) {
                    return $found;
                }
            }
        }

        // 2. Jika tidak ada di metadata percakapan, cari berdasarkan deskripsi di mutasi terbaru
        if (!empty($description)) {
            $found = FinanceTransaction::where('tenant_id', $tenantId)
                ->where('description', 'like', '%' . trim($description) . '%')
                ->orderBy('created_at', 'desc')
                ->first();
            if ($found) {
                return $found;
            }
        }

        // 3. Fallback: ambil mutasi yang baru saja dibuat dalam 2 jam terakhir di ruang kerja ini
        return FinanceTransaction::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subHours(2))
            ->orderBy('created_at', 'desc')
            ->first();
    }
}

