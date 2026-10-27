<?php

namespace App\Modules\Pekan\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AttachmentUploadService
{
    /**
     * Simpan beberapa gambar ke public/uploads/{folder}
     *
     * @param UploadedFile[] $files
     * @param string $folder 'reminders' | 'savings'
     * @return array
     */
    public function uploadMultiple(array $files, string $folder = 'attachments'): array
    {
        $uploaded = [];
        $targetDir = public_path("uploads/{$folder}");

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                continue;
            }

            $uuid = (string) Str::uuid();
            $filename = "{$uuid}.{$ext}";
            $file->move($targetDir, $filename);

            $filePath = "{$targetDir}/{$filename}";
            $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

            $uploaded[] = [
                'id' => $uuid,
                'name' => $file->getClientOriginalName(),
                'url' => "/uploads/{$folder}/{$filename}",
                'size' => $fileSize,
                'created_at' => now()->toIso8601String(),
            ];
        }

        return $uploaded;
    }

    /**
     * Hapus berkas gambar lama dari direktori public uploads
     */
    public function deleteFile(?string $url): void
    {
        if (empty($url) || !str_starts_with($url, '/uploads/')) {
            return;
        }

        $relativePath = ltrim($url, '/');
        $fullPath = public_path($relativePath);
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
