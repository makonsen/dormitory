<?php
session_start();

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Check for messages from redirects (e.g., from login.php or validation errors)
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message after displaying
}
// Check for post data to re-populate forms if validation failed
$old_post_data = isset($_SESSION['post_data']) ? $_SESSION['post_data'] : [];
unset($_SESSION['post_data']);

// Check if user is logged in, otherwise redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
date_default_timezone_set('Asia/Bangkok');
$now = new DateTimeImmutable();

define('DATA_FILE', 'data.json');

// Function to load data from JSON file
function getData()
{
    if (!file_exists(DATA_FILE)) {
        // หากไฟล์ไม่มีอยู่ ให้คืนค่าโครงสร้างเริ่มต้นที่ว่างเปล่า
        error_log("DATA_FILE not found: " . DATA_FILE);
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    $jsonData = @file_get_contents(DATA_FILE); // ใช้ @ เพื่อซ่อน warning
    if ($jsonData === false) {
        error_log("Failed to read DATA_FILE: " . DATA_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถอ่านไฟล์ข้อมูลหลักได้ กรุณาลองใหม่ภายหลัง';
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in DATA_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไฟล์ข้อมูลหลักเสียหาย กรุณาติดต่อผู้ดูแลระบบ';
        return ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []];
    }
    // ตรวจสอบให้แน่ใจว่าข้อมูลที่อ่านมาเป็น array และมี key หลักครบถ้วน
    $data = is_array($data) ? $data : [];
    $data += ['incomes' => [], 'expenses' => [], 'items' => [], 'rents' => []]; // เพิ่ม key ที่อาจขาดไป
    return $data;
}

// Function to save data to JSON file
function saveData($data): bool
{
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonData === false) {
        error_log("JSON encode error for DATA_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไม่สามารถบันทึกข้อมูลได้: ข้อมูลไม่ถูกต้อง';
        return false;
    }
    if (@file_put_contents(DATA_FILE, $jsonData) === false) { // ใช้ @ เพื่อซ่อน warning
        error_log("Failed to write DATA_FILE: " . DATA_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถบันทึกไฟล์ข้อมูลหลักได้ กรุณาลองใหม่ภายหลัง';
        return false;
    }
    return true;
}

// Function to load categories from JSON file
function getCategories()
{
    define('CATEGORIES_FILE', 'categories.json');
    $defaultCategories = [
        'income_categories' => ['เงินค่าประกัน', 'เงินค่าห้อง', 'ขายของ', 'ดอกเบี้ย/ปันผล', 'เงินให้เปล่า', 'อื่นๆ'],
        'expense_categories' => ['วัสดุและอุปกรณ์', 'ค่าไฟ', 'ค่าน้ำ', 'บิลและสาธารณูปโภค', 'สุขภาพ', 'ค่าอาหาร', 'อื่นๆ']
    ];
    if (!file_exists(CATEGORIES_FILE)) {
        error_log("CATEGORIES_FILE not found: " . CATEGORIES_FILE);
        return $defaultCategories;
    }
    $json = @file_get_contents(CATEGORIES_FILE); // ใช้ @ เพื่อซ่อน warning
    if ($json === false) {
        error_log("Failed to read CATEGORIES_FILE: " . CATEGORIES_FILE . " - " . error_get_last()['message']);
        $_SESSION['message'] = 'ไม่สามารถอ่านไฟล์หมวดหมู่ได้ กรุณาลองใหม่ภายหลัง';
        return $defaultCategories;
    }
    $categories = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error in CATEGORIES_FILE: " . json_last_error_msg());
        $_SESSION['message'] = 'ไฟล์หมวดหมู่เสียหาย กรุณาติดต่อผู้ดูแลระบบ';
        return $defaultCategories;
    }
    return is_array($categories) ? $categories : $defaultCategories;
}
$data = getData(); // โหลดข้อมูลเริ่มต้นโดยใช้ฟังก์ชันใหม่
$all_categories = getCategories();
$inc_categories = $all_categories['income_categories'];
$categories = $all_categories['expense_categories'];

$month = $_GET['month'] ?? $now->format('Y-m');
$time = strtotime($month . "-01");
list($s_yr, $s_mo) = explode('-', $month);
$prev_mo = date('Y-m', strtotime($month . " -1 month"));
$next_mo = date('Y-m', strtotime($month . " +1 month"));

// ตั้งค่าวัดเริ่มต้นของปฏิทิน:
// 1. ถ้ามี 'day' ใน URL (เช่น หลังบันทึก/แก้ไข) ให้ใช้วันนั้น
// 2. ถ้าไม่มี, ให้ตรวจสอบว่าเป็นเดือนปัจจุบันหรือไม่ ถ้าใช่ให้ใช้วันปัจจุบัน, ถ้าไม่ใช่ให้ใช้วันที่ 1
$default_day = isset($_GET['day']) ? (int)$_GET['day'] : (($now->format('Y-m') === $month) ? (int)$now->format('d') : 1);

// 2. ดึงข้อมูลการ Edit ขึ้นมาประมวลผลก่อน
$e_inc = null;
if (!empty($_GET['edit_inc'])) foreach ($data['incomes'] as $r) if ((string)$r['id'] === (string)$_GET['edit_inc']) {
    $e_inc = $r;
    break;
}
$e_exp = null;
if (!empty($_GET['edit_exp'])) foreach ($data['expenses'] as $r) if ((string)$r['id'] === (string)$_GET['edit_exp']) {
    $e_exp = $r;
    break;
}
$e_item = null;
if (!empty($_GET['edit_item'])) foreach ($data['items'] as $r) if ((string)$r['id'] === (string)$_GET['edit_item']) {
    $e_item = $r;
    break;
}
$e_rent = null;
if (!empty($_GET['edit_rent'])) foreach ($data['rents'] as $r) if ((string)$r['id'] === (string)$_GET['edit_rent']) {
    $e_rent = $r;
    break;
}

$def_d = "$month-" . str_pad($default_day, 2, '0', STR_PAD_LEFT);

if ($e_inc) {
    $default_day = (int)substr($e_inc['date'], 8, 2);
    $def_d = $e_inc['date'];
} elseif ($e_exp) {
    $default_day = (int)substr($e_exp['date'], 8, 2);
    $def_d = $e_exp['date'];
} elseif ($e_item) {
    $default_day = (int)$e_item['due_date'];
} elseif ($e_rent) {
    $default_day = (int)$e_rent['due_date'];
}

// 3. จัดการ Actions (POST/GET) สำหรับบันทึก แก้ไข ลบ
$action = $_REQUEST['action'] ?? null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = 'เกิดข้อผิดพลาดด้านความปลอดภัย (CSRF Token ไม่ถูกต้อง)';
        header('Location: ?month=' . $month . '&day=' . $default_day); // Redirect back to current view
        exit;
    }

    $id = !empty($_POST['id']) ? $_POST['id'] : uniqid();
    $redirect_month = $month;
    $redirect_day = $default_day;
    $errors = [];

    if (in_array($action, ['save_income', 'save_expense'])) {
        $type = $action === 'save_income' ? 'incomes' : 'expenses';

        $title = trim($_POST['title'] ?? '');
        $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $date = $_POST['date'] ?? '';
        $category = $_POST['category'] ?? '';

        if (empty($title)) {
            $errors[] = 'กรุณากรอกหัวข้อ/รายการ';
        }
        if ($amount === false || $amount <= 0) {
            $errors[] = 'จำนวนเงินไม่ถูกต้อง';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $errors[] = 'รูปแบบวันที่ไม่ถูกต้อง';
        }
        if ($type === 'incomes' && !in_array($category, $inc_categories)) {
            $errors[] = 'หมวดหมู่รายรับไม่ถูกต้อง';
        }
        if ($type === 'expenses' && !in_array($category, $categories)) {
            $errors[] = 'หมวดหมู่รายจ่ายไม่ถูกต้อง';
        }

        if (empty($errors)) {
            $redirect_month = substr($date, 0, 7);
            $redirect_day = (int)substr($date, 8, 2);
            $payload = ['id' => $id, 'title' => htmlspecialchars($title), 'amount' => $amount, 'date' => $date, 'month' => $redirect_month, 'category' => $category];
        }
    } elseif ($action === 'save_item') {
        $type = 'items';

        $name = trim($_POST['name'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $down = filter_var($_POST['down'] ?? '', FILTER_VALIDATE_FLOAT);
        $interest_rate = filter_var($_POST['interest_rate'] ?? '', FILTER_VALIDATE_FLOAT);
        $months = filter_var($_POST['months'] ?? '', FILTER_VALIDATE_INT);
        $start_month = $_POST['start_month'] ?? '';
        $due_date = filter_var($_POST['due_date'] ?? '', FILTER_VALIDATE_INT);

        if (empty($name)) {
            $errors[] = 'กรุณากรอกชื่อรายการสินค้า';
        }
        if ($price === false || $price <= 0) {
            $errors[] = 'ราคาเต็มไม่ถูกต้อง';
        }
        if ($down === false || $down < 0) {
            $errors[] = 'เงินดาวน์ไม่ถูกต้อง';
        }
        if ($interest_rate === false || $interest_rate < 0) {
            $errors[] = 'อัตราดอกเบี้ยไม่ถูกต้อง';
        }
        if ($months === false || $months <= 0) {
            $errors[] = 'จำนวนงวดไม่ถูกต้อง';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $start_month) || !strtotime($start_month . '-01')) {
            $errors[] = 'รูปแบบเดือนเริ่มต้นไม่ถูกต้อง';
        }
        if ($due_date === false || $due_date < 1 || $due_date > 31) {
            $errors[] = 'วันที่ดีลไม่ถูกต้อง (1-31)';
        }

        if (empty($errors)) {
            $net = ($price - $down) + (($price - $down) * ($interest_rate / 100) * ($months / 12));
            $redirect_day = $due_date;
            $payload = ['id' => $id, 'name' => htmlspecialchars($name), 'price' => $price, 'down' => $down, 'interest_rate' => $interest_rate, 'total_months' => $months, 'start_month' => $start_month, 'due_date' => $redirect_day, 'net_debt' => $net, 'monthly_payment' => $net / $months, 'status_by_month' => [$month => 'ค้างชำระ']];
        }
    } elseif ($action === 'save_rent') {
        $type = 'rents';

        $name = trim($_POST['name'] ?? '');
        $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $due_date = filter_var($_POST['due_date'] ?? '', FILTER_VALIDATE_INT);
        $start_month = $_POST['start_month'] ?? '';
        $end_month = $_POST['end_month'] ?? '';

        if (empty($name)) {
            $errors[] = 'กรุณากรอกชื่อรายการค่าเช่า/รายจ่ายประจำ';
        }
        if ($amount === false || $amount <= 0) {
            $errors[] = 'จำนวนเงินไม่ถูกต้อง';
        }
        if ($due_date === false || $due_date < 1 || $due_date > 31) {
            $errors[] = 'วันที่ดีลไม่ถูกต้อง (1-31)';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $start_month) || !strtotime($start_month . '-01')) {
            $errors[] = 'รูปแบบเดือนเริ่มต้นไม่ถูกต้อง';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $end_month) || !strtotime($end_month . '-01')) {
            $errors[] = 'รูปแบบเดือนสิ้นสุดไม่ถูกต้อง';
        }
        if (strtotime($start_month . '-01') > strtotime($end_month . '-01')) {
            $errors[] = 'เดือนเริ่มต้นต้องไม่เกินเดือนสิ้นสุด';
        }

        if (empty($errors)) {
            $redirect_day = $due_date;
            $payload = ['id' => $id, 'name' => htmlspecialchars($name), 'amount' => $amount, 'due_date' => $due_date, 'start_month' => $start_month, 'end_month' => $end_month, 'status_by_month' => [$month => 'ค้างชำระ']];
        }
    }

    if (!empty($errors)) {
        $_SESSION['message'] = implode('<br>', $errors);
        $_SESSION['post_data'] = $_POST; // Preserve POST data for re-populating form
        header("Location: ?month={$redirect_month}&day={$redirect_day}");
        exit;
    } else {
        // Only proceed with saving if there are no validation errors
        if (isset($type)) {
            $found = false;
            foreach ($data[$type] as &$r) {
                if ((string)$r['id'] === (string)$id) {
                    // Preserve existing status_by_month for items/rents if editing
                    if (in_array($type, ['items', 'rents'])) {
                        $payload['status_by_month'] = $r['status_by_month'] ?? [];
                        // Ensure current month's status is preserved if not explicitly set in new payload
                        if (!isset($payload['status_by_month'][$month])) {
                            $payload['status_by_month'][$month] = $r['status_by_month'][$month] ?? 'ค้างชำระ';
                        }
                    }
                    $r = $payload;
                    $found = true;
                    break;
                }
            }
            // หากไม่พบ ID แสดงว่าเป็นรายการใหม่ ให้เพิ่มเข้าไปใน array
            if (!$found) {
                $data[$type][] = $payload;
            }
            if (!saveData($data)) { // บันทึกข้อมูลลงไฟล์ JSON โดยใช้ฟังก์ชันใหม่
                // หากบันทึกไม่สำเร็จ, ข้อความ error จะถูกตั้งค่าใน saveData() แล้ว
            }
            // Clear post data after successful save
            unset($_SESSION['post_data']);
        }
        header("Location: ?month={$redirect_month}&day={$redirect_day}");
        exit;
    }
}

if ($action && isset($_GET['id'])) {
    $id = $_GET['id'];
    if (in_array($action, ['toggle_status', 'toggle_status_rent'])) {
        $t = $action === 'toggle_status' ? 'items' : 'rents';
        foreach ($data[$t] as &$r) {
            if ((string)$r['id'] === (string)$id) {
                $r['status_by_month'][$month] = ($r['status_by_month'][$month] ?? 'ค้างชำระ') === 'จ่ายแล้ว' ? 'ค้างชำระ' : 'จ่ายแล้ว';
                break;
            }
        }
    } else {
        $t = str_replace('delete_', '', $action) . 's';
        if (isset($data[$t])) $data[$t] = array_values(array_filter($data[$t], fn($r) => (string)$r['id'] !== (string)$id));
    }
    if (!saveData($data)) { // บันทึกข้อมูลลงไฟล์ JSON โดยใช้ฟังก์ชันใหม่
        // หากบันทึกไม่สำเร็จ, ข้อความ error จะถูกตั้งค่าใน saveData() แล้ว
    }
    header("Location: ?month={$month}&day={$default_day}");
    exit;
}

// 4. คำนวณยอดรวมและรายละเอียดตามสูตร
$cal = [];
$ledger = [];
$t_inc = 0;
$t_exp_base = 0;
$t_rent_paid = 0;
$t_item_paid = 0;
$t_debt = 0;

$active_items = [];
$active_rents = [];

foreach (['incomes', 'expenses'] as $t) {
    foreach ($data[$t] as $r) {
        // เปลี่ยนมาใช้ strpos() เพื่อรองรับ PHP ได้ทุกเวอร์ชัน (แก้ปัญหา Error บันทึกแล้วจอขาว/ไม่ติด)
        if ((isset($r['month']) && $r['month'] === $month) || (isset($r['date']) && strpos($r['date'], $month) === 0)) {
            if ($t === 'incomes') {
                $t_inc += $r['amount'];
            } else {
                $t_exp_base += $r['amount'];
            }
            $cal[(int)substr($r['date'], 8, 2)][$t][] = $r;

            if ((isset($r['month']) && $r['month'] === $month) || strpos($r['date'], $month) === 0) {
                $ledger[] = [
                    'date' => $r['date'],
                    'title' => ($t === 'incomes' ? "➕ " : "➖ ") . $r['title'],
                    'inc' => $t === 'incomes' ? $r['amount'] : 0,
                    'exp' => $t === 'expenses' ? $r['amount'] : 0
                ];
            }
        }
    }
}

foreach ($data['items'] as &$item) {
    $st_time = strtotime($item['start_month'] . "-01");
    $inst_no = (($s_yr - date('Y', $st_time)) * 12) + ($s_mo - date('m', $st_time)) + 1;
    if ($inst_no > 0 && $inst_no <= $item['total_months']) {
        $paid = count(array_filter($item['status_by_month'] ?? [], fn($s) => $s === 'จ่ายแล้ว'));
        $item['current_installment_no'] = $inst_no;
        $item['real_remaining_debt'] = max(0, $item['net_debt'] - ($item['monthly_payment'] * $paid));
        $status = $item['status_by_month'][$month] ?? 'ค้างชำระ';

        if ($status === 'จ่ายแล้ว') {
            $t_item_paid += $item['monthly_payment'];
        } else {
            $t_debt += $item['monthly_payment'];
        }

        $active_items[] = $item;
        $cal[(int)$item['due_date']]['items'][] = ['id' => $item['id'], 'name' => $item['name'], 'amount' => $item['monthly_payment'], 'status' => $status, 'installment' => "$inst_no/{$item['total_months']}"];

        $ledger[] = ['date' => "$month-" . str_pad($item['due_date'], 2, '0', STR_PAD_LEFT), 'title' => "📦 {$item['name']} (งวด $inst_no/{$item['total_months']})", 'inc' => 0, 'exp' => $item['monthly_payment']];
    }
}
unset($item);

foreach ($data['rents'] as &$rent) {
    if ($month >= $rent['start_month'] && $month <= $rent['end_month']) {
        $status = $rent['status_by_month'][$month] ?? 'ค้างชำระ';

        if ($status === 'จ่ายแล้ว') {
            $t_rent_paid += $rent['amount'];
        }

        $active_rents[] = $rent;
        $cal[(int)$rent['due_date']]['rents'][] = ['id' => $rent['id'], 'name' => $rent['name'], 'amount' => $rent['amount'], 'status' => $status];

        $ledger[] = ['date' => "$month-" . str_pad($rent['due_date'], 2, '0', STR_PAD_LEFT), 'title' => "🏢 {$rent['name']}", 'inc' => 0, 'exp' => $rent['amount']];
    }
}
unset($rent);

$t_exp_mo = $t_exp_base + $t_rent_paid + $t_item_paid;
$rem = $t_inc - $t_exp_mo;
$t_rent_and_item = $t_rent_paid + $t_item_paid;

$total_plan_count = count($active_items) + count($active_rents);
$total_plan_amount = array_sum(array_column($active_items, 'monthly_payment')) + array_sum(array_column($active_rents, 'amount'));

usort($ledger, fn($a, $b) => strcmp($a['date'], $b['date']));
$running_bal = 0;
foreach ($ledger as &$l) {
    $running_bal += $l['inc'] - $l['exp'];
    $l['balance'] = $running_bal;
}
unset($l);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบัญชีส่วนบุคคล Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .active-day {
            border-color: #4f46e5 !important;
            background-color: #f5f3ff !important;
            border-width: 2px;
        }

        #pdf-render-area {
            position: absolute;
            left: -9999px;
            top: -9999px;
            width: 700px;
            background: white;
            padding: 30px;
        }

        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
        }

        .pdf-table th,
        .pdf-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
        }

        .pdf-table th {
            background-color: #f8fafc;
            color: #334155;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <!-- พื้นที่ซ่อนสำหรับเรนเดอร์ PDF -->
    <div id="pdf-render-area">
        <div class="text-center" style="margin-bottom: 25px;">
            <h1 style="font-size: 22px; font-weight: bold; margin: 0; color: #1e293b;">รายงานบัญชีรายรับ - รายจ่าย</h1>
            <p id="print-period-title" style="font-size: 14px; margin: 5px 0 0 0; color: #64748b;"></p>
        </div>
        <table class="pdf-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 15%;">วันที่</th>
                    <th style="width: 45%;">รายการ</th>
                    <th class="text-right" style="width: 13%;">รายรับ</th>
                    <th class="text-right" style="width: 13%;">รายจ่าย</th>
                    <th class="text-right" style="width: 14%;">คงเหลือ</th>
                </tr>
            </thead>
            <tbody id="print-ledger-body"></tbody>
        </table>
    </div>

    <div class="pb-12">
        <!-- แถบนำทาง (Navbar) -->
        <nav class="bg-white border-b border-gray-200 p-4 sticky top-0 z-50 shadow-sm">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
                <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-wallet text-indigo-600"></i>ระบบบัญชีส่วนบุคคล Pro</h1>
                <div class="flex items-center gap-4 flex-wrap justify-center">
                    <div class="flex items-center gap-1 bg-gray-50 p-1.5 rounded-full shadow-inner border border-gray-200">
                        <a href="?month=<?= $prev_mo ?>&day=<?= $default_day ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                        <form method="GET" class="flex items-center mx-2">
                            <input type="month" name="month" value="<?= $month ?>" onchange="this.form.submit()" class="bg-transparent text-sm font-bold text-gray-700 outline-none cursor-pointer text-center" style="width: 100px;">
                        </form>
                        <a href="?month=<?= $next_mo ?>&day=<?= $default_day ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                    </div>
                    <button onclick="togglePdfModal(true)" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-file-pdf"></i> PDF</button>
                    <button onclick="exportExcel()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-file-excel"></i> Excel</button>
                    <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-right-from-bracket"></i> ออกจากระบบ</a>
                </div>
            </div>
        </nav>

        <!-- Modal สำหรับโหลด PDF -->
        <?php if ($message): ?>
            <div class="max-w-7xl mx-auto px-4 mt-6">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $message ?></span>
                </div>
            </div>
        <?php endif; ?>
        <div id="pdfModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-lg p-6 max-w-md w-full mx-4">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="text-md font-bold text-gray-800"><i class="fa-solid fa-file-pdf text-rose-500 mr-1"></i> เลือกช่วงเวลาที่ต้องการดาวน์โหลด PDF</h3>
                    <button onclick="togglePdfModal(false)" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-2">รูปแบบการดาวน์โหลด</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" onclick="setPdfType('month')" id="btn-pdf-month" class="py-2 border rounded-lg font-medium text-center bg-indigo-50 border-indigo-500 text-indigo-600">ทั้งเดือน</button>
                            <button type="button" onclick="setPdfType('single')" id="btn-pdf-single" class="py-2 border rounded-lg font-medium text-center bg-white border-gray-200 text-gray-600 hover:bg-gray-50">วันเดียว</button>
                            <button type="button" onclick="setPdfType('range')" id="btn-pdf-range" class="py-2 border rounded-lg font-medium text-center bg-white border-gray-200 text-gray-600 hover:bg-gray-50">หลายวัน</button>
                        </div>
                    </div>
                    <div id="pdf-single-input" class="hidden">
                        <label class="block font-medium text-gray-700 mb-1">เลือกวันที่</label>
                        <input type="date" id="pdf-date-start" value="<?= $def_d ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div id="pdf-range-input" class="hidden grid grid-cols-2 gap-2">
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">ตั้งแต่วันที่</label>
                            <input type="date" id="pdf-range-start" value="<?= "$month-01" ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block font-medium text-gray-700 mb-1">ถึงวันที่</label>
                            <input type="date" id="pdf-range-end" value="<?= date("Y-m-t", $time) ?>" class="w-full px-3 py-2 border rounded-lg">
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex gap-2 justify-end">
                    <button onclick="togglePdfModal(false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">ยกเลิก</button>
                    <button id="btn-submit-pdf" onclick="generatePdf()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium"><i class="fa-solid fa-download mr-1"></i> ดาวน์โหลด PDF</button>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 mt-6">
            <!-- ข้อมูลสรุปด้านบน -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">รายรับเดือนนี้</p>
                        <h3 class="text-lg font-bold text-emerald-600 mt-1">฿<?= number_format($t_inc, 2) ?></h3>
                    </div>
                    <div class="bg-emerald-50 p-2 rounded-lg text-emerald-600"><i class="fa-solid fa-arrow-down"></i></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">รายจ่ายโดยรวม</p>
                        <h3 class="text-lg font-bold text-rose-500 mt-1">฿<?= number_format($t_exp_mo, 2) ?></h3>
                    </div>
                    <div class="bg-rose-50 p-2 rounded-lg text-rose-500"><i class="fa-solid fa-arrow-up"></i></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center <?= $rem < 0 ? 'bg-red-50' : '' ?>">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">คงเหลือสุทธิ</p>
                        <h3 class="text-lg font-bold <?= $rem < 0 ? 'text-red-600' : 'text-indigo-600' ?> mt-1">฿<?= number_format($rem, 2) ?></h3>
                    </div>
                    <div class="p-2 rounded-lg <?= $rem < 0 ? 'bg-red-100 text-red-600' : 'bg-indigo-50 text-indigo-600' ?>"><i class="fa-solid fa-scale-balanced"></i></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">ยอดที่ค้างผ่อน</p>
                        <h3 class="text-lg font-bold text-amber-600 mt-1">฿<?= number_format($t_debt, 2) ?></h3>
                    </div>
                    <div class="bg-amber-50 p-2 rounded-lg text-amber-600"><i class="fa-solid fa-clock"></i></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">จ่ายและเช่า (รวม)</p>
                        <h3 class="text-lg font-bold text-indigo-600 mt-1">฿<?= number_format($t_rent_and_item, 2) ?></h3>
                    </div>
                    <div class="bg-indigo-50 p-2 rounded-lg text-indigo-600"><i class="fa-solid fa-check-double"></i></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border flex justify-between items-center col-span-2 md:col-span-1">
                    <div class="truncate">
                        <p class="text-[11px] text-gray-500 font-medium">แผนเช่า & ผ่อนชำระ</p>
                        <h3 class="text-lg font-bold text-slate-700 mt-1">฿<?= number_format($total_plan_amount, 2) ?></h3>
                        <p class="text-[10px] text-gray-400 mt-0.5"><?= $total_plan_count ?> รายการทั้งหมด</p>
                    </div>
                    <div class="bg-slate-100 p-2 rounded-lg text-slate-600"><i class="fa-solid fa-layer-group"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- กราฟวงล้อรายเดือน -->
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-center items-center">
                    <h3 class="text-sm font-bold text-gray-700 w-full text-left mb-2"><i class="fa-solid fa-chart-pie text-indigo-500 mr-1"></i> สรุปวงล้อรายเดือน</h3>
                    <div class="relative h-56 w-full"><canvas id="mChart"></canvas></div>
                </div>
                <!-- กราฟวงล้อรายวัน -->
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex flex-col">
                    <h3 class="text-sm font-bold text-gray-700 mb-2"><i class="fa-solid fa-chart-donut text-indigo-500 mr-1"></i> สรุปวงล้อรายวัน <span id="dChartTitle" class="text-indigo-600 ml-1 bg-indigo-50 px-2 rounded-full border"></span></h3>
                    <div class="relative h-48 w-full mb-4"><canvas id="dChart"></canvas></div>
                    <div class="mt-auto border-t pt-3">
                        <div class="mb-2">
                            <span class="text-[11px] font-bold text-emerald-600 block mb-1">หมวดหมู่รายรับ:</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($inc_categories as $cat): ?>
                                    <label class="text-[10px] flex items-center gap-1 cursor-pointer text-gray-600"><input type="checkbox" checked onchange="updateDailyChart()" class="d-filter-inc accent-emerald-500" value="<?= $cat ?>"> <?= $cat ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <span class="text-[11px] font-bold text-rose-600 block mb-1">หมวดหมู่รายจ่าย:</span>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($categories as $cat): ?>
                                    <label class="text-[10px] flex items-center gap-1 cursor-pointer text-gray-600"><input type="checkbox" checked onchange="updateDailyChart()" class="d-filter-exp accent-rose-500" value="<?= $cat ?>"> <?= $cat ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-indigo-600 block mb-1">ภาระอื่นๆ:</span>
                            <div class="flex flex-wrap gap-2">
                                <label class="text-[10px] flex items-center gap-1 cursor-pointer text-gray-600"><input type="checkbox" checked onchange="updateDailyChart()" id="d-filter-rent" class="accent-orange-500"> ค่าเช่า/รายจ่ายประจำ</label>
                                <label class="text-[10px] flex items-center gap-1 cursor-pointer text-gray-600"><input type="checkbox" checked onchange="updateDailyChart()" id="d-filter-item" class="accent-indigo-500"> ผ่อนชำระค่างวด</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- แบบฟอร์มเพิ่ม/แก้ไข -->
                <div class="space-y-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-3 flex justify-between"><span><i class="fa-solid fa-wallet text-emerald-500 mr-1"></i> <?= $e_inc ? 'แก้ไข' : 'เพิ่ม' ?>รายรับ</span><?php if ($e_inc): ?><a href="?month=<?= $month ?>" class="text-xs text-gray-400">ยกเลิก</a><?php endif; ?></h3>
                        <form method="POST" action="?month=<?= $month ?>" class="space-y-3 text-sm">
                            <input type="hidden" name="action" value="save_income"><input type="hidden" name="id" value="<?= $e_inc['id'] ?? ($old_post_data['id'] ?? '') ?>"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2"><input type="text" name="title" value="<?= htmlspecialchars($e_inc['title'] ?? ($old_post_data['title'] ?? '')) ?>" placeholder="หัวข้อ" class="w-full px-3 py-2 border rounded-lg" required></div>
                                <div><input type="date" name="date" id="inc_date" value="<?= $e_inc['date'] ?? ($old_post_data['date'] ?? $def_d) ?>" class="w-full px-2 py-2 border rounded-lg text-xs font-semibold" required></div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="category" class="w-full px-3 py-2 border rounded-lg bg-white" required>
                                    <?php foreach ($inc_categories as $cat): ?><option value="<?= $cat ?>" <?= ($e_inc && isset($e_inc['category']) && $e_inc['category'] === $cat) || (isset($old_post_data['category']) && $old_post_data['category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option><?php endforeach; ?>
                                </select>
                                <input type="number" step="0.01" name="amount" value="<?= $e_inc['amount'] ?? ($old_post_data['amount'] ?? '') ?>" placeholder="จำนวนเงิน" class="w-full px-3 py-2 border rounded-lg font-bold text-emerald-600" required>
                            </div>
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2 rounded-lg font-medium">บันทึกรายรับ</button>
                        </form>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-3 flex justify-between"><span><i class="fa-solid fa-basket-shopping text-rose-500 mr-1"></i> <?= $e_exp ? 'แก้ไข' : 'เพิ่ม' ?>รายจ่าย</span><?php if ($e_exp): ?><a href="?month=<?= $month ?>" class="text-xs text-gray-400">ยกเลิก</a><?php endif; ?></h3>
                        <form method="POST" action="?month=<?= $month ?>" class="space-y-3 text-sm">
                            <input type="hidden" name="action" value="save_expense"><input type="hidden" name="id" value="<?= $e_exp['id'] ?? ($old_post_data['id'] ?? '') ?>"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="grid grid-cols-3 gap-2">
                                <div class="col-span-2"><input type="text" name="title" value="<?= htmlspecialchars($e_exp['title'] ?? ($old_post_data['title'] ?? '')) ?>" placeholder="รายการ" class="w-full px-3 py-2 border rounded-lg" required></div>
                                <div><input type="date" name="date" id="exp_date" value="<?= $e_exp['date'] ?? ($old_post_data['date'] ?? $def_d) ?>" class="w-full px-2 py-2 border rounded-lg text-xs font-semibold" required></div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="category" class="w-full px-3 py-2 border rounded-lg bg-white" required>
                                    <?php foreach ($categories as $cat): ?><option value="<?= $cat ?>" <?= ($e_exp && isset($e_exp['category']) && $e_exp['category'] === $cat) || (isset($old_post_data['category']) && $old_post_data['category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option><?php endforeach; ?>
                                </select>
                                <input type="number" step="0.01" name="amount" value="<?= $e_exp['amount'] ?? ($old_post_data['amount'] ?? '') ?>" placeholder="จำนวนเงิน" class="w-full px-3 py-2 border rounded-lg font-bold text-rose-600" required>
                            </div>
                            <button type="submit" class="w-full bg-rose-600 hover:bg-rose-700 text-white py-2 rounded-lg font-medium">บันทึกรายจ่าย</button>
                        </form>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-orange-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-3 flex justify-between"><span><i class="fa-solid fa-building text-orange-500 mr-1"></i> <?= $e_rent ? 'แก้ไข' : 'เพิ่ม' ?>ค่าเช่า/รายจ่ายประจำ</span><?php if ($e_rent): ?><a href="?month=<?= $month ?>" class="text-xs text-gray-400">ยกเลิก</a><?php endif; ?></h3>
                        <form method="POST" action="?month=<?= $month ?>" class="space-y-3 text-sm">
                            <input type="hidden" name="action" value="save_rent"><input type="hidden" name="id" value="<?= $e_rent['id'] ?? ($old_post_data['id'] ?? '') ?>"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($e_rent['name'] ?? ($old_post_data['name'] ?? '')) ?>" placeholder="รายการ (เช่น ค่าเช่าตึก)" class="w-full px-3 py-2 border rounded-lg" required>
                            <div class="grid grid-cols-2 gap-2"><input type="number" step="0.01" name="amount" value="<?= $e_rent['amount'] ?? ($old_post_data['amount'] ?? '') ?>" placeholder="จำนวนเงิน" class="w-full px-3 py-2 border rounded-lg font-bold text-orange-600" required><select name="due_date" id="rent_due" class="w-full px-3 py-2 border rounded-lg" required><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>" <?= ($e_rent && $e_rent['due_date'] == $i || (isset($old_post_data['due_date']) && $old_post_data['due_date'] == $i) || (!$e_rent && !isset($old_post_data['due_date']) && $i == $default_day)) ? 'selected' : '' ?>>ดีลวันที่ <?= $i ?></option><?php endfor; ?></select></div>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="text-[10px] text-gray-500 block">เริ่มจ่ายเดือน:</label><input type="month" name="start_month" value="<?= $e_rent['start_month'] ?? ($old_post_data['start_month'] ?? $month) ?>" class="w-full px-3 py-2 border rounded-lg" required></div>
                                <div><label class="text-[10px] text-gray-500 block">จ่ายถึงเดือน:</label><input type="month" name="end_month" value="<?= $e_rent['end_month'] ?? ($old_post_data['end_month'] ?? date('Y-m', strtotime($month . ' +1 year'))) ?>" class="w-full px-3 py-2 border rounded-lg" required></div>
                            </div>
                            <button type="submit" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-2 rounded-lg font-medium">บันทึกค่าเช่า</button>
                        </form>
                    </div>

                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-bold text-gray-700 mb-3 flex justify-between"><span><i class="fa-solid fa-box text-indigo-500 mr-1"></i> <?= $e_item ? 'แก้ไข' : 'เพิ่ม' ?>รายการผ่อน</span><?php if ($e_item): ?><a href="?month=<?= $month ?>" class="text-xs text-gray-400">ยกเลิก</a><?php endif; ?></h3>
                        <form method="POST" action="?month=<?= $month ?>" class="space-y-3 text-sm">
                            <input type="hidden" name="action" value="save_item"><input type="hidden" name="id" value="<?= $e_item['id'] ?? ($old_post_data['id'] ?? '') ?>"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($e_item['name'] ?? ($old_post_data['name'] ?? '')) ?>" placeholder="รายการสินค้า" class="w-full px-3 py-2 border rounded-lg" required>
                            <div class="grid grid-cols-2 gap-2"><input type="number" step="0.01" name="price" value="<?= $e_item['price'] ?? ($old_post_data['price'] ?? '') ?>" placeholder="ราคาเต็ม" class="w-full px-3 py-2 border rounded-lg" required><input type="number" step="0.01" name="down" value="<?= $e_item['down'] ?? ($old_post_data['down'] ?? '') ?>" placeholder="เงินดาวน์" class="w-full px-3 py-2 border rounded-lg" required></div>
                            <div class="grid grid-cols-2 gap-2"><input type="number" step="0.01" name="interest_rate" value="<?= $e_item['interest_rate'] ?? ($old_post_data['interest_rate'] ?? '') ?>" placeholder="ดอกเบี้ย %" class="w-full px-3 py-2 border rounded-lg" required><input type="number" name="months" value="<?= $e_item['total_months'] ?? ($old_post_data['months'] ?? '') ?>" placeholder="จำนวนงวด" class="w-full px-3 py-2 border rounded-lg font-bold text-indigo-600" required></div>
                            <div class="grid grid-cols-2 gap-2"><input type="month" name="start_month" value="<?= $e_item['start_month'] ?? ($old_post_data['start_month'] ?? $month) ?>" class="w-full px-3 py-2 border rounded-lg" required><select name="due_date" id="item_due" class="w-full px-3 py-2 border rounded-lg" required><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>" <?= ($e_item && $e_item['due_date'] == $i || (isset($old_post_data['due_date']) && $old_post_data['due_date'] == $i) || (!$e_item && !isset($old_post_data['due_date']) && $i == $default_day)) ? 'selected' : '' ?>>ดีลวันที่ <?= $i ?></option><?php endfor; ?></select></div>
                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-medium">บันทึกผ่อน</button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <!-- ปฏิทิน -->
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-md font-bold text-gray-800"><i class="fa-solid fa-calendar text-indigo-500 mr-1"></i> ปฏิทิน: <?= date('m/Y', $time) ?></h3><span class="text-[11px] text-indigo-600 font-bold bg-indigo-50 px-2 py-0.5 rounded-md">คลิกเพื่อตั้งวันที่</span>
                        </div>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            <?php
                            for ($d = 1; $d <= 31; $d++):
                                $c = $cal[$d] ?? [];
                                $i = $c['incomes'] ?? [];
                                $e = $c['expenses'] ?? [];
                                $it = $c['items'] ?? [];
                                $re = $c['rents'] ?? [];
                                $bc = 'border-gray-100 bg-gray-50/50';

                                $has_unpaid = count(array_filter($it, fn($x) => $x['status'] !== 'จ่ายแล้ว')) > 0 || count(array_filter($re, fn($x) => $x['status'] !== 'จ่ายแล้ว')) > 0;
                                if ($it || $re) $bc = !$has_unpaid ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-400 bg-rose-50/40 ring-1 ring-rose-300 animate-pulse';
                                elseif ($i || $e) $bc = 'border-indigo-200 bg-white shadow-sm';
                            ?>
                                <div id="cal-day-<?= $d ?>" onclick="selectDay(<?= $d ?>, true)" class="calendar-day-btn relative border rounded-lg p-2 flex flex-col items-center min-h-[65px] transition-all cursor-pointer hover:border-indigo-500 <?= $bc ?>">
                                    <div class="text-xs font-bold text-gray-700"><?= $d ?></div>
                                    <div class="flex flex-wrap gap-1 mt-1 justify-center">
                                        <?php if ($i) echo "<span class='text-[10px] font-black text-emerald-600 bg-emerald-100 px-1 rounded'>+</span>"; ?>
                                        <?php if ($e) echo "<span class='text-[10px] font-black text-rose-600 bg-rose-100 px-1.5 rounded'>-</span>"; ?>
                                        <?php if ($re) echo "<span class='text-[10px] drop-shadow-sm'>🏢</span>"; ?>
                                        <?php if ($it) echo "<span class='text-[10px] drop-shadow-sm'>📦</span>"; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- ค้นหา -->
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-magnifying-glass text-indigo-400"></i></div>
                        <input type="text" id="searchInput" oninput="searchData()" placeholder="ค้นหารายการ (รายรับ, รายจ่าย, ค่าเช่า, ผ่อน)..." class="w-full bg-white border border-gray-200 text-sm font-medium rounded-xl pl-11 pr-4 py-3 shadow-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all placeholder:font-normal">
                    </div>

                    <!-- สรุปรายวัน -->
                    <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 border-b pb-4 mb-4">
                            <h3 class="text-md font-bold text-gray-800" id="list-title"><i class="fa-solid fa-list text-indigo-600"></i> สรุปวันที่ <span id="sel-day" class="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-lg">1</span></h3>
                            <div class="flex flex-wrap bg-gray-100 p-1 rounded-lg text-[10px] font-semibold text-gray-600 w-full sm:w-auto" id="filter-buttons">
                                <?php foreach (['all' => 'ทั้งหมด', 'incomes' => 'รับ', 'expenses' => 'จ่าย', 'rents' => 'เช่า', 'items' => 'ผ่อน'] as $k => $v): ?>
                                    <button id="fil-<?= $k ?>" onclick="setFil('<?= $k ?>')" class="px-2 py-1 rounded-md transition-all <?= $k === 'all' ? 'bg-white text-indigo-600 shadow-sm' : 'hover:bg-gray-200/60' ?>"><?= $v ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mb-4 text-center" id="daily-stats">
                            <div class="bg-emerald-50 p-2 rounded-lg">
                                <p class="text-[10px] text-gray-500">รายรับ</p>
                                <p id="d-inc" class="text-sm font-bold text-emerald-600">฿0</p>
                            </div>
                            <div class="bg-rose-50 p-2 rounded-lg">
                                <p class="text-[10px] text-gray-500">รายจ่าย</p>
                                <p id="d-exp" class="text-sm font-bold text-rose-600">฿0</p>
                            </div>
                            <div class="bg-gray-50 p-2 rounded-lg">
                                <p class="text-[10px] text-gray-500">คงเหลือ</p>
                                <p id="d-rem" class="text-sm font-bold text-gray-700">฿0</p>
                            </div>
                        </div>
                        <div id="d-list" class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                            <p class="text-center text-xs text-gray-400 py-4">เลือกวันบนปฏิทิน</p>
                        </div>
                    </div>

                    <!-- ตารางผ่อน/เช่า -->
                    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                        <div class="p-4 border-b flex justify-between items-center bg-gray-50">
                            <h3 class="text-sm font-bold text-gray-700"><i class="fa-solid fa-list-check text-indigo-500 mr-1"></i> แผนผ่อนชำระ & รายจ่ายประจำ</h3>
                            <span class="text-xs bg-white border text-indigo-600 px-2 py-1 rounded-full font-semibold"><?= $total_plan_count ?> รายการ</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead class="bg-white text-gray-500 text-[11px] uppercase border-b">
                                    <th class="p-3">รายการ</th>
                                    <th class="p-3 text-center">ประเภท/งวด</th>
                                    <th class="p-3">หนี้คงเหลือ</th>
                                    <th class="p-3 text-center">ที่ต้องจ่าย</th>
                                    <th class="p-3 text-center">สถานะ</th>
                                    <th class="p-3 text-center">จัดการ</th>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    <?php if (!$active_items && !$active_rents): ?><tr>
                                            <td colspan="6" class="p-6 text-center text-gray-400">ไม่มีรายการ</td>
                                        </tr><?php endif; ?>

                                    <?php foreach ($active_rents as $rt): $st = $rt['status_by_month'][$month] ?? 'ค้างชำระ'; ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-bold"><span class="text-[14px]">🏢</span> <?= $rt['name'] ?><div class="text-[10px] text-gray-400 mt-0.5">ดิววันที่ <?= $rt['due_date'] ?></div>
                                            </td>
                                            <td class="p-3 text-center"><span class="bg-orange-50 text-orange-700 px-2 py-0.5 rounded text-[10px] font-bold border border-orange-100">รายเดือน</span></td>
                                            <td class="p-3 text-gray-400 text-xs font-bold">-</td>
                                            <td class="p-3 text-center font-bold text-orange-600">฿<?= number_format($rt['amount'], 2) ?></td>
                                            <td class="p-3 text-center"><span class="<?= $st === 'จ่ายแล้ว' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-500 bg-rose-50' ?> text-[10px] font-bold border px-2 py-0.5 rounded-full"><?= $st ?></span></td>
                                            <td class="p-3 text-center flex justify-center items-center gap-1.5">
                                                <a href="?action=toggle_status_rent&id=<?= $rt['id'] ?>&month=<?= $month ?>" class="text-[10px] text-white px-2 py-1 rounded <?= $st === 'จ่ายแล้ว' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700' ?> transition-colors"><?= $st === 'จ่ายแล้ว' ? 'ยกเลิก' : 'จ่ายแล้ว' ?></a>
                                                <a href="?month=<?= $month ?>&edit_rent=<?= $rt['id'] ?>" class="text-gray-400 hover:text-indigo-600 px-1 transition-colors text-xs" title="แก้ไข"><i class="fa-solid fa-pen"></i></a>
                                                <a href="?action=delete_rent&id=<?= $rt['id'] ?>&month=<?= $month ?>" onclick="return confirm('คุณมั่นใจที่จะลบรายการเช่านี้?')" class="text-gray-400 hover:text-red-500 px-1 transition-colors text-xs" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php foreach ($active_items as $it): $st = $it['status_by_month'][$month] ?? 'ค้างชำระ'; ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-3 font-bold"><span class="text-[14px]">📦</span> <?= $it['name'] ?><div class="text-[10px] text-gray-400 mt-0.5">ดิววันที่ <?= $it['due_date'] ?></div>
                                            </td>
                                            <td class="p-3 text-center"><span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded text-[10px] font-bold border border-indigo-100"><?= $it['current_installment_no'] ?>/<?= $it['total_months'] ?></span></td>
                                            <td class="p-3 text-rose-600 font-bold text-xs">฿<?= number_format($it['real_remaining_debt'], 2) ?></td>
                                            <td class="p-3 text-center font-bold text-indigo-600">฿<?= number_format($it['monthly_payment'], 2) ?></td>
                                            <td class="p-3 text-center"><span class="<?= $st === 'จ่ายแล้ว' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-500 bg-rose-50' ?> text-[10px] font-bold border px-2 py-0.5 rounded-full"><?= $st ?></span></td>
                                            <td class="p-3 text-center flex justify-center items-center gap-1.5">
                                                <a href="?action=toggle_status&id=<?= $it['id'] ?>&month=<?= $month ?>" class="text-[10px] text-white px-2 py-1 rounded <?= $st === 'จ่ายแล้ว' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700' ?> transition-colors"><?= $st === 'จ่ายแล้ว' ? 'ยกเลิก' : 'จ่ายแล้ว' ?></a>
                                                <a href="?month=<?= $month ?>&edit_item=<?= $it['id'] ?>" class="text-gray-400 hover:text-indigo-600 px-1 transition-colors text-xs" title="แก้ไข"><i class="fa-solid fa-pen"></i></a>
                                                <a href="?action=delete_item&id=<?= $it['id'] ?>&month=<?= $month ?>" onclick="return confirm('คุณมั่นใจที่จะลบรายการผ่อนนี้?')" class="text-gray-400 hover:text-red-500 px-1 transition-colors text-xs" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        const calData = <?= json_encode($cal) ?>;
        const ledgerData = <?= json_encode($ledger) ?>;
        const moStr = "<?= $month ?>";
        let curDay = <?= $default_day ?>,
            fil = 'all';
        let pdfType = 'month';
        const fNum = n => `฿${parseFloat(n).toLocaleString('th-TH',{minimumFractionDigits:2})}`;

        const chartColors = {
            incomes: ['#10b981', '#34d399', '#059669', '#6ee7b7', '#a7f3d0', '#047857'],
            expenses: ['#f43f5e', '#fb7185', '#e11d48', '#fda4af', '#be123c', '#9f1239', '#881337'],
            rents: '#f59e0b',
            items: '#6366f1'
        };

        let mChartInstance = null;
        let dChartInstance = null;

        function togglePdfModal(show) {
            const modal = document.getElementById('pdfModal');
            modal.style.display = show ? 'flex' : 'none';
            if (show) {
                const currentStr = `${moStr}-${String(curDay).padStart(2, '0')}`;
                document.getElementById('pdf-date-start').value = currentStr;
            }
        }

        function setPdfType(type) {
            pdfType = type;
            ['month', 'single', 'range'].forEach(t => {
                const btn = document.getElementById(`btn-pdf-${t}`);
                if (t === type) {
                    btn.className = "py-2 border rounded-lg font-medium text-center bg-indigo-50 border-indigo-500 text-indigo-600";
                } else {
                    btn.className = "py-2 border rounded-lg font-medium text-center bg-white border-gray-200 text-gray-600 hover:bg-gray-50";
                }
            });
            document.getElementById('pdf-single-input').style.display = type === 'single' ? 'block' : 'none';
            document.getElementById('pdf-range-input').style.display = type === 'range' ? 'block' : 'none';
        }

        function generatePdf() {
            const submitBtn = document.getElementById('btn-submit-pdf');
            submitBtn.innerHTML = `<i class="fa-solid fa-spinner animate-spin mr-1"></i> กำลังสร้างไฟล์...`;
            submitBtn.disabled = true;

            let filteredLedger = [];
            let titlePeriod = '';
            let fileName = `Ledger_Report_${moStr}`;

            if (pdfType === 'month') {
                filteredLedger = [...ledgerData];
                titlePeriod = `ประจำเดือน ${moStr}`;
                fileName = `รายงานบัญชี_เดือน_${moStr}`;
            } else if (pdfType === 'single') {
                const targetDate = document.getElementById('pdf-date-start').value;
                filteredLedger = ledgerData.filter(l => l.date === targetDate);
                const dFormated = targetDate.split('-').reverse().join('-');
                titlePeriod = `ประจำวันที่ ${targetDate.split('-').reverse().join('/')}`;
                fileName = `รายงานบัญชี_วันที่_${dFormated}`;
            } else if (pdfType === 'range') {
                const start = document.getElementById('pdf-range-start').value;
                const end = document.getElementById('pdf-range-end').value;
                filteredLedger = ledgerData.filter(l => l.date >= start && l.date <= end);
                const dStart = start.split('-').reverse().join('-');
                const dEnd = end.split('-').reverse().join('-');
                titlePeriod = `ตั้งแต่วันที่ ${start.split('-').reverse().join('/')} ถึงวันที่ ${end.split('-').reverse().join('/')}`;
                fileName = `รายงานบัญชี_ช่วง_${dStart}_ถึง_${dEnd}`;
            }

            let sumInc = 0;
            let sumExp = 0;
            let currentBalance = 0;
            let html = '';

            if (filteredLedger.length === 0) {
                html = `<tr><td colspan="5" class="text-center" style="padding: 20px; color: #94a3b8;">ไม่มีข้อมูลในช่วงเวลาที่เลือก</td></tr>`;
            } else {
                filteredLedger.forEach(l => {
                    sumInc += parseFloat(l.inc);
                    sumExp += parseFloat(l.exp);
                    currentBalance += parseFloat(l.inc) - parseFloat(l.exp);
                    const dateArr = l.date.split('-');
                    const showDate = `${dateArr[2]}/${dateArr[1]}/${dateArr[0]}`;
                    html += `<tr><td class="text-center">${showDate}</td><td>${l.title}</td><td class="text-right" style="color: #059669;">${l.inc > 0 ? parseFloat(l.inc).toLocaleString('th-TH',{minimumFractionDigits:2}) : '-'}</td><td class="text-right" style="color: #e11d48;">${l.exp > 0 ? parseFloat(l.exp).toLocaleString('th-TH',{minimumFractionDigits:2}) : '-'}</td><td class="text-right" style="font-weight: bold; color: #1e293b;">${currentBalance.toLocaleString('th-TH',{minimumFractionDigits:2})}</td></tr>`;
                });
            }

            html += `<tr style="background-color: #f8fafc; font-weight: bold;"><td colspan="2" class="text-center">รวมทั้งสิ้น</td><td class="text-right" style="color: #059669;">${sumInc.toLocaleString('th-TH',{minimumFractionDigits:2})}</td><td class="text-right" style="color: #e11d48;">${sumExp.toLocaleString('th-TH',{minimumFractionDigits:2})}</td><td class="text-right" style="color: #1e293b;">${(sumInc - sumExp).toLocaleString('th-TH',{minimumFractionDigits:2})}</td></tr>`;
            document.getElementById('print-period-title').innerText = titlePeriod;
            document.getElementById('print-ledger-body').innerHTML = html;

            const element = document.getElementById('pdf-render-area');
            const opt = {
                margin: 15,
                filename: `${fileName}.pdf`,
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().set(opt).from(element).save().then(() => {
                submitBtn.innerHTML = `<i class="fa-solid fa-download mr-1"></i> ดาวน์โหลด PDF`;
                submitBtn.disabled = false;
                togglePdfModal(false);
            });
        }

        function drawMonthlyChart() {
            const mCtx = document.getElementById('mChart').getContext('2d');
            const incTotal = <?= $t_inc ?>;
            const expTotal = <?= $t_exp_mo ?>;
            let data = [],
                labels = [],
                colors = [];
            if (incTotal > 0) {
                labels.push('รายรับรวม');
                data.push(incTotal);
                colors.push('#10b981');
            }
            if (expTotal > 0) {
                labels.push('รายจ่ายรวม');
                data.push(expTotal);
                colors.push('#f43f5e');
            }
            if (data.length === 0) {
                mChartInstance = new Chart(mCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['ไม่มีข้อมูล'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['#e2e8f0'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                enabled: false
                            }
                        }
                    }
                });
                return;
            }
            mChartInstance = new Chart(mCtx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: 'Sarabun'
                                },
                                boxWidth: 12
                            }
                        }
                    }
                }
            });
        }

        function updateDailyChart() {
            let state = {
                incomes: {},
                expenses: {},
                rents: false,
                items: false
            };
            document.querySelectorAll(`.d-filter-inc`).forEach(cb => state.incomes[cb.value] = cb.checked);
            document.querySelectorAll(`.d-filter-exp`).forEach(cb => state.expenses[cb.value] = cb.checked);
            state.rents = document.getElementById('d-filter-rent')?.checked;
            state.items = document.getElementById('d-filter-item')?.checked;

            let dData = {
                labels: [],
                values: [],
                colors: []
            };
            let dSummary = {
                inc: {},
                exp: {},
                rent: 0,
                item: 0
            };

            for (let d in calData) {
                if (parseInt(d) !== curDay) continue;
                let day = calData[d];
                if (day.incomes) day.incomes.forEach(x => dSummary.inc[x.category || 'อื่นๆ'] = (dSummary.inc[x.category || 'อื่นๆ'] || 0) + parseFloat(x.amount));
                if (day.expenses) day.expenses.forEach(x => dSummary.exp[x.category || 'อื่นๆ'] = (dSummary.exp[x.category || 'อื่นๆ'] || 0) + parseFloat(x.amount));
                if (day.rents) day.rents.forEach(x => dSummary.rent += parseFloat(x.amount));
                if (day.items) day.items.forEach(x => dSummary.item += parseFloat(x.amount));
            }

            let cIdx = 0;
            for (let cat in dSummary.inc) {
                if (state.incomes[cat] && dSummary.inc[cat] > 0) {
                    dData.labels.push(`รับ: ${cat}`);
                    dData.values.push(dSummary.inc[cat]);
                    dData.colors.push(chartColors.incomes[cIdx % chartColors.incomes.length]);
                    cIdx++;
                }
            }
            cIdx = 0;
            for (let cat in dSummary.exp) {
                if (state.expenses[cat] && dSummary.exp[cat] > 0) {
                    dData.labels.push(`จ่าย: ${cat}`);
                    dData.values.push(dSummary.exp[cat]);
                    dData.colors.push(chartColors.expenses[cIdx % chartColors.expenses.length]);
                    cIdx++;
                }
            }
            if (state.rents && dSummary.rent > 0) {
                dData.labels.push(`ค่าเช่า`);
                dData.values.push(dSummary.rent);
                dData.colors.push(chartColors.rents);
            }
            if (state.items && dSummary.item > 0) {
                dData.labels.push(`ผ่อนชำระ`);
                dData.values.push(dSummary.item);
                dData.colors.push(chartColors.items);
            }

            document.getElementById('dChartTitle').innerText = `วันที่ ${curDay}`;
            const dCtx = document.getElementById('dChart').getContext('2d');
            if (dChartInstance) dChartInstance.destroy();

            if (dData.values.length === 0) {
                dChartInstance = new Chart(dCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['ไม่มีข้อมูล'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['#e2e8f0'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            tooltip: {
                                enabled: false
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                dChartInstance = new Chart(dCtx, {
                    type: 'doughnut',
                    data: {
                        labels: dData.labels,
                        datasets: [{
                            data: dData.values,
                            backgroundColor: dData.colors,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    font: {
                                        family: 'Sarabun'
                                    },
                                    boxWidth: 10
                                }
                            }
                        }
                    }
                });
            }
        }

        window.onload = () => {
            selectDay(curDay, false);
            drawMonthlyChart();
        };

        function searchData() {
            let kw = document.getElementById('searchInput').value.toLowerCase();
            if (!kw) {
                document.getElementById('daily-stats').style.display = 'grid';
                document.getElementById('filter-buttons').style.display = 'flex';
                render();
                return;
            }
            document.getElementById('list-title').innerHTML = `<i class="fa-solid fa-magnifying-glass text-indigo-600"></i> ผลการค้นหา "${kw}"`;
            document.getElementById('daily-stats').style.display = 'none';
            document.getElementById('filter-buttons').style.display = 'none';
            let html = '';
            for (let d in calData) {
                let day = calData[d];
                if (day.incomes) day.incomes.filter(x => x.title.toLowerCase().includes(kw) || (x.category && x.category.toLowerCase().includes(kw))).forEach(x => html += getRowHtml('income', x, d));
                if (day.expenses) day.expenses.filter(x => x.title.toLowerCase().includes(kw) || (x.category && x.category.toLowerCase().includes(kw))).forEach(x => html += getRowHtml('expense', x, d));
                if (day.rents) day.rents.filter(x => x.name.toLowerCase().includes(kw)).forEach(x => html += getRowHtml('rent', x, d));
                if (day.items) day.items.filter(x => x.name.toLowerCase().includes(kw)).forEach(x => html += getRowHtml('item', x, d));
            }
            document.getElementById('d-list').innerHTML = html || `<p class="text-center text-xs text-gray-400 py-6">ไม่พบข้อมูลที่ตรงกับ "${kw}"</p>`;
        }

        function getRowHtml(type, x, d = curDay) {
            let dateBadge = `<span class="text-[9px] bg-white px-1.5 rounded text-gray-500 mr-2 border">วันที่ ${d}</span>`;
            if (type === 'income') return `<div class="flex justify-between items-center bg-emerald-50/40 px-3 py-2 border border-emerald-100 rounded-xl text-xs"><span class="font-medium flex items-center"><span class="text-[10px] font-black text-emerald-600 bg-emerald-100 px-1 rounded mr-1">+</span> ${dateBadge}${x.title} <span class="ml-2 text-[9px] bg-white border border-emerald-100 text-emerald-600 px-1.5 rounded shadow-sm">${x.category||'อื่นๆ'}</span></span><div class="flex items-center gap-3"><span class="font-bold text-emerald-600">${fNum(x.amount)}</span><a href="?month=${moStr}&edit_inc=${x.id}" class="text-indigo-400 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-pen"></i></a><a href="?action=delete_income&id=${x.id}&month=${moStr}" onclick="return confirm('คุณมั่นใจที่จะลบรายการนี้?')" class="text-rose-300 hover:text-rose-500 transition-colors"><i class="fa-solid fa-trash"></i></a></div></div>`;
            if (type === 'expense') return `<div class="flex justify-between items-center bg-rose-50/40 px-3 py-2 border border-rose-100 rounded-xl text-xs"><span class="font-medium flex items-center"><span class="text-[10px] font-black text-rose-600 bg-rose-100 px-1.5 rounded mr-1">-</span> ${dateBadge}${x.title} <span class="ml-2 text-[9px] bg-white border border-rose-100 text-rose-600 px-1.5 rounded shadow-sm">${x.category||'อื่นๆ'}</span></span><div class="flex items-center gap-3"><span class="font-bold text-rose-600">${fNum(x.amount)}</span><a href="?month=${moStr}&edit_exp=${x.id}" class="text-indigo-400 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-pen"></i></a><a href="?action=delete_expense&id=${x.id}&month=${moStr}" onclick="return confirm('คุณมั่นใจที่จะลบรายการนี้?')" class="text-rose-300 hover:text-rose-500 transition-colors"><i class="fa-solid fa-trash"></i></a></div></div>`;
            if (type === 'rent') return `<div class="flex justify-between items-center bg-orange-50/30 px-3 py-2 border border-orange-200/70 rounded-xl text-xs"><span class="font-bold">🏢 ${dateBadge}${x.name}</span><div class="flex items-center gap-2"><span class="font-bold text-orange-600 mr-2">${fNum(x.amount)}</span><span class="${x.status==='จ่ายแล้ว'?'bg-emerald-100 text-emerald-800':'bg-rose-100 text-rose-800'} px-1.5 py-0.5 rounded-full text-[10px] font-bold">${x.status}</span></div></div>`;
            if (type === 'item') return `<div class="flex justify-between items-center bg-indigo-50/30 px-3 py-2 border border-indigo-200/70 rounded-xl text-xs"><span class="font-bold">📦 ${dateBadge}${x.name}</span><div class="flex items-center gap-2"><span class="font-bold text-indigo-600 mr-2">${fNum(x.amount)}</span><span class="${x.status==='จ่ายแล้ว'?'bg-emerald-100 text-emerald-800':'bg-rose-100 text-rose-800'} px-1.5 py-0.5 rounded-full text-[10px] font-bold">${x.status}</span></div></div>`;
            return '';
        }

        function selectDay(d, isUserClick = false) {
            curDay = d;
            document.querySelectorAll('.calendar-day-btn').forEach(b => b.classList.remove('active-day'));
            document.getElementById(`cal-day-${d}`)?.classList.add('active-day');
            if (isUserClick) {
                const dd = String(d).padStart(2, '0');
                const fullDate = `${moStr}-${dd}`;
                ['inc_date', 'exp_date', 'item_due', 'rent_due'].forEach(id => {
                    let el = document.getElementById(id);
                    if (el) {
                        el.value = id.includes('due') ? d : fullDate;
                        flashEffect(el);
                    }
                });
                document.getElementById('searchInput').value = '';
                document.getElementById('daily-stats').style.display = 'grid';
                document.getElementById('filter-buttons').style.display = 'flex';
            }
            render();
            updateDailyChart();
        }

        function flashEffect(el) {
            el.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50');
            setTimeout(() => el.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50'), 400);
        }

        function setFil(t) {
            fil = t;
            ['all', 'incomes', 'expenses', 'rents', 'items'].forEach(k => {
                const btn = document.getElementById(`fil-${k}`);
                if (btn) btn.className = k === t ? "px-2 py-1 rounded-md bg-white text-indigo-600 shadow-sm border border-gray-200" : "px-2 py-1 rounded-md hover:bg-gray-200/60 transition-all text-gray-500";
            });
            render();
        }

        function render() {
            document.getElementById('list-title').innerHTML = `<i class="fa-solid fa-list text-indigo-600"></i> สรุปวันที่ <span id="sel-day" class="text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-lg">${curDay}</span>`;
            const d = calData[curDay] || {
                incomes: [],
                expenses: [],
                items: [],
                rents: []
            };
            const i = d.incomes || [],
                e = d.expenses || [],
                it = d.items || [],
                re = d.rents || [];

            const tI = i.reduce((s, c) => s + parseFloat(c.amount), 0);
            const tE = e.reduce((s, c) => s + parseFloat(c.amount), 0) + it.reduce((s, c) => s + parseFloat(c.amount), 0) + re.reduce((s, c) => s + parseFloat(c.amount), 0);

            document.getElementById('d-inc').innerText = fNum(tI);
            document.getElementById('d-exp').innerText = fNum(tE);
            document.getElementById('d-rem').innerText = fNum(tI - tE);

            let html = '';
            if (['all', 'incomes'].includes(fil)) i.forEach(x => html += getRowHtml('income', x));
            if (['all', 'expenses'].includes(fil)) e.forEach(x => html += getRowHtml('expense', x));
            if (['all', 'rents'].includes(fil)) re.forEach(x => html += getRowHtml('rent', x));
            if (['all', 'items'].includes(fil)) it.forEach(x => html += getRowHtml('item', x));
            document.getElementById('d-list').innerHTML = html || `<p class="text-center text-xs text-gray-400 py-6">ไม่มีรายการในวันนี้</p>`;
        }

        function exportExcel() {
            const incTotal = <?= $t_inc ?>;
            const expTotal = <?= $t_exp_mo ?>;
            const balTotal = incTotal - expTotal;

            let tableHTML = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta charset="utf-8"></head>
                <body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                <h2 style="text-align:center; color: #1e293b;">บัญชีรายรับ-รายจ่าย ประจำเดือน ${moStr}</h2>
                <table border="1" style="border-collapse: collapse; width: 100%; border: 1px solid #cbd5e1;">
                    <thead>
                        <tr style="background-color: #334155; color: #ffffff; font-weight: bold; text-align: center; height: 40px;">
                            <th style="padding: 10px; width: 120px;">วันที่</th>
                            <th style="padding: 10px; width: 300px;">รายการ</th>
                            <th style="padding: 10px; width: 150px; background-color: #047857;">รายรับ</th>
                            <th style="padding: 10px; width: 150px; background-color: #be123c;">รายจ่าย</th>
                            <th style="padding: 10px; width: 150px;">คงเหลือสุทธิ</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            ledgerData.forEach(l => {
                tableHTML += `
                    <tr style="height: 30px;">
                        <td style="padding: 5px; text-align: center; vertical-align: middle;">${l.date}</td>
                        <td style="padding: 5px; vertical-align: middle;">${l.title}</td>
                        <td style="padding: 5px; text-align: right; color: #059669; font-weight: bold; vertical-align: middle;">${l.inc > 0 ? l.inc.toLocaleString(undefined, {minimumFractionDigits: 2}) : '-'}</td>
                        <td style="padding: 5px; text-align: right; color: #e11d48; font-weight: bold; vertical-align: middle;">${l.exp > 0 ? l.exp.toLocaleString(undefined, {minimumFractionDigits: 2}) : '-'}</td>
                        <td style="padding: 5px; text-align: right; color: #1e293b; font-weight: bold; background-color: #f8fafc; vertical-align: middle;">${l.balance.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
            tableHTML += `
                    <tr style="height: 40px; background-color: #f1f5f9; font-weight: bold;">
                        <td colspan="2" style="text-align: center; font-size: 14px;">รวมยอดสุทธิทั้งสิ้น</td>
                        <td style="text-align: right; color: #059669; font-size: 14px;">${incTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td style="text-align: right; color: #e11d48; font-size: 14px;">${expTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td style="text-align: right; color: #1e293b; font-size: 14px;">${balTotal.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                    </tbody>
                </table>
                </body>
                </html>
            `;
            let blob = new Blob([tableHTML], {
                type: 'application/vnd.ms-excel'
            });
            let link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Ledger_Report_${moStr}.xls`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>