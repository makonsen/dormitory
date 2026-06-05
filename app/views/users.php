<?php
// Users view
?>
<div class="card">
    <h2>รายชื่อผู้ใช้งาน</h2>
    <p>ระบบสร้างตัวอย่างผู้ใช้งานไว้ให้เพื่อทดสอบการทำงาน</p>
    <table>
        <thead>
            <tr>
                <th>รหัส</th>
                <th>ชื่อผู้ใช้</th>
                <th>ชื่อ-นามสกุล</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($user['fullname'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
