<?php

define('APP_NAME', 'ระบบจัดการหอพัก');

// Database configuration for MySQL (SQLite driver not available)
define(
    'DB_DSN',
    'mysql:host=localhost;charset=utf8mb4'
);

define('DB_USER', 'root');
define('DB_PASS', 'root');

define('DB_CONNECTIONS', [
    [
        'name' => 'mysql_mamp',
        'dsn' => 'mysql:host=localhost;charset=utf8mb4',
        'user' => 'root',
        'pass' => 'root',
        'database' => 'dormitory',
    ],
    [
        'name' => 'mysql_localhost',
        'dsn' => 'mysql:host=127.0.0.1:3306;charset=utf8mb4',
        'user' => 'root',
        'pass' => 'root',
        'database' => 'dormitory',
    ],
]);
