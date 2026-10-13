<?php

return [
    'name' => env('APP_NAME', 'RUDIMAH'),
    'tagline' => 'Ruang Digital Manajemen Harian',
    'locale' => 'id_ID',
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    'modules' => [
        'pekan' => [
            'key' => 'pekan',
            'slug' => 'pekan',
            'name' => 'PEKAN',
            'description' => 'Pengelolaan keuangan harian, transaksi, dan anggaran',
            'enabled' => true,
        ],
        'app1' => [
            'key' => 'app1',
            'slug' => 'app1',
            'name' => 'Catatan Harian',
            'description' => 'Log harian dan agenda tugas',
            'enabled' => false,
        ],
        'app2' => [
            'key' => 'app2',
            'slug' => 'app2',
            'name' => 'Inventaris Rumah',
            'description' => 'Pencatatan aset dan garansi barang',
            'enabled' => false,
        ],
    ],

    'mfa' => [
        'privileged_roles' => ['owner', 'admin'],
        'required_methods' => ['passkey', 'totp'],
        'allowed_low_assurance_channels' => ['email', 'whatsapp', 'telegram'],
        'otp_ttl_seconds' => 300,
        'otp_resend_cooldown_seconds' => 60,
        'max_attempts' => 5,
    ],

    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'local_vision'),
        'max_file_size_bytes' => 10 * 1024 * 1024,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'auto_commit' => false,
    ],
];
