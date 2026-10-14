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

    'ai' => [
        'default_provider' => env('AI_DEFAULT_PROVIDER', 'gemini'),
        'gemini_key' => env('GEMINI_API_KEY', ''),
        'openai_key' => env('OPENAI_API_KEY', ''),
        'hermes_key' => env('HERMES_API_KEY', ''),
        'hermes_endpoint' => env('HERMES_API_ENDPOINT', 'http://localhost:11434/v1'),
        'openclaw_key' => env('OPENCLAW_API_KEY', ''),
        'openclaw_endpoint' => env('OPENCLAW_API_ENDPOINT', 'http://localhost:8080/v1'),
        'enforce_strict_tenant_isolation' => true,
    ],

    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'gemini'),
        'model' => env('OCR_MODEL', 'gemini-1.5-flash'),
        'max_file_size_bytes' => 10 * 1024 * 1024,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'auto_commit' => false,
    ],

    'notifications' => [
        'mail' => [
            'enabled' => !empty(env('MAIL_HOST')) && env('MAIL_MAILER', 'smtp') !== 'log',
            'host' => env('MAIL_HOST', ''),
            'port' => (int) env('MAIL_PORT', 587),
            'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@rudimah.id'),
        ],
        'telegram' => [
            'enabled' => !empty(env('TELEGRAM_BOT_TOKEN')),
            'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
            'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),
        ],
        'whatsapp' => [
            'enabled' => !empty(env('WAHA_API_URL')) || !empty(env('WHATSAPP_API_URL')),
            'waha_api_url' => env('WAHA_API_URL', env('WHATSAPP_API_URL', '')),
            'waha_api_key' => env('WAHA_API_KEY', env('WHATSAPP_API_KEY', '')),
            'waha_session' => env('WAHA_SESSION', 'default'),
        ],
    ],
];
