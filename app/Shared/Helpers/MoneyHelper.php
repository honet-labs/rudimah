<?php

namespace App\Shared\Helpers;

class MoneyHelper
{
    /**
     * Konversi minor unit (rupiah penuh) ke format teks lokal id-ID.
     */
    public static function formatIdr(int $amountMinor, bool $showSign = false): string
    {
        $isNegative = $amountMinor < 0;
        $absValue = abs($amountMinor);

        $formatted = number_format($absValue, 0, ',', '.');

        $prefix = 'Rp ';
        if ($isNegative) {
            $prefix = '- Rp ';
        } elseif ($showSign && $amountMinor > 0) {
            $prefix = '+ Rp ';
        }

        return $prefix . $formatted;
    }
}
