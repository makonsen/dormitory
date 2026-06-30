<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../functions.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$now = new DateTimeImmutable();
$month = $_GET['month'] ?? $now->format('Y-m');
$prev_mo = date('Y-m', strtotime($month . " -1 month"));
$next_mo = date('Y-m', strtotime($month . " +1 month"));

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$action = $_GET['action'] ?? null;

if ($action === 'toggle_status_rent' && isset($_GET['id']) && isset($_GET['month_toggle'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['message'] = 'CSRF token validation failed.';
    } else {
        $data = getData();
        foreach ($data['rents'] as &$r) {
            if ((string)$r['id'] === (string)$_GET['id']) {
                $toggle_month = $_GET['month_toggle'];
                $r['status_by_month'][$toggle_month] = ($r['status_by_month'][$toggle_month] ?? 'ค้างชำระ') === 'จ่ายแล้ว' ? 'ค้างชำระ' : 'จ่ายแล้ว';
                saveData($data);
                $_SESSION['message'] = 'ปรับปรุงสถานะการชำระเงินสำเร็จ';
                break;
            }
        }
    }
    header('Location: rent_bill.php?month=' . $month);
    exit;
}

if ($action === 'delete_rent' && isset($_GET['id'])) {
    // CSRF check for GET-based destructive action
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        $_SESSION['message'] = 'CSRF token validation failed.';
        header('Location: rent_bill.php?month=' . $month);
        exit;
    }
    $id_to_delete = $_GET['id'];
    $data = getData();
    $original_count = count($data['rents']);
    $data['rents'] = array_values(array_filter($data['rents'], fn($r) => $r['id'] !== $id_to_delete));

    if (count($data['rents']) < $original_count) {
        if (saveData($data)) {
            $_SESSION['message'] = 'ลบรายการเช่าสำเร็จ';
        }
    }
    header('Location: rent_bill.php?month=' . $month);
    exit;
}

$all_categories = getCategories();
$utility_prices = $all_categories['utility_prices'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = 'CSRF token validation failed.';
        header('Location: rent_bill.php?month=' . $month);
        exit;
    }

    $data = getData();

    // Save new/edited rent item
    if (isset($_POST['save_rent'])) {
        $id = !empty($_POST['id']) ? $_POST['id'] : uniqid();
        $errors = [];

        $name = trim($_POST['name'] ?? '');
        $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
        $water_fee = filter_var($_POST['water_fee'] ?? '200', FILTER_VALIDATE_FLOAT);
        $due_date = filter_var($_POST['due_date'] ?? '', FILTER_VALIDATE_INT);
        $start_month = $_POST['start_month'] ?? '';
        $end_month = $_POST['end_month'] ?? '';

        if (empty($name)) $errors[] = 'กรุณากรอกชื่อรายการค่าเช่า/รายจ่ายประจำ';
        if ($water_fee === false || $water_fee < 0) $errors[] = 'ค่าน้ำไม่ถูกต้อง';
        if ($amount === false || $amount <= 0) $errors[] = 'จำนวนเงินไม่ถูกต้อง';
        if ($due_date === false || $due_date < 1 || $due_date > 31) $errors[] = 'วันที่ดีลไม่ถูกต้อง (1-31)';
        if (!preg_match('/^\d{4}-\d{2}$/', $start_month)) $errors[] = 'รูปแบบเดือนเริ่มต้นไม่ถูกต้อง';
        if (!preg_match('/^\d{4}-\d{2}$/', $end_month)) $errors[] = 'รูปแบบเดือนสิ้นสุดไม่ถูกต้อง';
        if (strtotime($start_month . '-01') > strtotime($end_month . '-01')) $errors[] = 'เดือนเริ่มต้นต้องไม่เกินเดือนสิ้นสุด';

        if (empty($errors)) {
            $payload = [
                'id' => $id,
                'name' => htmlspecialchars($name),
                'amount' => $amount,
                'water_fee' => $water_fee,
                'due_date' => $due_date,
                'start_month' => $start_month,
                'end_month' => $end_month
            ];

            $found = false;
            foreach ($data['rents'] as &$r) {
                if ((string)$r['id'] === (string)$id) {
                    // Preserve existing data when editing
                    $payload['status_by_month'] = $r['status_by_month'] ?? [];
                    $payload['utility_readings'] = $r['utility_readings'] ?? [];
                    $r = $payload;
                    $found = true;
                    break;
                }
            }
            unset($r);

            if (!$found) {
                $data['rents'][] = $payload;
            }

            if (saveData($data)) {
                $_SESSION['message'] = 'บันทึกรายการเช่าสำเร็จ';
            }
        } else {
            $_SESSION['message'] = implode('<br>', $errors);
            // TODO: Preserve post data for re-populating form
        }
    }

    // Save utility readings
    if (isset($_POST['save_readings'])) {
        foreach ($_POST['readings'] as $rent_id => $values) {
            foreach ($data['rents'] as &$rent) {
                if ($rent['id'] === $rent_id) {
                    $elec = filter_var($values['electricity'], FILTER_VALIDATE_FLOAT);
                    $elec_prev = filter_var($values['electricity_prev'] ?? null, FILTER_VALIDATE_FLOAT);

                    // Handle previous electricity meter editing
                    if ($elec_prev !== false && $elec_prev !== null) {
                        $rent['utility_readings'][$prev_mo]['electricity'] = $elec_prev;
                    }
                    // Handle current electricity meter
                    if ($elec !== false) {
                        $rent['utility_readings'][$month]['electricity'] = $elec;
                    }
                    break;
                }
            }
            unset($rent);
        }
        if (saveData($data)) {
            $_SESSION['message'] = 'บันทึกมิเตอร์เรียบร้อยแล้ว';
        }
    }

    // Save utility price settings
    if (isset($_POST['save_settings'])) {
        $all_categories['utility_prices']['water_per_unit'] = filter_var($_POST['water_price'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $all_categories['utility_prices']['electricity_per_unit'] = filter_var($_POST['electricity_price'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $all_categories['utility_prices']['min_water_charge'] = filter_var($_POST['min_water_charge'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        if (file_put_contents(CATEGORIES_FILE, json_encode($all_categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $_SESSION['message'] = 'บันทึกการตั้งค่าสำเร็จ';
        } else {
            $_SESSION['message'] = 'ไม่สามารถบันทึกการตั้งค่าได้';
        }
    }

    header('Location: rent_bill.php?month=' . $month);
    exit;
}

$data = getData();

// Handle editing a rent item
$e_rent = null;
if (!empty($_GET['edit_rent'])) {
    foreach ($data['rents'] as $r) {
        if ((string)$r['id'] === (string)$_GET['edit_rent']) {
            $e_rent = $r;
            break;
        }
    }
}
$active_rents = array_filter($data['rents'], fn($rent) => ($month >= $rent['start_month'] && $month <= $rent['end_month']));

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบิลค่าเช่า - ระบบบัญชีส่วนบุคคล Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
        }

        .meter-input {
            width: 80px;
            text-align: center;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <!-- Settings Modal -->
    <div id="settingsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-lg p-6 max-w-md w-full mx-4">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="month" value="<?= $month ?>">
                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 class="text-md font-bold text-gray-800"><i class="fa-solid fa-sliders text-slate-500 mr-1"></i> ตั้งค่าสาธารณูปโภค</h3>
                    <button type="button" onclick="toggleSettingsModal(false)" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="space-y-4 text-sm">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">ราคาต่อหน่วย (ค่าน้ำ)</label>
                        <input type="number" step="0.01" name="water_price" value="<?= $utility_prices['water_per_unit'] ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">ขั้นต่ำ (ค่าน้ำ)</label>
                        <input type="number" step="0.01" name="min_water_charge" value="<?= $utility_prices['min_water_charge'] ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">ราคาต่อหน่วย (ค่าไฟ)</label>
                        <input type="number" step="0.01" name="electricity_price" value="<?= $utility_prices['electricity_per_unit'] ?>" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="mt-6 flex gap-2 justify-end">
                    <button type="button" onclick="toggleSettingsModal(false)" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                    <button type="submit" name="save_settings" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">บันทึกการตั้งค่า</button>
                </div>
            </form>
        </div>
    </div>

    <div class="pb-12">
        <nav class="bg-white border-b border-gray-200 p-4 sticky top-0 z-40 shadow-sm">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
                <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-cyan-600"></i>จัดการบิลค่าเช่า</h1>
                <div class="flex items-center gap-4 flex-wrap justify-center">
                    <div class="flex items-center gap-1 bg-gray-50 p-1.5 rounded-full shadow-inner border border-gray-200">
                        <a href="?month=<?= $prev_mo ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                        <form method="GET" class="flex items-center mx-2">
                            <input type="month" name="month" value="<?= $month ?>" onchange="this.form.submit()" class="bg-transparent text-sm font-bold text-gray-700 outline-none cursor-pointer text-center" style="width: 100px;">
                        </form>
                        <a href="?month=<?= $next_mo ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                    </div>
                    <button onclick="toggleSettingsModal(true)" class="bg-slate-600 hover:bg-slate-700 text-white px-3 py-2 rounded-lg text-sm font-medium"><i class="fa-solid fa-sliders"></i> ตั้งค่า</button>
                    <a href="../index.php?month=<?= $month ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2 rounded-lg text-sm font-medium"><i class="fa-solid fa-arrow-left"></i> กลับหน้าหลัก</a>
                </div>
            </div>
        </nav>

        <div class="max-w-7xl mx-auto px-4 mt-6 ">
            <?php if ($message) : ?>
                <div class="bg-<?= strpos($message, 'สำเร็จ') !== false || strpos($message, 'เรียบร้อย') !== false ? 'green' : 'red' ?>-100 border border-<?= strpos($message, 'สำเร็จ') !== false || strpos($message, 'เรียบร้อย') !== false ? 'green' : 'red' ?>-400 text-<?= strpos($message, 'สำเร็จ') !== false || strpos($message, 'เรียบร้อย') !== false ? 'green' : 'red' ?>-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline"><?= $message ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="month" value="<?= $month ?>">
                        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                            <div class="p-4 border-b flex justify-between items-center bg-gray-50/80">
                                <h3 class="text-md font-bold text-gray-700"><i class="fa-solid fa-list-check text-indigo-500 mr-1"></i> รายการห้องเช่าประจำเดือน: <?= $month ?></h3>
                                <button type="submit" name="save_readings" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-save"></i> บันทึกมิเตอร์ทั้งหมด</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm whitespace-nowrap">
                                    <thead class="bg-white text-gray-500 text-[11px] uppercase border-b">
                                        <th class="p-3">ห้อง/ผู้เช่า</th>
                                        <th class="p-3 text-center">มิเตอร์ไฟ (ก่อน/หลัง)</th>
                                        <th class="p-3 text-center">ค่าน้ำ/ค่าไฟ</th>
                                        <th class="p-3 text-center">ค่าเช่า</th>
                                        <th class="p-3 text-center">ยอดรวม</th>
                                        <th class="p-3 text-center">สถานะ</th>
                                        <th class="p-3 text-center">จัดการ</th>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-700">
                                        <?php if (empty($active_rents)) : ?>
                                            <tr>
                                                <td colspan="8" class="p-6 text-center text-gray-400">ไม่มีรายการค่าเช่าในเดือนนี้</td>
                                            </tr>
                                        <?php else : ?>
                                            <?php foreach ($active_rents as $rent) :
                                                $status = $rent['status_by_month'][$month] ?? 'ค้างชำระ';

                                                // Get utility readings
                                                $elec_prev = $rent['utility_readings'][$prev_mo]['electricity'] ?? 0;
                                                $elec_curr = $rent['utility_readings'][$month]['electricity'] ?? '';

                                                // Calculate costs
                                                $water_cost = $rent['water_fee'] ?? 200; // Fixed water cost, default 200
                                                $elec_cost = 0;
                                                $total_bill = $rent['amount'];

                                                if ($elec_curr !== '' && $elec_prev !== '' && $elec_curr > $elec_prev) {
                                                    $elec_units = $elec_curr - $elec_prev;
                                                    $elec_cost = $elec_units * $utility_prices['electricity_per_unit'];
                                                }
                                                $total_bill += $water_cost + $elec_cost;
                                            ?>
                                                <tr class="hover:bg-gray-50/80">
                                                    <td class="p-3 font-bold">
                                                        <span class="text-[14px]">🏢</span> <?= htmlspecialchars($rent['name']) ?>
                                                        <div class="text-[10px] text-gray-400 mt-0.5">ชำระทุกวันที่ <?= $rent['due_date'] ?></div>
                                                    </td>
                                                    <td class="p-3 text-center">
                                                        <div class="flex items-center justify-center gap-1">
                                                            <input type="number" step="any" name="readings[<?= $rent['id'] ?>][electricity_prev]" value="<?= $elec_prev ?>" class="meter-input border rounded-md p-1 text-xs">
                                                            <i class="fa-solid fa-arrow-right-long text-gray-300 text-xs"></i>
                                                            <input type="number" step="any" name="readings[<?= $rent['id'] ?>][electricity]" value="<?= $elec_curr ?>" class="meter-input border rounded-md p-1 text-xs">
                                                        </div>
                                                    </td>
                                                    <td class="p-3 text-center text-xs">
                                                        <div class="text-blue-600">฿<?= number_format($water_cost, 2) ?></div>
                                                        <div class="text-orange-600">฿<?= number_format($elec_cost, 2) ?></div>
                                                    </td>
                                                    <td class="p-3 text-center font-medium text-gray-600">฿<?= number_format($rent['amount'], 2) ?></td>
                                                    <td class="p-3 text-center font-bold text-indigo-600">฿<?= number_format($total_bill, 2) ?></td>
                                                    <td class="p-3 text-center">
                                                        <a href="?action=toggle_status_rent&id=<?= $rent['id'] ?>&month_toggle=<?= $month ?>&csrf_token=<?= $csrf_token ?>" class="<?= $status === 'จ่ายแล้ว' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-500 bg-rose-50' ?> text-[10px] font-bold border px-2 py-0.5 rounded-full hover:opacity-75">
                                                            <?= $status ?>
                                                        </a>
                                                    </td>
                                                    <td class="p-3 text-center flex justify-center items-center gap-2">
                                                        <a href="receipt.php?id=<?= $rent['id'] ?>&month=<?= $month ?>" target="_blank" class="text-gray-400 hover:text-blue-600 px-1 transition-colors text-xs" title="ดาวน์โหลดสลิป"><i class="fa-solid fa-receipt"></i></a>
                                                        <a href="?month=<?= $month ?>&edit_rent=<?= $rent['id'] ?>" class="text-gray-400 hover:text-indigo-600 px-1 transition-colors text-xs" title="แก้ไข"><i class="fa-solid fa-pen"></i></a>
                                                        <a href="?action=delete_rent&id=<?= $rent['id'] ?>&month=<?= $month ?>&csrf_token=<?= $csrf_token ?>" onclick="return confirm('คุณมั่นใจที่จะลบรายการเช่านี้? การกระทำนี้จะลบข้อมูลทั้งหมดของห้องเช่านี้อย่างถาวร')" class="text-gray-400 hover:text-red-500 px-1 transition-colors text-xs" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="space-y-6">
                    <div class="bg-white p-5 rounded-xl shadow-sm border">
                        <h3 class="text-md font-bold text-gray-700 mb-4 flex justify-between items-center">
                            <span>
                                <i class="fa-solid <?= $e_rent ? 'fa-pen-to-square text-indigo-500' : 'fa-plus text-emerald-500' ?> mr-1"></i>
                                <?= $e_rent ? 'แก้ไขข้อมูลห้องเช่า' : 'เพิ่มห้องเช่าใหม่' ?>
                            </span>
                            <?php if ($e_rent): ?><a href="?month=<?= $month ?>" class="text-xs font-normal bg-gray-100 px-2 py-1 rounded-md hover:bg-gray-200">ยกเลิก</a><?php endif; ?>
                        </h3>
                        <form method="POST" action="rent_bill.php?month=<?= $month ?>" class="space-y-3 text-sm">
                            <input type="hidden" name="action" value="save_rent">
                            <input type="hidden" name="id" value="<?= $e_rent['id'] ?? '' ?>">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                            <input type="text" name="name" value="<?= htmlspecialchars($e_rent['name'] ?? '') ?>" placeholder="ชื่อห้อง / ผู้เช่า" class="w-full px-3 py-2 border rounded-lg" required>

                            <div class="grid grid-cols-2 gap-2"><input type="number" step="0.01" name="water_fee" value="<?= $e_rent['water_fee'] ?? '200.00' ?>" placeholder="ค่าน้ำ (บาท)" class="w-full px-3 py-2 border rounded-lg font-bold text-blue-600" required>
                                <input type="number" step="0.01" name="amount" value="<?= $e_rent['amount'] ?? '' ?>" placeholder="ค่าเช่า (บาท)" class="w-full px-3 py-2 border rounded-lg font-bold text-orange-600" required>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <select name="due_date" class="w-full px-3 py-2 border rounded-lg bg-white" required>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($e_rent && $e_rent['due_date'] == $i) ? 'selected' : '' ?>>ชำระทุกวันที่ <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="text-[10px] text-gray-500 block">เริ่มสัญญาเดือน:</label><input type="month" name="start_month" value="<?= $e_rent['start_month'] ?? $month ?>" class="w-full px-3 py-2 border rounded-lg" required></div>
                                <div><label class="text-[10px] text-gray-500 block">สิ้นสุดสัญญาเดือน:</label><input type="month" name="end_month" value="<?= $e_rent['end_month'] ?? date('Y-m', strtotime($month . ' +1 year')) ?>" class="w-full px-3 py-2 border rounded-lg" required></div>
                            </div>
                            <button type="submit" name="save_rent" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 rounded-lg font-medium">บันทึกข้อมูลห้องเช่า</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSettingsModal(show) {
            const modal = document.getElementById('settingsModal');
            modal.style.display = show ? 'flex' : 'none';
        }
    </script>
</body>

</html>