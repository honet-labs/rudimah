<?php

namespace App\Modules\Pekan\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceTransaction;
use App\Modules\Pekan\Models\FinanceTransactionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function createTransaction(array $data, array $items = []): FinanceTransaction
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            throw new \RuntimeException('Konteks ruang kerja belum aktif.');
        }

        return DB::connection('pekan')->transaction(function () use ($data, $items, $tenantId) {
            $accountId = $data['account_id'] ?? null;
            $account = $accountId ? FinanceAccount::find($accountId) : FinanceAccount::first();

            // Jika belum ada akun kas di ruang kerja ini, buatkan akun default otomatis
            if (!$account) {
                $account = FinanceAccount::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => 'Kas Utama',
                    'account_type' => 'cash',
                    'currency' => 'IDR',
                    'opening_balance_minor' => 0,
                    'current_balance_minor' => 0,
                    'is_active' => true,
                    'created_by' => auth()->id() ?? 'system',
                ]);
            }
            $resolvedAccountId = $account->id;

            // Resolusi Kategori: bila category_id kosong namun category string diberikan
            $categoryId = $data['category_id'] ?? null;
            if (!$categoryId && !empty($data['category'])) {
                $categoryName = trim($data['category']);
                $category = FinanceCategory::where('name', 'like', "%{$categoryName}%")->first();
                if (!$category) {
                    $category = FinanceCategory::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'name' => $categoryName,
                        'category_type' => in_array($data['type'] ?? '', ['income', 'expense', 'savings']) ? $data['type'] : 'expense',
                        'is_active' => true,
                        'created_by' => auth()->id() ?? 'system',
                    ]);
                }
                $categoryId = $category->id;
            }

            $txId = (string) Str::uuid();
            $transaction = FinanceTransaction::create([
                'id' => $txId,
                'tenant_id' => $tenantId,
                'account_id' => $resolvedAccountId,
                'category_id' => $categoryId,
                'type' => $data['type'],
                'amount_minor' => (int) $data['amount_minor'],
                'currency' => $data['currency'] ?? 'IDR',
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'input_date' => now()->toDateString(),
                'description' => $data['description'],
                'merchant_name' => $data['merchant_name'] ?? null,
                'receipt_number' => $data['receipt_number'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'subtotal_minor' => (int) ($data['subtotal_minor'] ?? $data['amount_minor']),
                'tax_minor' => (int) ($data['tax_minor'] ?? 0),
                'service_charge_minor' => (int) ($data['service_charge_minor'] ?? 0),
                'receipt_discount_minor' => (int) ($data['receipt_discount_minor'] ?? 0),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id() ?? 'system',
                'updated_by' => auth()->id() ?? 'system',
            ]);

            // Simpan baris item jika ada
            foreach ($items as $item) {
                FinanceTransactionItem::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'transaction_id' => $txId,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'price_minor' => (int) $item['price_minor'],
                    'discount_minor' => (int) ($item['discount_minor'] ?? 0),
                    'total_minor' => (int) $item['total_minor'],
                    'notes' => $item['notes'] ?? null,
                    'created_by' => auth()->id() ?? 'system',
                    'updated_by' => auth()->id() ?? 'system',
                ]);
            }

            // Sesuaikan saldo kas jika model account tersedia
            if ($account) {
                if ($data['type'] === 'income') {
                    $account->increment('current_balance_minor', (int) $data['amount_minor']);
                } elseif ($data['type'] === 'expense' || $data['type'] === 'savings') {
                    $account->decrement('current_balance_minor', (int) $data['amount_minor']);
                }
            }

            AuditLogger::log('create_transaction', 'finance_transactions', $txId, null, $transaction->toArray());

            return $transaction;
        });
    }

    public function updateTransaction(FinanceTransaction $transaction, array $data, array $items = []): FinanceTransaction
    {
        $tenantId = TenantContext::id();

        return DB::connection('pekan')->transaction(function () use ($transaction, $data, $items, $tenantId) {
            $oldAmount = $transaction->amount_minor;
            $oldType = $transaction->type;
            $account = FinanceAccount::find($transaction->account_id);

            // Batalkan efek saldo lama
            if ($account) {
                if ($oldType === 'income') {
                    $account->decrement('current_balance_minor', (int) $oldAmount);
                } elseif ($oldType === 'expense' || $oldType === 'savings') {
                    $account->increment('current_balance_minor', (int) $oldAmount);
                }
            }

            $transaction->update([
                'category_id' => $data['category_id'] ?? $transaction->category_id,
                'type' => $data['type'] ?? $transaction->type,
                'amount_minor' => (int) ($data['amount_minor'] ?? $transaction->amount_minor),
                'transaction_date' => $data['transaction_date'] ?? $transaction->transaction_date,
                'description' => $data['description'] ?? $transaction->description,
                'merchant_name' => $data['merchant_name'] ?? $transaction->merchant_name,
                'receipt_number' => $data['receipt_number'] ?? $transaction->receipt_number,
                'notes' => $data['notes'] ?? $transaction->notes,
                'updated_by' => auth()->id() ?? 'system',
            ]);

            // Perbarui item jika disertakan
            if (!empty($items)) {
                FinanceTransactionItem::where('transaction_id', $transaction->id)->delete();
                foreach ($items as $item) {
                    FinanceTransactionItem::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'transaction_id' => $transaction->id,
                        'item_name' => $item['item_name'],
                        'quantity' => $item['quantity'] ?? 1,
                        'price_minor' => (int) $item['price_minor'],
                        'discount_minor' => (int) ($item['discount_minor'] ?? 0),
                        'total_minor' => (int) $item['total_minor'],
                        'notes' => $item['notes'] ?? null,
                        'created_by' => auth()->id() ?? 'system',
                        'updated_by' => auth()->id() ?? 'system',
                    ]);
                }
            }

            // Terapkan efek saldo baru
            if ($account) {
                if ($transaction->type === 'income') {
                    $account->increment('current_balance_minor', (int) $transaction->amount_minor);
                } elseif ($transaction->type === 'expense' || $transaction->type === 'savings') {
                    $account->decrement('current_balance_minor', (int) $transaction->amount_minor);
                }
            }

            AuditLogger::log('update_transaction', 'finance_transactions', $transaction->id, null, $transaction->toArray());

            return $transaction;
        });
    }
}

