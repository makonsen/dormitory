<?php
// Home view
?>
<div class="card">
    <h2>ยินดีต้อนรับสู่ระบบจัดการหอพัก</h2>
    <p>ระบบพื้นฐานสำหรับจัดการข้อมูลหอพัก, ห้องพัก และผู้ใช้งาน</p>
    <div style="display:grid; gap:18px; margin-top:24px;">
        <div style="padding:18px; border-radius:14px; background:#eef2ff;">
            <strong>ผู้ใช้งานทั้งหมด:</strong> <?= count($users) ?> คน
        </div>
        <div style="padding:18px; border-radius:14px; background:#eef2ff;">
            <strong>ห้องพักทั้งหมด:</strong> <?= count($rooms) ?> ห้อง
        </div>
    </div>
</div>
<div class="card">
    <h3>คำแนะนำ</h3>
    <p>ใช้เมนูด้านบนเพื่อดูรายการห้องพัก หรือจัดการผู้ใช้งาน เมื่อยังไม่ได้เข้าสู่ระบบ ระบบจะให้ล็อกอินก่อน</p>
</div>
