
<?php
return [
    'db_driver'   => getenv('DB_DRIVER') ?: 'mysql',
    'db_host'     => getenv('DB_HOST') ?: 'localhost',
    'db_name'     => getenv('DB_NAME') ?: 'tulipschat',
    'db_user'     => getenv('DB_USER') ?: 'root',
    'db_pass'     => getenv('DB_PASS') ?: '',
    'sqlite_path' => __DIR__ . '/data/chat.sqlite',

    'online_timeout' => 60,
    'poll_ms'        => 2000,
    'timezone'       => 'Asia/Kolkata',
];
