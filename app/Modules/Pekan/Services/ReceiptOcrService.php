<?php

namespace App\Modules\Pekan\Services;

use App\Core\AI\AiProviderManager;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReceiptScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ReceiptOcrService
{
    protected AiProviderManager $providerManager;

    public function __construct(?AiProviderManager $providerManager = null)
    {
        $this->providerManager = $providerManager ?: new AiProviderManager();
    }

    public function processUpload(UploadedFile $file): FinanceReceiptScan
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            throw new \RuntimeException('Konteks ruang kerja belum aktif.');
        }

        // Validasi batasan berkas
        $maxBytes = config('rudimah.ocr.max_file_size_bytes', 10 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('Ukuran berkas melebihi batas maksimum 10MB.');
        }

        $allowedMimes = config('rudimah.ocr.allowed_mimes', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
        if (!in_array($file->getClientMimeType(), $allowedMimes, true)) {
            throw new \InvalidArgumentException('Tipe berkas tidak didukung untuk pemindaian OCR.');
        }

        $scanId = (string) Str::uuid();
        $providerCode = config('rudimah.ocr.provider', 'gemini');
        $provider = $this->providerManager->resolve($providerCode);

        // Ekstraksi via AI Vision Provider
        $fileBase64 = base64_encode($file->getContent());
        $mimeType = $file->getClientMimeType();

        try {
            $extractedDraft = $provider->extractReceipt($fileBase64, $mimeType, [
                'model' => config('rudimah.ocr.model', 'gemini-1.5-flash'),
            ]);
        } catch (\Throwable $e) {
            $extractedDraft = [
                'error' => 'Gagal membaca struk secara otomatis: ' . $e->getMessage(),
                'merchant_name' => 'Koreksi Manual Diperlukan',
                'receipt_number' => 'ERR-' . date('Ymd-His'),
                'transaction_date' => date('Y-m-d'),
                'currency' => 'Rp',
                'subtotal_minor' => 0,
                'tax_minor' => 0,
                'service_charge_minor' => 0,
                'discount_minor' => 0,
                'total_minor' => 0,
                'items' => [],
            ];
        }

        $scan = FinanceReceiptScan::create([
            'id' => $scanId,
            'tenant_id' => $tenantId,
            'provider_code' => $provider->getName(),
            'model_name' => config('rudimah.ocr.model', 'gemini-1.5-flash'),
            'status' => 'pending_review',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'extracted_json' => $extractedDraft,
            'created_by' => auth()->id() ?? 'system',
        ]);

        AuditLogger::log('ocr_scan_created', 'finance_receipt_scans', $scanId, null, [
            'filename' => $file->getClientOriginalName(),
            'provider' => $provider->getName(),
        ]);

        return $scan;
    }
}
