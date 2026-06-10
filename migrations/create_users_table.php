<?php
// Migration: create users table for SQLite
try {
    $dbFile = __DIR__ . '/../storage/database.sqlite';
    $dsn = 'sqlite:' . $dbFile;
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password TEXT NOT NULL,
      fullname TEXT
    );");

    echo "Migration completed: users table created or already exists\n";
} catch (Exception $e) {
    echo 'Migration error: ' . $e->getMessage() . "\n";
    exit(1);
}
