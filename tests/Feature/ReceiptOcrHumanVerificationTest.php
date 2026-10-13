<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReceiptOcrHumanVerificationTest extends TestCase
{
    public function test_ocr_does_not_auto_commit_without_human_verification(): void
    {
        // Kebijakan non-negotiable: OCR tidak boleh mengesahkan transaksi tanpa verifikasi pengguna
        $autoCommit = config('rudimah.ocr.auto_commit');
        $this->assertFalse($autoCommit, 'OCR auto_commit wajib bernilai false untuk menjamin prinsip human-in-the-loop.');
    }
}
