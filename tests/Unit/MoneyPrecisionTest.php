<?php

namespace Tests\Unit;

use App\Shared\Helpers\MoneyHelper;
use PHPUnit\Framework\TestCase;

class MoneyPrecisionTest extends TestCase
{
    public function test_format_idr_outputs_correct_indonesian_rupiah_format(): void
    {
        $this->assertEquals('Rp 15.500.000', MoneyHelper::formatIdr(15500000));
        $this->assertEquals('Rp 0', MoneyHelper::formatIdr(0));
        $this->assertEquals('- Rp 450.000', MoneyHelper::formatIdr(-450000));
        $this->assertEquals('+ Rp 1.200.000', MoneyHelper::formatIdr(1200000, true));
    }
}
