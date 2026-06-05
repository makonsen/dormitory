<?php
// User model

require_once __DIR__ . '/Database.php';

class User
{
    public static function findByUsername(string $username): array
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: [];
    }

    public static function getAll(): array
    {
        $conn = Database::getConnection();
        return $conn->query('SELECT id, username, fullname FROM users ORDER BY id ASC')->fetchAll();
    }
}
