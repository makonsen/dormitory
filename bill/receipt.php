<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../functions.php';

// You can configure your dormitory name here
$dormitory_name = "หอพักสุขใจ"; // Default name

$id = $_GET['id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');

if (!$id) {
    die('ไม่พบ ID ของบิล');
}

$data = getData();
$all_categories = getCategories();
$utility_prices = $all_categories['utility_prices'];

$rent_item = null;
foreach ($data['rents'] as $rent) {
    if ($rent['id'] === $id) {
        $rent_item = $rent;
        break;
    }
}

if (!$rent_item) {
    die('ไม่พบบิลที่ระบุ');
}

// --- Calculation Logic ---
$prev_mo = date('Y-m', strtotime($month . " -1 month"));

$elec_prev = $rent_item['utility_readings'][$prev_mo]['electricity'] ?? 0;
$elec_curr = $rent_item['utility_readings'][$month]['electricity'] ?? 0;

$water_cost = $rent_item['water_fee'] ?? 200;

$elec_units = ($elec_curr > $elec_prev) ? $elec_curr - $elec_prev : 0;
$elec_cost = $elec_units * $utility_prices['electricity_per_unit'];

$total_amount = $rent_item['amount'] + $water_cost + $elec_cost;
$status = $rent_item['status_by_month'][$month] ?? 'ค้างชำระ';

// --- Thai Date Formatting ---
$thai_month_arr = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
list($s_yr, $s_mo) = explode('-', $month);
$thai_month = $thai_month_arr[(int)$s_mo];
$thai_year = (int)$s_yr + 543;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแจ้งค่าบริการ ห้อง <?= htmlspecialchars($rent_item['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
    <style>
        body { background-color: #E5E7EB; font-family: 'Sarabun', sans-serif; }
        .receipt { font-family: 'IBM Plex Mono', 'Sarabun', monospace; width: 320px; background: white; padding: 20px; margin: 2rem auto; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        .receipt-header, .receipt-footer { text-align: center; }
        .receipt-item { display: flex; justify-content: space-between; }
        .dotted-line { border-top: 1px dashed #333; margin: 10px 0; }
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
            <h1 class="text-lg font-bold"><?= htmlspecialchars($dormitory_name) ?></h1>
            <p>ใบแจ้งค่าบริการ</p>
            <p>ประจำเดือน <?= $thai_month ?> <?= $thai_year ?></p>
            <p>วันที่ออก: <?= date('d/m/Y H:i') ?></p>
        </div>
        <div class="dotted-line"></div>
        <div class="mb-2">
            <p><strong>ห้อง/ผู้เช่า:</strong> <?= htmlspecialchars($rent_item['name']) ?></p>
            <p><strong>เลขที่อ้างอิง:</strong> <?= htmlspecialchars($rent_item['id']) ?></p>
        </div>
        <div class="dotted-line"></div>

        <div class="space-y-1">
            <div class="receipt-item"><span>ค่าเช่าห้อง</span><span><?= number_format($rent_item['amount'], 2) ?></span></div>
            <div class="receipt-item"><span>ค่าน้ำ (เหมาจ่าย)</span><span><?= number_format($water_cost, 2) ?></span></div>
            <div class="receipt-item"><span>ค่าไฟฟ้า</span><span><?= number_format($elec_cost, 2) ?></span></div>
            <div class="text-xs pl-4">
                <p>หน่วยก่อน: <?= number_format($elec_prev, 2) ?></p>
                <p>หน่วยหลัง: <?= number_format($elec_curr, 2) ?></p>
                <p>ใช้ไป: <?= number_format($elec_units, 2) ?> หน่วย</p>
            </div>
        </div>

        <div class="dotted-line"></div>
        <div class="receipt-item text-lg font-bold"><span>ยอดรวม</span><span><?= number_format($total_amount, 2) ?></span></div>
        <div class="dotted-line"></div>

        <?php if ($status === 'จ่ายแล้ว'): ?>
            <div class="receipt-footer my-4"><p class="text-lg font-bold text-emerald-600">*** ชำระเงินเรียบร้อยแล้ว ***</p><p>ขอบคุณที่ใช้บริการ</p></div>
        <?php else: ?>
            <div class="receipt-footer my-4"><p class="text-lg font-bold text-rose-600">*** ยังไม่ชำระเงิน ***</p><p>กรุณาชำระภายในวันที่ <?= str_pad($rent_item['due_date'], 2, '0', STR_PAD_LEFT) ?>/<?= date('m/Y', strtotime($month)) ?></p></div>
        <?php endif; ?>
    </div>

    <div class="text-center no-print">
        <button id="download-btn" onclick="downloadReceipt(this)" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 w-48 text-center">
            <i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดสลิป
        </button>
        <a href="rent_bill.php?month=<?= $month ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 ml-2">กลับ</a>
    </div>

    <script>
        function downloadReceipt(button) {
            const element = document.querySelector('.receipt');
            const roomName = "<?= htmlspecialchars($rent_item['name']) ?>";
            const billMonth = "<?= htmlspecialchars($month) ?>";
            const filename = `ใบแจ้งหนี้-${roomName.replace(/ /g, '_')}-${billMonth}.pdf`;

            const opt = {
                margin: [10, 10, 10, 10],
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 3, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: [100, 297], orientation: 'portrait' }
            };

            const originalMargin = element.style.margin;
            const originalShadow = element.style.boxShadow;
            element.style.margin = '0';
            element.style.boxShadow = 'none';

            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังสร้าง...';
            button.disabled = true;

            html2pdf().from(element).set(opt).save().then(() => {
                element.style.margin = originalMargin;
                element.style.boxShadow = originalShadow;
                button.innerHTML = '<i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดสลิป';
                button.disabled = false;
            }).catch((err) => {
                console.error("PDF generation failed:", err);
                alert("เกิดข้อผิดพลาดในการสร้าง PDF: " + err.message);
                element.style.margin = originalMargin;
                element.style.boxShadow = originalShadow;
                button.innerHTML = '<i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดสลิป';
                button.disabled = false;
            });
        }
    </script>
</body>
</html>