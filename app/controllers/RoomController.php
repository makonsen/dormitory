<?php
// Room controller

require_once __DIR__ . '/../models/Room.php';

class RoomController
{
    public static function index(): void
    {
        require_login();
        $rooms = Room::getAll();
        view('rooms', [
            'title' => 'ห้องพัก',
            'rooms' => $rooms,
        ]);
    }
}
