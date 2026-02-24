<?php

require_once __DIR__ . '/main_function.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$conn = connectDB();

/** --------------------------------
 *  Load data
 * -------------------------------- */
$tours = getAllTours($conn);
$api   = getPartnerTourApi();

$actions = compare($tours, $api);

/** --------------------------------
 *  Summary init
 * -------------------------------- */
$summary = [
    'INSERT'       => 0,
    'UPDATE'       => 0,
    'SKIP'         => 0,
    'SOLDOUT'  => 0, // soldout
    'LOG_SUCCESS'  => 0,
    'LOG_FAILED'   => 0,
];

$validActions = ['INSERT','UPDATE','SKIP','MISS_API','SOFT_INACTIVE','SOLDOUT','ERROR']; 

$total   = count($actions);
$current = 0;

/** --------------------------------
 *  Process actions
 * -------------------------------- */
foreach ($actions as $item) {

    $current++;
    $percent = $total > 0 ? floor(($current / $total) * 100) : 100;

    try {

        // 1️⃣ normalize action
        $action = strtoupper($item['action_type'] ?? 'SKIP');

        if (!in_array($action, $validActions, true)) {
            $action = 'SKIP';
            $item['note'] = ($item['note'] ?? '') . ' | invalid action fallback';
        }

        // 2️⃣ MISS_API → SOFT_DELETE (soldout)
        $logAction = ($action === 'MISS_API') ? 'SOLDOUT' : $action;

        // 3️⃣ log ทุก action
        logSync($conn, [
            'tour_id'          => $item['tour_id'],
            'tour_code'        => $item['tour_code'],
            'wscode_tour'      => $item['wscode_tour'],
            'api_updated_at'   => $item['api_updated_at'],
            'local_updated_at' => $item['local_updated_at'],
            'data_json'        => $item['data_json'],
            'note'             => $item['note'] ?? null
        ], $logAction, 0);

        // 4️⃣ summary
        if ($logAction === 'SOLDOUT') {
            $summary['SOLDOUT']++;
        } else {
            $summary[$logAction]++;
        }

        $summary['LOG_SUCCESS']++;

    } catch (Throwable $e) {

        $summary['LOG_FAILED']++;

        // 🔥 log error
        logSync($conn, [
            'tour_id'   => $item['tour_id'] ?? null,
            'tour_code' => $item['tour_code'] ?? null,
            'note'      => $e->getMessage()
        ], 'ERROR', 1);
    }

    // 5️⃣ SSE progress
    echo "data: " . json_encode([
        'percent' => $percent,
        'done'    => false
    ]) . "\n\n";

    ob_flush();
    flush();
    usleep(100000);
}

/** --------------------------------
 *  Finish
 * -------------------------------- */
echo "data: " . json_encode([
    'percent' => 100,
    'done'    => true,
    'summary' => $summary
]) . "\n\n";

ob_flush();
flush();
