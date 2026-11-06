<?php

namespace App\Core\AI\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\AiConversation;
use App\Core\AI\Models\AiMessage;
use App\Core\AI\Models\TenantAiSetting;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceTransaction;
use App\Modules\Pekan\Models\FinanceReminder;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceSavings;
use App\Modules\Pekan\Services\TransactionService;
use App\Shared\Helpers\MoneyHelper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $platformSettings = PlatformAiService::getSettings();
        $activePlatformProvider = $platformSettings['active_provider'] ?? config('rudimah.ai.default_provider', 'gemini');
        $activeProviderDetails = $platformSettings['providers'][$activePlatformProvider] ?? [];

        $setting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        $hasCustomKey = !empty($setting?->api_key_encrypted) && !empty($setting?->api_key);
        $isUsingCustom = $hasCustomKey && !empty($setting?->provider);

        // Otomatis mewarisi dari Administrasi Sistem jika ruang kerja tidak menggunakan kunci kustom
        $activeProvider = $isUsingCustom ? $setting->provider : $activePlatformProvider;
        $normalizedProvider = (strtolower($activeProvider) === 'ninerouter') ? '9router' : strtolower($activeProvider);
        $activeDetails = $isUsingCustom ? [] : ($platformSettings['providers'][$normalizedProvider] ?? []);

        $activeModel = $isUsingCustom
            ? ($setting->model ?: ($activeDetails['model'] ?? ''))
            : ($activeDetails['model'] ?? config("rudimah.ai.{$normalizedProvider}_model", ''));

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
        // Lindungi proses eksekusi dari pemutusan koneksi klien (tab ditutup, refresh, atau berpindah tab)
        // agar seluruh operasi database dan AI tetap berjalan tuntas hingga selesai
        ignore_user_abort(true);
        if (!ini_get('safe_mode')) {
            @set_time_limit(180);
        }

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
            $refusalText = "Maaf, sebagai Asisten Cerdas RUDIMAH, peran saya dikhususkan untuk membantu manajemen keuangan harian (PEKAN), pencatatan transaksi, pos anggaran, target tabungan, pengingat tagihan, dan visualisasi grafik keuangan pada ruang kerja ini. Saya tidak dapat melayani permintaan penulisan kode program/skrip atau topik di luar manajemen keuangan platform RUDIMAH.";

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
        $platformSettings = PlatformAiService::getSettings();
        $activePlatformProvider = $platformSettings['active_provider'] ?? config('rudimah.ai.default_provider', 'gemini');
        $activeProviderDetails = $platformSettings['providers'][$activePlatformProvider] ?? [];

        $hasCustomKey = !empty($setting?->api_key_encrypted) && !empty($setting?->api_key);
        $isUsingCustom = $hasCustomKey && !empty($setting?->provider);

        $activeProvider = $isUsingCustom ? $setting->provider : $activePlatformProvider;
        $normalizedProvider = (strtolower($activeProvider) === 'ninerouter') ? '9router' : strtolower($activeProvider);
        $activeDetails = $isUsingCustom ? [] : ($platformSettings['providers'][$normalizedProvider] ?? []);

        $activeModel = $isUsingCustom
            ? ($setting->model ?: ($activeDetails['model'] ?? ''))
            : ($activeDetails['model'] ?? config("rudimah.ai.{$normalizedProvider}_model", ''));

        $activeEndpoint = $isUsingCustom && !empty($setting?->api_endpoint)
            ? $setting->api_endpoint
            : ($activeDetails['api_endpoint'] ?? config("rudimah.ai.{$normalizedProvider}_endpoint"));

        // Jika percakapan lama memiliki provider atau model yang berbeda dari konfigurasi aktif, sinkronkan ke konfigurasi aktif
        if ($conversation->provider !== $activeProvider || $conversation->model !== $activeModel) {
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
            if ($pm->role === 'assistant') {
                // Bersihkan info sistem teknis internal yang mungkin pernah tersimpan di pesan asisten sebelumnya
                $content = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $content);
                $content = trim($content);
            }

            $messagesPayload[] = [
                'role' => $pm->role,
                'content' => $content,
            ];

            // Masukkan informasi mutasi yang berhasil diproses sebelumnya sebagai pesan peran 'system' (bukan ucapan asisten)
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
                $messagesPayload[] = [
                    'role' => 'system',
                    'content' => "[Konteks Sistem: Transaksi yang baru dicatat/diubah pada sesi ini: " . implode('; ', $summaryList) . "]",
                ];
            }
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
                'provider' => $provider->getName(),
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

        // Jika terjadi error pada provider AI (misal timeout atau gangguan gateway),
        // cek apakah pengguna mengirim instruksi transaksi/koreksi langsung.
        // Jika ya, selesaikan mutasi secara lokal agar pengguna tidak terganggu kegagalan gateway eksternal.
        $parsedActions = [];
        $cleanedReply = $replyContent;
        if (!empty($meta['error'])) {
            [$fallbackActions, $fallbackCleaned] = $this->extractTransactionActions('', $userMessageText, $conversation->id, $tenantId);
            if (!empty($fallbackActions)) {
                $parsedActions = $fallbackActions;
                $cleanedReply = '';
                unset($meta['error'], $meta['error_detail']);
            }
        }

        if (empty(trim($replyContent))) {
            $replyContent = "Maaf, asisten tidak menghasilkan respon yang dapat ditampilkan. Silakan coba ajukan pertanyaan kembali.";
        }

        // 6. Deteksi dan Eksekusi Tindakan Transaksi (Pencatatan, Pengubahan/Ralat, dan Penghapusan)
        $recordedTransactions = [];
        if (empty($parsedActions)) {
            [$parsedActions, $cleanedReply] = $this->extractTransactionActions($replyContent, $userMessageText, $conversation->id, $tenantId);
        }

        $userMessageTrimmed = trim($userMessageText);
        $isCorrectionIntent = (bool) preg_match('/\b(salah|rubah|ubah|ralat|koreksi|ganti|edit|revisi|bukan\b.*?\btapi|ko\s+tanggal|kok\s+tanggal|masih\s+tanggal)\b/i', $userMessageTrimmed);
        $isDeleteIntent = (bool) preg_match('/\b(hapus|batalkan|delete)\s+(transaksi|mutasi|catatan)\b/i', $userMessageTrimmed);
        $isExplicitCreate = (bool) preg_match('/\b(catat|catet|nyatet|tambah|masuk(?:in|kan)?|simpan|input|buatkan|tadi\s+(?:makan|beli|minum|jajan|belanja)|beli|makan|bayar)\b/i', $userMessageTrimmed);

        $detectedUnitPriceInfo = null;
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

                    // Resolusi Merchant Otomatis untuk Bahan Bakar (BBM) jika belum terisi
                    if (empty($merchantName)) {
                        $fuelSource = strtolower(($action['description'] ?? '') . ' ' . $userMessageTrimmed);
                        if (preg_match('/\b(pertamax(?:\s*(?:turbo|green))?|pertalite|dexlite|biosolar|solar pertamina|pertamina dex)\b/i', $fuelSource)) {
                            $merchantName = 'SPBU Pertamina';
                        } elseif (preg_match('/\b(shell\s*(?:super|v-power|diesel)?)\b/i', $fuelSource)) {
                            $merchantName = 'Shell';
                        } elseif (preg_match('/\b(bp\s*(?:92|ultimate|diesel)?)\b/i', $fuelSource)) {
                            $merchantName = 'BP-AKR';
                        } elseif (preg_match('/\b(revvo|vivo)\b/i', $fuelSource)) {
                            $merchantName = 'SPBU Vivo';
                        } elseif (preg_match('/\b(bensin|bbm|spbu|pom bensin)\b/i', $fuelSource)) {
                            $merchantName = 'SPBU';
                        }
                    }

                    // Resolusi Deskripsi Ringkas & Catatan Tambahan Lengkap
                    $rawDescription = trim($action['description'] ?? '');
                    $rawNotes = trim($action['notes'] ?? '');

                    // Catatan Tambahan (notes): Simpan informasi lengkap dan detail
                    $fullDetailedNotes = $rawNotes;
                    if (empty($fullDetailedNotes) || in_array(strtolower($fullDetailedNotes), ['dicatat secara otomatis melalui asisten ai', 'transaksi'])) {
                        $fullDetailedNotes = $rawDescription;
                    }
                    if (empty($fullDetailedNotes) || in_array(strtolower($fullDetailedNotes), ['transaksi', 'makan', 'makan siang', 'makan malam', 'makan pagi', 'pengeluaran'])) {
                        $fullDetailedNotes = $userMessageTrimmed;
                    }
                    if (!empty($merchantName) && stripos($fullDetailedNotes, $merchantName) === false) {
                        $fullDetailedNotes .= ' di ' . $merchantName;
                    }

                    // Nama Transaksi (description): Cukup summary atau singkatan ringkas (contoh: "Sarapan", "Makan Siang", "Beli Bensin")
                    $description = $this->generateShortTransactionSummary($rawDescription, $userMessageTrimmed, $merchantName);

                    // Deteksi Harga Satuan dan Kuantitas/Volume (misal BBM per liter atau timbangan per kg)
                    $unitPriceInfo = $this->detectUnitPriceAndVolume($userMessageTrimmed, $amountMinor);
                    if ($unitPriceInfo) {
                        $detectedUnitPriceInfo = $unitPriceInfo;
                    }

                    // Ekstraksi / Pembentukan Baris Item Transaksi
                    $items = [];
                    $calculatedItemsTotal = 0;
                    if (!empty($action['items']) && is_array($action['items'])) {
                        foreach ($action['items'] as $item) {
                            $itemName = trim($item['item_name'] ?? $item['name'] ?? $item['deskripsi'] ?? '');
                            if (!empty($itemName)) {
                                $rawQty = $item['quantity'] ?? $item['qty'] ?? 1;
                                $itemQty = is_numeric($rawQty) ? (float) $rawQty : 1;
                                if ($itemQty <= 0) {
                                    $itemQty = 1;
                                }
                                $itemPrice = (int) ($item['price_minor'] ?? $item['price'] ?? 0);
                                if ($itemPrice <= 0 && $itemQty > 0 && count($action['items']) === 1) {
                                    $itemPrice = (int) round($amountMinor / $itemQty);
                                }
                                $itemTotal = (int) ($item['total_minor'] ?? round($itemPrice * $itemQty));
                                if ($itemTotal <= 0 && $itemPrice > 0) {
                                    $itemTotal = (int) round($itemPrice * $itemQty);
                                }

                                // Koreksi jika model AI hanya mengisi quantity: 1 dan price_minor sama dengan total belanja,
                                // padahal pengguna secara eksplisit menyebutkan harga satuan per unit/liter/kg
                                if (count($action['items']) === 1 && $unitPriceInfo && $unitPriceInfo['unit_price'] > 0) {
                                    if ($itemQty == 1 && ($itemPrice == $amountMinor || $itemPrice <= 0)) {
                                        $itemQty = $unitPriceInfo['quantity'];
                                        $itemPrice = $unitPriceInfo['unit_price'];
                                        $itemTotal = $amountMinor;
                                    }
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
                        if (preg_match('/(?:beli|isi)\s+(bensin\s+[a-z0-9]+|pertamax(?:\s*(?:turbo|green))?|pertalite|solar|dexlite)\b/i', $userMessageTrimmed, $fmMatch)) {
                            $cleanItemName = ucwords($fmMatch[1]);
                        }

                        $itemQty = 1;
                        $itemPrice = $amountMinor;
                        if ($unitPriceInfo && $unitPriceInfo['unit_price'] > 0) {
                            $itemQty = $unitPriceInfo['quantity'];
                            $itemPrice = $unitPriceInfo['unit_price'];
                        }

                        $items[] = [
                            'item_name' => $cleanItemName ?: 'Transaksi',
                            'quantity' => $itemQty,
                            'price_minor' => $itemPrice,
                            'total_minor' => $amountMinor,
                            'discount_minor' => 0,
                        ];
                    }

                    // A. AKSI PENGHAPUSAN TRANSAKSI
                    if ($actionType === 'delete') {
                        $targetTx = null;
                        $candidateTxId = ltrim(trim($action['transaction_id'] ?? ''), '#');
                        if (empty($candidateTxId) && preg_match('/#([a-f0-9]{6,36})\b/i', $userMessageTrimmed, $idm)) {
                            $candidateTxId = $idm[1];
                        }
                        if (!empty($candidateTxId)) {
                            $targetTx = FinanceTransaction::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($candidateTxId) {
                                    $q->where('id', $candidateTxId)
                                      ->orWhere('id', 'like', $candidateTxId . '%');
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
                        $candidateTxId = ltrim(trim($action['transaction_id'] ?? ''), '#');
                        if (empty($candidateTxId)) {
                            if (preg_match('/(?:#|id\s*:?\s*|transaksi\s+(?:id\s*:?\s*)?)([a-f0-9]{8,36})\b/i', $userMessageTrimmed, $idm)) {
                                $candidateTxId = $idm[1];
                            } elseif (preg_match('/#([a-f0-9]{6,36})\b/i', $userMessageTrimmed, $idm)) {
                                $candidateTxId = $idm[1];
                            }
                        }
                        if (!empty($candidateTxId)) {
                            $targetTx = FinanceTransaction::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($candidateTxId) {
                                    $q->where('id', $candidateTxId)
                                      ->orWhere('id', 'like', $candidateTxId . '%');
                                })->first();
                        }
                        if (!$targetTx) {
                            $targetTx = $this->resolveLastRecordedTransaction($conversation->id, $tenantId, $description);
                        }

                        if ($targetTx) {
                            $updatePayload = [];
                            $resolvedUpdateDate = $this->resolveTransactionDate($userMessageTrimmed, $action['date'] ?? null, $targetTx->transaction_date);
                            if (!empty($resolvedUpdateDate)) {
                                $updatePayload['transaction_date'] = $resolvedUpdateDate;
                            }
                            if ($amountMinor > 0) {
                                $updatePayload['amount_minor'] = $amountMinor;
                            }
                            if (!empty($description)) {
                                $updatePayload['description'] = $description;
                            }
                            if (!empty($fullDetailedNotes)) {
                                $updatePayload['notes'] = $fullDetailedNotes;
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

                    $resolvedTxDate = $this->resolveTransactionDate($userMessageTrimmed, $action['date'] ?? null);

                    $tx = $this->transactionService->createTransaction([
                        'type' => $type,
                        'amount_minor' => $amountMinor,
                        'description' => $description,
                        'merchant_name' => $merchantName,
                        'category' => $action['category'] ?? null,
                        'transaction_date' => $resolvedTxDate,
                        'notes' => $fullDetailedNotes ?: 'Dicatat secara otomatis melalui Asisten AI',
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

                    // Sinkronkan penyebutan tanggal di balasan AI jika sempat berbeda
                    $firstCreated = collect($recordedTransactions)->firstWhere('action', 'created');
                    if ($firstCreated && !empty($firstCreated['date'])) {
                        $realDateFormatted = \Carbon\Carbon::parse($firstCreated['date'])->translatedFormat('d F Y');
                        $replyContent = preg_replace('/\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|Mei|May|Jun|Jul|Agu|Aug|Sep|Okt|Oct|Nov|Des|Dec)[a-z]*\s+\d{4}\b/i', $realDateFormatted, $replyContent);
                    }
                }

                // Bersihkan kalimat matematika janggal/halusinasi jika ada (seperti "Total dua transaksi Rp 30.000 untuk hari ini")
                $replyContent = preg_replace('/\s*Total\s+(?:dua|tiga|\d+)\s+transaksi\s+Rp\s*[\d\.,]+\s+untuk\s+hari\s+ini\.?/i', '', $replyContent);

                // Jika transaksi mencatat pembelian berbasis satuan (misal BBM per liter atau timbangan),
                // pastikan balasan asisten merinci volume dan harga satuannya
                if (!empty($detectedUnitPriceInfo) && $detectedUnitPriceInfo['unit_price'] > 0 && $hasCreate && in_array($detectedUnitPriceInfo['unit'], ['liter', 'ltr', 'lt', 'kg', 'kilo', 'gram', 'gr', 'pcs', 'pc', 'bungkus', 'buah', 'meter'])) {
                    $qtyFormatted = number_format($detectedUnitPriceInfo['quantity'], $detectedUnitPriceInfo['quantity'] == round($detectedUnitPriceInfo['quantity']) ? 0 : 2, ',', '.');
                    $unitPriceFormatted = 'Rp ' . number_format($detectedUnitPriceInfo['unit_price'], 0, ',', '.');
                    $unitLabel = $detectedUnitPriceInfo['unit'];

                    if (stripos($replyContent, (string) $detectedUnitPriceInfo['unit_price']) === false && stripos($replyContent, $unitPriceFormatted) === false) {
                        $detailSnippet = " ({$qtyFormatted} {$unitLabel} @ {$unitPriceFormatted})";
                        if (preg_match('/(Rp\s*[\d\.,]+)/i', $replyContent, $rpm)) {
                            $replyContent = preg_replace('/' . preg_quote($rpm[1], '/') . '/', $rpm[1] . $detailSnippet, $replyContent, 1);
                        }
                    }
                }

                if ($hasUpdate) {
                    $replyContent = preg_replace('/Transaksi\s+dicatat\s*:/i', 'Transaksi berhasil diperbarui:', $replyContent);
                    if (empty(trim($replyContent)) || preg_match('/(?:Terjadi kendala saat menghubungi layanan AI|cURL error)/i', $replyContent) || preg_match('/(?:Koreksi tanggal\. Selesai\.)/i', $replyContent)) {
                        $firstUp = collect($recordedTransactions)->firstWhere('action', 'updated');
                        $upDate = $firstUp['date'] ? \Carbon\Carbon::parse($firstUp['date'])->translatedFormat('d F Y') : 'hari ini';
                        $shortId = '#' . substr($firstUp['id'], 0, 8);
                        $replyContent = "Transaksi {$shortId} ('{$firstUp['description']}') telah berhasil diperbarui ke tanggal {$upDate}.";
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
            } elseif ($isExplicitCreate && !empty($parsedActions)) {
                // Jika pengguna meminta pencatatan transaksi tetapi eksekusi database gagal,
                // cegah asisten memberikan klaim berhasil palsu
                if (preg_match('/(?:Tercatat|Berhasil\s+dicatat|Sudah\s+dicatat)/i', $replyContent)) {
                    $replyContent = "Terjadi kendala saat menyimpan mutasi transaksi ke dalam sistem. Mohon coba ulangi instruksi Anda.";
                }
            }
        }

        // Jika pengguna menyatakan koreksi/salah tapi belum menyebutkan rincian nilai pengganti yang baru,
        // sebutkan transaksi mutasi terakhir yang relevan beserta ID-nya agar pengguna mudah memperbaikinya.
        if ($isCorrectionIntent && empty($recordedTransactions)) {
            $lastTx = $this->resolveLastRecordedTransaction($conversation->id, $tenantId);
            if ($lastTx) {
                $lastShortId = '#' . substr($lastTx->id, 0, 8);
                if (stripos($replyContent, $lastShortId) === false) {
                    $amtStr = MoneyHelper::formatIdr($lastTx->amount_minor);
                    $replyContent .= "\n\nTransaksi terakhir yang terdeteksi: {$lastShortId} ('{$lastTx->description}', {$amtStr}). Anda dapat menyebutkan bagian yang ingin diperbaiki (misal: 'ubah {$lastShortId} nominal jadi 40rb' atau 'ganti bakso jadi 15rb').";
                }
            }
        }

        // 6b. Deteksi dan Eksekusi Tindakan Tagihan & Pengingat (Reminder Actions)
        $recordedReminders = [];
        [$parsedReminderActions, $cleanedFromReminder] = $this->extractReminderActions($replyContent, $userMessageText);

        if (!empty($parsedReminderActions)) {
            $this->ensureReminderSchema();
            foreach ($parsedReminderActions as $remAction) {
                try {
                    $rawRemAction = strtolower($remAction['action'] ?? 'create');
                    $remTitle = trim($remAction['title'] ?? '');
                    if (empty($remTitle)) {
                        $remTitle = 'Tagihan Rutin';
                    }

                    $remAmount = (int) ($remAction['amount_minor'] ?? 0);
                    if ($remAmount <= 0 && !empty($remAction['amount'])) {
                        $remAmount = (int) round((float) $remAction['amount']);
                    }
                    if ($remAmount <= 0) {
                        $remAmount = $this->parseIndonesianNominal($userMessageTrimmed);
                    }

                    $remInterval = strtolower($remAction['repeat_interval'] ?? 'monthly');
                    if (!in_array($remInterval, ['once', 'daily', 'weekly', 'monthly', 'yearly'])) {
                        $remInterval = 'monthly';
                    }

                    $remDueDate = $this->calculateNextDueDate($remAction['due_date'] ?? null, $remInterval, $userMessageTrimmed);

                    // A. AKSI HAPUS TAGIHAN
                    if ($rawRemAction === 'delete') {
                        $targetRem = null;
                        if (!empty($remAction['reminder_id'])) {
                            $targetRem = FinanceReminder::where('tenant_id', $tenantId)->find($remAction['reminder_id']);
                        }
                        if (!$targetRem && !empty($remTitle)) {
                            $targetRem = FinanceReminder::where('tenant_id', $tenantId)
                                ->where('title', 'like', '%' . $remTitle . '%')
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }
                        if ($targetRem) {
                            $deletedTitle = $targetRem->title;
                            $deletedAmount = $targetRem->amount_minor;
                            $targetRem->delete();
                            $recordedReminders[] = [
                                'id' => $targetRem->id,
                                'action' => 'deleted',
                                'title' => $deletedTitle,
                                'amount_minor' => $deletedAmount,
                                'due_date' => $targetRem->due_date ? \Carbon\Carbon::parse($targetRem->due_date)->format('Y-m-d') : null,
                                'repeat_interval' => $targetRem->repeat_interval,
                            ];
                        }
                        continue;
                    }

                    // B. AKSI PERBARUI / EDIT TAGIHAN (UPDATE)
                    if ($rawRemAction === 'update') {
                        $targetRem = null;
                        if (!empty($remAction['reminder_id'])) {
                            $targetRem = FinanceReminder::where('tenant_id', $tenantId)->find($remAction['reminder_id']);
                        }
                        if (!$targetRem && !empty($remTitle)) {
                            $targetRem = FinanceReminder::where('tenant_id', $tenantId)
                                ->where('title', 'like', '%' . $remTitle . '%')
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }
                        if ($targetRem) {
                            if ($remAmount > 0) $targetRem->amount_minor = $remAmount;
                            if (!empty($remTitle)) $targetRem->title = $remTitle;
                            if (!empty($remDueDate)) $targetRem->due_date = $remDueDate;
                            if (!empty($remInterval)) $targetRem->repeat_interval = $remInterval;
                            $targetRem->updated_by = auth()->id() ?: null;
                            $targetRem->save();

                            $recordedReminders[] = [
                                'id' => $targetRem->id,
                                'action' => 'updated',
                                'title' => $targetRem->title,
                                'amount_minor' => $targetRem->amount_minor,
                                'due_date' => $targetRem->due_date ? \Carbon\Carbon::parse($targetRem->due_date)->format('Y-m-d') : null,
                                'repeat_interval' => $targetRem->repeat_interval,
                            ];
                            continue;
                        }
                    }

                    // C. AKSI JADWALKAN TAGIHAN BARU (CREATE)
                    if ($remAmount <= 0) {
                        continue;
                    }

                    $reminder = new FinanceReminder();
                    $reminder->id = (string) Str::uuid();
                    $reminder->tenant_id = $tenantId;
                    $reminder->title = $remTitle;
                    $reminder->amount_minor = $remAmount;
                    $reminder->currency = 'IDR';
                    $reminder->due_date = $remDueDate;
                    $reminder->repeat_interval = $remInterval;
                    $reminder->description = $remAction['description'] ?? "Jadwal {$remTitle} {$remInterval}";
                    $reminder->status = 'pending';
                    $reminder->created_by = auth()->id() ?: null;

                    // Cari kategori tagihan/utilitas yang cocok
                    $category = FinanceCategory::where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->where('category_type', 'expense')
                        ->where(function ($q) use ($remTitle) {
                            $q->where('name', 'like', '%Tagihan%')
                              ->orWhere('name', 'like', '%Utilitas%')
                              ->orWhere('name', 'like', '%Server%')
                              ->orWhere('name', 'like', '%Langganan%')
                              ->orWhere('name', 'like', '%Operasional%');
                        })->first();

                    if ($category) {
                        $reminder->category_id = $category->id;
                    }

                    $reminder->save();

                    $recordedReminders[] = [
                        'id' => $reminder->id,
                        'action' => 'created',
                        'title' => $reminder->title,
                        'amount_minor' => $reminder->amount_minor,
                        'due_date' => $reminder->due_date ? \Carbon\Carbon::parse($reminder->due_date)->format('Y-m-d') : null,
                        'repeat_interval' => $reminder->repeat_interval,
                    ];
                } catch (\Throwable $e) {
                    Log::error('[TenantAssistantService] Gagal mengeksekusi aksi tagihan/pengingat: ' . $e->getMessage());
                }
            }

            if (!empty($recordedReminders)) {
                $meta['recorded_reminders'] = $recordedReminders;
                $replyContent = $cleanedFromReminder;

                // Cegah respon halusinasi "Fitur tambah jadwal tagihan via chat belum tersedia"
                if (preg_match('/Fitur\s+tambah\s+jadwal\s+tagihan\s+via\s+chat\s+belum\s+tersedia/i', $replyContent) ||
                    preg_match('/Input\s+manual\s+lewat\s+menu\s+Tagihan/i', $replyContent) ||
                    empty(trim($replyContent))) {
                    $firstRem = $recordedReminders[0];
                    $nominalFormatted = MoneyHelper::formatIdr((int) $firstRem['amount_minor']);
                    $dueDateFormatted = $firstRem['due_date'] ? \Carbon\Carbon::parse($firstRem['due_date'])->translatedFormat('d F Y') : 'sesuai jadwal';
                    $intervalLabel = match ($firstRem['repeat_interval'] ?? 'monthly') {
                        'monthly' => 'setiap bulan',
                        'weekly' => 'setiap minggu',
                        'yearly' => 'setiap tahun',
                        'daily' => 'setiap hari',
                        default => 'sekali',
                    };
                    $replyContent = "Baik, jadwal tagihan {$firstRem['title']} sebesar {$nominalFormatted} (jatuh tempo {$dueDateFormatted}, {$intervalLabel}) telah berhasil dimasukkan ke dalam daftar Tagihan & Pengingat.";
                }
            }
        }

        // 6c. Deteksi dan Eksekusi Tindakan Tabungan (Savings Actions)
        $recordedSavings = [];
        [$parsedSavingsActions, $cleanedFromSavings] = $this->extractSavingsActions($replyContent, $userMessageText);

        if (!empty($parsedSavingsActions)) {
            $this->ensureSavingsSchema();
            foreach ($parsedSavingsActions as $savAction) {
                try {
                    $rawSavAction = strtolower($savAction['action'] ?? 'create');
                    $savName = trim($savAction['name'] ?? '');
                    if (empty($savName)) {
                        $savName = 'Tabungan Impian';
                    }

                    // A. AKSI HAPUS TARGET TABUNGAN
                    if ($rawSavAction === 'delete') {
                        $targetSav = null;
                        if (!empty($savAction['savings_id'])) {
                            $targetSav = FinanceSavings::where('tenant_id', $tenantId)->find($savAction['savings_id']);
                        }
                        if (!$targetSav && !empty($savName)) {
                            $targetSav = FinanceSavings::where('tenant_id', $tenantId)
                                ->where('name', 'like', '%' . $savName . '%')
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }
                        if ($targetSav) {
                            $deletedName = $targetSav->name;
                            $deletedTarget = $targetSav->target_amount_minor;
                            $targetSav->delete();
                            $recordedSavings[] = [
                                'id' => $targetSav->id,
                                'action' => 'deleted',
                                'name' => $deletedName,
                                'target_amount_minor' => $deletedTarget,
                            ];
                        }
                        continue;
                    }

                    // B. AKSI MENABUNG / SETOR DANA KE TABUNGAN (DEPOSIT)
                    if ($rawSavAction === 'deposit') {
                        $depAmount = (int) ($savAction['amount_minor'] ?? 0);
                        if ($depAmount <= 0 && !empty($savAction['amount'])) {
                            $depAmount = (int) round((float) $savAction['amount']);
                        }
                        if ($depAmount <= 0) {
                            $depAmount = $this->parseIndonesianNominal($userMessageTrimmed);
                        }
                        if ($depAmount <= 0) {
                            if (preg_match('/(?:sebesar|senilai|total)?\s*(?:Rp\.?|IDR)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i', $replyContent, $sam)) {
                                $depAmount = $this->parseIndonesianNominal($sam[1]);
                            }
                        }

                        if ($depAmount > 0) {
                            $targetSav = null;
                            if (!empty($savAction['savings_id'])) {
                                $targetSav = FinanceSavings::where('tenant_id', $tenantId)->find($savAction['savings_id']);
                            }
                            if (!$targetSav && !empty($savName)) {
                                $targetSav = FinanceSavings::where('tenant_id', $tenantId)
                                    ->where('name', 'like', '%' . $savName . '%')
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                            }
                            // Jika belum ada pos tabungan bernama itu, buatkan pos tabungan baru otomatis
                            if (!$targetSav) {
                                $targetSav = new FinanceSavings();
                                $targetSav->id = (string) Str::uuid();
                                $targetSav->tenant_id = $tenantId;
                                $targetSav->name = $savName;
                                $targetSav->target_amount_minor = $depAmount * 10;
                                $targetSav->current_amount_minor = 0;
                                $targetSav->currency = 'IDR';
                                $targetSav->start_date = now()->toDateString();
                                $targetSav->status = 'active';
                                $targetSav->created_by = auth()->id() ?: null;
                                $targetSav->save();
                            }

                            $targetSav->current_amount_minor += $depAmount;
                            if ($targetSav->target_amount_minor > 0 && $targetSav->current_amount_minor >= $targetSav->target_amount_minor) {
                                $targetSav->status = 'completed';
                            }
                            $targetSav->save();

                            // Catat transaksi mutasi kas bertipe savings agar buku kas sinkron
                            try {
                                $this->transactionService->createTransaction([
                                    'type' => 'savings',
                                    'amount_minor' => $depAmount,
                                    'description' => "Setoran Tabungan: {$targetSav->name}",
                                    'transaction_date' => now()->toDateString(),
                                    'notes' => 'Setoran tabungan via Asisten AI',
                                ]);
                            } catch (\Throwable $te) {
                                Log::warning('[TenantAssistantService] Gagal mencatat transaksi kas tabungan: ' . $te->getMessage());
                            }

                            $recordedSavings[] = [
                                'id' => $targetSav->id,
                                'action' => 'deposited',
                                'name' => $targetSav->name,
                                'amount_minor' => $depAmount,
                                'current_amount_minor' => $targetSav->current_amount_minor,
                                'target_amount_minor' => $targetSav->target_amount_minor,
                                'progress_percent' => $targetSav->progress_percent,
                            ];
                        }
                        continue;
                    }

                    // C. AKSI MENARIK DANA TABUNGAN (WITHDRAW)
                    if ($rawSavAction === 'withdraw') {
                        $wthAmount = (int) ($savAction['amount_minor'] ?? 0);
                        if ($wthAmount <= 0) {
                            $wthAmount = $this->parseIndonesianNominal($userMessageTrimmed);
                        }
                        if ($wthAmount <= 0) {
                            if (preg_match('/(?:sebesar|senilai|total)?\s*(?:Rp\.?|IDR)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i', $replyContent, $sam)) {
                                $wthAmount = $this->parseIndonesianNominal($sam[1]);
                            }
                        }

                        if ($wthAmount > 0) {
                            $targetSav = FinanceSavings::where('tenant_id', $tenantId)
                                ->where('name', 'like', '%' . $savName . '%')
                                ->orderBy('created_at', 'desc')
                                ->first();

                            if ($targetSav) {
                                $targetSav->current_amount_minor = max(0, $targetSav->current_amount_minor - $wthAmount);
                                if ($targetSav->status === 'completed' && $targetSav->current_amount_minor < $targetSav->target_amount_minor) {
                                    $targetSav->status = 'active';
                                }
                                $targetSav->save();

                                try {
                                    $this->transactionService->createTransaction([
                                        'type' => 'income',
                                        'amount_minor' => $wthAmount,
                                        'description' => "Penarikan Tabungan: {$targetSav->name}",
                                        'transaction_date' => now()->toDateString(),
                                        'notes' => 'Penarikan tabungan via Asisten AI',
                                    ]);
                                } catch (\Throwable $te) {
                                    Log::warning('[TenantAssistantService] Gagal mencatat transaksi penarikan tabungan: ' . $te->getMessage());
                                }

                                $recordedSavings[] = [
                                    'id' => $targetSav->id,
                                    'action' => 'withdrawn',
                                    'name' => $targetSav->name,
                                    'amount_minor' => $wthAmount,
                                    'current_amount_minor' => $targetSav->current_amount_minor,
                                    'target_amount_minor' => $targetSav->target_amount_minor,
                                    'progress_percent' => $targetSav->progress_percent,
                                ];
                            }
                        }
                        continue;
                    }

                    // D. AKSI MEMBUAT TARGET TABUNGAN BARU (CREATE)
                    $targetAmount = (int) ($savAction['target_amount_minor'] ?? 0);
                    if ($targetAmount <= 0 && !empty($savAction['amount'])) {
                        $targetAmount = (int) round((float) $savAction['amount']);
                    }
                    if ($targetAmount <= 0) {
                        $targetAmount = $this->parseIndonesianNominal($userMessageTrimmed);
                    }
                    if ($targetAmount <= 0) {
                        if (preg_match('/(?:target|sebesar|senilai)?\s*(?:Rp\.?|IDR)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i', $replyContent, $sam)) {
                            $targetAmount = $this->parseIndonesianNominal($sam[1]);
                        }
                    }

                    if ($targetAmount < 0) {
                        $targetAmount = 0;
                    }

                    $initialAmount = (int) ($savAction['current_amount_minor'] ?? 0);
                    $targetDate = !empty($savAction['target_date']) ? $savAction['target_date'] : null;

                    $savings = new FinanceSavings();
                    $savings->id = (string) Str::uuid();
                    $savings->tenant_id = $tenantId;
                    $savings->name = $savName;
                    $savings->target_amount_minor = $targetAmount;
                    $savings->current_amount_minor = $initialAmount;
                    $savings->currency = 'IDR';
                    $savings->start_date = now()->toDateString();
                    $savings->target_date = $targetDate;
                    $savings->notes = $savAction['notes'] ?? "Target tabungan {$savName}";
                    $savings->status = ($initialAmount >= $targetAmount && $targetAmount > 0) ? 'completed' : 'active';
                    $savings->created_by = auth()->id() ?: null;
                    $savings->save();

                    $recordedSavings[] = [
                        'id' => $savings->id,
                        'action' => 'created',
                        'name' => $savings->name,
                        'target_amount_minor' => $savings->target_amount_minor,
                        'current_amount_minor' => $savings->current_amount_minor,
                        'target_date' => $savings->target_date ? \Carbon\Carbon::parse($savings->target_date)->format('Y-m-d') : null,
                        'progress_percent' => $savings->progress_percent,
                    ];
                } catch (\Throwable $e) {
                    Log::error('[TenantAssistantService] Gagal mengeksekusi aksi tabungan: ' . $e->getMessage());
                }
            }

            if (!empty($recordedSavings)) {
                $meta['recorded_savings'] = $recordedSavings;
                $replyContent = $cleanedFromSavings;

                // Cegah respon halusinasi penolakan
                if (preg_match('/Fitur\s+(?:tambah|buat)?\s*tabungan\s+via\s+chat\s+belum\s+tersedia/i', $replyContent) ||
                    preg_match('/Input\s+manual\s+lewat\s+menu\s+Tabungan/i', $replyContent) ||
                    empty(trim($replyContent))) {
                    $firstSav = $recordedSavings[0];
                    if (($firstSav['action'] ?? '') === 'deposited') {
                        $amtStr = MoneyHelper::formatIdr((int) $firstSav['amount_minor']);
                        $currStr = MoneyHelper::formatIdr((int) $firstSav['current_amount_minor']);
                        $targetStr = MoneyHelper::formatIdr((int) $firstSav['target_amount_minor']);
                        $pct = $firstSav['progress_percent'] ?? 0;
                        $replyContent = "Baik, setoran sebesar {$amtStr} ke pos tabungan '{$firstSav['name']}' telah berhasil dicatat. Saldo saat ini: {$currStr} dari target {$targetStr} ({$pct}%).";
                    } elseif (($firstSav['action'] ?? '') === 'withdrawn') {
                        $amtStr = MoneyHelper::formatIdr((int) $firstSav['amount_minor']);
                        $currStr = MoneyHelper::formatIdr((int) $firstSav['current_amount_minor']);
                        $replyContent = "Baik, penarikan sebesar {$amtStr} dari pos tabungan '{$firstSav['name']}' telah berhasil dicatat. Sisa saldo tabungan: {$currStr}.";
                    } elseif (($firstSav['action'] ?? '') === 'deleted') {
                        $replyContent = "Pos tabungan '{$firstSav['name']}' telah berhasil dihapus dari sistem.";
                    } else {
                        $targetStr = MoneyHelper::formatIdr((int) $firstSav['target_amount_minor']);
                        $targetDateStr = !empty($firstSav['target_date']) ? ", target pencapaian " . \Carbon\Carbon::parse($firstSav['target_date'])->translatedFormat('d F Y') : '';
                        $replyContent = "Baik, target tabungan '{$firstSav['name']}' sebesar {$targetStr}{$targetDateStr} telah berhasil dibuat ke dalam daftar Tabungan Impian.";
                    }
                }
            }
        }

        // 7. Bersihkan kalimat penutup klise repetitif (seperti "Ada transaksi lain?", "Transaksi lain?")
        $replyContent = $this->cleanRepetitiveClosingPhrases($replyContent);

        // 7a. Pastikan format teks sistem internal tidak pernah bocor ke antarmuka pengguna
        $replyContent = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
        $replyContent = preg_replace('/ID:\s*`?[a-f0-9\-]{8,36}`?\s*(?:\(contoh\s+ID\s+baru\))?\.?/i', '', $replyContent);
        $replyContent = preg_replace('/->\s*`?skipped:[^`\n]+`?\.?/i', '', $replyContent);
        $replyContent = trim(preg_replace('/\n{3,}/', "\n\n", $replyContent));

        // 7b. Ekstraksi atau Pembuatan Visualisasi Grafik Apache ECharts
        $chartOption = $this->extractChartOption($replyContent, $userMessageText, $tenantId);
        if (!empty($chartOption)) {
            $meta['chart_option'] = $chartOption;
        }

        // 8. Simpan respon asisten
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
     * Mengekstrak instruksi pencatatan transaksi baik dari blok JSON terstruktur,
     * format info sistem teknis, narasi konfirmasi asisten, maupun dari pesan eksplisit pengguna.
     */
    protected function extractTransactionActions(string $replyContent, string $userMessageText, ?string $conversationId = null, ?string $tenantId = null): array
    {
        // 0. Cegah pencatatan mutasi transaksi jika pesan pengguna jelas berupa pertanyaan informasional atau keluhan/pertanyaan klarifikasi
        $isInformationalQuery = (bool) preg_match('/^\s*(berapa|apa|apakah|kapan|cek|info|lihat|tampilkan|daftar)\b/i', $userMessageText)
            && !preg_match('/\b(catat|catet|nyatet|masuk|simpan|input|buatkan mutasi|bayar|ubah|ganti|ralat)\b/i', $userMessageText);

        $isNegatedOrQuestion = (bool) preg_match('/\b(belum|jangan|tidak|bukan|kok|kenapa|mengapa)\s+(?:pernah\s+)?(?:di)?(?:catat|catet|simpan|masuk|input|perbarui|update)\b/i', $userMessageText)
            && !preg_match('/\b(tolong\s+(?:catat|catet)|sekarang\s+(?:catat|catet)|catat\s+ulang|catet\s+ulang|masukkan\s+ulang|buatkan\s+baru)\b/i', $userMessageText);

        $isReminderScheduleIntent = (bool) preg_match('/\b(?:masuk(?:in|kan)?|jadwal(?:kan)?|tambah(?:kan)?|catat|catet|simpan|buat(?:kan)?|ingatkan)\s+(?:ini\s+)?(?:ke\s+tagihan|tagihan|jadwal\s+tagihan|pengingat)\b/i', $userMessageText)
            || (bool) preg_match('/\b(jadwal(?:kan)?|pengingat)\s+tagihan\b/i', $userMessageText)
            || (bool) preg_match('/\btagihan\b.*?\b(?:setiap|tiap|jatuh\s+tempo|bulanan|mingguan|tahunan)\b/i', $userMessageText)
            || (bool) preg_match('/\btagihan\s+([A-Za-z0-9\s]{2,30}?)\s+(?:sebesar\s+)?(?:rp\.?|idr)?\s*[\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?/i', $userMessageText);

        $isSavingsIntent = (bool) preg_match('/\b(?:buat(?:kan)?|tambah(?:kan)?|bikin|pos)\s+(?:target\s+)?tabungan\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:nabung|menabung|setor|setorkan|tabung|simpan)\s+.*?\b(?:ke|untuk|buat|di)\s+(?:tabungan|pos\s+tabungan)\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:nabung|menabung|tabung)\s+(?:sebesar\s+)?(?:rp\.?|idr)?\s*[\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?/i', $userMessageText)
            || (bool) preg_match('/\b(?:tarik|ambil)\s+.*?dari\s+tabungan\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:hapus|batalkan)\s+(?:target\s+)?tabungan\b/i', $userMessageText);

        if ($isInformationalQuery || $isNegatedOrQuestion || $isReminderScheduleIntent || $isSavingsIntent) {
            // Bersihkan blok kode JSON action jika model AI keliru menyertakannya saat menjawab pertanyaan informasi atau penjadwalan tagihan/tabungan
            $cleaned = preg_replace('/```(?:json:transaction_action|transaction_action)\s*[\s\S]*?```/is', '', $replyContent);
            $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $cleaned);
            return [[], trim($cleaned ?: $replyContent)];
        }

        // 1. Ekstraksi Primer: Blok ```json:transaction_action ... ``` atau ```transaction_action ... ``` atau ```json ... ```
        if (preg_match('/```(?:json:transaction_action|transaction_action|json)?\s*([\s\S]*?)\s*```/i', $replyContent, $fenceMatches)) {
            $codeBlock = trim($fenceMatches[1]);
            if (preg_match('/(\[\s*[\s\S]*\s*\]|\{\s*[\s\S]*\s*\})/s', $codeBlock, $jsonMatches)) {
                $rawJson = trim($jsonMatches[1]);
                $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', $rawJson);
                $parsed = json_decode($cleanJson, true);
                if (is_array($parsed) && !empty($parsed)) {
                    $firstElement = isset($parsed[0]) ? $parsed[0] : $parsed;
                    $isTxPayload = isset($firstElement['type'])
                        || isset($firstElement['amount_minor'])
                        || (isset($firstElement['action']) && in_array(strtolower($firstElement['action']), ['create', 'update', 'delete']) && !isset($firstElement['repeat_interval']) && !isset($firstElement['target_amount_minor']));
                    if ($isTxPayload) {
                        $cleaned = trim(str_replace($fenceMatches[0], '', $replyContent));
                        $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $cleaned);
                        return [isset($parsed['type']) || isset($parsed['action']) ? [$parsed] : $parsed, trim($cleaned)];
                    }
                }
            }
        }

        // 2. Ekstraksi Sekunder: JSON array mentah tanpa markdown fence
        if (preg_match('/(\[\s*\{\s*"(?:type|action)"\s*:[\s\S]*?"amount_minor"\s*:[\s\S]*?\}\s*\])/i', $replyContent, $matches)) {
            $rawJson = trim($matches[1]);
            $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', $rawJson);
            $parsed = json_decode($cleanJson, true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $cleaned);
                return [$parsed, trim($cleaned)];
            }
        }

        // 3. Ekstraksi Aksi Koreksi / Ralat / Ubah Transaksi (UPDATE Intent dari Bahasa Alami Pengguna atau Asisten)
        $isUpdateOrCorrectionIntent = (bool) preg_match('/\b(ubah|rubah|ganti|ralat|koreksi|revisi|edit|ko\s+tanggal|kok\s+tanggal|masih\s+tanggal|bukan\s+tanggal|sekarang\s+kan\s+tanggal)\b/i', $userMessageText)
            || (bool) preg_match('/bukan\b.*?\btapi\b/i', $userMessageText)
            || (bool) preg_match('/\b(diubah|berhasil\s+diubah|berhasil\s+diperbarui|telah\s+diperbarui)\b/i', $replyContent);

        if ($isUpdateOrCorrectionIntent) {
            $candidateTxId = null;
            if (preg_match('/(?:#|id\s*:?\s*|transaksi\s+(?:id\s*:?\s*)?)([a-f0-9]{8,36})\b/i', $userMessageText, $uidM)) {
                $candidateTxId = $uidM[1];
            } elseif (preg_match('/#([a-f0-9]{6,36})\b/i', $userMessageText, $uidM2)) {
                $candidateTxId = $uidM2[1];
            } elseif (preg_match('/\bID\s*`?([a-f0-9]{8,36})`?/i', $replyContent, $ridM)) {
                $candidateTxId = $ridM[1];
            }

            $currentTenantId = $tenantId ?: TenantContext::id();
            $targetTx = null;
            if ($candidateTxId && $currentTenantId) {
                $targetTx = FinanceTransaction::where('tenant_id', $currentTenantId)
                    ->where(function ($q) use ($candidateTxId) {
                        $q->where('id', $candidateTxId)
                          ->orWhere('id', 'like', $candidateTxId . '%');
                    })->first();
            }

            if (!$targetTx && $currentTenantId) {
                $targetTx = $this->resolveLastRecordedTransaction($conversationId, $currentTenantId);
            }

            if ($targetTx) {
                $isDateChange = (bool) preg_match('/\b(tanggal|tgl|hari\s+ini|sekarang|kemarin)\b/i', $userMessageText)
                    || (bool) preg_match('/(?:diubah|koreksi\s+tanggal)\s+(\d{4}-\d{2}-\d{2})/i', $replyContent);

                $resolvedDate = null;
                if ($isDateChange) {
                    $resolvedDate = $this->resolveTransactionDate($userMessageText, null, $targetTx->transaction_date);
                }

                $resolvedAmount = null;
                if (preg_match('/(?:nominal|harga|sebesar|senilai|jumlah|jadi)\s*(?:sebesar\s+)?(?:rp\.?|idr)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i', $userMessageText, $amtM)) {
                    $parsedAmt = $this->parseIndonesianNominal($amtM[1]);
                    if ($parsedAmt > 0) {
                        $resolvedAmount = $parsedAmt;
                    }
                }

                $resolvedDesc = null;
                if (preg_match('/(?:ganti|ubah)\s+(?:nama|deskripsi|keterangan)\s+(?:jadi|menjadi)\s+([a-zA-Z0-9\s]+?)(?:\.|$)/i', $userMessageText, $descM)) {
                    $resolvedDesc = trim($descM[1]);
                }

                if ($resolvedDate || $resolvedAmount || $resolvedDesc) {
                    $updateAction = [
                        'action' => 'update',
                        'transaction_id' => $targetTx->id,
                    ];
                    if ($resolvedDate) {
                        $updateAction['date'] = $resolvedDate;
                    }
                    if ($resolvedAmount) {
                        $updateAction['amount_minor'] = $resolvedAmount;
                    }
                    if ($resolvedDesc) {
                        $updateAction['description'] = $resolvedDesc;
                    }

                    $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
                    return [[$updateAction], trim($cleaned)];
                }
            }
        }

        // 4. Ekstraksi Format [Info Sistem: Mutasi yang diproses: ...] (Jika model AI menghasilkan string format info sistem teknis)
        if (preg_match('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?):\s*(.*?)(?:\]\s*\.?|$)/is', $replyContent, $infoMatches)) {
            $infoRaw = $infoMatches[1];
            $cleaned = trim(str_replace($infoMatches[0], '', $replyContent));

            $parsedItems = [];
            if (preg_match('/items:\s*(\[\s*\{.*?\}\s*\])/is', $infoRaw, $im)) {
                $rawItemsStr = $im[1];
                $jsonCand = preg_replace('/(?<=\{|\,)\s*([a-zA-Z0-9_]+)\s*:/', '"$1":', $rawItemsStr);
                $jsonCand = preg_replace("/'([^']*)'/", '"$1"', $jsonCand);
                $decodedItems = json_decode($jsonCand, true);
                if (is_array($decodedItems) && !empty($decodedItems)) {
                    $parsedItems = $decodedItems;
                }
            }

            $amountMinor = 0;
            $infoWithoutItems = preg_replace('/items:\s*\[.*?\]/is', '', $infoRaw);
            if (preg_match('/(?:Rp\.?|IDR)?\s*([\d\.,]+)/i', $infoWithoutItems, $am)) {
                $amountMinor = (int) preg_replace('/[^\d]/', '', $am[1]);
            }
            if ($amountMinor <= 0 && !empty($parsedItems)) {
                foreach ($parsedItems as $pit) {
                    $amountMinor += (int) ($pit['total_minor'] ?? (($pit['price_minor'] ?? 0) * ($pit['quantity'] ?? 1)));
                }
            }

            $txDate = $this->resolveTransactionDate($userMessageText, preg_match('/(\d{4}-\d{2}-\d{2})/', $infoRaw, $dm) ? $dm[1] : null);

            $action = 'create';
            if (preg_match('/status:\s*(created|updated|deleted|dicatat|diperbarui|dihapus)/i', $infoRaw, $sm)) {
                $st = strtolower($sm[1]);
                if (in_array($st, ['updated', 'diperbarui'])) $action = 'update';
                elseif (in_array($st, ['deleted', 'dihapus'])) $action = 'delete';
            }

            $txId = null;
            if (preg_match('/ID:\s*([a-f0-9\-]+)/i', $infoRaw, $idm)) {
                $txId = trim($idm[1]);
            }

            $description = '';
            $merchantName = null;
            if (preg_match('/\((.*?)(?:,\s*Rp|,\s*\d{4}|,\s*status|$)/i', $infoRaw, $dcm)) {
                $descRaw = trim($dcm[1]);
                if (preg_match('/^(.*?)\s+di\s+(.*?)$/i', $descRaw, $dmm)) {
                    $description = trim($dmm[1]);
                    $candMerchant = trim($dmm[2], '[] ');
                    if (!preg_match('/(?:tidak disebutkan|unknown|none|null|\-)/i', $candMerchant)) {
                        $merchantName = $candMerchant;
                    }
                } else {
                    $description = $descRaw;
                }
            }
            if (empty($description)) {
                $description = 'Pengeluaran via Asisten AI';
            }

            if ($amountMinor > 0) {
                $txAction = [
                    'action' => $action,
                    'type' => 'expense',
                    'description' => $description,
                    'merchant_name' => $merchantName,
                    'amount_minor' => $amountMinor,
                    'date' => $txDate,
                    'category' => null,
                    'items' => $parsedItems,
                ];
                if ($action !== 'create' && $txId && strlen($txId) >= 8) {
                    $txAction['transaction_id'] = $txId;
                }

                return [[$txAction], $cleaned];
            }
        }

        // 5. Ekstraksi Cerdas: Permintaan Eksplisit Pencatatan Transaksi dari Pengguna atau Konfirmasi AI
        $isUserAskingToRecord = !$isNegatedOrQuestion && (bool) preg_match('/\b(masuk(?:in|kan)?|catat(?:kan|in)?|catet(?:in)?|nyatet|tambah(?:kan)?|simpan|input|buatkan\s+mutasi|beli|makan|minum|jajan|belanja|bayar|sarapan|ngopi|kopi|isi\s+bensin|bensin|pertamax|pertalite|solar|parkir|ongkir|pulsa|paket\s+data|listrik|token|obat|gaji|honor|upah|transfer)\b/i', $userMessageText);
        $isAiConfirmingRecord = (bool) preg_match('/\b(catat|mencatat|dicatat|berhasil\s+dicatat|dimasukkan|memasukkan|telah\s+masuk|disimpan|menyimpan|didaftarkan|tersimpan|dibukukan|berhasil\s+disimpan)\b/i', $replyContent);

        if ($isUserAskingToRecord || $isAiConfirmingRecord) {
            // A. Urai item-item transaksi dari teks pesan pengguna
            $userItems = $this->parseUserExpenseItems($userMessageText);

            // B. Ekstraksi nominal & kategori dari balasan konfirmasi AI atau pesan pengguna
            $narrativeAmountMinor = 0;
            if (preg_match('/(?:total\s+(?:sebesar\s+)?|sebesar\s+|senilai\s+)?(?:Rp\.?|IDR)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)\s*(?:tanggal|\btanggal\b|ke\s+kategori|dicatat|dimasukkan|berhasil|tersimpan)/i', $replyContent, $totM)) {
                $narrativeAmountMinor = $this->parseIndonesianNominal($totM[1]);
            }
            if ($narrativeAmountMinor <= 0 && preg_match('/(?:total|sebesar|senilai)\s+(?:Rp\.?|IDR)?\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i', $replyContent, $totM2)) {
                $narrativeAmountMinor = $this->parseIndonesianNominal($totM2[1]);
            }
            if ($narrativeAmountMinor <= 0 && preg_match('/(?:Rp\.?|IDR)\s*([\d\.,]+)/i', $replyContent, $totM3)) {
                $narrativeAmountMinor = (int) preg_replace('/[^\d]/', '', $totM3[1]);
            }
            if ($narrativeAmountMinor <= 0) {
                $narrativeAmountMinor = $this->parseIndonesianNominal($userMessageText);
            }

            $categoryCandidate = null;
            if (preg_match('/kategori\s+([A-Za-z0-9\s&]+?)(?:\.|\,|$|\s+tanggal|\s+sebesar)/i', $replyContent, $catM)) {
                $categoryCandidate = trim($catM[1]);
            }

            $txDate = $this->resolveTransactionDate($userMessageText);

            $merchantCandidate = null;
            if (preg_match('/\bdi\s+([A-Za-z0-9\s]{2,35}?)(?:\s+(?:sebesar|seharga|senilai|\d|rp|\,|$|\.))/i', $userMessageText . ' ' . $replyContent, $umMatch)) {
                $cand = trim($umMatch[1]);
                if (!preg_match('/^(hari|sini|situ|sana|atas|bawah|dalam|luar|kemarin|tadi)$/i', $cand)) {
                    $merchantCandidate = ucwords($cand);
                }
            }

            // Jika user menyebutkan beberapa item belanjaan secara spesifik
            if (!empty($userItems)) {
                $itemsTotal = array_sum(array_column($userItems, 'total_minor'));
                $finalAmount = $narrativeAmountMinor > 0 ? $narrativeAmountMinor : $itemsTotal;

                $itemNames = array_map(fn($it) => $it['item_name'], array_slice($userItems, 0, 3));
                $desc = 'Beli ' . implode(', ', $itemNames);
                if (count($userItems) > 3) {
                    $desc .= ' dan ' . (count($userItems) - 3) . ' item lainnya';
                }

                if (!empty($merchantCandidate) && stripos($desc, $merchantCandidate) === false) {
                    $desc .= " di {$merchantCandidate}";
                }

                $shortTitle = $this->generateShortTransactionSummary($desc, $userMessageText, $merchantCandidate);
                $txAction = [
                    'action' => 'create',
                    'type' => 'expense',
                    'description' => $shortTitle,
                    'notes' => $desc,
                    'merchant_name' => $merchantCandidate,
                    'amount_minor' => $finalAmount,
                    'date' => $txDate,
                    'category' => $categoryCandidate,
                    'items' => $userItems,
                ];

                $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
                $cleaned = preg_replace('/ID:\s*`?[a-f0-9\-]{8,36}`?\s*(?:\(contoh\s+ID\s+baru\))?\.?/i', '', $cleaned);
                $cleaned = preg_replace('/->\s*`?skipped:[^`\n]+`?\.?/i', '', $cleaned);
                $cleaned = trim(preg_replace('/\n{3,}/', "\n\n", $cleaned));
                if (empty($cleaned) || preg_match('/^Transaksi\s+baru\s+dicatat/i', $cleaned)) {
                    $nominalFormatted = 'Rp ' . number_format($finalAmount, 0, ',', '.');
                    $catStr = $categoryCandidate ?: 'Makan dan Jajan';
                    $cleaned = "Pengeluaran {$shortTitle} sebesar {$nominalFormatted} tanggal {$txDate} dicatat ke kategori {$catStr}.";
                }

                return [[$txAction], trim($cleaned)];
            }

            // Jika rincian item tunggal atau nominal dari pesan user/AI valid
            if ($narrativeAmountMinor > 0) {
                $cleanUserText = trim($userMessageText);
                $cleanUserText = preg_replace('/(?:tolong|segera|mohon)?\s*(?:di)?(?:catat(?:kan|in)?|catet(?:in)?|simpan|masuk(?:in|kan)?|nyatet|input)\s*(?:ya|dong|min|om)?[\.!]?$/i', '', $cleanUserText);
                $cleanUserText = preg_replace('/^(?:tolong|segera|mohon)?\s*(?:di)?(?:catat(?:kan|in)?|catet(?:in)?|simpan|masuk(?:in|kan)?|nyatet|input)\s*/i', '', $cleanUserText);
                $cleanUserText = trim($cleanUserText, " \t\n\r\0\x0B.,;:-");

                $shortTitle = $this->generateShortTransactionSummary($cleanUserText ?: 'Pengeluaran', $userMessageText, $merchantCandidate);
                $isIncome = (bool) preg_match('/(pemasukan|gaji|income|transfer masuk|honor|upah)\b/i', $userMessageText);
                $type = $isIncome ? 'income' : 'expense';

                $txAction = [
                    'action' => 'create',
                    'type' => $type,
                    'description' => $shortTitle,
                    'notes' => $cleanUserText ?: $shortTitle,
                    'merchant_name' => $merchantCandidate,
                    'amount_minor' => $narrativeAmountMinor,
                    'date' => $txDate,
                    'category' => $categoryCandidate,
                    'items' => [
                        [
                            'item_name' => $shortTitle,
                            'quantity' => 1,
                            'price_minor' => $narrativeAmountMinor,
                            'total_minor' => $narrativeAmountMinor,
                            'discount_minor' => 0,
                        ],
                    ],
                ];

                $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
                return [[$txAction], trim($cleaned)];
            }
        }

            // C. Ekstraksi format list baris item standar ("- Item: Rp XX.XXX")
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
                        $shortTitle = $this->generateShortTransactionSummary($itemDesc, $userMessageText);
                        $transactions[] = [
                            'type' => $type,
                            'description' => $shortTitle,
                            'notes' => $itemDesc,
                            'amount_minor' => $amountMinor,
                            'date' => now()->toDateString(),
                            'category' => null,
                        ];
                    }
                }

                if (!empty($transactions)) {
                    $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
                    return [$transactions, trim($cleaned)];
                }
            }

        $cleaned = preg_replace('/\[(?:Info Sistem|System Info):\s*(?:Mutasi yang diproses|Processed transactions?).*?\]/is', '', $replyContent);
        return [[], trim($cleaned)];
    }

    /**
     * Mengekstrak rincian item belanja dan harga dari teks pesan pengguna bahasa Indonesia.
     * Contoh: "Saya tadi makan malam beli rebusan jagung 7rb dan kacang rebus 10rb dan tadi makan siang beli bakso 16rb, nasi putih 8rb, telor dadar dan kentang mustofa 7rb tolong catet ya"
     *
     * @return array<int, array{item_name: string, quantity: int|float, price_minor: int, total_minor: int, discount_minor: int}>
     */
    protected function parseUserExpenseItems(string $text): array
    {
        $text = trim($text);
        if (empty($text)) {
            return [];
        }

        // Hapus penutup umum seperti "tolong catet ya", "tolong catat ya", "catet ya", "masukin ya"
        $cleanedText = preg_replace('/(?:tolong|segera|mohon)?\s*(?:di)?(?:catat|catet|simpan|masuk(?:in|kan)?|nyatet|input)\s*(?:ya|dong|min|om)?[\.!]?$/i', '', $text);
        $cleanedText = trim($cleanedText);

        // Pola item dan nominal dalam bahasa Indonesia:
        // "<nama item> <angka nominal>"
        $pattern = '/(?:(?:dan|serta|sama|\,)\s+)?(?:(?:tadi\s+)?(?:makan\s+(?:siang|malam|pagi)\s+)?(?:beli\s+)?)?([a-zA-Z0-9\s\&\+\-\/\.]{2,80}?)\s+([0-9\.,]+(?:\s*(?:ribu|rb|k|juta|jt))|\b\d{4,8}\b)(?=(?:\s*(?:dan|serta|sama|\,|\.|\;|\btolong\b|\bcatat\b|\bcatet\b|\bya\b|\bbeli\b|\bdi\b|$)))/i';

        $items = [];
        if (preg_match_all($pattern, $cleanedText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $rawItemName = trim($match[1]);
                $rawNominal = trim($match[2]);

                // Bersihkan stopwords di awal nama item
                $rawItemName = preg_replace('/^(?:dan|serta|sama|lalu|juga|tadi|beli|makan|minum)\s+/i', '', $rawItemName);
                $rawItemName = preg_replace('/^(?:makan\s+(?:siang|malam|pagi)\s+)/i', '', $rawItemName);
                $rawItemName = preg_replace('/^(?:beli\s+)/i', '', $rawItemName);
                $rawItemName = preg_replace('/^(?:sarapan|jajan|jajanan)\s+/i', '', $rawItemName);
                $rawItemName = preg_replace('/\s+di\s+[a-zA-Z0-9\s]+$/i', '', $rawItemName);
                $rawItemName = trim($rawItemName);

                // Abaikan jika nama item adalah kata keterangan waktu atau umum
                if (in_array(strtolower($rawItemName), ['hari ini', 'kemarin', 'tadi', 'total', 'subtotal', 'uang', 'pengeluaran', 'makan malam', 'makan siang', 'makan pagi'])) {
                    continue;
                }

                $nominal = $this->parseIndonesianNominal($rawNominal);
                if ($nominal > 0 && strlen($rawItemName) >= 2) {
                    $items[] = [
                        'item_name' => ucwords($rawItemName),
                        'quantity' => 1,
                        'price_minor' => $nominal,
                        'total_minor' => $nominal,
                        'discount_minor' => 0,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * Menghasilkan nama transaksi ringkas (summary / singkatan) untuk field description,
     * seperti "Sarapan", "Makan Siang", "Makan Malam", "Beli Bensin", "Jajan", dll.
     */
    protected function generateShortTransactionSummary(string $rawDescription, string $userMessageText = '', ?string $merchantName = null): string
    {
        $source = strtolower(trim($userMessageText . ' ' . $rawDescription));

        // 1. Pola Waktu Makan & Minum
        if (preg_match('/\b(sarapan|makan\s+pagi)\b/i', $source)) {
            return 'Sarapan';
        }
        if (preg_match('/\bmakan\s+siang\b/i', $source)) {
            return 'Makan Siang';
        }
        if (preg_match('/\bmakan\s+malam\b/i', $source)) {
            return 'Makan Malam';
        }
        if (preg_match('/\b(ngopi|kopi|coffee|cafe)\b/i', $source)) {
            return 'Kopi';
        }
        if (preg_match('/\b(jajan|jajanan|kue|snack|camilan|gorengan)\b/i', $source)) {
            return 'Jajan';
        }

        // 2. Pola Transportasi & Bahan Bakar
        if (preg_match('/\b(bensin|bbm|pertamax|pertalite|solar|biosolar|dexlite|spbu|pom\s+bensin)\b/i', $source)) {
            return 'Beli Bensin';
        }
        if (preg_match('/\b(parkir|ongkir|ongkos|tol|e-toll|etoll|angkot|busway|krl|mrt|lrt|ojek|grab|gojek|maxim)\b/i', $source)) {
            return 'Transportasi';
        }

        // 3. Pola Belanja & Tagihan
        if (preg_match('/\bbelanja\s+bulanan\b/i', $source)) {
            return 'Belanja Bulanan';
        }
        if (preg_match('/\bbelanja\s+mingguan\b/i', $source)) {
            return 'Belanja Mingguan';
        }
        if (preg_match('/\b(belanja|supermarket|minimarket|pasar)\b/i', $source)) {
            return 'Belanja';
        }
        if (preg_match('/\b(obat|apotek|dokter|klinik|rumah\s+sakit|vitamin)\b/i', $source)) {
            return 'Beli Obat';
        }
        if (preg_match('/\b(pulsa|kuota|paket\s+data)\b/i', $source)) {
            return 'Beli Pulsa';
        }
        if (preg_match('/\b(listrik|pln|token)\b/i', $source)) {
            return 'Listrik';
        }
        if (preg_match('/\b(pdam|air)\b/i', $source)) {
            return 'Tagihan Air';
        }
        if (preg_match('/\b(wifi|internet|indihome|biznet|myrepublic)\b/i', $source)) {
            return 'Internet';
        }

        // 4. Pola Pemasukan
        if (preg_match('/\b(gaji|payroll|honor|upah)\b/i', $source)) {
            return 'Gaji';
        }
        if (preg_match('/\b(bonus|insentif|thr)\b/i', $source)) {
            return 'Bonus';
        }
        if (preg_match('/\b(transfer\s+masuk|pemasukan|piutang|kembalian)\b/i', $source)) {
            return 'Pemasukan';
        }

        // 5. Cek jika rawDescription atau pesan user menyebutkan menu makanan spesifik
        if (preg_match('/\bmakan\b/i', $source)) {
            if (preg_match('/(?:makan\s+(?:siang|pagi|malam)?\s*(?:makai|pakai|menu)?\s*)([A-Za-z0-9\s]{2,25}?)(?:\s+(?:sebesar|seharga|senilai|\d|rp|di\b|$))/i', $source, $mDish)) {
                $cand = trim($mDish[1]);
                if (!empty($cand) && mb_strlen($cand) <= 25 && count(explode(' ', $cand)) <= 3) {
                    return ucwords($cand);
                }
            }
            return 'Makan';
        }

        // 6. Jika rawDescription dari AI sudah cukup singkat (<= 3 kata dan <= 25 karakter)
        $cleanDesc = trim($rawDescription);
        if ($merchantName) {
            $cleanDesc = trim(preg_replace('/\s+di\s+' . preg_quote($merchantName, '/') . '/i', '', $cleanDesc));
        }
        $cleanDesc = trim(preg_replace('/^(?:beli|bayar|tadi|pesan|isi)\s+/i', '', $cleanDesc));
        $words = preg_split('/\s+/', $cleanDesc);
        if (!empty($cleanDesc) && count($words) <= 3 && mb_strlen($cleanDesc) <= 25) {
            return ucwords($cleanDesc);
        }

        // 7. Ambil maksimal 2 kata pertama dari cleanDesc
        if (!empty($words) && !empty($words[0])) {
            $shortSlice = array_slice($words, 0, min(2, count($words)));
            return ucwords(implode(' ', $shortSlice));
        }

        return 'Pengeluaran';
    }

    /**
     * Mendeteksi upaya injeksi prompt atau permintaan yang jelas di luar domain keuangan.
     */
    protected function isExplicitOutOfScopeOrInjection(string $text): bool
    {
        $normalized = strtolower(trim($text));

        // Permintaan visualisasi grafik Apache ECharts adalah kemampuan resmi platform
        if (preg_match('/\b(grafik|chart|diagram|plot|visualisasi|echarts)\b/i', $normalized)) {
            return false;
        }

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
     * Mengekstrak opsi grafik Apache ECharts dari balasan AI atau membuat visualisasi fallback
     * otomatis dari data mutasi riil ruang kerja jika diminta oleh pengguna.
     */
    protected function extractChartOption(string &$replyContent, string $userMessageText, string $tenantId): ?array
    {
        // 1. Ekstraksi dari blok kode json:echarts atau echarts
        if (preg_match('/```(?:json:echarts|echarts|json)?\s*(\{[\s\S]*?"series"[\s\S]*?\})\s*```/i', $replyContent, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded) && !empty($decoded['series'])) {
                // Hapus blok kode dari teks balasan agar bubble chat tetap rapi
                $replyContent = trim(str_replace($m[0], '', $replyContent));
                return $decoded;
            }
        }

        // 2. Deteksi apakah pengguna meminta visualisasi grafik / chart secara eksplisit
        $isChartRequest = (bool) preg_match('/\b(grafik|chart|diagram|plot|visualisasi|echarts)\b/i', $userMessageText);
        if ($isChartRequest) {
            // Jika balasan AI keliru menolak ("Maaf, sebagai Asisten Cerdas RUDIMAH..."), ganti dengan teks pengantar yang ramah
            if (preg_match('/Maaf,\s*sebagai\s*Asisten\s*Cerdas\s*RUDIMAH/i', $replyContent) ||
                preg_match('/tidak\s*dapat\s*melayani\s*permintaan\s*penulisan\s*kode/i', $replyContent)) {
                $replyContent = "Berikut grafik visualisasi data transaksi keuangan untuk ruang kerja Anda:";
            }

            return $this->buildFallbackChartOption($userMessageText, $tenantId);
        }

        return null;
    }

    /**
     * Membangun opsi Apache ECharts dari transaksi keuangan riil di ruang kerja.
     */
    protected function buildFallbackChartOption(string $userMessageText, string $tenantId): array
    {
        $query = FinanceTransaction::where('tenant_id', $tenantId);

        // Filter jika menanyakan bensin/BBM spesifik
        if (preg_match('/\b(bensin|bbm|pertamax|pertalite|solar|spbu|shell)\b/i', $userMessageText)) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%bensin%')
                  ->orWhere('description', 'like', '%bbm%')
                  ->orWhere('description', 'like', '%pertamax%')
                  ->orWhere('description', 'like', '%pertalite%')
                  ->orWhere('description', 'like', '%solar%')
                  ->orWhere('merchant_name', 'like', '%spbu%')
                  ->orWhere('merchant_name', 'like', '%pertamina%')
                  ->orWhere('merchant_name', 'like', '%shell%');
            });
        }

        // Ambil transaksi mutasi terbaru
        $transactions = (clone $query)->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get()
            ->sortBy('transaction_date')
            ->values();

        if ($transactions->isEmpty()) {
            $transactions = FinanceTransaction::where('tenant_id', $tenantId)
                ->orderBy('transaction_date', 'desc')
                ->take(10)
                ->get()
                ->sortBy('transaction_date')
                ->values();
        }

        // Cek apakah meminta grafik distribusi kategori (Pie Chart)
        $isPie = (bool) preg_match('/\b(kategori|distribusi|pos|alokasi|pie|lingkaran)\b/i', $userMessageText);

        if ($isPie && $transactions->isNotEmpty()) {
            $categoryMap = [];
            foreach ($transactions as $tx) {
                $cat = $tx->category_name ?: ($tx->merchant_name ?: 'Lainnya');
                $categoryMap[$cat] = ($categoryMap[$cat] ?? 0) + $tx->amount_minor;
            }
            $pieData = [];
            foreach ($categoryMap as $name => $val) {
                $pieData[] = [
                    'name' => $name,
                    'value' => $val,
                ];
            }

            return [
                'title' => [
                    'text' => 'Distribusi Pengeluaran',
                    'left' => 'center',
                    'textStyle' => ['fontSize' => 14],
                ],
                'tooltip' => [
                    'trigger' => 'item',
                    'formatter' => '{b}: Rp {c} ({d}%)',
                ],
                'series' => [
                    [
                        'name' => 'Kategori',
                        'type' => 'pie',
                        'radius' => ['35%', '65%'],
                        'avoidLabelOverlap' => true,
                        'itemStyle' => [
                            'borderRadius' => 6,
                            'borderColor' => '#fff',
                            'borderWidth' => 2,
                        ],
                        'data' => $pieData,
                    ],
                ],
            ];
        }

        // Grafik Batang (Bar Chart) Riwayat Transaksi
        $categories = [];
        $seriesData = [];

        if ($transactions->isNotEmpty()) {
            foreach ($transactions as $tx) {
                $dateLabel = $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('d M') : 'Hari ini';
                $shortDesc = mb_strimwidth($tx->description ?: 'Transaksi', 0, 15, '..');
                $categories[] = "{$dateLabel}\n({$shortDesc})";
                $seriesData[] = $tx->amount_minor;
            }
        } else {
            $categories = ['Belum Ada Transaksi'];
            $seriesData = [0];
        }

        return [
            'title' => [
                'text' => 'Grafik Riwayat Transaksi Keuangan',
                'left' => 'center',
                'textStyle' => ['fontSize' => 14],
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'formatter' => '{b}<br/>Nominal: Rp {c}',
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $categories,
                'axisLabel' => [
                    'interval' => 0,
                    'fontSize' => 10,
                ],
            ],
            'yAxis' => [
                'type' => 'value',
                'axisLabel' => [
                    'formatter' => 'Rp {value}',
                ],
            ],
            'series' => [
                [
                    'name' => 'Nominal (Rp)',
                    'type' => 'bar',
                    'barMaxWidth' => 36,
                    'itemStyle' => [
                        'borderRadius' => [4, 4, 0, 0],
                        'color' => '#0d9488',
                    ],
                    'data' => $seriesData,
                ],
            ],
        ];
    }

    /**
     * Mencari transaksi sasaran yang baru saja dicatat pada percakapan ini
     * atau transaksi mutasi paling baru di ruang kerja (sebagai target update/koreksi).
     */
    protected function resolveLastRecordedTransaction(?string $conversationId, string $tenantId, ?string $description = null): ?FinanceTransaction
    {
        // 1. Cari dari pesan asisten paling baru di percakapan ini yang memiliki metadata recorded_transactions
        if ($conversationId) {
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

    /**
     * Parsing nominal uang Bahasa Indonesia (misal: "Rp 15.950", "35rb", "15.950", "35.000", "50 ribu").
     */
    protected function parseIndonesianNominal(string $text): int
    {
        $text = trim($text);
        if (empty($text)) {
            return 0;
        }

        $isRibu = (bool) preg_match('/(?:ribu|rb|k)\b/i', $text);
        $isJuta = (bool) preg_match('/(?:juta|jt)\b/i', $text);

        // Hapus teks Rp, IDR, spasi, dan akhiran kata nominal
        $cleaned = preg_replace('/^(?:rp\.?|idr)\s*/i', '', $text);
        $cleaned = preg_replace('/\s*(?:ribu|rb|k|juta|jt).*$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        // Bersihkan karakter selain angka, titik, dan koma
        $cleaned = preg_replace('/[^\d\.,]/', '', $cleaned);
        if (empty($cleaned)) {
            return 0;
        }

        // Jika ada titik atau koma, tentukan apakah ribuan atau pecahan desimal
        // Pola ribuan standar: 15.950 atau 1.500.000 atau 15,950 (3 digit di akhir)
        if (preg_match('/^\d{1,3}(?:[\.,]\d{3})+$/', $cleaned)) {
            $val = (float) str_replace(['.', ','], '', $cleaned);
        } elseif (preg_match('/^\d+[\.,]\d+$/', $cleaned)) {
            // Pecahan desimal, misal 15,5 atau 2.5 (biasanya sebelum rb/juta)
            $val = (float) str_replace(',', '.', $cleaned);
        } else {
            $val = (float) str_replace(['.', ','], '', $cleaned);
        }

        if ($isRibu && $val < 1000) {
            $val *= 1000;
        } elseif ($isJuta && $val < 1000000) {
            $val *= 1000000;
        }

        return (int) round($val);
    }

    /**
     * Mendeteksi harga satuan dan volume/kuantitas dari teks pesan pengguna
     * (misal: "beli bensin pertamax 35rb untuk 1liternya Rp15.950", "beli telur 30rb sekilonya 28000", "50rb @ 10000/liter").
     *
     * @return array{unit_price: int, quantity: float, unit: string}|null
     */
    protected function detectUnitPriceAndVolume(string $text, int $totalAmountMinor): ?array
    {
        // Guard ketat: Hanya aktif jika teks pesan pengguna secara eksplisit menyebutkan indikator harga satuan / BBM / timbangan
        $hasUnitPricingIndicator = (bool) preg_match('/\b(per\s+(?:liter|ltr|kg|kilo|gram|meter|pcs|buah)|(?:\/|@)\s*(?:liter|ltr|kg|kilo|gram|meter|pcs|buah)|sekilo(?:nya)?|seliter(?:nya)?|semeter(?:nya)?|1\s*(?:liter|ltr|kg|kilo|gram|meter)|bensin|pertamax|pertalite|solar|dexlite|biosolar|bahan\s+bakar|bbm|spbu|timbangan)\b/i', $text)
            || (bool) preg_match('/@\s*(?:rp\.?\s*)?\d+/i', $text);

        if (!$hasUnitPricingIndicator) {
            return null;
        }

        // Unit hanya yang valid dengan batas kata (\b), dilarang mencocokkan huruf 'm' tunggal yang sering merupakan akhiran nama makanan (seperti 'sumsum')
        $unitRegex = '\b(liter|ltr|lt|kg|kilo|gram|gr|pcs|pc|bungkus|buah|meter)\b';

        // Pola 1: "untuk 1liternya Rp15.950", "seliternya 10rb", "sekilonya 25.000", "harga 1 liter 15950", "per liter 10rb"
        $pattern1 = '/(?:untuk|harga|seharga|senilai|adalah|sebesar|per|tiap|@)?\s*(?:1|se)?\s*' . $unitRegex . '\s*(?:nya)?\s*(?:seharga|senilai|adalah|sebesar|rp\.?|:|@)?\s*([0-9\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)/i';

        // Pola 2: "15.950/liter", "15rb per liter", "28000 per kg"
        $pattern2 = '/(?:rp\.?\s*)?([0-9\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)\s*(?:\/|\s+per\s+)\s*' . $unitRegex . '/i';

        // Pola 3: "@ 15.950", "@ Rp 15.950"
        $pattern3 = '/@\s*(?:rp\.?\s*)?([0-9\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)(?:\s*(?:\/|\s+per\s+)?' . $unitRegex . ')?/i';

        $unitPrice = 0;
        $unit = '';

        if (preg_match($pattern1, $text, $m)) {
            $unit = strtolower($m[1]);
            $unitPrice = $this->parseIndonesianNominal($m[2]);
        } elseif (preg_match($pattern2, $text, $m)) {
            $unitPrice = $this->parseIndonesianNominal($m[1]);
            $unit = strtolower($m[2]);
        } elseif (preg_match($pattern3, $text, $m)) {
            $unitPrice = $this->parseIndonesianNominal($m[1]);
            $unit = !empty($m[2]) ? strtolower($m[2]) : 'unit';
        }

        if ($unitPrice <= 0) {
            return null;
        }

        // Jika totalAmountMinor diketahui dan lebih besar dari unitPrice
        if ($totalAmountMinor > 0 && $totalAmountMinor >= $unitPrice) {
            $calculatedQty = round($totalAmountMinor / $unitPrice, 4);
            return [
                'unit_price' => $unitPrice,
                'quantity' => $calculatedQty,
                'unit' => $unit,
            ];
        }

        // Jika pengguna justru menyebutkan kuantitas dan harga satuan: misal "2.5 liter harganya 15950 per liter"
        if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)\s*' . $unitRegex . '/i', $text, $qm)) {
            $rawQty = str_replace(',', '.', $qm[1]);
            $qty = (float) $rawQty;
            if ($qty > 0) {
                return [
                    'unit_price' => $unitPrice,
                    'quantity' => round($qty, 4),
                    'unit' => strtolower($qm[2]),
                ];
            }
        }

        return [
            'unit_price' => $unitPrice,
            'quantity' => 1,
            'unit' => $unit,
        ];
    }

    /**
     * Menyelesaikan tanggal transaksi secara akurat berdasarkan pesan pengguna,
     * kandidat tanggal dari ekstraksi, dan waktu saat ini (now).
     * Pesan pengguna adalah Single Source of Truth untuk waktu/tanggal relatif dan eksplisit.
     */
    public function resolveTransactionDate(string $userText, ?string $candidateDate = null, ?string $referenceDate = null): string
    {
        $today = now()->toDateString();
        $userTextTrimmed = trim($userText);

        if (empty($userTextTrimmed)) {
            return $candidateDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate) ? $candidateDate : $today;
        }

        // 1. Keterangan Waktu Relatif Hari Ini (MANDATORY: Mengesampingkan tanggal halusinasi AI)
        if (preg_match('/\b(hari\s+ini|sekarang|saat\s+ini|tadi\s+pagi|pagi\s+ini|siang\s+ini|sore\s+ini|malam\s+ini|barusan|tadi)\b/i', $userTextTrimmed)) {
            if (!preg_match('/\b(bukan|bukanlah|jangan)\s+(?:hari\s+ini|sekarang)\b/i', $userTextTrimmed)) {
                return $today;
            }
        }

        // 2. Keterangan Waktu Relatif Kemarin Lusa
        if (preg_match('/\bkemarin\s+lusa\b/i', $userTextTrimmed)) {
            return now()->subDays(2)->toDateString();
        }

        // 3. Keterangan Waktu Relatif Kemarin
        if (preg_match('/\bkemarin\b/i', $userTextTrimmed)) {
            return now()->subDay()->toDateString();
        }

        // 4. Keterangan Waktu Relatif Besok
        if (preg_match('/\bbesok\b/i', $userTextTrimmed)) {
            return now()->addDay()->toDateString();
        }

        // 5. Pola Sanggahan / Koreksi Tanggal dalam Kalimat (contoh: "ko tanggal 3? sekarang kan tanggal 6", "masih tanggal 3 bukan tanggal 6", "bukan tgl 3 tapi tgl 6")
        if (preg_match('/(?:sekarang\s+kan|seharusnya|mestinya|harusnya|jadikan|ubah\s+(?:ke|jadi)|ganti\s+(?:ke|jadi)|tapi|melainkan)\s+(?:tanggal|tgl)?\s*(\d{1,2})(?:\s+([A-Za-z]+)(?:\s+(\d{4}))?)?/i', $userTextTrimmed, $corrM)) {
            return $this->formatDayMonthYearToIso($corrM[1], $corrM[2] ?? null, $corrM[3] ?? null);
        }

        // Pola khusus: "masih tanggal X bukan tanggal Y" -> Y adalah tanggal yang diinginkan
        if (preg_match('/masih\s+(?:tanggal|tgl)\s*(\d{1,2}).*?bukan\s+(?:tanggal|tgl)?\s*(\d{1,2})/i', $userTextTrimmed, $masihM)) {
            return $this->formatDayMonthYearToIso($masihM[2]);
        }

        // 6. Tanggal Eksplisit Format ISO (YYYY-MM-DD) dalam Teks Pengguna
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $userTextTrimmed, $isoM)) {
            return $isoM[1];
        }

        // 7. Tanggal Eksplisit Format Bahasa Indonesia (DD Month YYYY atau DD Month) dalam Teks Pengguna
        $monthPattern = '(?:Jan(?:uari)?|Feb(?:ruari)?|Mar(?:et)?|Apr(?:il)?|Mei|May|Jun(?:i)?|Jul(?:i)?|Agu(?:stus)?|Aug(?:ust)?|Sep(?:tember)?|Okt(?:ober)?|Oct(?:ober)?|Nov(?:ember)?|Des(?:ember)?|Dec(?:ember)?)';
        if (preg_match('/(?:tanggal|tgl)?\s*(\d{1,2})\s+(' . $monthPattern . ')(?:\s+(\d{4}))?/i', $userTextTrimmed, $dmM)) {
            return $this->formatDayMonthYearToIso($dmM[1], $dmM[2], $dmM[3] ?? null);
        }

        // 8. Tanggal Eksplisit Angka Tanggal Saja (contoh: "tanggal 6", "tgl 6", "pada tgl 6")
        if (preg_match('/\b(?:tanggal|tgl)\s+(\d{1,2})\b/i', $userTextTrimmed, $dayM)) {
            $dayNum = (int) $dayM[1];
            if ($dayNum >= 1 && $dayNum <= 31) {
                return $this->formatDayMonthYearToIso((string) $dayNum);
            }
        }

        // 9. Jika ada candidate date yang valid
        if (!empty($candidateDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate)) {
            return $candidateDate;
        }

        // 10. Default Aman: Selalu gunakan tanggal HARI INI
        return $today;
    }

    /**
     * Memformat hari, nama bulan (opsional), dan tahun (opsional) menjadi string ISO YYYY-MM-DD.
     */
    protected function formatDayMonthYearToIso(string $day, ?string $monthStr = null, ?string $yearStr = null): string
    {
        $dayNum = str_pad(trim($day), 2, '0', STR_PAD_LEFT);
        $now = now();
        $year = !empty($yearStr) && is_numeric($yearStr) ? (int) $yearStr : (int) $now->format('Y');

        if (!empty($monthStr)) {
            $monthMap = [
                'jan' => '01', 'januari' => '01', 'january' => '01',
                'feb' => '02', 'februari' => '02', 'february' => '02',
                'mar' => '03', 'maret' => '03', 'march' => '03',
                'apr' => '04', 'april' => '04',
                'mei' => '05', 'may' => '05',
                'jun' => '06', 'juni' => '06', 'june' => '06',
                'jul' => '07', 'juli' => '07', 'july' => '07',
                'agu' => '08', 'agustus' => '08', 'aug' => '08', 'august' => '08',
                'sep' => '09', 'sept' => '09', 'september' => '09',
                'okt' => '10', 'oktober' => '10', 'oct' => '10', 'october' => '10',
                'nov' => '11', 'november' => '11',
                'des' => '12', 'desember' => '12', 'dec' => '12', 'december' => '12',
            ];
            $key = strtolower(trim($monthStr));
            $monthNum = $monthMap[$key] ?? $now->format('m');
        } else {
            $monthNum = $now->format('m');
        }

        try {
            return \Carbon\Carbon::createFromDate($year, (int) $monthNum, (int) $dayNum)->format('Y-m-d');
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }

    /**
     * Membersihkan pertanyaan penutup klise/basi yang berulang dari balasan AI
     * (contoh: "Ada transaksi lain?", "Transaksi lain?", "Ada mutasi lain?").
     */
    protected function cleanRepetitiveClosingPhrases(string $content): string
    {
        $patterns = [
            '/(?:[\.\,\s]*)?\b(?:ada\s+)?transaksi\s+lain(?:\s+yang\s+(?:ingin|mau|perlu|bisa)\s+(?:saya\s+bantu\s+)?(?:di)?(?:catat|bantu)?)?\s*[\?\.]*\s*$/i',
            '/(?:[\.\,\s]*)?\b(?:ada\s+)?mutasi\s+lain(?:\s+yang\s+(?:ingin|mau|perlu|bisa)\s+(?:saya\s+bantu\s+)?(?:di)?(?:catat|bantu)?)?\s*[\?\.]*\s*$/i',
            '/(?:[\.\,\s]*)?\bada\s+yang\s+ingin\s+(?:saya\s+bantu\s+)?(?:di)?catat\s+lagi\s*[\?\.]*\s*$/i',
            '/(?:[\.\,\s]*)?\bada\s+transaksi\s+atau\s+data\s+keuangan\s+yang\s+ingin\s+saya\s+bantu\s+catat\s*[\?\.]*\s*$/i',
            '/(?:[\.\,\s]*)?\btransaksi\s+lain\s*\?\s*$/i',
        ];

        $cleaned = $content;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        $trimmed = trim($cleaned);
        if (!empty($trimmed)) {
            // Jika kalimat terpotong tanpa tanda baca di ujungnya setelah pembersihan, tambahkan tanda titik rapi
            if (!preg_match('/[\.\?\!]$/', $trimmed) && preg_match('/[\.\?\!]/', $content)) {
                $trimmed .= '.';
            }
            return $trimmed;
        }

        return $content;
    }

    /**
     * Memastikan skema tabel finance_reminders lengkap (category_id, images).
     */
    protected function ensureReminderSchema(): void
    {
        try {
            $schema = Schema::connection('pekan');
            if ($schema->hasTable('finance_reminders')) {
                if (!$schema->hasColumn('finance_reminders', 'category_id')) {
                    $schema->table('finance_reminders', function (Blueprint $table) {
                        $table->uuid('category_id')->nullable()->after('tenant_id')->index();
                    });
                }
                if (!$schema->hasColumn('finance_reminders', 'images')) {
                    $schema->table('finance_reminders', function (Blueprint $table) {
                        $table->json('images')->nullable()->after('description');
                    });
                }
            }
        } catch (\Throwable) {
            // Abaikan jika migrasi sudah berjalan atau permission terbatas
        }
    }

    /**
     * Mengekstrak instruksi penjadwalan tagihan / pengingat baik dari blok JSON
     * (json:reminder_action) maupun dari pola teks pesan pengguna secara heuristik.
     */
    protected function extractReminderActions(string $replyContent, string $userMessageText): array
    {
        // 1. Ekstraksi Primer: Blok ```json:reminder_action ... ``` atau ```reminder_action ... ``` atau ```json ... ``` dengan payload reminder
        if (preg_match('/```(?:json:reminder_action|reminder_action|json)?\s*([\s\S]*?)\s*```/i', $replyContent, $fenceMatches)) {
            $codeBlock = trim($fenceMatches[1]);
            if (preg_match('/(\[\s*[\s\S]*\s*\]|\{\s*[\s\S]*\s*\})/s', $codeBlock, $jsonMatches)) {
                $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', trim($jsonMatches[1]));
                $parsed = json_decode($cleanJson, true);
                if (is_array($parsed) && !empty($parsed)) {
                    $firstElement = isset($parsed[0]) ? $parsed[0] : $parsed;
                    $isReminderPayload = isset($firstElement['title'])
                        || isset($firstElement['repeat_interval'])
                        || (isset($firstElement['action']) && in_array(strtolower($firstElement['action']), ['create', 'update', 'delete']) && isset($firstElement['due_date']));
                    if ($isReminderPayload) {
                        $cleaned = trim(str_replace($fenceMatches[0], '', $replyContent));
                        return [isset($parsed['title']) ? [$parsed] : $parsed, trim($cleaned)];
                    }
                }
            }
        }

        // 2. Ekstraksi Sekunder: JSON array/objek tanpa markdown fence
        if (preg_match('/(\[\s*\{[\s\S]*?"title"\s*:[\s\S]*?\}\s*\]|\{\s*[\s\S]*?"title"\s*:[\s\S]*?"(?:amount_minor|due_date|repeat_interval)"[\s\S]*?\})/i', $replyContent, $matches)) {
            $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', trim($matches[1]));
            $parsed = json_decode($cleanJson, true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                return [isset($parsed['title']) ? [$parsed] : $parsed, trim($cleaned)];
            }
        }

        // 3. Ekstraksi Heuristik jika pengguna berniat menjadwalkan/memasukkan tagihan ATAU jika AI mengonfirmasi penjadwalan tagihan
        $isReminderScheduleIntent = (bool) preg_match('/\b(?:masuk(?:in|kan)?|jadwal(?:kan)?|tambah(?:kan)?|catat|catet|simpan|buat(?:kan)?|ingatkan)\s+(?:ini\s+)?(?:ke\s+tagihan|tagihan|jadwal\s+tagihan|pengingat)\b/i', $userMessageText)
            || (bool) preg_match('/\b(jadwal(?:kan)?|pengingat)\s+tagihan\b/i', $userMessageText)
            || (bool) preg_match('/\btagihan\b.*?\b(?:setiap|tiap|jatuh\s+tempo|bulanan|mingguan|tahunan)\b/i', $userMessageText)
            || (bool) preg_match('/\btagihan\s+([A-Za-z0-9\s]{2,30}?)\s+(?:sebesar\s+)?(?:rp\.?|idr)?\s*[\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?/i', $userMessageText);

        $isAiConfirmingReminder = (bool) preg_match('/\b(?:jadwal\s+tagihan|tagihan\s+.*?\b(?:berhasil\s+(?:dijadwalkan|dimasukkan|dicatat|disimpan)|telah\s+(?:dijadwalkan|dimasukkan|dicatat)|sudah\s+dijadwalkan))\b/i', $replyContent);

        if ($isReminderScheduleIntent || $isAiConfirmingReminder) {
            $extracted = $this->extractReminderFromUserText($userMessageText, $replyContent);
            if ($extracted) {
                $cleaned = preg_replace('/```(?:json:reminder_action|reminder_action|json)?\s*[\s\S]*?```/i', '', $replyContent);
                return [[$extracted], trim($cleaned ?: $replyContent)];
            }
        }

        return [[], $replyContent];
    }

    /**
     * Mengekstrak rincian jadwal tagihan secara heuristik dari kalimat pengguna dan balasan AI.
     */
    protected function extractReminderFromUserText(string $userText, string $replyContent = ''): ?array
    {
        $amountMinor = 0;
        $combinedText = $userText . ' ' . $replyContent;

        // Cari nominal uang terverifikasi: nominal dengan embel-embel Rp, rb, ribu, k, juta, jt, atau bilangan bulat >= 1000
        if (preg_match_all('/(?:(?:rp\.?|idr)\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)|([\d\.,]+)\s*(?:ribu|rb|k|juta|jt)|\b(\d{4,9})\b)/i', $combinedText, $allMatches, PREG_SET_ORDER)) {
            $candidates = [];
            foreach ($allMatches as $m) {
                $rawStr = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[0] : $m[3]);
                $val = $this->parseIndonesianNominal($rawStr);
                if ($val >= 1000) {
                    $candidates[] = $val;
                }
            }
            if (!empty($candidates)) {
                $amountMinor = max($candidates);
            }
        }

        if ($amountMinor <= 0) {
            return null;
        }

        // Interval perulangan
        $interval = 'monthly';
        if (preg_match('/\b(sekali|hanya\s+sekali|satu\s+kali)\b/i', $userText)) {
            $interval = 'once';
        } elseif (preg_match('/\b(?:setiap|tiap)\s+minggu\b/i', $userText)) {
            $interval = 'weekly';
        } elseif (preg_match('/\b(?:setiap|tiap)\s+tahun\b/i', $userText)) {
            $interval = 'yearly';
        } elseif (preg_match('/\b(?:setiap|tiap)\s+hari\b/i', $userText)) {
            $interval = 'daily';
        }

        $dueDate = $this->calculateNextDueDate(null, $interval, $userText);

        // Resolusi Nama / Judul Tagihan
        $title = '';
        if (preg_match('/(?:ke\s+tagihan\s*,?\s*|\btagihan\s+:?\s*)([A-Za-z0-9\s\-_]{3,40}?)(?:\s+\d|\s+sebesar|\s+seharga|\s+setiap|\s+tiap|\s+tgl|\s+tanggal|\s+jatuh|\s*$)/i', $userText, $tm)) {
            $candidate = trim($tm[1]);
            if (!empty($candidate)) {
                $title = stripos($candidate, 'tagihan') === false ? 'Tagihan ' . ucwords($candidate) : ucwords($candidate);
            }
        }

        if (empty($title)) {
            if (preg_match('/(?:tagihan\s+jadwal|jadwal\s+tagihan|tagihan)\s+([A-Za-z0-9\s\-_]{3,35}?)(?:\s+sebesar|\s+jatuh|\s+\d|\s*$)/i', $replyContent, $rtm)) {
                $candidate = trim($rtm[1]);
                if (!empty($candidate)) {
                    $title = stripos($candidate, 'tagihan') === false ? 'Tagihan ' . ucwords($candidate) : ucwords($candidate);
                }
            }
        }

        if (empty($title)) {
            if (preg_match('/(?:untuk|buat|jadwal)\s+([A-Za-z0-9\s\-_]{3,35}?)(?:\s+\d|\s+sebesar|\s+seharga|\s+setiap|\s+tiap|\s+tgl|\s+tanggal|\s+jatuh|\s*$)/i', $userText, $tm2)) {
                $candidate = trim($tm2[1]);
                if (!empty($candidate)) {
                    $title = 'Tagihan ' . ucwords($candidate);
                }
            }
        }

        if (empty($title)) {
            $title = 'Tagihan Rutin';
        }

        return [
            'action' => 'create',
            'title' => $title,
            'amount_minor' => $amountMinor,
            'due_date' => $dueDate,
            'repeat_interval' => $interval,
            'description' => trim($userText),
        ];
    }

    /**
     * Menghitung tanggal jatuh tempo terdekat berikutnya dari hari ini berdasarkan interval dan teks pengguna.
     */
    protected function calculateNextDueDate(?string $dueDateStr, string $interval, string $userText): string
    {
        if (!empty($dueDateStr) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDateStr)) {
            return $dueDateStr;
        }

        $today = now();

        // Pola "setiap tanggal X" atau "tiap tanggal X" atau "tgl X"
        if (preg_match('/(?:tanggal|tgl)\s*(\d{1,2})/i', $userText, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                if ($day >= $today->day) {
                    try {
                        return $today->copy()->day($day)->toDateString();
                    } catch (\Throwable) {
                        return $today->copy()->endOfMonth()->toDateString();
                    }
                } else {
                    try {
                        return $today->copy()->addMonthNoOverflow()->day($day)->toDateString();
                    } catch (\Throwable) {
                        return $today->copy()->addMonthNoOverflow()->endOfMonth()->toDateString();
                    }
                }
            }
        }

        if (preg_match('/\bbesok\b/i', $userText)) {
            return $today->copy()->addDay()->toDateString();
        }

        if (preg_match('/\bminggu\s+depan\b/i', $userText)) {
            return $today->copy()->addWeek()->toDateString();
        }

        if (preg_match('/\bbulan\s+depan\b/i', $userText)) {
            return $today->copy()->addMonthNoOverflow()->toDateString();
        }

        return match ($interval) {
            'weekly' => $today->copy()->addWeek()->toDateString(),
            'yearly' => $today->copy()->addYear()->toDateString(),
            'daily' => $today->copy()->addDay()->toDateString(),
            default => $today->copy()->addMonthNoOverflow()->toDateString(),
        };
    }

    /**
     * Memastikan skema tabel finance_savings lengkap (images).
     */
    protected function ensureSavingsSchema(): void
    {
        try {
            $schema = Schema::connection('pekan');
            if ($schema->hasTable('finance_savings')) {
                if (!$schema->hasColumn('finance_savings', 'images')) {
                    $schema->table('finance_savings', function (Blueprint $table) {
                        $table->json('images')->nullable()->after('notes');
                    });
                }
            }
        } catch (\Throwable) {
            // Abaikan jika migrasi sudah berjalan atau permission terbatas
        }
    }

    /**
     * Mengekstrak instruksi pengelolaan tabungan baik dari blok JSON (json:savings_action)
     * maupun dari pola teks pesan pengguna secara heuristik.
     */
    protected function extractSavingsActions(string $replyContent, string $userMessageText): array
    {
        // 1. Ekstraksi Primer: Blok ```json:savings_action ... ``` atau ```savings_action ... ``` atau ```json ... ``` dengan payload savings
        if (preg_match('/```(?:json:savings_action|savings_action|json)?\s*([\s\S]*?)\s*```/i', $replyContent, $fenceMatches)) {
            $codeBlock = trim($fenceMatches[1]);
            if (preg_match('/(\[\s*[\s\S]*\s*\]|\{\s*[\s\S]*\s*\})/s', $codeBlock, $jsonMatches)) {
                $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', trim($jsonMatches[1]));
                $parsed = json_decode($cleanJson, true);
                if (is_array($parsed) && !empty($parsed)) {
                    $firstElement = isset($parsed[0]) ? $parsed[0] : $parsed;
                    $isSavingsPayload = isset($firstElement['name'])
                        || isset($firstElement['target_amount_minor'])
                        || (isset($firstElement['action']) && in_array(strtolower($firstElement['action']), ['create', 'deposit', 'withdraw', 'delete']));
                    if ($isSavingsPayload) {
                        $cleaned = trim(str_replace($fenceMatches[0], '', $replyContent));
                        return [isset($parsed['name']) ? [$parsed] : $parsed, trim($cleaned)];
                    }
                }
            }
        }

        // 2. Ekstraksi Sekunder: JSON array/objek tanpa markdown fence
        if (preg_match('/(\[\s*\{[\s\S]*?"name"\s*:[\s\S]*?\}\s*\]|\{\s*[\s\S]*?"name"\s*:[\s\S]*?"(?:target_amount_minor|amount_minor|action)"[\s\S]*?\})/i', $replyContent, $matches)) {
            $cleanJson = preg_replace('/,\s*([\]\}])/', '$1', trim($matches[1]));
            $parsed = json_decode($cleanJson, true);
            if (is_array($parsed) && !empty($parsed)) {
                $cleaned = trim(str_replace($matches[0], '', $replyContent));
                return [isset($parsed['name']) ? [$parsed] : $parsed, trim($cleaned)];
            }
        }

        // 3. Ekstraksi Heuristik jika pengguna berniat membuat/menabung/menarik tabungan ATAU AI mengonfirmasi tindakan tabungan
        $isSavingsIntent = (bool) preg_match('/\b(?:buat(?:kan)?|tambah(?:kan)?|bikin|pos)\s+(?:target\s+)?tabungan\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:nabung|menabung|setor|setorkan|tabung|simpan)\s+.*?\b(?:ke|untuk|buat|di)\s+(?:tabungan|pos\s+tabungan)\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:nabung|menabung|tabung)\s+(?:sebesar\s+)?(?:rp\.?|idr)?\s*[\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?/i', $userMessageText)
            || (bool) preg_match('/\b(?:tarik|ambil)\s+.*?dari\s+tabungan\b/i', $userMessageText)
            || (bool) preg_match('/\b(?:hapus|batalkan)\s+(?:target\s+)?tabungan\b/i', $userMessageText);

        $isAiConfirmingSavings = (bool) preg_match('/\b(?:target\s+tabungan|pos\s+tabungan|setoran\s+tabungan|penarikan\s+tabungan|tabungan\s+.*?\b(?:berhasil\s+(?:dibuat|dicatat|disimpan|dihapus)|telah\s+(?:dibuat|dicatat|disimpan)))\b/i', $replyContent);

        if ($isSavingsIntent || $isAiConfirmingSavings) {
            $extracted = $this->extractSavingsFromUserText($userMessageText, $replyContent);
            if ($extracted) {
                $cleaned = preg_replace('/```(?:json:savings_action|savings_action|json)?\s*[\s\S]*?```/i', '', $replyContent);
                return [[$extracted], trim($cleaned ?: $replyContent)];
            }
        }

        return [[], $replyContent];
    }

    /**
     * Mengekstrak rincian aksi tabungan secara heuristik dari teks pengguna dan balasan AI.
     */
    protected function extractSavingsFromUserText(string $userText, string $replyContent = ''): ?array
    {
        $amountMinor = 0;
        $combinedText = $userText . ' ' . $replyContent;

        // Cari nominal uang terverifikasi: nominal dengan embel-embel Rp, rb, ribu, k, juta, jt, atau bilangan bulat >= 1000
        if (preg_match_all('/(?:(?:rp\.?|idr)\s*([\d\.,]+(?:\s*(?:ribu|rb|k|juta|jt))?)|([\d\.,]+)\s*(?:ribu|rb|k|juta|jt)|\b(\d{4,9})\b)/i', $combinedText, $allMatches, PREG_SET_ORDER)) {
            $candidates = [];
            foreach ($allMatches as $m) {
                $rawStr = !empty($m[1]) ? $m[1] : (!empty($m[2]) ? $m[0] : $m[3]);
                $val = $this->parseIndonesianNominal($rawStr);
                if ($val >= 1000) {
                    $candidates[] = $val;
                }
            }
            if (!empty($candidates)) {
                $amountMinor = max($candidates);
            }
        }

        // Deteksi Aksi
        $action = 'create';
        if (preg_match('/\b(?:nabung|menabung|setor|setorkan|masuk(?:in|kan)?|simpan|isi)\b/i', $userText)) {
            $action = 'deposit';
        } elseif (preg_match('/\b(?:tarik|ambil)\b/i', $userText)) {
            $action = 'withdraw';
        } elseif (preg_match('/\b(?:hapus|batalkan)\b/i', $userText)) {
            $action = 'delete';
        }

        // Resolusi Nama Tabungan
        $name = '';
        if (preg_match('/(?:ke\s+tabungan|dari\s+tabungan|target\s+tabungan|pos\s+tabungan|untuk\s+tabungan|di\s+tabungan|tabungan)\s+([A-Za-z0-9\s\-_]{3,40}?)(?:\s+\d|\s+sebesar|\s+seharga|\s+target|\s*$)/i', $userText, $nm)) {
            $candidate = trim($nm[1]);
            if (!empty($candidate)) {
                $name = ucwords($candidate);
            }
        }

        if (empty($name)) {
            if (preg_match('/(?:pos\s+tabungan|target\s+tabungan|tabungan)\s+[\'"]?([A-Za-z0-9\s\-_]{3,35}?)[\'"]?(?:\s+sebesar|\s+telah|\s+berhasil|\s*$)/i', $replyContent, $rnm)) {
                $candidate = trim($rnm[1]);
                if (!empty($candidate)) {
                    $name = ucwords($candidate);
                }
            }
        }

        if (empty($name)) {
            if (preg_match('/(?:buat|target|pos)\s+tabungan\s+([A-Za-z0-9\s\-_]{3,35})/i', $userText, $nm2)) {
                $name = ucwords(trim($nm2[1]));
            }
        }

        if (empty($name)) {
            $name = 'Tabungan Impian';
        }

        // Deteksi target date jika ada (misal "target 31 Desember 2026", "target tanggal 2026-12-31")
        $targetDate = null;
        if (preg_match('/target\s+(?:tanggal\s+)?(\d{4}-\d{2}-\d{2})/i', $userText, $dm)) {
            $targetDate = $dm[1];
        } elseif (preg_match('/target\s+(?:tanggal\s+)?(\d{1,2}\s+[A-Za-z]+\s+\d{4})/i', $userText, $dm2)) {
            try {
                $targetDate = \Carbon\Carbon::parse($dm2[1])->toDateString();
            } catch (\Throwable) {}
        }

        if ($action === 'create') {
            return [
                'action' => 'create',
                'name' => $name,
                'target_amount_minor' => max(0, $amountMinor),
                'current_amount_minor' => 0,
                'target_date' => $targetDate,
                'notes' => trim($userText),
            ];
        }

        if ($action === 'deposit') {
            return [
                'action' => 'deposit',
                'name' => $name,
                'amount_minor' => $amountMinor,
                'notes' => trim($userText),
            ];
        }

        if ($action === 'withdraw') {
            return [
                'action' => 'withdraw',
                'name' => $name,
                'amount_minor' => $amountMinor,
                'notes' => trim($userText),
            ];
        }

        return [
            'action' => 'delete',
            'name' => $name,
        ];
    }
}


