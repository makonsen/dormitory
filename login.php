<?php
session_start();

require_once 'config.php';
require_once 'includes/helper.php';
require_once 'includes/auth.php';
require_once 'app/models/User.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = User::findByUsername($username);

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        redirect('index.php');
    }

    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: radial-gradient(circle at top, #eef2ff 0%, #c7d2fe 40%, #4338ca 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            padding: 34px;
            border-radius: 18px;
            box-shadow: 0 18px 55px rgba(15, 23, 42, 0.18);
        }
        h1 {
            font-size: 28px;
            margin-bottom: 14px;
            color: #111827;
            text-align: center;
        }
        p {
            color: #475569;
            margin-bottom: 24px;
            text-align: center;
            line-height: 1.6;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 16px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        }
        .btn-submit {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background-color: #4f46e5;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
        }
        .link-back {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        .link-back:hover {
            text-decoration: underline;
        }
        .error-message {
            margin-bottom: 18px;
            color: #b91c1c;
            text-align: center;
            background: #fee2e2;
            padding: 12px 14px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>เข้าสู่ระบบ</h1>
        <p>กรุณาเข้าสู่ระบบด้วยชื่อผู้ใช้และรหัสผ่านของคุณ</p>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" placeholder="กรอกรหัสผ่าน" required>
            </div>
            <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
        </form>
        <a class="link-back" href="index.php">กลับหน้าแรก</a>
    </div>
</body>
</html>
