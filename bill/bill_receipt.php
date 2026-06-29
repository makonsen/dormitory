<?php
date_default_timezone_set('Asia/Bangkok');
// You can configure your dormitory name here
$dormitory_name = "Dormitory System"; // <<<<<<< You can change this name

define('RENT_BILL_FILE', '../Rent_bil.json');

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

$thai_month_arr = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&family=IBM+Plex+Mono&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #E5E7EB;
        }

        .receipt {
            font-family: 'IBM Plex Mono', 'Sarabun', monospace;
            width: 310px;
            background: white;
            padding: 20px;
            margin: 2rem auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .receipt-header,
        .receipt-footer {
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
            body {
                background-color: white;
            }

            .no-print {
                display: none;
            }

            .receipt {
                margin: 0;
                box-shadow: none;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="receipt text-sm">
        <div class="receipt-header">
            <h1 class="text-lg font-bold"><?= htmlspecialchars($dormitory_name) ?></h1>
            <p>ใบแจ้งค่าบริการ</p>
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
        <button onclick="downloadReceipt(this)" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 w-48 text-center">
            <i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดใบเสร็จ
        </button>
        <a href="rent_bill.php?month=<?= $bill['month'] ?>" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300 ml-2">กลับ</a>
    </div>

    <script>
        function downloadReceipt(button) {
            const element = document.querySelector('.receipt');
            const roomNumber = "<?= htmlspecialchars($bill['room_number']) ?>";
            const billMonth = "<?= htmlspecialchars($bill['month']) ?>";

            // สร้างวันที่ปัจจุบันสำหรับชื่อไฟล์
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            // สร้างชื่อไฟล์ใหม่: เลขห้อง-ปี-เดือน-วัน.pdf
            const newFilename = `${roomNumber}-${billMonth}-${day}.pdf`;

            const opt = {
                margin: [10, 7, 10, 7], // top, left, bottom, right
                filename: newFilename,
                image: {
                    type: 'jpeg',
                    quality: 0.95
                },
                html2canvas: {
                    scale: 2, // ลด scale เพื่อเพิ่มความเร็วในการสร้าง PDF
                    useCORS: true,
                    logging: true // เปิด logging เพื่อช่วยในการ debug
                },
                jsPDF: {
                    unit: 'mm',
                    format: [100, 250], // ปรับความกว้างและความยาว
                    orientation: 'portrait',
                }
            };

            const originalMargin = element.style.margin;
            const originalShadow = element.style.boxShadow;
            element.style.margin = '0';
            element.style.boxShadow = 'none';

            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังสร้าง...';
            button.disabled = true;

            console.log('Starting PDF generation with options:', opt);

            // ใช้ Promise wrapper เพื่อจัดการ timeout ป้องกันการค้าง
            const generationPromise = new Promise((resolve, reject) => {
                const timeoutId = setTimeout(() => {
                    reject(new Error('การสร้าง PDF ใช้เวลานานเกินไป (15 วินาที) อาจมีปัญหาในการโหลดทรัพยากรภายนอกเช่นฟอนต์'));
                }, 15000);

                html2pdf().from(element).set(opt).outputPdf('blob')
                    .then((blob) => {
                        clearTimeout(timeoutId);
                        resolve(blob);
                    })
                    .catch((error) => {
                        clearTimeout(timeoutId);
                        reject(error);
                    });
            });

            generationPromise
                .then((pdfBlob) => {
                    console.log('PDF Blob created successfully.');
                    const url = URL.createObjectURL(pdfBlob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = newFilename;
                    document.body.appendChild(a);
                    a.click();

                    URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    console.log('Download triggered.');
                })
                .catch((err) => {
                    console.error("PDF generation failed:", err);
                    alert("เกิดข้อผิดพลาดในการสร้าง PDF: " + err.message + "\nกรุณาตรวจสอบ Console (กด F12) สำหรับข้อมูลเพิ่มเติม และลองใหม่อีกครั้ง");
                })
                .finally(() => {
                    // คืนค่าสไตล์และสถานะของปุ่มให้เหมือนเดิมไม่ว่าจะสำเร็จหรือล้มเหลว
                    element.style.margin = originalMargin;
                    element.style.boxShadow = originalShadow;
                    button.innerHTML = '<i class="fa-solid fa-download mr-2"></i>ดาวน์โหลดใบเสร็จ';
                    button.disabled = false;
                });
        }
    </script>
</body>

</html>