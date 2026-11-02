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

        // Ambil penyedia dan model yang dikonfigurasi di Administrasi Sistem atau Ruang Kerja
        $providerCode = $isUsingCustom ? $tenantSetting->provider : $activePlatformProvider;
        $normalizedCode = (strtolower($providerCode) === 'ninerouter') ? '9router' : strtolower($providerCode);
        $activeDetails = $isUsingCustom ? [] : ($platformSettings['providers'][$normalizedCode] ?? []);

        $modelName = $isUsingCustom
            ? ($tenantSetting->model ?: ($activeDetails['model'] ?? ''))
            : ($activeDetails['model'] ?? config("rudimah.ai.{$normalizedCode}_model", ''));

        if (empty($modelName)) {
            $modelName = match ($normalizedCode) {
                'openai' => 'gpt-4o-mini',
                'gemini' => 'gemini-1.5-flash',
                'claude' => 'claude-3-5-sonnet-20241022',
                '9router' => config('rudimah.ai.9router_model', config('rudimah.ai.ninerouter_model', 'gpt-4o-mini')),
                default => 'gpt-4o-mini',
            };
        }

        $endpoint = $isUsingCustom
            ? $tenantSetting->api_endpoint
            : ($activeDetails['api_endpoint'] ?? config("rudimah.ai.{$normalizedCode}_endpoint"));

        $scanId = (string) Str::uuid();
        $provider = $this->providerManager->resolve($normalizedCode, $tenantSetting);

        // Ekstraksi via AI Vision Provider dengan pra-optimasi resolusi gambar
        [$fileBase64, $preparedMime] = $this->prepareImageForOcr($file, $mimeType);

        // Simpan salinan berkas fisik kuitansi/struk di public/uploads/receipts agar terlampir permanen
        $uploadService = new AttachmentUploadService();
        $savedImages = $uploadService->uploadMultiple([$file], 'receipts');

        try {
            $extractedDraft = $provider->extractReceipt($fileBase64, $preparedMime, [
                'model' => $modelName,
                'api_endpoint' => $endpoint,
            ]);
        } catch (\Throwable $e) {
            Log::error("[ReceiptOcrService] Ekstraksi OCR gagal ({$providerCode} - {$modelName}): " . $e->getMessage());

            FinanceReceiptScan::create([
                'id' => $scanId,
                'tenant_id' => $tenantId,
                'provider_code' => $normalizedCode,
                'model_name' => $modelName,
                'status' => 'failed',
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'images' => $savedImages,
                'extracted_json' => null,
                'error_message' => $e->getMessage(),
                'created_by' => auth()->id() ?? 'system',
            ]);

            throw new \RuntimeException($e->getMessage());
        }

        if (is_array($extractedDraft)) {
            $extractedDraft['_receipt_images'] = $savedImages;
        }

        $scan = FinanceReceiptScan::create([
            'id' => $scanId,
            'tenant_id' => $tenantId,
            'provider_code' => $provider->getName(),
            'model_name' => $modelName,
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

        // Jika berkas sudah berupa JPEG dengan ukuran ringan (hasil pra-kompresi browser <= 500KB), langsung gunakan tanpa proses GD ulang
        if ($file->getSize() <= 500 * 1024 && in_array(strtolower($mimeType), ['image/jpeg', 'image/jpg'], true)) {
            return [base64_encode($file->getContent()), 'image/jpeg'];
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

            $maxDim = 1280;
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
            imagejpeg($img, null, 75);
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
