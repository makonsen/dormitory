<?php
// Home controller

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Room.php';

class HomeController
{
    public static function index(): void
    {
        $users = User::getAll();
        $rooms = Room::getAll();
        view('home', [
            'title' => 'หน้าหลัก',
            'users' => $users,
            'rooms' => $rooms,
        ]);
    }
}
