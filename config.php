<?php

define('APP_NAME', 'ระบบจัดการหอพัก');

define(
    'DB_DSN',
    'sqlite:' . __DIR__ . '/storage/database.sqlite'
);

define('DB_USER', null);
define('DB_PASS', null);

define('DB_CONNECTIONS', [
    [
        'name' => 'sqlite',
        'dsn' => 'sqlite:' . __DIR__ . '/storage/database.sqlite',
        'user' => null,
        'pass' => null,
    ],
    [
        'name' => 'mysql_laragon',
        'dsn' => 'mysql:host=127.0.0.1;charset=utf8mb4',
        'user' => 'root',
        'pass' => '',
        'database' => 'dormitory',
    ],
    [
        'name' => 'mysql_config',
        'dsn' => 'mysql:host=localhost;dbname=dormitory;charset=utf8mb4',
        'user' => 'dormitory_user',
        'pass' => 'StrongPassword123!',
    ],
]);
