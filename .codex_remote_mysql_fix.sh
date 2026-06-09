#!/usr/bin/env bash
set -euo pipefail

read -r SUDO_PASSWORD
printf '%s\n' "$SUDO_PASSWORD" | sudo -S -v >/dev/null

sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS dormitory
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'dormitory_user'@'localhost'
    IDENTIFIED BY 'StrongPassword123!';

ALTER USER 'dormitory_user'@'localhost'
    IDENTIFIED BY 'StrongPassword123!';

GRANT ALL PRIVILEGES ON dormitory.*
    TO 'dormitory_user'@'localhost';

FLUSH PRIVILEGES;
SQL

mysql -udormitory_user -pStrongPassword123! -e 'SELECT DATABASE(); SHOW TABLES;' dormitory

cd /var/www/dormitory
php -r 'require "app/models/Database.php"; $db = Database::getConnection(); echo "driver=" . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL; echo "users=" . $db->query("SELECT COUNT(*) FROM users")->fetchColumn() . PHP_EOL; echo "rooms=" . $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn() . PHP_EOL;'
