<?php
// Room model

require_once __DIR__ . '/Database.php';

class Room
{
    public static function getAll(): array
    {
        $conn = Database::getConnection();
        return $conn->query('SELECT * FROM rooms ORDER BY number ASC')->fetchAll();
    }
}
