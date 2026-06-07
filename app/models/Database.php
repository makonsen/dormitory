<?php
// Database model

require_once __DIR__ . '/../../config.php';

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        try {
            self::$connection = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            self::handleConnectionError($exception);
        }

        self::initialize();
        return self::$connection;
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

    private static function handleConnectionError(PDOException $exception): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $message = sprintf(
            "[%s] %s\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage()
        );
        error_log($message, 3, $logDir . '/db_error.log');

        http_response_code(500);
        echo '<!DOCTYPE html>';
        echo '<html lang="th"><head><meta charset="UTF-8"><title>Database Error</title></head><body>';
        echo '<h1>เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล</h1>';
        echo '<p>โปรดตรวจสอบการตั้งค่าฐานข้อมูลหรือดูบันทึกข้อผิดพลาดใน storage/logs/db_error.log</p>';
        echo '</body></html>';
        exit;
    }
}
