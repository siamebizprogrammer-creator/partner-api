<?php

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/main_function.php';
require_once __DIR__ . '/getAPIGo365.php';

try {

    $conn = connectDB();

    $dbTours = getTour($conn);
    $apiData = getTourAll();

    $dataTourNew = [];
    $dataTourUpdate = [];
    $dataTourDelete = [];

    $apiTourCodes = [];

    /* =============================
       1️⃣  LOOP API DATA
    ============================== */

    foreach ($apiData['data'] as $data) {

        if ($data['tour_website_id'] == 42) continue;

        $tourId   = $data['tour_id'];
        $tourCode = $data['tour_code'];
        $apiDate  = $data['tour_update']['date_update'];

        $apiTourCodes[] = $tourId;

        $result = checkTourCode($conn, $tourId);

        /* ---------- NEW ---------- */
        if (!$result) {

            insertSyncLogGo365($conn, $tourId, $tourCode, $apiDate);

            $dataTourNew[] = [
                'tour_id' => $tourId,
                'tour_code' => $tourCode
            ];

            continue;
        }

        /* ---------- UPDATE ---------- */

        $apiDateTime = new DateTime($apiDate);

        $dbDateTime = !empty($result['api_updated_at'])
            ? new DateTime($result['api_updated_at'])
            : new DateTime('1970-01-01 00:00:00');

        if ($apiDateTime > $dbDateTime) {

            updateSyncLogGo365(
                $conn,
                $tourId,
                $apiDate,
                'UPDATE',
                $tourCode
            );

            $dataTourUpdate[] = [
                'tour_id' => $tourId,
                'tour_code' => $tourCode
            ];
        }
    }

    /* =============================
       2️⃣  LOOP DB DATA (DELETE)
    ============================== */

    foreach ($dbTours as $dbTour) {

        if (!in_array($dbTour['tour_code'], $apiTourCodes)) {

            updateSyncLogGo365Delete(
                $conn,
                $dbTour['tour_code'],
                'DELETE',
                $dbTour['wscode_tour']
            );

            $dataTourDelete[] = [
                'tour_code' => $dbTour['tour_code']
            ];
        }
    }

    /* =============================
       3️⃣ RESPONSE
    ============================== */

    echo json_encode([
        'status' => 'success',
        'summary' => [
            'new_count' => count($dataTourNew),
            'update_count' => count($dataTourUpdate),
            'delete_count' => count($dataTourDelete),
        ],
        'data' => [
            'new' => $dataTourNew,
            'update' => $dataTourUpdate,
            'delete' => $dataTourDelete,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error',
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
