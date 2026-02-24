<?php

require_once __DIR__ . '/main_function.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$conn = connectDB();

$tours   = getAllTours($conn);
$api     = getPartnerTourApi($conn);

$actions = compare($tours, $api);

$summary = [
    'INSERT'       => 0,
    'UPDATE'       => 0,
    'SOFT_DELETE'  => 0,
    'SKIP'         => 0,
    'LOG_SUCCESS'  => 0,
    'LOG_FAILED'   => 0
];

$validActions = ['INSERT', 'UPDATE', 'SOFT_DELETE', 'SKIP'];

$total   = count($actions);
$current = 0;

foreach ($actions as $item) {
    $current++;
    $percent = $total > 0 ? floor(($current / $total) * 100) : 100;

    try {
        // 1️⃣ normalize action
        $action = strtoupper($item['action'] ?? 'SKIP');
        if (!in_array($action, $validActions, true)) {
            $action = 'SKIP';
            $item['note'] = ($item['note'] ?? '') . ' | invalid action fallback';
        }

        // 2️⃣ log ทุก action (แม้ SKIP)
        $result = logSync($conn, $item, $action, 0);

        // 3️⃣ summary เฉพาะ action ที่เรารู้จัก
        $summary[$action]++;

        $summary['LOG_SUCCESS']++;

    } catch (Throwable $e) {
        $summary['LOG_FAILED']++;

        // 🔥 log error ไว้ด้วย (สำคัญมากตอน debug)
        logSync($conn, [
            'pid'    => $item['pid'] ?? null,
            'action' => 'ERROR',
            'note'   => $e->getMessage()
        ], 'ERROR', 1);
    }

    // SSE progress
    echo "data: " . json_encode([
        'percent' => $percent,
        'done'    => false
    ]) . "\n\n";

    ob_flush();
    flush();
    usleep(100000);
}

// ✅ ส่ง summary ตอนจบ
echo "data: " . json_encode([
    'percent' => 100,
    'done'    => true,
    'summary' => $summary
]) . "\n\n";

ob_flush();
flush();

