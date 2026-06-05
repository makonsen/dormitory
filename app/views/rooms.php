<?php
// Rooms view
?>
<div class="card">
    <h2>รายการห้องพัก</h2>
    <table>
        <thead>
            <tr>
                <th>หมายเลขห้อง</th>
                <th>ประเภท</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rooms as $room): ?>
                <tr>
                    <td><?= htmlspecialchars($room['number'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($room['type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($room['status'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
