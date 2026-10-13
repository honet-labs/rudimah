<?php

return [
    'default' => env('DB_CONNECTION', 'platform'),

    'connections' => [
        'platform' => [
            'driver' => 'mariadb',
            'url' => env('DB_PLATFORM_URL'),
            'host' => env('DB_PLATFORM_HOST', env('DB_HOST', 'mariadb')),
            'port' => env('DB_PLATFORM_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_PLATFORM_DATABASE', 'rudimah_platform'),
            'username' => env('DB_PLATFORM_USERNAME', 'rudimah_user'),
            'password' => env('DB_PLATFORM_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],

        'pekan' => [
            'driver' => 'mariadb',
            'url' => env('DB_PEKAN_URL'),
            'host' => env('DB_PEKAN_HOST', env('DB_HOST', 'mariadb')),
            'port' => env('DB_PEKAN_PORT', env('DB_PORT', '3306')),
            'database' => env('DB_PEKAN_DATABASE', 'rudimah_pekan'),
            'username' => env('DB_PEKAN_USERNAME', 'pekan_user'),
            'password' => env('DB_PEKAN_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'rudimah_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', 'redis'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
