<?php
// cleanup_orphan_files.php
// เรียกด้วย cron job เพื่อลบไฟล์รูปและ PDF ที่ไม่มีในฐานข้อมูล (tour_online)

 
require_once __DIR__ . '/main_function.php';
$conn = connectDB();
$sql = "SELECT filebanner, filepdf FROM tour_online";
$result = $conn->query($sql);
$imgDir = realpath(__DIR__ . '/../imgtour');
$pdfDir = realpath(__DIR__ . '/../programtour');
$imgInDb = [];
$pdfInDb = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['filebanner']) $imgInDb[$row['filebanner']] = true;
        if ($row['filepdf']) $pdfInDb[$row['filepdf']] = true;
    }
}

$deletedImgs = [];
foreach (glob($imgDir . '/tour_*') as $f) {
    $fname = basename($f);
    if (!isset($imgInDb[$fname]) && is_file($f)) {
        if (@unlink($f)) $deletedImgs[] = $fname;
    }
}
$deletedPdfs = [];
foreach (glob($pdfDir . '/tour_*') as $f) {
    $fname = basename($f);
    if (!isset($pdfInDb[$fname]) && is_file($f)) {
        if (@unlink($f)) $deletedPdfs[] = $fname;
    }
}
$msg = "[" . date('Y-m-d H:i:s') . "] ลบรูปที่ไม่อยู่ในฐานข้อมูลแล้วจำนวน " . count($deletedImgs) . " ไฟล์ และ PDF " . count($deletedPdfs) . " ไฟล์\n";
file_put_contents(__DIR__ . '/cleanup_orphan_files.log', $msg, FILE_APPEND);
// สามารถสั่งรันไฟล์นี้ด้วย cron: */5 * * * * php /path/to/cleanup_orphan_files.php
