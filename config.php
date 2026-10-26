<?php
return [
    // Database settings from Render Environment
    'db_driver' => getenv('DB_DRIVER') ?: 'pgsql',
    'db_host' => getenv('DB_HOST') ?: '',
    'db_port' => getenv('DB_PORT') ?: '5432',
    'db_name' => getenv('DB_NAME') ?: 'tulipschat',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_sslmode' => getenv('DB_SSLMODE') ?: 'require',

    // SQLite fallback path
    'sqlite_path' => __DIR__ . '/data/chat.sqlite',

    // Chat settings
    'online_timeout' => 60,
    'poll_ms' => 2000,
    'timezone' => 'Asia/Kolkata',
];
