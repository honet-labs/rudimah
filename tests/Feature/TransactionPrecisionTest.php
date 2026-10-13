<?php

namespace Tests\Feature;

use App\Shared\Helpers\MoneyHelper;
use Tests\TestCase;

class TransactionPrecisionTest extends TestCase
{
    public function test_integer_minor_units_preserve_exact_amounts(): void
    {
        $priceMinor = 75000; // Rp 75.000
        $qty = 3;
        $discountMinor = 5000; // Rp 5.000
        $taxMinor = 12000; // Rp 12.000

        $subtotal = ($priceMinor * $qty);
        $total = $subtotal - $discountMinor + $taxMinor;

        $this->assertEquals(225000, $subtotal);
        $this->assertEquals(232000, $total);

        // Uji pemformatan teks rupiah id-ID
        $formatted = MoneyHelper::formatIdr($total);
        $this->assertEquals('Rp 232.000', $formatted);
    }
}
