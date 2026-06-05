<?php
session_start();

require_once 'config.php';
require_once 'includes/helper.php';
require_once 'includes/auth.php';
require_once 'app/controllers/HomeController.php';
require_once 'app/controllers/UserController.php';
require_once 'app/controllers/RoomController.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'users':
        UserController::users();
        break;
    case 'rooms':
        RoomController::index();
        break;
    case 'logout':
        UserController::logout();
        break;
    default:
        HomeController::index();
        break;
}