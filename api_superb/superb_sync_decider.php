<?php
require_once __DIR__ . '/main_function.php';
require_once __DIR__ . '/insertTour.php';
require_once __DIR__ . '/updateTour.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = connectDB();
$conn->set_charset('utf8mb4');

/* นับทั้งหมด */
$total = $conn->query("
    SELECT COUNT(*) AS total
    FROM sync_log_superb
    WHERE is_synced = 0
      AND action_type NOT IN ('ERROR','SKIP')
      AND (miss_api_count < 4 OR miss_api_count IS NULL)
")->fetch_assoc()['total'];

$done = 0;

$sql = "
    SELECT *
    FROM sync_log_superb
    WHERE is_synced = 0
      AND action_type NOT IN ('ERROR','SKIP')
      AND (miss_api_count < 4 OR miss_api_count IS NULL)
      LIMIT 5
";

$result = $conn->query($sql);

while ($log = $result->fetch_assoc()) {

    try {
        processLog($conn, $log);
        $status = "SUCCESS";
    } catch (Throwable $e) {
        $status = "ERROR: " . $e->getMessage();
    }

    $done++;
    $percent = $total > 0 ? round(($done / $total) * 100, 2) : 100;

    /* ส่งสถานะกลับไปหน้า index */
    echo json_encode([
        'done'    => $done,
        'total'   => $total,
        'percent' => $percent,
        'tour_id' => $log['tour_id'],
        'action'  => $log['action_type'],
        'status'  => $status
    ]) . PHP_EOL;

    @ob_flush();
    @flush();

    usleep(200000); // ชะลอให้ดูทัน (0.2 วิ)
}

$conn->close();
