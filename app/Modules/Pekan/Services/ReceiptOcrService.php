<?php

namespace App\Modules\Pekan\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Services\PlatformAiService;
use App\Core\Audit\AuditLogger;
use App\Core\Tenancy\TenantContext;
use App\Modules\Pekan\Models\FinanceReceiptScan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
        PlatformAiService::applyRuntimeConfig();

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
        $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \InvalidArgumentException('Tipe berkas tidak didukung untuk pemindaian OCR. Gunakan gambar JPG, PNG, WEBP, atau berkas PDF.');
        }

        $scanId = (string) Str::uuid();
        $providerCode = config('rudimah.ocr.provider', config('rudimah.ai.default_provider', 'gemini'));
        $modelName = config('rudimah.ocr.model', config("rudimah.ai.{$providerCode}_model", 'high-models-agent'));
        $provider = $this->providerManager->resolve($providerCode);

        // Ekstraksi via AI Vision Provider
        $fileBase64 = base64_encode($file->getContent());

        try {
            $extractedDraft = $provider->extractReceipt($fileBase64, $mimeType, [
                'model' => $modelName,
            ]);
        } catch (\Throwable $e) {
            Log::error("[ReceiptOcrService] Ekstraksi OCR gagal ({$providerCode}): " . $e->getMessage());

            FinanceReceiptScan::create([
                'id' => $scanId,
                'tenant_id' => $tenantId,
                'provider_code' => $providerCode,
                'model_name' => $modelName,
                'status' => 'failed',
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'extracted_json' => null,
                'error_message' => $e->getMessage(),
                'created_by' => auth()->id() ?? 'system',
            ]);

            throw new \RuntimeException("Gagal memproses struk dengan model AI {$providerCode}: " . $e->getMessage());
        }

        $scan = FinanceReceiptScan::create([
            'id' => $scanId,
            'tenant_id' => $tenantId,
            'provider_code' => $provider->getName(),
            'model_name' => $modelName,
            'status' => 'pending_review',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'extracted_json' => $extractedDraft,
            'created_by' => auth()->id() ?? 'system',
        ]);

        AuditLogger::log('ocr_scan_created', 'finance_receipt_scans', $scanId, null, [
            'filename' => $file->getClientOriginalName(),
            'provider' => $provider->getName(),
            'model' => $modelName,
        ]);

        return $scan;
    }
}
