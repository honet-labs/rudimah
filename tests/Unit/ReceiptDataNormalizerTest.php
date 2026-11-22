<?php

namespace Tests\Unit;

use App\Modules\Pekan\Services\ReceiptDataNormalizer;
use PHPUnit\Framework\TestCase;

class ReceiptDataNormalizerTest extends TestCase
{
    public function test_indonesian_dates_are_day_first_and_invalid_dates_are_not_guessed(): void
    {
        foreach (['03/11/26' => '2026-11-03', '19-11-2026 12:53' => '2026-11-19',
            '19 November 2026' => '2026-11-19', '2026-11-19' => '2026-11-19',
            '29/02/2024' => '2024-02-29'] as $input => $expected) {
            $this->assertSame($expected, ReceiptDataNormalizer::date($input));
        }
        foreach (['31/02/2026', '29/02/2025', '11/19/2026', '19/11', '', null] as $input) {
            $this->assertNull(ReceiptDataNormalizer::date($input));
        }
    }

    public function test_printed_date_has_priority_over_model_interpretation(): void
    {
        $receipt = ReceiptDataNormalizer::normalize([
            'merchant_name' => 'Toko Uji', 'total_minor' => 45000, 'subtotal_minor' => 48500,
            'discount_minor' => 3500, 'transaction_date_raw' => '03/11/26', 'transaction_date' => '2026-03-11',
        ]);
        $this->assertSame('2026-11-03', $receipt['transaction_date']);
        $this->assertSame(45000, $receipt['total_minor']);
        $this->assertTrue($receipt['totals_match']);
    }

    public function test_missing_date_and_total_mismatch_require_review_without_replacing_printed_total(): void
    {
        $receipt = ReceiptDataNormalizer::normalize([
            'merchant_name' => 'Toko Uji', 'total_minor' => 45000, 'subtotal_minor' => 48500,
            'items' => [['item_name' => 'Barang', 'price_minor' => 48500, 'total_minor' => 48500]],
        ]);
        $this->assertNull($receipt['transaction_date']);
        $this->assertTrue($receipt['date_needs_review']);
        $this->assertFalse($receipt['totals_match']);
        $this->assertSame(45000, $receipt['total_minor']);
    }

    public function test_rupiah_separators_and_zero_price_promotions_are_preserved(): void
    {
        foreach (['Rp 48.500' => 48500, '48.500,00' => 48500, '48,500.00' => 48500, '48500.00' => 48500] as $raw => $amount) {
            $this->assertSame($amount, ReceiptDataNormalizer::money($raw));
        }
        $receipt = ReceiptDataNormalizer::normalize([
            'merchant_name' => 'Toko', 'total_minor' => 45000,
            'items' => [['item_name' => 'Bonus', 'quantity' => 1, 'price_minor' => 20000, 'total_minor' => 0]],
        ]);
        $this->assertSame(0, $receipt['items'][0]['total_minor']);
    }

    public function test_unreadable_receipt_is_rejected_instead_of_becoming_an_empty_transaction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ReceiptDataNormalizer::normalize([]);
    }
}
