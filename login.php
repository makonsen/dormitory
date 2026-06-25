<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

define('USERS_FILE', 'users.json');

// Function to load users from JSON file
function getUsers() {
    if (!file_exists(USERS_FILE)) {
        return [];
    }
    $usersJson = file_get_contents(USERS_FILE);
    $users = json_decode($usersJson, true);
    return is_array($users) ? $users : [];
}

// Function to save users to JSON file
function saveUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Handle Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $message = '';

    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($password !== $confirmPassword) {
        $message = 'รหัสผ่านไม่ตรงกัน';
    } else {
        $users = getUsers();
        if (isset($users[$username])) {
            $message = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
        } else {
            $users[$username] = ['password' => password_hash($password, PASSWORD_DEFAULT)];
            saveUsers($users);
            $message = 'ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ';
            // Clear form fields after successful registration
            $_POST = array(); 
        }
    }
    $_SESSION['message'] = $message;
    header('Location: login.php');
    exit;
}

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $message = '';

    $users = getUsers();

    if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
        $_SESSION['user_id'] = $username; // Store username in session
        header('Location: index.php'); // Redirect to the main application page
        exit;
    } else {
        $message = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    $_SESSION['message'] = $message;
    header('Location: login.php');
    exit;
}

// Check for messages from redirects
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying
}

// If already logged in, redirect to index.php
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบัญชีส่วนบุคคล Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">เข้าสู่ระบบ</h2>

        <?php if ($message): ?>
            <div class="bg-<?= strpos($message, 'สำเร็จ') !== false ? 'green' : 'red' ?>-100 border border-<?= strpos($message, 'สำเร็จ') !== false ? 'green' : 'red' ?>-400 text-<?= strpos($message, 'สำเร็จ') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $message ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                <input type="text" id="username" name="username" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
            <div>
                <button type="submit" name="login" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    เข้าสู่ระบบ
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">ยังไม่มีบัญชี? <a href="#" id="show-register" class="font-medium text-indigo-600 hover:text-indigo-500">ลงทะเบียนที่นี่</a></p>
        </div>

        <div id="register-form-container" class="mt-8 p-6 border border-gray-200 rounded-lg bg-gray-50 hidden">
            <h3 class="text-xl font-bold text-center text-gray-800 mb-4">ลงทะเบียนผู้ใช้ใหม่</h3>
            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="reg_username" class="block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                    <input type="text" id="reg_username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="reg_password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                    <input type="password" id="reg_password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="reg_confirm_password" class="block text-sm font-medium text-gray-700">ยืนยันรหัสผ่าน</label>
                    <input type="password" id="reg_confirm_password" name="confirm_password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <button type="submit" name="register" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                        ลงทะเบียน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('show-register').addEventListener('click', function(e) {
            e.preventDefault();
            const registerForm = document.getElementById('register-form-container');
            registerForm.classList.toggle('hidden');
            if (!registerForm.classList.contains('hidden')) {
                registerForm.scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Show register form if there was a registration attempt (e.g., error or success)
        <?php if (isset($_POST['register']) || (isset($_SESSION['message']) && (strpos($_SESSION['message'], 'ลงทะเบียน') !== false || strpos($_SESSION['message'], 'ชื่อผู้ใช้มีอยู่แล้ว') !== false))): ?>
            document.getElementById('register-form-container').classList.remove('hidden');
        <?php endif; ?>
    </script>
</body>
</html>