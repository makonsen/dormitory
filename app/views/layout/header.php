<?php
// Header layout
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fb;
            color: #333;
            margin: 0;
            line-height: 1.6;
        }
        .layout {
            width: min(1100px, calc(100% - 40px));
            margin: 0 auto;
            padding: 20px 0;
        }
        header.site-header {
            background: #4f46e5;
            color: #fff;
            padding: 20px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        header .layout {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        header h1 {
            margin: 0;
            font-size: 1.3rem;
        }
        nav a {
            color: #e0e7ff;
            text-decoration: none;
            margin-left: 16px;
            font-weight: 600;
        }
        nav a:hover {
            text-decoration: underline;
        }
        main {
            min-height: calc(100vh - 220px);
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            padding: 26px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            margin-bottom: 24px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-top: 16px;
        }
        table th,
        table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        table th {
            background: #eef2ff;
            color: #1e3a8a;
        }
        .button {
            display: inline-block;
            padding: 12px 22px;
            border-radius: 10px;
            color: #fff;
            background: #4f46e5;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s ease, background 0.2s ease;
        }
        .button:hover {
            transform: translateY(-1px);
            background: #4338ca;
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="layout">
        <h1><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
        <nav>
            <a href="index.php">หน้าหลัก</a>
            <a href="index.php?page=rooms">ห้องพัก</a>
            <a href="index.php?page=users">ผู้ใช้งาน</a>
            <?php if (is_logged_in()): ?>
                <a href="index.php?page=logout">ออกจากระบบ</a>
            <?php else: ?>
                <a href="login.php">เข้าสู่ระบบ</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="layout">
