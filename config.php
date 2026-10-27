<?php
return [
    'db_driver' => getenv('DB_DRIVER') ?: 'pgsql',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => (int) (getenv('DB_PORT') ?: 3306),
    'db_name' => getenv('DB_NAME') ?: 'tulipschat',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'db_sslmode' => getenv('DB_SSLMODE') ?: 'require',

    'sqlite_path' => __DIR__ . '/data/chat.sqlite',
    'online_timeout' => 60,
    'poll_ms' => 2000,
    'timezone' => 'Asia/Kolkata',
];
