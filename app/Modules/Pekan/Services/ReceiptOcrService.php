<?php

namespace App\Modules\Pekan\Services;

use App\Core\AI\AiProviderManager;
use App\Core\AI\Models\TenantAiSetting;
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
        @set_time_limit(180);
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

        // Ambil konfigurasi AI ruang kerja atau platform global (100% sinkron dan sama persis dengan Chat AI)
        $tenantSetting = TenantAiSetting::where('tenant_id', $tenantId)->first();
        if ($tenantSetting && !$tenantSetting->is_enabled) {
            throw new \RuntimeException('Layanan AI dinonaktifkan untuk ruang kerja ini.');
        }

        $platformSettings = PlatformAiService::getSettings();
        $activePlatformProvider = $platformSettings['active_provider'] ?? config('rudimah.ai.default_provider', 'gemini');
        $activeProviderDetails = $platformSettings['providers'][$activePlatformProvider] ?? [];

        $hasCustomKey = !empty($tenantSetting?->api_key_encrypted) && !empty($tenantSetting?->api_key);
        $isUsingCustom = $hasCustomKey && !empty($tenantSetting?->provider);

        // 1. Tentukan penyedia utama aktif
        $providerCode = $isUsingCustom ? $tenantSetting->provider : $activePlatformProvider;
        $primaryNormalized = (strtolower($providerCode) === 'ninerouter') ? '9router' : strtolower($providerCode);

        // 2. Susun daftar penyedia terkonfigurasi untuk dicoba secara berurutan:
        //    Prioritaskan penyedia utama, lalu coba penyedia lain yang memiliki kunci API terkonfigurasi di Administrasi Sistem
        $providersToTry = [$primaryNormalized];

        if (!$isUsingCustom && !empty($platformSettings['providers'])) {
            foreach (['gemini', 'openai', 'claude', '9router'] as $pCode) {
                if ($pCode !== $primaryNormalized && !empty($platformSettings['providers'][$pCode]['is_configured'])) {
                    $providersToTry[] = $pCode;
                }
            }
        }

        $scanId = (string) Str::uuid();

        // Ekstraksi via AI Vision Provider dengan pra-optimasi resolusi gambar
        [$fileBase64, $preparedMime] = $this->prepareImageForOcr($file, $mimeType);

        // Simpan salinan berkas fisik kuitansi/struk di public/uploads/receipts agar terlampir permanen
        $uploadService = new AttachmentUploadService();
        $savedImages = $uploadService->uploadMultiple([$file], 'receipts');

        $extractedDraft = null;
        $successfulProvider = $primaryNormalized;
        $successfulModel = null;
        $errors = [];

        foreach ($providersToTry as $pCode) {
            $pDetails = $platformSettings['providers'][$pCode] ?? [];
            $mName = ($pCode === $primaryNormalized && $isUsingCustom)
                ? ($tenantSetting->model ?: ($pDetails['model'] ?? ''))
                : ($pDetails['model'] ?? config("rudimah.ai.{$pCode}_model", ''));

            $ePoint = ($pCode === $primaryNormalized && $isUsingCustom)
                ? $tenantSetting->api_endpoint
                : ($pDetails['api_endpoint'] ?? config("rudimah.ai.{$pCode}_endpoint"));

            try {
                $providerInstance = $this->providerManager->resolve($pCode, $pCode === $primaryNormalized ? $tenantSetting : null);
                $result = $providerInstance->extractReceipt($fileBase64, $preparedMime, [
                    'model' => $mName,
                    'api_endpoint' => $ePoint,
                ]);

                if (is_array($result) && !empty($result)) {
                    $extractedDraft = $result;
                    $successfulProvider = $providerInstance->getName();
                    $successfulModel = $mName;
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning("[ReceiptOcrService] Ekstraksi OCR dengan penyedia {$pCode} ({$mName}) gagal: " . $e->getMessage());
                $errors[$pCode] = $e->getMessage();
            }
        }

        if (!$extractedDraft) {
            $errorSummary = implode('; ', array_map(fn($p, $msg) => "{$p}: {$msg}", array_keys($errors), $errors));

            FinanceReceiptScan::create([
                'id' => $scanId,
                'tenant_id' => $tenantId,
                'provider_code' => $primaryNormalized,
                'model_name' => $successfulModel ?: 'unknown',
                'status' => 'failed',
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'images' => $savedImages,
                'extracted_json' => null,
                'error_message' => $errorSummary,
                'created_by' => auth()->id() ?? 'system',
            ]);

            throw new \RuntimeException("Gagal memproses struk dengan seluruh penyedia AI terkonfigurasi: {$errorSummary}");
        }

        $extractedDraft['_receipt_images'] = $savedImages;

        $scan = FinanceReceiptScan::create([
            'id' => $scanId,
            'tenant_id' => $tenantId,
            'provider_code' => $successfulProvider,
            'model_name' => $successfulModel,
            'status' => 'pending_review',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'images' => $savedImages,
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

    /**
     * Mengoptimasi ukuran gambar struk belanja sebelum dikirim ke gateway Vision AI.
     * Mengubah resolusi gambar ultra-besar menjadi maksimal 1600px dan mengompres ke JPEG
     * agar payload cepat terunggah dan model vision memprosesnya tanpa timeout.
     */
    protected function prepareImageForOcr(UploadedFile $file, string $mimeType): array
    {
        if ($mimeType === 'application/pdf' || !extension_loaded('gd')) {
            return [base64_encode($file->getContent()), $mimeType];
        }

        try {
            $path = $file->getRealPath();
            if (!$path || !file_exists($path)) {
                return [base64_encode($file->getContent()), $mimeType];
            }

            $img = match ($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($path),
                'image/png' => @imagecreatefrompng($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                default => null,
            };

            if (!$img) {
                return [base64_encode($file->getContent()), $mimeType];
            }

            $width = imagesx($img);
            $height = imagesy($img);

            $maxDim = 1600;
            if ($width > $maxDim || $height > $maxDim) {
                if ($width >= $height) {
                    $newWidth = $maxDim;
                    $newHeight = (int) round($height * ($maxDim / $width));
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int) round($width * ($maxDim / $height));
                }

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($img);
                $img = $resized;
            }

            ob_start();
            imagejpeg($img, null, 85);
            $compressedData = ob_get_clean();
            imagedestroy($img);

            if (!empty($compressedData)) {
                return [base64_encode($compressedData), 'image/jpeg'];
            }
        } catch (\Throwable $e) {
            Log::warning('[ReceiptOcrService] Gagal kompresi gambar pra-OCR, menggunakan berkas asli: ' . $e->getMessage());
        }

        return [base64_encode($file->getContent()), $mimeType];
    }
}
