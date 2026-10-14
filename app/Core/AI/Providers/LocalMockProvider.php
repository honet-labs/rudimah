<?php

namespace App\Core\AI\Providers;

use App\Core\AI\Contracts\AiProviderInterface;

class LocalMockProvider implements AiProviderInterface
{
    public function getName(): string
    {
        return 'mock';
    }

    public function chat(array $messages, array $options = []): array
    {
        $lastMsg = end($messages)['content'] ?? '';
        $model = $options['model'] ?? 'local-mock-v1';

        $reply = "Halo dari Local Mock Assistant ({$model}). Pesan Anda: \"" . mb_strimwidth($lastMsg, 0, 80, '...') . "\" telah diterima dalam batas aman ruang kerja Anda.";

        return [
            'content' => $reply,
            'model' => $model,
            'tokens_used' => 42,
            'metadata' => [
                'provider' => 'mock',
                'offline' => true,
            ],
        ];
    }

    public function extractReceipt(string $fileBase64, string $mimeType, array $options = []): array
    {
        return [
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
    }
}
