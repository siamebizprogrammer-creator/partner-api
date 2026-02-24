<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/main_function.php';
require_once __DIR__ . '/getAPIGo365.php';
require_once __DIR__ . '/insertTour.php';
require_once __DIR__ . '/updateTour.php';

try {

    $conn = connectDB();

    $res = getSyncLogGo365($conn);

    if (!is_array($res)) {
        error_log('getSyncLogGo365 failed: ' . ($conn->error ?? 'no mysqli error'));
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'getSyncLogGo365 failed',
            'detail'  => ($conn->error ?? 'unknown')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($res)) {
        echo json_encode([
            'status' => 'ไม่พบข้อมูล Action',
            'message' => 'getSyncLogGo365 ไม่พบข้อมูล',
            'detail'  => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach ($res as $data) {

    $conn->begin_transaction();

    try {

        $action = $data['action_type'];
        $tourId = strval($data['tour_id']);
        $tourCode = $data['tour_code'];
        $tourUpdate = !empty($data['tour_create_date']) 
        ? $data['tour_create_date'] 
        : $data['tour_date_update'];
        /* ================= INSERT ================= */
        if ($action === 'INSERT') {

            $detail = getDetail($tourId);
            $detail['tour_update'] = $tourUpdate;
            insertTour($conn, $detail, $tourCode);

            updateSyncLogGo365Finish($conn, $tourId, $tourCode);
        }

        /* ================= UPDATE ================= */
        elseif ($action === 'UPDATE') {

            $wholesale_code = '14';
            $userCreate = 'API GO 365';
            $stmt = $conn->prepare("
                SELECT code, filepdf, filebanner 
                FROM tour_online 
                WHERE tour_code = ? AND wholesale_code = ? AND user_create = ?
            ");

            $stmt->bind_param("sss", $tourId, $wholesale_code, $userCreate);
            $stmt->execute();
            $resultDb = $stmt->get_result();

            if (!$row = $resultDb->fetch_assoc()) {
                throw new Exception("Tour not found for update");
            }

            $code = $row['code'];

            if (!empty($row['filepdf'])) {
                delePDF($row['filepdf']);
            }

            if (!empty($row['filebanner'])) {
                delePict($row['filebanner']);
            }

            $detail = getDetail($tourId);
            $detail['tour_update'] = $tourUpdate;
            updateTour($conn, $detail, $code);

            updateSyncLogGo365Finish($conn, $tourId, $tourCode);
        }

        /* ================= DELETE ================= */
        elseif ($action === 'DELETE') {

            deleteTour($conn, $tourId, $tourCode);

            updateSyncLogGo365Finish($conn, $tourId, $tourCode);
        }

        else {
            throw new Exception("Unknown action type: {$action}");
        }

        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        updataSyncLogGo365Error(
            $conn,
            $data['tour_id'],
            $data['tour_code'],
            $e->getMessage()
        );

        error_log($e->getMessage());
    }
}


    //var_dump($arr);
} catch (Throwable $e) {

    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal Server Error',
        'detail'  => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
