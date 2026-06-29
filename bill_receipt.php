<?php
date_default_timezone_set('Asia/Bangkok');
define('RENT_BILL_FILE', 'Rent_bil.json');

$bill_id = $_GET['id'] ?? null;
if (!$bill_id) {
    die('ไม่พบ ID ของบิล');
}

$all_bills = file_exists(RENT_BILL_FILE) ? json_decode(file_get_contents(RENT_BILL_FILE), true) : [];
$bill = null;
foreach ($all_bills as $b) {
    if ($b['id'] === $bill_id) {
        $bill = $b;
        break;
    }
}

if (!$bill) {
    die('ไม่พบบิลที่ระบุ');
}

$thai_month_arr = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
list($s_yr, $s_mo) = explode('-', $bill['month']);
$thai_month = $thai_month_arr[(int)$s_mo];
$thai_year = (int)$s_yr + 543;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแจ้งค่าบริการ ห้อง <?= htmlspecialchars($bill['room_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #E5E7EB;
        }
        .receipt {
            font-family: 'IBM Plex Mono', 'Sarabun', monospace;
            width: 320px;
            background: white;
            padding: 20px;
            margin: 2rem auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .receipt-header, .receipt-footer {
            text-align: center;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
        }
        .dotted-line {
            border-top: 1px dashed #333;
            margin: 10px 0;
        }
        @media print {
            body { background-color: white; }
            .no-print { display: none; }
            .receipt { margin: 0; box-shadow: none; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="receipt text-sm">
        <div class="receipt-header">
            <h1 class="text-lg font-bold">ใบแจ้งค่าบริการ</h1>
            <p>Dormitory System</p>
            <p>ประจำเดือน <?= $thai_month ?> <?= $thai_year ?></p>
            <p>วันที่ออกบิล: <?= date('d/m/Y H:i') ?></p>
        </div>
        <div class="dotted-line"></div>
        <div class="mb-2">
            <p><strong>ห้อง:</strong> <?= htmlspecialchars($bill['room_number']) ?></p>
            <p><strong>เลขที่อ้างอิง:</strong> <?= htmlspecialchars($bill['id']) ?></p>
        </div>
        <div class="dotted-line"></div>
        
        <div class="space-y-1">
            <div class="receipt-item">
                <span>ค่าเช่าห้อง</span>
                <span><?= number_format($bill['rent_amount'], 2) ?></span>
            </div>
            <div class="receipt-item">
                <span>ค่าน้ำประปา</span>
                <span><?= number_format($bill['water_bill'], 2) ?></span>
            </div>
            <div class="receipt-item">
                <span>ค่าไฟฟ้า</span>
                <span><?= number_format($bill['electricity_cost'], 2) ?></span>
            </div>
            <div class="text-xs pl-4">
                <p>หน่วยก่อน: <?= number_format($bill['prev_electric_meter'], 2) ?></p>
                <p>หน่วยหลัง: <?= number_format($bill['current_electric_meter'], 2) ?></p>
                <p>ใช้ไป: <?= number_format($bill['electric_units_used'], 2) ?> หน่วย</p>
            </div>
        </div>

        <div class="dotted-line"></div>

        <div class="receipt-item text-lg font-bold">
            <span>ยอดรวม</span>
            <span><?= number_format($bill['total_amount'], 2) ?></span>
        </div>

        <div class="dotted-line"></div>
        
        <?php if (!empty($bill['notes'])): ?>
            <div class="text-xs mt-2">
                <p><strong>หมายเหตุ:</strong></p>
                <p><?= nl2br(htmlspecialchars($bill['notes'])) ?></p>
            </div>
            <div class="dotted-line"></div>
        <?php endif; ?>

        <?php if ($bill['status'] === 'จ่ายแล้ว'): ?>
            <div class="receipt-footer my-4">
                <p class="text-lg font-bold text-emerald-600">*** ชำระเงินเรียบร้อยแล้ว ***</p>
                <p>ขอบคุณที่ใช้บริการ</p>
            </div>
        <?php else: ?>
            <div class="receipt-footer my-4">
                <p class="text-lg font-bold text-rose-600">*** ยังไม่ชำระเงิน ***</p>
                <p>กรุณาชำระภายในวันที่กำหนด</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center no-print">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">พิมพ์ใบเสร็จ</button>
        <a href="rent_bill.php?month=<?= $bill['month'] ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 ml-2">กลับ</a>
    </div>
</body>
</html>
