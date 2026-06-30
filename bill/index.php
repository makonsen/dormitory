<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../functions.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

$data = getData();
$all_categories = getCategories();
$utility_prices = $all_categories['utility_prices'];

$item = null;
$bill_details = [];

if ($type === 'rent' && $id) {
    foreach ($data['rents'] as $rent) {
        if ($rent['id'] === $id) {
            $item = $rent;
            break;
        }
    }

    if ($item) {
        $prev_mo = date('Y-m', strtotime($month . " -1 month"));

        $elec_prev = $item['utility_readings'][$prev_mo]['electricity'] ?? 0;
        $elec_curr = $item['utility_readings'][$month]['electricity'] ?? 0;

        $water_cost = $item['water_fee'] ?? 200;

        $elec_units = ($elec_curr > $elec_prev) ? $elec_curr - $elec_prev : 0;
        $elec_cost = $elec_units * $utility_prices['electricity_per_unit'];

        $total_amount = $item['amount'] + $water_cost + $elec_cost;

        $bill_details = [
            'title' => 'ใบแจ้งหนี้ค่าเช่าและค่าบริการ',
            'name' => $item['name'],
            'period' => 'ประจำเดือน ' . date('F Y', strtotime($month . '-01')),
            'due_date' => date('d/m/Y', strtotime($month . '-' . str_pad($item['due_date'], 2, '0', STR_PAD_LEFT))),
            'lines' => [
                ['desc' => 'ค่าเช่าห้อง', 'amount' => $item['amount']],
                ['desc' => "ค่าน้ำ (เหมาจ่าย)", 'amount' => $water_cost],
                ['desc' => "ค่าไฟ (หน่วย: $elec_prev -> $elec_curr = $elec_units หน่วย)", 'amount' => $elec_cost],
            ],
            'total' => $total_amount
        ];
    }
} else {
    // Placeholder for other bill types like 'item' if needed in the future
    die('ประเภทใบแจ้งหนี้ไม่ถูกต้อง');
}

if (!$item) {
    die('ไม่พบข้อมูลสำหรับสร้างใบแจ้งหนี้');
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?= $bill_details['title'] ?> - <?= $bill_details['name'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="max-w-2xl mx-auto my-8 bg-white shadow-lg rounded-lg p-8" id="invoice">
        <header class="flex justify-between items-center border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800"><?= $bill_details['title'] ?></h1>
                <p class="text-gray-500"><?= $bill_details['period'] ?></p>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-bold">หอพัก Pro</h2>
                <p class="text-sm text-gray-500">เลขที่ 123 หมู่ 4 ต.ในเมือง อ.เมือง จ.ขอนแก่น 40000</p>
            </div>
        </header>

        <section class="mt-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-600 uppercase">สำหรับ:</h3>
                    <p class="text-gray-800 font-bold"><?= htmlspecialchars($bill_details['name']) ?></p>
                </div>
                <div class="text-right">
                    <h3 class="text-sm font-bold text-gray-600 uppercase">วันที่ออกบิล:</h3>
                    <p class="text-gray-800 font-bold"><?= date('d/m/Y') ?></p>
                    <h3 class="text-sm font-bold text-gray-600 uppercase mt-2">กำหนดชำระ:</h3>
                    <p class="text-gray-800 font-bold"><?= $bill_details['due_date'] ?></p>
                </div>
            </div>
        </section>

        <section class="mt-8">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="p-3 text-sm font-bold text-gray-600 uppercase">รายการ</th>
                        <th class="p-3 text-right text-sm font-bold text-gray-600 uppercase">จำนวนเงิน (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bill_details['lines'] as $line) : ?>
                        <tr class="border-b">
                            <td class="p-3 text-gray-700"><?= $line['desc'] ?></td>
                            <td class="p-3 text-right text-gray-800"><?= number_format($line['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="mt-6 flex justify-end">
            <div class="w-full max-w-xs">
                <div class="bg-gray-100 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-800 text-lg">ยอดรวมสุทธิ</span>
                        <span class="font-bold text-indigo-600 text-xl">฿<?= number_format($bill_details['total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </section>

        <footer class="mt-12 text-center text-xs text-gray-500 border-t pt-4">
            <p>ขอบคุณที่ใช้บริการ</p>
            <p>หากมีข้อสงสัย กรุณาติดต่อผู้ดูแลหอพัก</p>
        </footer>
    </div>

    <div class="max-w-2xl mx-auto my-8 text-center no-print">
        <button onclick="window.print()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-print mr-2"></i>พิมพ์ใบแจ้งหนี้
        </button>
        <a href="rent_bill.php?month=<?= $month ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <i class="fa-solid fa-arrow-left mr-2"></i>กลับ
        </a>
    </div>
</body>
</html>