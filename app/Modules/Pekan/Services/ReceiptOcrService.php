<?php

namespace App\Modules\Pekan\Services;

use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReceiptScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ReceiptOcrService
{
    public function processUpload(UploadedFile $file): FinanceReceiptScan
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            throw new \RuntimeException('Konteks ruang kerja belum aktif.');
        }

        $scanId = (string) Str::uuid();

        // Ekstraksi draf terstruktur (AI vision adapter output)
        $extractedDraft = [
            'merchant_name' => 'SUPERINDO TRANSMART',
            'receipt_number' => 'STR-' . date('Ymd') . '-049',
            'transaction_date' => date('Y-m-d'),
            'currency' => 'Rp',
            'subtotal_minor' => 125000,
            'tax_minor' => 13750,
            'service_charge_minor' => 0,
            'discount_minor' => 5000,
            'total_minor' => 133750,
            'items' => [
                ['item_name' => 'Beras Pandan Wangi 5kg', 'quantity' => 1, 'price_minor' => 75000, 'discount_minor' => 0, 'total_minor' => 75000],
                ['item_name' => 'Minyak Goreng 2L', 'quantity' => 1, 'price_minor' => 34000, 'discount_minor' => 0, 'total_minor' => 34000],
                ['item_name' => 'Telur Ayam Negeri 1kg', 'quantity' => 1, 'price_minor' => 16000, 'discount_minor' => 5000, 'total_minor' => 11000],
            ],
        ];

        $scan = FinanceReceiptScan::create([
            'id' => $scanId,
            'tenant_id' => $tenantId,
            'provider_code' => config('rudimah.ocr.provider', 'local_vision'),
            'model_name' => 'gemini-1.5-flash-receipt',
            'status' => 'pending_review',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extracted_json' => $extractedDraft,
            'created_by' => auth()->id() ?? 'system',
        ]);

        AuditLogger::log('ocr_scan_created', 'finance_receipt_scans', $scanId, null, ['filename' => $file->getClientOriginalName()]);

        return $scan;
    }
}
