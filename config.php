
<?php
return [
    'db_driver'   => getenv('DB_DRIVER') ?: 'pgsql',
    'db_host'     => getenv('DB_HOST') ?: 'localhost',
    'db_port'     => getenv('DB_PORT') ?: '5432',
    'db_name'     => getenv('DB_NAME') ?: 'tulipschat',
    'db_user'     => getenv('DB_USER') ?: 'root',
    'db_pass'     => getenv('DB_PASS') ?: '',
    'db_sslmode'  => getenv('DB_SSLMODE') ?: 'require',

    'sqlite_path' => __DIR__ . '/data/chat.sqlite',
    'online_timeout' => 60,
    'poll_ms' => 2000,
    'timezone' => 'Asia/Kolkata',
];
