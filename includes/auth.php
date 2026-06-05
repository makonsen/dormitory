<?php
// Authentication helpers

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'fullname' => $user['fullname'] ?? ''
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
    session_destroy();
}
