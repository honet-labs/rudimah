<?php

namespace App\Modules\Pekan\Http\Controllers;

use App\Core\Applications\AppRegistry;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceAccount;
use App\Modules\Pekan\Models\FinanceCategory;
use App\Modules\Pekan\Models\FinanceReceiptScan;
use App\Modules\Pekan\Services\ReceiptOcrService;
use App\Modules\Pekan\Services\TransactionService;
use App\Shared\Helpers\MoneyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptScanController
{
    public function __construct(
        protected ReceiptOcrService $ocrService,
        protected TransactionService $transactionService
    ) {}

    public function index(Request $request, ?string $tenantSlug = null): Response
    {
        $tenant = TenantContext::get();
        $user = $request->user();

        try {
            $categories = FinanceCategory::where('is_active', true)
                ->where('category_type', 'expense')
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            $categories = collect();
        }

        try {
            $accounts = FinanceAccount::where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Throwable) {
            $accounts = collect();
        }

        return Inertia::render('Pekan/ReceiptScan/Index', [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'has_mfa' => (bool) $user->mfaMethods()->where('is_verified', true)->exists(),
            ] : null,
            'tenant' => $tenant,
            'tenants' => $user ? $user->getActiveTenants() : [],
            'apps' => AppRegistry::all(),
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }

    public function upload(Request $request, ?string $tenantSlug = null): JsonResponse|RedirectResponse
    {
        $request->validate([
            'receipt_file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        try {
            $scan = $this->ocrService->processUpload($request->file('receipt_file'));

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'scan_id' => $scan->id,
                    'extracted' => $scan->extracted_json,
                ]);
            }

            $tenant = TenantContext::get();
            $slug = $tenant?->slug ?: $tenantSlug;
            return redirect("/t/{$slug}/apps/pekan/receipt-scan")
                ->with('status', 'Struk berhasil diproses untuk ditinjau.');
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function confirm(Request $request, ?string $tenantSlug = null): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'scan_id' => 'nullable|string',
            'merchant_name' => 'required|string|max:200',
            'receipt_number' => 'nullable|string|max:100',
            'transaction_date' => 'required|date',
            'category_id' => 'nullable|string',
            'account_id' => 'nullable|string',
            'subtotal_minor' => 'nullable|integer|min:0',
            'tax_minor' => 'nullable|integer|min:0',
            'service_charge_minor' => 'nullable|integer|min:0',
            'discount_minor' => 'nullable|integer|min:0',
            'total_minor' => 'required|integer|min:0',
            'notes' => 'nullable|string|max:500',
            'items' => 'nullable|array',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0.01',
            'items.*.price_minor' => 'nullable|integer|min:0',
            'items.*.discount_minor' => 'nullable|integer|min:0',
            'items.*.total_minor' => 'nullable|integer|min:0',
        ]);

        $tenant = TenantContext::get();
        $slug = $tenant?->slug ?: $tenantSlug;

        // 1. Resolve Rekening Akun
        $accountId = $validated['account_id'] ?? null;
        if (empty($accountId)) {
            $defaultAccount = FinanceAccount::where('is_active', true)->first();
            $accountId = $defaultAccount ? $defaultAccount->id : 'default-account';
        }

        // 2. Resolve Kategori
        $categoryId = $validated['category_id'] ?? null;
        if (empty($categoryId)) {
            $defaultCategory = FinanceCategory::where('is_active', true)
                ->where('category_type', 'expense')
                ->first();
            $categoryId = $defaultCategory?->id;
        }

        $totalMinor = (int) $validated['total_minor'];
        $subtotalMinor = (int) ($validated['subtotal_minor'] ?? $totalMinor);
        $taxMinor = (int) ($validated['tax_minor'] ?? 0);
        $discountMinor = (int) ($validated['discount_minor'] ?? 0);
        $serviceChargeMinor = (int) ($validated['service_charge_minor'] ?? 0);

        $txData = [
            'account_id' => $accountId,
            'category_id' => $categoryId,
            'type' => 'expense',
            'amount_minor' => $totalMinor,
            'currency' => 'Rp',
            'transaction_date' => $validated['transaction_date'],
            'description' => 'Struk Belanja: ' . $validated['merchant_name'],
            'merchant_name' => $validated['merchant_name'],
            'receipt_number' => $validated['receipt_number'] ?? null,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'service_charge_minor' => $serviceChargeMinor,
            'receipt_discount_minor' => $discountMinor,
            'notes' => $validated['notes'] ?? 'Diverifikasi dari pemindaian struk OCR',
        ];

        $items = $validated['items'] ?? [];

        $transaction = $this->transactionService->createTransaction($txData, $items);

        // 3. Tandai status scan OCR sebagai confirmed
        if (!empty($validated['scan_id'])) {
            FinanceReceiptScan::where('id', $validated['scan_id'])->update([
                'status' => 'confirmed',
            ]);
        }

        AuditLogger::log('ocr_receipt_confirmed', 'finance_transactions', $transaction->id, null, [
            'scan_id' => $validated['scan_id'] ?? null,
            'total_minor' => $totalMinor,
            'merchant' => $validated['merchant_name'],
        ]);

        $formattedTotal = MoneyHelper::formatIdr($totalMinor);
        $successMsg = "Transaksi belanja '{$validated['merchant_name']}' ({$formattedTotal}) berhasil diverifikasi dan disimpan.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMsg,
                'transaction_id' => $transaction->id,
                'redirect_url' => "/t/{$slug}/apps/pekan/transactions",
            ]);
        }

        return redirect("/t/{$slug}/apps/pekan/transactions")
            ->with('status', $successMsg);
    }
}

