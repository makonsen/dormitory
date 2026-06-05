<?php
// General helper functions

function base_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/../app/views/' . $name . '.php';
    if (!file_exists($viewFile)) {
        echo '<p>ไม่พบหน้าจอ: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>';
        return;
    }

    require_once __DIR__ . '/../app/views/layout/header.php';
    require_once $viewFile;
    require_once __DIR__ . '/../app/views/layout/footer.php';
}
