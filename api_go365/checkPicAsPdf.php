<?php
// checkPicasPdf.php
// ตรวจสอบไฟล์รูปและ PDF ในฐานข้อมูลกับไฟล์จริงในโฟลเดอร์

require_once __DIR__ . '/main_function.php';

$conn = connectDB();
$sql = "SELECT code, tour_code, filebanner, filepdf FROM tour_online";
$result = $conn->query($sql);

$imgDir = realpath(__DIR__ . '/../imgtour');
$pdfDir = realpath(__DIR__ . '/../programtour');

$rows = [];
$totalImgSize = 0;
$totalPdfSize = 0;
$imgInDb = [];
$pdfInDb = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $imgFile = $row['filebanner'] ? $imgDir . DIRECTORY_SEPARATOR . $row['filebanner'] : null;
        $pdfFile = $row['filepdf'] ? $pdfDir . DIRECTORY_SEPARATOR . $row['filepdf'] : null;
        if ($row['filebanner']) $imgInDb[$row['filebanner']] = true;
        if ($row['filepdf']) $pdfInDb[$row['filepdf']] = true;
        $row['img_exists'] = ($imgFile && file_exists($imgFile)) ? '✔️' : '❌';
        $row['pdf_exists'] = ($pdfFile && file_exists($pdfFile)) ? '✔️' : '❌';
        if ($row['img_exists'] === '✔️') {
            $row['img_size'] = filesize($imgFile);
            $totalImgSize += $row['img_size'];
        } else {
            $row['img_size'] = 0;
        }
        if ($row['pdf_exists'] === '✔️') {
            $row['pdf_size'] = filesize($pdfFile);
            $totalPdfSize += $row['pdf_size'];
        } else {
            $row['pdf_size'] = 0;
        }
        $rows[] = $row;
    }
}
$conn->close();
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
// หาไฟล์ที่ขึ้นต้นด้วย tour_ ในโฟลเดอร์แต่ไม่มีในฐานข้อมูล
$orphanImgs = [];
$orphanImgsSize = 0;
foreach (glob($imgDir . '/tour_*') as $f) {
    $fname = basename($f);
    if (!isset($imgInDb[$fname]) && is_file($f)) {
        $orphanImgs[] = [
            'name' => $fname,
            'size' => filesize($f),
            'modified' => date('Y-m-d H:i:s', filemtime($f))
        ];
        $orphanImgsSize += filesize($f);
    }
}
$orphanPdfs = [];
$orphanPdfsSize = 0;
foreach (glob($pdfDir . '/tour_*') as $f) {
    $fname = basename($f);
    if (!isset($pdfInDb[$fname]) && is_file($f)) {
        $orphanPdfs[] = [
            'name' => $fname,
            'size' => filesize($f),
            'modified' => date('Y-m-d H:i:s', filemtime($f))
        ];
        $orphanPdfsSize += filesize($f);
    }
}
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจสอบไฟล์รูปและ PDF ใน tour_online</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #f7f7f7; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        th { background: #e0e0e0; }
        tr:nth-child(even) { background: #f0f0f0; }
        .ok { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
    </style>
</head>
<body>
<h2>ตรวจสอบไฟล์รูปและ PDF ในฐานข้อมูล (tour_online)</h2>
<p><b>ขนาดไฟล์รูปทั้งหมด:</b> <?= formatSize($totalImgSize) ?> &nbsp; | &nbsp; <b>ขนาดไฟล์ PDF ทั้งหมด:</b> <?= formatSize($totalPdfSize) ?></p>
<table>
    <tr>
        <th>code</th>
        <th>tour_code</th>
        <th>filebanner</th>
        <th>รูปมีจริง?</th>
        <th>ขนาดรูป</th>
        <th>filepdf</th>
        <th>PDF มีจริง?</th>
        <th>ขนาด PDF</th>
    </tr>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td><?= htmlspecialchars($r['code'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['tour_code'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['filebanner'] ?? '') ?></td>
        <td class="<?= $r['img_exists'] === '✔️' ? 'ok' : 'fail' ?>"><?= $r['img_exists'] ?></td>
        <td><?= $r['img_exists'] === '✔️' ? formatSize($r['img_size']) : '-' ?></td>
        <td><?= htmlspecialchars($r['filepdf'] ?? '') ?></td>
        <td class="<?= $r['pdf_exists'] === '✔️' ? 'ok' : 'fail' ?>"><?= $r['pdf_exists'] ?></td>
        <td><?= $r['pdf_exists'] === '✔️' ? formatSize($r['pdf_size']) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<br>
<h3>ไฟล์รูปที่ขึ้นต้นด้วย tour_ แต่ไม่มีในฐานข้อมูล (<?= count($orphanImgs) ?> ไฟล์, รวม <?= formatSize($orphanImgsSize) ?>)</h3>
<ul>
    <?php foreach ($orphanImgs as $f): ?>
    <li><?= htmlspecialchars($f['name']) ?> (<?= formatSize($f['size']) ?>) - แก้ไขล่าสุด: <?= htmlspecialchars($f['modified']) ?></li>
    <?php endforeach; ?>
</ul>
<h3>ไฟล์ PDF ที่ขึ้นต้นด้วย tour_ แต่ไม่มีในฐานข้อมูล (<?= count($orphanPdfs) ?> ไฟล์, รวม <?= formatSize($orphanPdfsSize) ?>)</h3>
<ul>
    <?php foreach ($orphanPdfs as $f): ?>
    <li><?= htmlspecialchars($f['name']) ?> (<?= formatSize($f['size']) ?>) - แก้ไขล่าสุด: <?= htmlspecialchars($f['modified']) ?></li>
    <?php endforeach; ?>
</ul>
</body>
</html>
