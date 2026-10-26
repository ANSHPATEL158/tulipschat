
<?php

return [
    // Local XAMPP MySQL database
    'db_driver' => 'mysql',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'tulipschat',
    'db_user' => 'root',
    'db_pass' => '',

    // SQLite fallback path
    'sqlite_path' => __DIR__ . '/data/chat.sqlite',

    // Chat settings
    'online_timeout' => 60,
    'poll_ms' => 2000,
    'timezone' => 'Asia/Kolkata',
];
