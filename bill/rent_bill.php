<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
date_default_timezone_set('Asia/Bangkok');
$now = new DateTimeImmutable();

define('RENT_BILL_FILE', '../Rent_bil.json');
define('WATER_BILL_AMOUNT', 200.0);
define('ELECTRICITY_RATE', 7);


// Helper functions for data
function getRentBills() {
    if (!file_exists(RENT_BILL_FILE)) return [];
    $json = @file_get_contents(RENT_BILL_FILE);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function saveRentBills($bills) {
    return @file_put_contents(RENT_BILL_FILE, json_encode($bills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function findBillIndexById($bills, $id) {
    if (empty($id) || empty($bills)) return false;
    $bill_ids = array_column($bills, 'id');
    return array_search($id, $bill_ids);
}

$month = $_GET['month'] ?? $now->format('Y-m');
$prev_mo = date('Y-m', strtotime($month . " -1 month"));
$next_mo = date('Y-m', strtotime($month . " +1 month"));

$all_bills = getRentBills();

// Prepare previous month's data for auto-fill feature
$prev_month_bills = array_filter($all_bills, fn($b) => $b['month'] === $prev_mo);
$prev_month_data_for_js = [];
foreach ($prev_month_bills as $bill) {
    // Use room number as key for easy JS lookup
    $prev_month_data_for_js[$bill['room_number']] = [
        'rent_amount' => $bill['rent_amount'],
        'last_meter' => $bill['current_electric_meter']
    ];
}

// Handle edit request
$bill_to_edit = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    foreach ($all_bills as $bill) {
        // Allow editing only for bills in the current month view and are unpaid
        if ($bill['id'] === $edit_id && $bill['month'] === $month && $bill['status'] === 'ยังไม่จ่าย') {
            $bill_to_edit = $bill;
            break;
        }
    }
}

// Handle Actions (POST/GET)
$action = $_REQUEST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create_all_bills') {
    // 1. Get bills from the previous month
    $prev_month_bills_to_copy = array_filter($all_bills, fn($b) => $b['month'] === $prev_mo);

    // 2. Get room numbers that already have a bill in the current month to avoid duplicates
    $current_month_room_numbers = array_column(array_filter($all_bills, fn($b) => $b['month'] === $month), 'room_number');

    $new_bills_created = 0;
    foreach ($prev_month_bills_to_copy as $prev_bill) {
        // 3. Check if a bill for this room already exists in the current month
        if (in_array($prev_bill['room_number'], $current_month_room_numbers)) {
            continue; // Skip if bill already exists
        }

        // 4. Create a new bill with data from the previous month
        $water_bill = WATER_BILL_AMOUNT;
        $rent_amount = $prev_bill['rent_amount'];
        $prev_meter = $prev_bill['current_electric_meter']; // Use last month's current meter
        
        // Set current meter to previous meter initially. The user must edit this.
        $current_meter = $prev_meter; 
        $electric_units = 0;
        $electricity_cost = 0;
        $total_amount = $rent_amount + $water_bill + $electricity_cost;

        $new_bill = [
            'id' => uniqid('rb_'),
            'month' => $month,
            'room_number' => $prev_bill['room_number'],
            'rent_amount' => $rent_amount,
            'water_bill' => $water_bill,
            'prev_electric_meter' => $prev_meter,
            'current_electric_meter' => $current_meter,
            'electric_units_used' => $electric_units,
            'electricity_cost' => $electricity_cost,
            'total_amount' => $total_amount,
            'notes' => 'สร้างอัตโนมัติจากข้อมูลเดือนก่อน',
            'status' => 'ยังไม่จ่าย',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $all_bills[] = $new_bill;
        $new_bills_created++;
    }

    if ($new_bills_created > 0) {
        saveRentBills($all_bills);
        $_SESSION['success_message'] = "สร้างบิลอัตโนมัติสำเร็จ {$new_bills_created} รายการ";
    } else {
        $_SESSION['error_message'] = "ไม่สามารถสร้างบิลได้ อาจเนื่องจากมีบิลสำหรับห้องเหล่านั้นในเดือนนี้อยู่แล้ว";
    }

    header("Location: ?month=" . $month);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_bill') {
    $id = !empty($_POST['id']) ? $_POST['id'] : uniqid('rb_');
    $room_number = trim($_POST['room_number'] ?? '');
    $rent_amount = filter_var($_POST['rent_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $prev_meter = filter_var($_POST['prev_meter'] ?? 0, FILTER_VALIDATE_FLOAT);
    $current_meter = filter_var($_POST['current_meter'] ?? 0, FILTER_VALIDATE_FLOAT);
    $notes = trim($_POST['notes'] ?? '');
    $bill_month = $_POST['bill_month'] ?? $month;

    // Basic validation
    if (!empty($room_number) && $rent_amount > 0 && $current_meter >= $prev_meter) {
        $water_bill = WATER_BILL_AMOUNT;
        $electric_units = $current_meter - $prev_meter;
        $electricity_cost = $electric_units * ELECTRICITY_RATE;
        $total_amount = $rent_amount + $water_bill + $electricity_cost;

        $new_bill = [
            'id' => $id,
            'month' => $bill_month,
            'room_number' => htmlspecialchars($room_number),
            'rent_amount' => $rent_amount,
            'water_bill' => $water_bill,
            'prev_electric_meter' => $prev_meter,
            'current_electric_meter' => $current_meter,
            'electric_units_used' => $electric_units,
            'electricity_cost' => $electricity_cost,
            'total_amount' => $total_amount,
            'notes' => htmlspecialchars($notes),
            'status' => 'ยังไม่จ่าย',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $key_to_update = findBillIndexById($all_bills, $id);

        if ($key_to_update !== false) {
            // Preserve original status and creation date on edit
            $new_bill['status'] = $all_bills[$key_to_update]['status'];
            $new_bill['created_at'] = $all_bills[$key_to_update]['created_at'];
            $all_bills[$key_to_update] = $new_bill;
        } else {
            $all_bills[] = $new_bill;
        }

        saveRentBills($all_bills);
        header("Location: ?month=" . $bill_month);
        exit;
    } else {
        // Handle error - maybe set a session message
        $_SESSION['error_message'] = "ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง";
        header("Location: ?month=" . $month);
        exit;
    }
}

if ($action && isset($_GET['id'])) {
    $id = $_GET['id'];
    if ($action === 'toggle_status') {
        $key = findBillIndexById($all_bills, $id);
        if ($key !== false) {
            $all_bills[$key]['status'] = ($all_bills[$key]['status'] === 'จ่ายแล้ว') ? 'ยังไม่จ่าย' : 'จ่ายแล้ว';
        }
    } elseif ($action === 'delete_bill') {
        $all_bills = array_values(array_filter($all_bills, fn($b) => $b['id'] !== $id));
    }
    saveRentBills($all_bills);
    header("Location: ?month=" . $month);
    exit;
}

// Filter bills for the selected month and calculate summaries
$bills_for_month = array_filter($all_bills, fn($b) => $b['month'] === $month);
usort($bills_for_month, fn($a, $b) => strcmp($a['room_number'], $b['room_number']));

// Calculate summaries more concisely
$paid_bills = array_filter($bills_for_month, fn($b) => $b['status'] === 'จ่ายแล้ว');

$paid_rooms_count = count($paid_bills);
$total_rent_income = array_sum(array_column($paid_bills, 'rent_amount'));
$total_electric_income = array_sum(array_column($paid_bills, 'electricity_cost'));
$total_water_income = array_sum(array_column($paid_bills, 'water_bill'));
$total_income_from_paid = $total_rent_income + $total_water_income + $total_electric_income;

// Check for error message
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check for success message
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการบิลค่าเช่า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white border-b border-gray-200 p-4 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2"><i class="fa-solid fa-file-invoice-dollar text-cyan-600"></i> จัดการบิลค่าเช่า</h1>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1 bg-gray-50 p-1.5 rounded-full shadow-inner border border-gray-200">
                    <a href="?month=<?= $prev_mo ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                    <form method="GET" class="flex items-center mx-2">
                        <input type="month" name="month" value="<?= $month ?>" onchange="this.form.submit()" class="bg-transparent text-sm font-bold text-gray-700 outline-none cursor-pointer text-center" style="width: 100px;">
                    </form>
                    <a href="?month=<?= $next_mo ?>" class="flex items-center justify-center w-8 h-8 rounded-full bg-white text-gray-600 shadow-sm hover:bg-indigo-50 hover:text-indigo-600 transition-colors"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                </div>
                <form method="POST" action="?month=<?= $month ?>" onsubmit="return confirm('คุณต้องการสร้างบิลทั้งหมดจากข้อมูลเดือนก่อนหน้าหรือไม่?');" class="hidden sm:block">
                    <input type="hidden" name="action" value="create_all_bills">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-bolt"></i> สร้างบิลทั้งหมด</button>
                </form>
                <a href="../index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium transition-all shadow-sm"><i class="fa-solid fa-arrow-left"></i> กลับหน้าหลัก</a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 mt-6 pb-12">
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $error_message ?></span>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?= $success_message ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary Section -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border"><p class="text-sm text-gray-500">ห้องที่จ่ายแล้ว</p><h3 class="text-2xl font-bold text-emerald-600 mt-1"><?= $paid_rooms_count ?> / <?= count($bills_for_month) ?></h3></div>
            <div class="bg-white p-4 rounded-xl shadow-sm border"><p class="text-sm text-gray-500">รายรับค่าห้อง (จ่ายแล้ว)</p><h3 class="text-2xl font-bold text-sky-600 mt-1">฿<?= number_format($total_rent_income, 2) ?></h3></div>
            <div class="bg-white p-4 rounded-xl shadow-sm border"><p class="text-sm text-gray-500">รายรับค่าไฟ (จ่ายแล้ว)</p><h3 class="text-2xl font-bold text-amber-600 mt-1">฿<?= number_format($total_electric_income, 2) ?></h3></div>
            <div class="bg-white p-4 rounded-xl shadow-sm border"><p class="text-sm text-gray-500">รายรับรวม (จ่ายแล้ว)</p><h3 class="text-2xl font-bold text-cyan-600 mt-1">฿<?= number_format($total_income_from_paid, 2) ?></h3></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Section -->
            <div class="lg:col-span-1 bg-white p-5 rounded-xl shadow-sm border">
                <h3 class="text-lg font-bold text-gray-800 mb-1"><i class="fa-solid fa-<?= $bill_to_edit ? 'edit' : 'plus-circle' ?> text-indigo-500"></i> <?= $bill_to_edit ? 'แก้ไขบิลค่าเช่า' : 'สร้างบิลค่าเช่าใหม่' ?></h3>
                <?php if ($bill_to_edit): ?>
                    <div class="mb-3 text-sm">
                        <span class="font-semibold">ห้อง: <?= htmlspecialchars($bill_to_edit['room_number']) ?></span>
                        <a href="?month=<?= $month ?>" class="text-indigo-600 hover:text-indigo-800 ml-2">[ยกเลิกการแก้ไข]</a>
                    </div>
                <?php endif; ?>
                <form method="POST" action="rent_bill.php?month=<?= $month ?>" class="space-y-4 text-sm" id="rent-form">
                    <input type="hidden" name="action" value="save_bill">
                    <input type="hidden" name="id" value="<?= $bill_to_edit['id'] ?? '' ?>">
                    <input type="hidden" name="bill_month" value="<?= $month ?>">
                    
                    <div><label class="font-medium">เลขห้อง</label><input type="text" name="room_number" value="<?= htmlspecialchars($bill_to_edit['room_number'] ?? '') ?>" placeholder="เช่น 101" class="w-full mt-1 px-3 py-2 border rounded-lg" required></div>
                    <div><label class="font-medium">ค่าเช่าห้อง</label><input type="number" step="0.01" name="rent_amount" id="rent_amount" value="<?= $bill_to_edit['rent_amount'] ?? '' ?>" placeholder="เช่น 5000" class="w-full mt-1 px-3 py-2 border rounded-lg" required></div>
                    <div><label class="font-medium">ค่าน้ำ</label><input type="text" value="<?= number_format(WATER_BILL_AMOUNT, 2) ?>" class="w-full mt-1 px-3 py-2 border rounded-lg bg-gray-100" readonly></div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="font-medium">มิเตอร์ไฟ (ก่อน)</label><input type="number" step="0.01" name="prev_meter" id="prev_meter" value="<?= $bill_to_edit['prev_electric_meter'] ?? '' ?>" placeholder="หน่วยเก่า" class="w-full mt-1 px-3 py-2 border rounded-lg" required></div>
                        <div><label class="font-medium">มิเตอร์ไฟ (หลัง)</label><input type="number" step="0.01" name="current_meter" id="current_meter" value="<?= $bill_to_edit['current_electric_meter'] ?? '' ?>" placeholder="หน่วยใหม่" class="w-full mt-1 px-3 py-2 border rounded-lg" required></div>
                    </div>
                    <div id="electric-calc-summary" class="text-sm text-gray-600 bg-gray-50 p-2 rounded-lg hidden mt-2"></div>
                    
                    <div><label class="font-medium">หมายเหตุ</label><textarea name="notes" rows="2" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)" class="w-full mt-1 px-3 py-2 border rounded-lg"><?= htmlspecialchars($bill_to_edit['notes'] ?? '') ?></textarea></div>

                    <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded-r-lg mt-4">
                        <div class="flex justify-between items-center">
                            <span class="font-bold text-indigo-800">ยอดรวมทั้งหมด</span>
                            <span id="total-summary" class="text-2xl font-bold text-indigo-800">฿0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-medium text-base"><?= $bill_to_edit ? 'อัปเดตข้อมูล' : 'บันทึกบิล' ?></button>
                </form>
            </div>

            <!-- Dashboard Table -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="p-4 border-b bg-gray-50"><h3 class="text-lg font-bold text-gray-800">รายการบิลประจำเดือน <?= date('m/Y', strtotime($month)) ?></h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                            <tr>
                                <th class="p-3">เลขห้อง</th>
                                <th class="p-3 text-right">ค่าเช่า</th>
                                <th class="p-3 text-center">หน่วยค่าไฟ</th>
                                <th class="p-3">ยอดรวม</th>
                                <th class="p-3 text-center">สถานะ</th>
                                <th class="p-3 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php if (empty($bills_for_month)): ?>
                                <tr><td colspan="6" class="p-6 text-center text-gray-400">ยังไม่มีบิลในเดือนนี้</td></tr>
                            <?php else: ?>
                                <?php foreach ($bills_for_month as $bill): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-3 font-bold"><?= $bill['room_number'] ?></td>
                                        <td class="p-3 text-right">฿<?= number_format($bill['rent_amount'], 2) ?></td>
                                        <td class="p-3 text-center"><?= number_format($bill['electric_units_used'], 2) ?> หน่วย</td>
                                        <td class="p-3 font-bold text-indigo-600">฿<?= number_format($bill['total_amount'], 2) ?></td>
                                        <td class="p-3 text-center"><span class="<?= $bill['status'] === 'จ่ายแล้ว' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?> text-xs font-bold px-2 py-1 rounded-full"><?= $bill['status'] ?></span></td>
                                        <td class="p-3 text-center">
                                            <div class="flex justify-center items-center gap-2">
                                                <a href="bill_receipt.php?id=<?= $bill['id'] ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="ดาวน์โหลดบิล"><i class="fa-solid fa-download"></i></a>
                                                <?php if ($bill['status'] === 'ยังไม่จ่าย'): ?>
                                                    <a href="?month=<?= $month ?>&edit_id=<?= $bill['id'] ?>#rent-form" class="text-indigo-500 hover:text-indigo-700" title="แก้ไข"><i class="fa-solid fa-pen-to-square"></i></a>
                                                <?php else: ?>
                                                    <span class="text-gray-300 cursor-not-allowed" title="ไม่สามารถแก้ไขบิลที่จ่ายแล้ว"><i class="fa-solid fa-pen-to-square"></i></span>
                                                <?php endif; ?>
                                                <a href="?action=toggle_status&id=<?= $bill['id'] ?>&month=<?= $month ?>" class="text-xs font-semibold text-white px-2 py-1 rounded <?= $bill['status'] === 'จ่ายแล้ว' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-500 hover:bg-emerald-600' ?>" title="<?= $bill['status'] === 'จ่ายแล้ว' ? 'ยกเลิกการจ่าย' : 'ทำเครื่องหมายว่าจ่ายแล้ว' ?>"><?= $bill['status'] === 'จ่ายแล้ว' ? 'ยกเลิก' : 'จ่ายแล้ว' ?></a>
                                                <a href="?action=delete_bill&id=<?= $bill['id'] ?>&month=<?= $month ?>" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะลบบิลนี้?')" class="text-red-500 hover:text-red-700" title="ลบ"><i class="fa-solid fa-trash-can"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data from previous month for auto-fill, generated by PHP
        const prevMonthData = <?= json_encode($prev_month_data_for_js, JSON_UNESCAPED_UNICODE) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('rent-form');
            const roomNumberEl = document.querySelector('input[name="room_number"]');
            const rentAmountEl = document.getElementById('rent_amount');
            const prevMeterEl = document.getElementById('prev_meter');
            const currentMeterEl = document.getElementById('current_meter');
            const totalSummaryEl = document.getElementById('total-summary');
            const electricCalcEl = document.getElementById('electric-calc-summary');

            function calculateAll() {
                const rent = parseFloat(rentAmountEl.value) || 0;
                const prevMeter = parseFloat(prevMeterEl.value) || 0;
                const currentMeter = parseFloat(currentMeterEl.value) || 0;
                
                const water = <?= WATER_BILL_AMOUNT ?>;
                let electricity = 0;
                let units = 0;

                if (currentMeter > prevMeter) {
                    units = currentMeter - prevMeter;
                    electricity = units * <?= ELECTRICITY_RATE ?>;
                }

                // Update electric calculation summary
                if (units > 0) {
                    electricCalcEl.innerHTML = `ใช้ไป: <span class="font-bold">${units.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span> หน่วย x <?= ELECTRICITY_RATE ?> = <span class="font-bold text-amber-700">฿${electricity.toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>`;
                    electricCalcEl.classList.remove('hidden');
                } else {
                    electricCalcEl.classList.add('hidden');
                }

                // Update total summary
                const total = rent + water + electricity;
                totalSummaryEl.textContent = '฿' + total.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            // Auto-fill logic when room number is entered and the field loses focus
            roomNumberEl.addEventListener('change', function() {
                const roomNumber = this.value.trim();
                const billData = prevMonthData[roomNumber];
                const isEditing = document.querySelector('input[name="id"]').value !== '';

                // Auto-fill only if data exists for the room and we are not in edit mode
                if (billData && !isEditing) {
                    rentAmountEl.value = billData.rent_amount;
                    prevMeterEl.value = billData.last_meter;
                    
                    // Trigger calculation and focus on the next logical field for quick entry
                    calculateAll();
                    currentMeterEl.focus();
                }
            });

            form.addEventListener('input', calculateAll);
            calculateAll(); // Initial calculation on page load (for edit mode)
        });
    </script>
</body>
</html>