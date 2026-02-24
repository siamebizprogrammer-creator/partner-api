<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/main_function.php';
require_once __DIR__ . '/getAPI2U.php';
require_once __DIR__ . '/insertTour.php';
require_once __DIR__ . '/updateTour.php';

try {

    $conn = connectDB();

    $res = getSyncLog2U($conn);

    if (!is_array($res)) {
        error_log('getSyncLog2U failed: ' . ($conn->error ?? 'no mysqli error'));
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'getSyncLog2U failed',
            'detail'  => ($conn->error ?? 'unknown')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($res)) {
        echo json_encode([
            'status' => 'ไม่พบข้อมูล Action',
            'message' => 'getSyncLog2U ไม่พบข้อมูล',
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
            $tourUpdate = !empty($data['tour_date_update'])
                ? $data['tour_date_update']
                : $data['tour_create_date'];
            /* ================= INSERT ================= */
            if ($action === 'INSERT') {

                $detail = getDetail($tourId);
                $detail['tour_update'] = $tourUpdate;
                insertTour($conn, $detail, $tourCode);

                updateSyncLog2UFinish($conn, $tourId, $tourCode);
            }

            /* ================= UPDATE ================= */ elseif ($action === 'UPDATE') {

                $wholesale_code = '293';

                $stmt = $conn->prepare("
                SELECT code, filepdf, filebanner 
                FROM tour_online 
                WHERE tour_code = ? AND wholesale_code = ?
            ");

                $stmt->bind_param("ss", $tourId, $wholesale_code);
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
                    //delePict($row['filebanner']);
                }

                $detail = getDetail($tourId);
                $detail['tour_update'] = $tourUpdate;
                updateTour($conn, $detail, $code);

                updateSyncLog2UFinish($conn, $tourId, $tourCode);
            }

            /* ================= DELETE ================= */ elseif ($action === 'DELETE') {

                deleteTour($conn, $tourId, $tourCode);

                updateSyncLog2UFinish($conn, $tourId, $tourCode);
            } else {
                throw new Exception("Unknown action type: {$action}");
            }

            $conn->commit();
        } catch (Throwable $e) {

            $conn->rollback();

            updataSyncLog2UError(
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
