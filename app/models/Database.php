<?php
// Database model

require_once __DIR__ . '/../../config.php';

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): ?PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $errors = [];
        foreach (self::connectionOptions() as $option) {
            try {
                self::$connection = new PDO($option['dsn'], $option['user'], $option['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                if (!empty($option['database'])) {
                    self::prepareMySqlDatabase(self::$connection, $option['database']);
                }

                self::initialize();
                return self::$connection;
            } catch (PDOException $exception) {
                self::$connection = null;
                $errors[] = ($option['name'] ?? $option['dsn']) . ': ' . $exception->getMessage();
            }
        }

        self::handleConnectionError($errors);
    }

    private static function connectionOptions(): array
    {
        if (defined('DB_CONNECTIONS') && is_array(DB_CONNECTIONS)) {
            return DB_CONNECTIONS;
        }

        return [
            [
                'name' => 'default',
                'dsn' => DB_DSN,
                'user' => DB_USER,
                'pass' => DB_PASS,
            ],
        ];
    }

    private static function prepareMySqlDatabase(PDO $conn, string $database): void
    {
        if ($conn->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $database = str_replace('`', '``', $database);
        $conn->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE `{$database}`");
    }

    private static function initialize(): void
    {
        $conn = self::$connection;
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $conn->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    fullname TEXT DEFAULT ""
                )'
            );

            $conn->exec(
                'CREATE TABLE IF NOT EXISTS rooms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    number TEXT NOT NULL,
                    type TEXT NOT NULL,
                    status TEXT NOT NULL
                )'
            );
        } else {
            $conn->exec(
                'CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    fullname VARCHAR(255) DEFAULT ""
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $conn->exec(
                'CREATE TABLE IF NOT EXISTS rooms (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    number VARCHAR(50) NOT NULL,
                    type VARCHAR(100) NOT NULL,
                    status VARCHAR(50) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $count = $conn->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO users (username, password, fullname) VALUES (?, ?, ?)');
            $stmt->execute(['admin', $password, 'ผู้ดูแลระบบ']);
        }

        $count = $conn->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
        if ($count == 0) {
            $stmt = $conn->prepare('INSERT INTO rooms (number, type, status) VALUES (?, ?, ?)');
            $rooms = [
                ['101', 'ห้องเดี่ยว', 'ว่าง'],
                ['102', 'ห้องคู่', 'ไม่ว่าง'],
                ['103', 'ห้องเดี่ยว', 'ว่าง'],
            ];
            foreach ($rooms as $room) {
                $stmt->execute($room);
            }
        }
    }

    private static function handleConnectionError(array $errors): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $message = '[' . date('Y-m-d H:i:s') . "] Database connection failed\n";
        foreach ($errors as $error) {
            $message .= '- ' . $error . "\n";
        }

        $logFile = $logDir . '/db_error.log';
        if (is_dir($logDir)) {
            @file_put_contents($logFile, $message . "\n", FILE_APPEND);
        }

        http_response_code(500);
        echo '<!DOCTYPE html>';
        echo '<html lang="th"><head><meta charset="UTF-8"><title>Database Error</title></head><body>';
        echo '<!-- db-handler-v2 -->';
        echo '<h1>เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h1>';
        echo '<p>โปรดตรวจสอบการตั้งค่าฐานข้อมูลหรือดูบันทึกข้อผิดพลาดใน storage/logs/db_error.log</p>';
        echo '</body></html>';
        exit;
    }
}
