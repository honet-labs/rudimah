<?php

namespace App\Modules\Pekan\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
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
            $resolvedAccountId = $account ? $account->id : 'default-account';

            $txId = (string) Str::uuid();
            $transaction = FinanceTransaction::create([
                'id' => $txId,
                'tenant_id' => $tenantId,
                'account_id' => $resolvedAccountId,
                'category_id' => $data['category_id'] ?? null,
                'type' => $data['type'],
                'amount_minor' => (int) $data['amount_minor'],
                'currency' => $data['currency'] ?? 'Rp',
                'transaction_date' => $data['transaction_date'],
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
                } elseif ($data['type'] === 'expense') {
                    $account->decrement('current_balance_minor', (int) $data['amount_minor']);
                }
            }

            AuditLogger::log('create_transaction', 'finance_transactions', $txId, null, $transaction->toArray());

            return $transaction;
        });
    }
}
