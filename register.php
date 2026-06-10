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
    $fullname = sanitize($_POST['fullname'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } elseif (User::findByUsername($username)) {
        $error = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
    } else {
        $created = User::create($username, $password, $fullname);
        if ($created) {
            $user = User::findByUsername($username);
            if ($user) {
                login_user($user);
                redirect('index.php');
            }
        }
        $error = 'ไม่สามารถสมัครสมาชิกได้ โปรดลองอีกครั้ง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; padding: 40px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 12px; box-shadow: 0 10px 30px rgba(2,6,23,0.08); }
        h1 { text-align: center; margin-bottom: 12px; }
        .form-group { margin-bottom: 14px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="password"] { width:100%; padding:12px 14px; border-radius:8px; border:1px solid #dbe4f5; }
        .btn { width:100%; padding:12px 14px; background:#4f46e5; color:#fff; border:none; border-radius:10px; font-weight:700; }
        .error { margin-bottom:12px; color:#b91c1c; background:#fee2e2; padding:10px; border-radius:8px; }
        .link { display:block; text-align:center; margin-top:12px; color:#4f46e5; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>สมัครสมาชิก</h1>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="register.php">
            <div class="form-group">
                <label for="username">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" placeholder="กรอกชื่อผู้ใช้" required>
            </div>
            <div class="form-group">
                <label for="fullname">ชื่อ-นามสกุล (ไม่บังคับ)</label>
                <input type="text" id="fullname" name="fullname" placeholder="ชื่อเต็ม">
            </div>
            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" placeholder="รหัสผ่าน" required>
            </div>
            <button class="btn" type="submit">สมัครสมาชิก</button>
        </form>
        <a class="link" href="login.php">มีบัญชีอยู่แล้ว? เข้าสู่ระบบ</a>
        <a class="link" href="index.php">กลับหน้าหลัก</a>
    </div>
</body>
</html>
