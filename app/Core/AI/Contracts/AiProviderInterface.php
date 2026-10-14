<?php

namespace App\Core\AI\Contracts;

interface AiProviderInterface
{
    /**
     * Mendapatkan nama pengenal unik provider.
     */
    public function getName(): string;

    /**
     * Mengirim percakapan multi-turn ke model AI dan mengembalikan respon teks terstruktur.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{content: string, model: string, tokens_used: int, metadata?: array}
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Mengekstrak data struk belanja berbasis Computer Vision / Multimodal AI.
     *
     * @param string $fileBase64 Berkas dalam format base64
     * @param string $mimeType Tipe MIME (cth: image/jpeg, image/png, application/pdf)
     * @param array<string, mixed> $options
     * @return array<string, mixed> Data JSON draf kuitansi terstruktur
     */
    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array;
}
