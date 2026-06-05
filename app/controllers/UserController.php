<?php
// User controller

require_once __DIR__ . '/../models/User.php';

class UserController
{
    public static function users(): void
    {
        require_login();
        $users = User::getAll();
        view('users', [
            'title' => 'ผู้ใช้งาน',
            'users' => $users,
        ]);
    }

    public static function logout(): void
    {
        logout_user();
        redirect('index.php');
    }
}
