<?php

ob_start();
set_time_limit(0);
date_default_timezone_set("Asia/Bangkok");
ini_set('memory_limit', '512M');
function connectDB(): mysqli
{
    // saimebiz1
    //$host = 'localhost';
    //$user = 'siamebiz_prod';
    //$password = '8x9j7y?1K';
    //$database = 'siamebiz1_db';
    //$port = '3306';

    // teawnaidee
    // $host = 'localhost';
    // $user = 'teawnaid_db';
    // $password = '557Fhap$6';
    // $database = 'teawnaid_db';

    // test localhost
    $host = 'host.docker.internal';
    $user = 'root';
    $password = 'root';
    $database = 'siamebiz1_db';
    $port = '3307';

    $conn = new mysqli($host, $user, $password, $database, $port);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

function DateNow()
{
    return date('Y-m-d H:i:s');
}

function getAllTours(mysqli $conn): array
{
    $sql = "
        SELECT 
            code AS tour_id,
            tour_code,
            wscode_tour,
            wholesale_code,
            api_updated_at AS local_updated_at
        FROM tour_online
        WHERE wholesale_code = 266 
        AND  user_create = 'API TTN Plus'
    ";

    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception('Query failed: ' . $conn->error);
    }

    $tours = [];

    while ($row = $result->fetch_assoc()) {
        $tours[] = $row;
    }

    return $tours;
}

function getPartnerTourApi(): array
{
    $url = 'https://www.ttnplus.co.th/api/program';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception('API error: ' . curl_error($ch));
    }

    curl_close($ch);

    $json = json_decode($response, true);

    if (!is_array($json)) {
        throw new Exception('Invalid API response');
    }

    $result = [];



    foreach ($json as $program) {

        if (empty($program['P_CODE']) || empty($program['updated_at'])) {
            continue;
        }

        $tourCode = $program['P_CODE'];

        // 1️⃣ เริ่มจาก updated_at ของ program
        $latestUpdatedAt = null;

        // 2️⃣ เช็ค period ที่อยู่ใน program
        if (!empty($program['period']) && is_array($program['period'])) {

            usort($program['period'], function ($a, $b) {
                $aEnd = strtotime($a['P_DUE_END'] ?? '');
                $bEnd = strtotime($b['P_DUE_END'] ?? '');

                if ($aEnd === false) $aEnd = PHP_INT_MAX;
                if ($bEnd === false) $bEnd = PHP_INT_MAX;

                $cmp = $aEnd <=> $bEnd;
                if ($cmp !== 0) return $cmp;

                $aStart = strtotime($a['P_DUE_START'] ?? '');
                $bStart = strtotime($b['P_DUE_START'] ?? '');

                if ($aStart === false) $aStart = PHP_INT_MAX;
                if ($bStart === false) $bStart = PHP_INT_MAX;

                return $aStart <=> $bStart;
            });

            foreach ($program['period'] as $period) {
                if (!empty($period['updated_at'])) {
                    $pTime = new DateTime($period['updated_at']);

                    if ($latestUpdatedAt === null || $pTime > $latestUpdatedAt) {
                        $latestUpdatedAt = $pTime;
                    }
                }
            }
        }

        if ($latestUpdatedAt < new DateTime('-1 year')) {
            continue;
        }

        $tourCode = $program['P_CODE'];
        $result[] = [
            'tour_code'      => $program['P_ID'] ?? null,
            'wscode_tour'    => $tourCode,
            'api_updated_at' => $latestUpdatedAt->format('Y-m-d H:i:s'),
            'data_json'      => $program,
        ];
    }


    return $result;
}

function compare(array $dbTours, array $apiTours): array
{
    $actions = [];

    // --- index db tours ด้วย wscode_tour (P_CODE) ---
    $dbIndex = [];
    foreach ($dbTours as $row) {
        $dbIndex[$row['tour_code']][$row['wholesale_code']] = $row;
    }

    // --- index api tours ด้วย wscode_tour (P_CODE) + tour_code (P_ID) ---
    $apiIndex = [];
    foreach ($apiTours as $api) {
        $apiIndex[$api['tour_code']]['266'] = true;
    }

    // --- 1) loop api ---
    foreach ($apiTours as $api) {

        $pCode = $api['wscode_tour'];
        $pId   = $api['tour_code'];

        // api มี แต่ db ไม่มี → INSERT
        if (!isset($dbIndex[$pId]['266'])) {

            $actions[] = [
                'action_type'       => 'INSERT',
                'tour_id'           => null,
                'tour_code'         => $pId,                       // P_ID
                'wscode_tour'       => $pCode,                     // P_CODE
                'api_updated_at'    => $api['api_updated_at'],
                'local_updated_at'  => null,
                'data_json'         => $api['data_json'],
                'note'              => 'API exists but local not found'
            ];

            continue;
        }

        // --- db มี / api มี ---
        $db = $dbIndex[$pId][266];

        // safety check: P_ID ต้องตรง
        if ((string)$db['tour_code'] !== (string)$pId) {
            // กรณี code mismatch (log ไว้ก่อน)
            $actions[] = [
                'action_type'       => 'SKIP',
                'tour_id'           => $db['tour_id'],
                'tour_code'         => $db['tour_code'],
                'wscode_tour'       => $pCode,
                'api_updated_at'    => $api['api_updated_at'],
                'local_updated_at'  => $db['local_updated_at'],
                'data_json'         => $api['data_json'],
                'note'              => 'P_ID mismatch'
            ];
            continue;
        }

        $apiTime   = strtotime($api['api_updated_at']);
        $localTime = strtotime($db['local_updated_at']);

        // api ใหม่กว่า → UPDATE
        if ($apiTime > $localTime) {

            $actions[] = [
                'action_type'       => 'UPDATE',
                'tour_id'           => $db['tour_id'],
                'tour_code'         => $db['tour_code'],
                'wscode_tour'       => $pCode,
                'api_updated_at'    => $api['api_updated_at'],
                'local_updated_at'  => $db['local_updated_at'],
                'data_json'         => $api['data_json'],
                'note'              => 'API updated'
            ];
        } else {
            // เวลาเท่ากันหรือ local ใหม่กว่า → SKIP
            $actions[] = [
                'action_type'       => 'SKIP',
                'tour_id'           => $db['tour_id'],
                'tour_code'         => $db['tour_code'],
                'wscode_tour'       => $pCode,
                'api_updated_at'    => $api['api_updated_at'],
                'local_updated_at'  => $db['local_updated_at'],
                'data_json'         => null,
                'note'              => 'No change'
            ];
        }
    }

    // --- 2) db มี แต่ api ไม่มี → MISS_API (sold out) ---
    // --- 2) db มี แต่ api ไม่มี → MISS_API (sold out) ---
    foreach ($dbIndex as $pCode => $dbById) {

        foreach ($dbById as $pId => $dbRow) {

            // api ไม่มี P_CODE นี้ หรือมี P_CODE แต่ไม่มี P_ID นี้
            if (
                !isset($apiIndex[$pCode]) ||
                !isset($apiIndex[$pCode][$pId])
            ) {

                $actions[] = [
                    'action_type'       => 'MISS_API',
                    'tour_id'           => $dbRow['tour_id'],
                    'tour_code'         => $dbRow['tour_code'], // P_ID
                    'wscode_tour'       => $dbRow['wscode_tour'],               // P_CODE
                    'api_updated_at'    => null,
                    'local_updated_at'  => $dbRow['local_updated_at'],
                    'data_json'         => null,
                    'note'              => 'Not found in API (sold out)'
                ];
            }
        }
    }

    return $actions;
}

function logSync(mysqli $conn, array $data, string $actionType, int $isFail = 0): bool
{
    /* ---------- normalize input ---------- */
    $tourCode        = $data['tour_code'] ?? null;
    if (!$tourCode) {
        throw new Exception('tour_code is required for logSync');
    }

    $tourId          = $data['tour_id'] ?? null;
    $wscodeTour      = $data['wscode_tour'] ?? null;
    $apiUpdatedAt    = $data['api_updated_at'] ?? null;
    $localUpdatedAt  = $data['local_updated_at'] ?? null;
    $createdAt       = DateNow();
    $note            = $data['note'] ?? null;
    $isSynced        = 0;

    $json = null;
    if (isset($data['data_json'])) {
        $json = json_encode(
            $data['data_json'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /* ---------- check existing log ---------- */
    $checkSql = "
        SELECT id, miss_api_count, action_type, is_synced
        FROM sync_log_TTNplus
        WHERE tour_code = ?
          AND wscode_tour = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('ss', $tourCode, $wscodeTour);
    $stmt->execute();
    $exist = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    /* ---------- miss_api_count ---------- */
    $missApiCount = 0;
    if ($actionType === 'SOFT_INACTIVE') {
        $missApiCount = ($exist['miss_api_count'] ?? 0) + 1;
    }

    /* ---------- UPDATE ---------- */
    if ($exist) {

        if (
            ($exist['action_type'] ?? '') === $actionType
            &&
            (int)($exist['is_synced'] ?? 0) === 1
        ) {
            $isSynced = 1;
        }
        $sql = "
            UPDATE sync_log_TTNplus SET
                tour_id = ?,
                is_synced = ?,
                action_type = ?,
                miss_api_count = ?,
                api_updated_at = ?,
                local_updated_at = ?,
                note = ?,
                data_json = ?,
                created_at = ?
            WHERE id = ?
        ";

        $stmt = $conn->prepare($sql);

        $logId = (int)$exist['id'];

        $stmt->bind_param(
            'iisisssssi',
            $tourId,
            $isSynced,
            $actionType,
            $missApiCount,
            $apiUpdatedAt,
            $localUpdatedAt,
            $note,
            $json,
            $createdAt,
            $logId
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /* ---------- INSERT ---------- */
    $sql = "
        INSERT INTO sync_log_TTNplus
        (
            tour_id,
            tour_code,
            wscode_tour,
            tour_create_date,
            is_synced,
            action_type,
            miss_api_count,
            api_updated_at,
            local_updated_at,
            note,
            data_json
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ";

    $stmt = $conn->prepare($sql);

    $tourCreateDate = $localUpdatedAt;

    $stmt->bind_param(
        'isssisissss',
        $tourId,
        $tourCode,
        $wscodeTour,
        $tourCreateDate,
        $isSynced,
        $actionType,
        $missApiCount,
        $apiUpdatedAt,
        $localUpdatedAt,
        $note,
        $json
    );

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function processLog(mysqli $conn, array $log): void
{

    if (empty($log['action_type'])) {
        throw new Exception('Missing action_type');
    }

    $action = strtoupper($log['action_type']);

    // แปลง data_json
    // if (empty($log['data_json'])) {
    //     throw new Exception('data_json is empty');
    // }

    $apiData = json_decode($log['data_json'] ?? '', true) ?? [];

    if (!is_array($apiData)) {
        throw new Exception('Invalid data_json: not array');
    }

    $data = parseTourApiData($apiData);
    switch ($action) {

        case 'INSERT':

            if (tourExists($conn, $log)) {
                markLogDone(
                    $conn,
                    $log['id'],
                    'SKIP: tour already exists (tour_online)'
                );
                break;
            }
            $data['updated_at'] = $log['api_updated_at'];
            $tourId = insertTour($conn, $data);
            markLogDone(
                $conn,
                $log['id'],
                'INSERT success',
                [
                    'tour_id'   => $tourId,
                    'is_synced' => 1
                ]
            );
            break;

        case 'UPDATE':

            $tour_code = $log['tour_code'];
            $wholesale_code = '266';

            $code = null;
            $pdf  = null;
            $pict = null;

            $stmt = $conn->prepare("
                    SELECT code, filepdf, filebanner
                    FROM tour_online
                    WHERE tour_code = ? AND wholesale_code = ? AND user_create = 'API TTN Plus'
                ");
            $stmt->bind_param("ss", $tour_code, $wholesale_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $code = $row['code'];
                $pdf  = $row['filepdf'];
                $pict = $row['filebanner'];
            }
            $stmt->close();

            $conn->begin_transaction();

            try {

                if (!$code) {
                    throw new Exception('Tour not found');
                }

                if ($pdf) delePDF($pdf);
                if ($pict) delePict($pict);
                $data['updated_at'] = $log['api_updated_at'];
                updateTour($conn, $data, $code);


                markLogDone(
                    $conn,
                    $log['id'],
                    'UPDATE success',
                    ['is_synced' => 1]
                );
                $conn->commit();
            } catch (Exception $e) {

                $conn->rollback();
                markLogDone($conn, $log['id'], 'UPDATE failed: ' . $e->getMessage());
            }
            //updateTour($conn, $apiData);
            break;

        case 'SOLDOUT':

            if (empty($log['tour_id'])) {
                throw new Exception('SOLDOUT requires tour_id');
            }

            $tour_code = $log['tour_code'];
            $wholesale_code = '266';

            $stmt = $conn->prepare("
                UPDATE tour_online
                SET soldout = 'Y'
                WHERE tour_code = ? AND wholesale_code = ? AND user_update = 'API TTN Plus'
            ");

            $stmt->bind_param("ss", $tour_code, $wholesale_code);
            $stmt->execute();

            break;

        case 'SKIP':
            // ไม่ต้องทำอะไร
            break;

        case 'MISS_API':
            // decider จัดการ miss_api_count แล้ว
            break;

        default:
            throw new Exception('Unsupported action_type: ' . $action);
    }
}


function parseTourApiData(array $apiData): array
{
    /* =======================
     * TOUR (ข้อมูลหลัก)
     * ======================= */
    $tour = [
        'tour_id'            => $apiData['P_ID'] ?? null,
        'tour_code'          => $apiData['P_CODE'] ?? null,
        'country_code'       => $apiData['P_LOCATION_CODE'] ?? null,
        'airline_iata'       => $apiData['P_AIRLINE_CODE'] ?? 'PKG',
        'tour_name'          => $apiData['P_NAME'] ?? null,
        'periodtime'         => ($apiData['P_DAY'] ?? 0) . 'วัน ' .  ($apiData['P_NIGHT'] ?? 0) . 'คืน',
        'tour_price'         => $apiData['P_PRICE'] ?? null,
        'airline'            => $apiData['P_AIRLINE'] ?? null,
        'airline_code'       => $apiData['P_AIRLINE_CODE'] ?? 'PKG',
        'location'           => $apiData['P_LOCATION'] ?? null,
        'location_code'      => $apiData['P_LOCATION_CODE'] ?? null,
        'pdf_url'            => $apiData['pdf_url'] ?? null,
        'file_url'           => $apiData['file_url'] ?? null,
        'banner_url'         => $apiData['banner_url'] ?? null,
        'updated_at'         => $apiData['updated_at'] ?? null,
    ];

    /* =======================
     * PERIOD (หลายช่วงเดินทาง)
     * ======================= */
    $periods = [];

    if (!empty($apiData['period']) && is_array($apiData['period'])) {
        foreach ($apiData['period'] as $p) {
            $periods[] = [
                'period_id'        => $p['P_ID'] ?? null,
                'tour_id'          => $p['P_PCODE'] ?? null,
                'code_group'       => $p['P_CODEGROUP'] ?? null,
                'due_text'         => $p['P_DUE'] ?? null,
                'due_start'        => $p['P_DUE_START'] ?? null,
                'due_end'          => $p['P_DUE_END'] ?? null,
                'price_adult'      => $p['P_ADULT'] ?? null,
                'price_child'      => $p['P_CHILDPRICE'] ?? null,
                'price_infant'     => $p['P_INFANT'] ?? null,
                'price_single'     => $p['P_SINGLE'] ?? null,
                'commission'       => $p['P_COMIMISSION'] ?? null,
                'volume'           => $p['P_VOLUME'] ?? null,
                'booking'          => $p['P_BOOKING'] ?? null,
                'available'        => $p['P_AVAILABLE'] ?? null,
                'status'           => $p['P_status'] ?? null,
                'updated_at'       => $p['updated_at'] ?? null,
            ];
        }
    }

    /* =======================
     * DETAIL DAY (กำหนดการรายวัน)
     * ======================= */
    $detailDays = [];

    if (!empty($apiData['detail']) && is_array($apiData['detail'])) {
        foreach ($apiData['detail'] as $d) {
            $detailDays[] = [
                'detail_id'        => $d['D_ID'] ?? null,
                'tour_id'          => $d['D_PID'] ?? null,
                'day_no'           => $d['D_DAY'] ?? null,
                'itinerary'        => $d['D_ITIN'] ?? null,
                'updated_at'       => $d['updated_at'] ?? null,
            ];
        }
    }

    return [
        'tour'      => $tour,
        'period'    => $periods,
        'detailDay' => $detailDays,
    ];
}

// เป็น function เอาไว้สร้างโค้ด โปรแกรมทัวร์
function genTourCode(mysqli $conn, string $idCountry, string $idAirline): string
{
    $text = $idCountry . '_' . $idAirline;

    // ดึง tour_code ล่าสุด
    $sql = "SELECT MAX(tourcode) AS max_code FROM tour_online WHERE tourcode LIKE '{$text}%'";
    $result = mysqli_query($conn, $sql);

    $next_number = 1; // เริ่มต้นที่ 1

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if (!empty($row['max_code'])) {
            // แยกเลข 5 หลักจากรหัสเดิม
            $last_code = $row['max_code'];
            $last_number = (int)substr($last_code, -5); // ดึงเลขท้าย
            $next_number = $last_number + 1;
        }
    }

    // สร้างรหัสใหม่
    $new_code = $text . str_pad($next_number, 5, '0', STR_PAD_LEFT);

    return $new_code;
}

// เป็น function ที่หาโค้ดประเทศ และส่ง country code กับ zone code กลับไป
function getCountryCode(mysqli $conn, string $countryCode): array
{

    $countryCode = $conn->real_escape_string($countryCode);
    $sql = "SELECT country_code, zone_code FROM country WHERE shortcode = '$countryCode'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return ['country_code' => '', 'zone_code' => ''];
}

// เป็น function ที่หา id airline และส่งกลับไป
function getAirlineCode(mysqli $conn, string $airlineCode): string
{

    $airlineCode = $conn->real_escape_string($airlineCode);
    $sql = "SELECT airline_id FROM airline WHERE airline_code = '$airlineCode'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['airline_id'];
    }
    return '';
}


// เกี่ยวกับ รูป และ pdf
function getGoogleDriveDirectDownload(string $url): string|false
{
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
        return "https://drive.google.com/uc?export=download&id=$fileId";
    }
    return false;
}

function getGoogleDocsPDFDownloadUrl(string $url): string|false
{
    if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
        return "https://docs.google.com/document/d/$fileId/export?format=pdf";
    }
    return false;
}

/**
 * ถ้า $finalUrl เป็น Google Drive /file/d/<id>/view
 * จะคืน direct-download URL candidates สำหรับรีเควสต์ซ้ำ
 * ไม่ใช่เคส Drive ให้คืน [] (ไม่ทำอะไร)
 */
function buildDirectPdfUrlsFromShortcut(string $finalUrl): array
{
    if (stripos($finalUrl, 'drive.google.com/file/d/') === false) {
        return [];
    }
    if (!preg_match('#/file/d/([A-Za-z0-9_\-]+)#', $finalUrl, $m)) {
        return [];
    }
    $id = $m[1];

    // จัดอันดับลองยิง: usercontent → uc?export=download
    return [
        "https://drive.usercontent.google.com/uc?id={$id}&export=download",
        "https://drive.google.com/uc?export=download&id={$id}",
    ];
}

/** ยิง cURL ง่าย ๆ (ตาม header เดิมของคุณ) แล้วคืน [code, ctype, final_url, body] */
function simpleFetch(string $url, ?string $referer = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/pdf,application/octet-stream;q=0.9,*/*;q=0.8',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
    ];
    if ($referer) $headers[] = 'Referer: ' . $referer;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
    ]);
    $body     = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype    = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
    $eff      = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;

    return [$code, $ctype, $eff, $body];
}

/** เช็คเร็ว ๆ ว่าเป็น PDF ไหม (content-type หรือขึ้นต้น %PDF-) */
function isPdfResponse(string $ctype, string $body, string $finalUrl = ''): bool
{
    $ct = strtolower($ctype);
    if (str_contains($ct, 'application/pdf')) return true;
    if ($body !== '' && strncmp($body, "%PDF-", 5) === 0) return true;
    $path = strtolower(parse_url($finalUrl, PHP_URL_PATH) ?? '');
    if ($path && str_ends_with($path, '.pdf')) return true;
    return false;
}

// เป็น function ตรวจสอบ ภาษาไทยก็ส่งไปหา ข้อมูล pdf
function normalizeUrl(string $url): string
{
    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'])) {
        return $url;
    }

    $path = $parts['path'] ?? '';
    $path = implode('/', array_map('rawurlencode', explode('/', $path)));

    return ($parts['scheme'] ?? 'https') . '://' .
        $parts['host'] .
        $path .
        (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function uploadTourPDF(mysqli $conn, string $filePathOrUrl, int $tourId): void
{
    $uploadDir = rtrim(__DIR__ . '/../programtour', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName   = 'tour' . '_' . $tourId . '.pdf';
    $uploadFile = $uploadDir . $fileName;

    // เช็ค Google Drive
    if (strpos($filePathOrUrl, 'drive.google.com') !== false) {
        $directUrl = getGoogleDriveDirectDownload($filePathOrUrl);
        if ($directUrl) {
            $filePathOrUrl = $directUrl;
        }
    }
    // เช็ค Google Docs
    if (strpos($filePathOrUrl, 'docs.google.com/document') !== false) {
        $directUrl = getGoogleDocsPDFDownloadUrl($filePathOrUrl);
        if ($directUrl) {
            $filePathOrUrl = $directUrl;
        }
    }

    if (filter_var($filePathOrUrl, FILTER_VALIDATE_URL)) {
        $ch = curl_init($filePathOrUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/pdf,application/octet-stream;q=0.9,*/*;q=0.8',
        ]);

        $fileContent = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        $finalUrl    = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $filePathOrUrl;

        // 👉 PATCH: ถ้าไม่ใช่ PDF และ $finalUrl คือหน้า Drive view → สร้าง direct URLs แล้วลองยิงซ้ำ
        if (!($httpCode === 200 && isPdfResponse($contentType, (string)$fileContent, $finalUrl))) {
            $candidates = buildDirectPdfUrlsFromShortcut($finalUrl);
            foreach ($candidates as $directUrl) {
                [$c2, $ct2, $eff2, $body2] = simpleFetch($directUrl, $finalUrl);
                if ($c2 === 200 && isPdfResponse($ct2, (string)$body2, $eff2)) {
                    // ใช้ผลลัพธ์ใหม่นี้แทน
                    $httpCode    = $c2;
                    $contentType = $ct2;
                    $finalUrl    = $eff2;
                    $fileContent = $body2;
                    break;
                }
            }
        }

        if ($httpCode !== 200 || !isPdfResponse($contentType, (string)$fileContent, $finalUrl)) {
            // บันทึกปัญหาใน note แล้ว return (ไม่ throw)
            $errorNote = json_encode([
                'note'        => '2',
                'error'       => 'pdf_download_failed',
                'timestamp'   => date('Y-m-d H:i:s'),
                'url'         => $filePathOrUrl,
                'http_code'   => $httpCode,
                'content_type' => $contentType,
                'finalUrl'    => $finalUrl
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $sql = "UPDATE tour_online 
                SET note = '" . $conn->real_escape_string($errorNote) . "'
                WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
            $conn->query($sql);
            return; // จบ function แต่ไม่ throw error
        }

        file_put_contents($uploadFile, $fileContent);
    } elseif (file_exists($filePathOrUrl)) {
        if (!copy($filePathOrUrl, $uploadFile)) {
            // บันทึกปัญหา copy file
            $errorNote = json_encode([
                'note' => '2',
                'error' => 'pdf_copy_failed',
                'timestamp' => date('Y-m-d H:i:s'),
                'source_file' => $filePathOrUrl
            ]);

            $sql = "UPDATE tour_online 
            SET note = '" . $conn->real_escape_string($errorNote) . "'
            WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
            $conn->query($sql);
            return; // จบ function แต่ไม่ throw
        }
    } else {
        // บันทึกปัญหา file not found
        $errorNote = json_encode([
            'note' => '2',
            'error' => 'pdf_file_not_found',
            'timestamp' => date('Y-m-d H:i:s'),
            'file_path' => $filePathOrUrl
        ]);

        $sql = "UPDATE tour_online 
            SET note = '" . $conn->real_escape_string($errorNote) . "'
            WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
        $conn->query($sql);
        return; // จบ function แต่ไม่ throw
    }

    // ตรวจสอบว่าไฟล์ถูกสร้างขึ้นจริงและมีขนาด > 0
    if (!file_exists($uploadFile) || filesize($uploadFile) === 0) {
        // บันทึกปัญหา file creation
        $errorNote = json_encode([
            'note' => '2',
            'error' => 'pdf_creation_failed',
            'timestamp' => date('Y-m-d H:i:s'),
            'upload_file' => $uploadFile,
            'file_size' => file_exists($uploadFile) ? filesize($uploadFile) : 'not_exists'
        ]);

        $sql = "UPDATE tour_online 
            SET note = '" . $conn->real_escape_string($errorNote) . "'
            WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
        $conn->query($sql);
        return; // จบ function แต่ไม่ throw
    }

    // บันทึกชื่อไฟล์ + hash ลงฐานข้อมูล เฉพาะกรณีที่ upload สำเร็จ 100%
    $pdfMd5 = md5_file($uploadFile);
    if ($pdfMd5 === false) {
        error_log("uploadTourPDF: md5_file failed {$uploadFile}");
        return;
    }

    $stmt = $conn->prepare("UPDATE tour_online SET filepdf = ?, hash_pdf = ?, note = '' WHERE code = ?");
    if (!$stmt) {
        error_log("uploadTourPDF: prepare failed " . $conn->error);
        return;
    }
    $stmt->bind_param("ssi", $fileName, $pdfMd5, $tourId);
    if (!$stmt->execute()) {
        // แม้ update DB ไม่สำเร็จ ก็แค่บันทึกปัญหา ไม่ throw
        error_log("Failed to update tour_online for tourId: $tourId - " . $stmt->error);
    }
    $stmt->close();
}

/**
 * Download an image and save it to ../imgtour/, then update DB record.
 *
 * @param mysqli $conn
 * @param string $url
 * @param int    $tourId
 * @return bool
 */

function uploadTourPicture(mysqli $conn, string $url, int $tourId): bool
{
    if (empty($url)) {
        error_log("uploadTourPicture: empty url for tourId={$tourId}");
        return false;
    }

    ini_set('memory_limit', '256M');
    /* ---------- FETCH IMAGE ---------- */
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MyApp/1.0; +https://www.siamebiz1.com)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $bin = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($bin === false || $httpCode < 200 || $httpCode >= 300) {
        error_log("uploadTourPicture: curl failed url={$url} http={$httpCode} err={$curlErr}");
        return false;
    }

    if (stripos($contentType, 'image/') !== 0) {
        error_log("uploadTourPicture: not image content-type={$contentType} url={$url}");
        return false;
    }

    /* ---------- HASH (SOURCE) ---------- */
    $md5Hash = md5($bin);
    $enableHashCheck = false;
    // ถ้า hash ตรงกับของเดิม -> ไม่ต้องแปลง/อัปเดตไฟล์
    $stmt = $conn->prepare("SELECT hash_img FROM tour_online WHERE code = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $stmt->bind_result($existingHash);
        $stmt->fetch();
        $stmt->close();

        if (!empty($existingHash) && $existingHash === $md5Hash) {
            if ($enableHashCheck) {
                return true;
            }
        }
    }

    /* ---------- CREATE IMAGE ---------- */
    $image = imagecreatefromstring($bin);
    if ($image === false) {
        error_log("uploadTourPicture: imagecreatefromstring failed url={$url}");
        return false;
    }

    // ⭐ คืน memory raw binary ทันที
    unset($bin);
    gc_collect_cycles();

    /* ---------- HARD LIMIT DIMENSION (สำคัญมาก) ---------- */
    $maxDim = 3000; // กัน RAM ระเบิด
    $w = imagesx($image);
    $h = imagesy($image);

    if ($w > $maxDim || $h > $maxDim) {
        $scale = min($maxDim / $w, $maxDim / $h);
        $newW = (int)($w * $scale);
        $newH = (int)($h * $scale);

        $tmp = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($tmp, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);
        $image = $tmp;
        gc_collect_cycles();
    }

    /* ---------- PREPARE PATH ---------- */
    $uploadDir = rtrim(__DIR__ . '/../imgtour', '/') . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        imagedestroy($image);
        error_log("uploadTourPicture: mkdir failed {$uploadDir}");
        return false;
    }

    $newFileName = sprintf('tour_%d_%d.webp', $tourId, time());
    $dest = $uploadDir . $newFileName;
    $tmpDest = $dest . '.tmp';

    /* ---------- COMPRESS + RESIZE TO <= 1MB ---------- */
    $maxBytes   = 1024 * 1024;
    $quality    = 85;
    $minQuality = 65;
    $maxLoops   = 6;

    for ($i = 0; $i < $maxLoops; $i++) {

        imagewebp($image, $tmpDest, $quality);

        if (filesize($tmpDest) <= $maxBytes) {
            break;
        }

        if ($quality > $minQuality) {
            $quality -= 5;
            continue;
        }

        // resize เมื่อ quality ต่ำสุด
        $w = imagesx($image);
        $h = imagesy($image);

        if ($w <= 900 || $h <= 900) {
            break;
        }

        $ratio = 0.6;
        $newW = (int)($w * $ratio);
        $newH = (int)($h * $ratio);

        // ⭐ memory guard
        if ($newW * $newH * 4 > 80 * 1024 * 1024) {
            break;
        }

        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($image);
        $image = $resized;
        $quality = 85;

        gc_collect_cycles();
    }

    imagedestroy($image);

    /* ---------- VERIFY FILE ---------- */
    if (!is_file($tmpDest) || filesize($tmpDest) === 0) {
        @unlink($tmpDest);
        error_log("uploadTourPicture: webp empty {$tmpDest}");
        return false;
    }

    if (!rename($tmpDest, $dest)) {
        @unlink($tmpDest);
        error_log("uploadTourPicture: rename failed {$tmpDest}");
        return false;
    }

    chmod($dest, 0644);

    /* ---------- UPDATE DB ---------- */
    $stmt = $conn->prepare("UPDATE tour_online SET filebanner = ?, hash_img = ? WHERE code = ?");
    if (!$stmt) {
        error_log("uploadTourPicture: prepare failed " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssi", $newFileName, $md5Hash, $tourId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function retry_as_browser_for_shorturl(string $url): array
{
    $cookie = tempnam(sys_get_temp_dir(), 'ck_');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,th;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Connection: keep-alive',
        ],
        CURLOPT_ENCODING       => '',
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_AUTOREFERER    => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body  = curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
    $eff   = (string)(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);

    @unlink($cookie);
    return ['code' => $code, 'ctype' => $ctype, 'final' => $eff, 'body' => $body ?: ''];
}

// เป็น function เอาไว้ลบไฟล์ pdf
function delePDF(string $pdf): void
{
    $uploadDir = __DIR__ . '../programtour/';
    $file = $uploadDir . $pdf;
    if (file_exists($file)) {
        @unlink($file);
    }
}

// เป็น function เอาไว้ลบไฟล์ รูปทัวร์
function delePict(string $pict): void
{
    $uploadDir = __DIR__ . '/../imgtour/';
    $file = $uploadDir . $pict;
    if (file_exists($file)) {
        @unlink($file);
    }
}


// เป็น function เอาไว้ ตรวจสอบ ว่า สถานะเป็น  Y หรือไหม
function checkTourEnable($conn, $code)
{
    $sql = "SELECT enable 
    FROM tour_online
    WHERE code = '$code'
    AND enable = 'Y'";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return false;
}

// เป็น function เอาไว้ ลบข้อมูล โดยเปลี่ยนสถานะเป็น soldout
function soldoutTour(mysqli $conn, $code, $tour_code): bool
{
    $wholesale_code = '266';
    $stmt = $conn->prepare(
        "UPDATE tour_online SET enable = 'N', soldout = 'Y' WHERE tour_code = ? AND wscode_tour = ? AND wholesale_code = ?"
    );
    $stmt->bind_param("sss", $code, $tour_code, $wholesale_code);
    $result = $stmt->execute();
    $stmt->close();
    if ($result) {
        return true;
    }
    return false;
}


function markLogDone(
    mysqli $conn,
    int $logId,
    string $note,
    array $extra = []
): bool {

    $fields = ['note = ?'];
    $params = [$note];
    $types  = 's';

    if (isset($extra['tour_id'])) {
        $fields[] = 'tour_id = ?';
        $params[] = $extra['tour_id'];
        $types   .= 'i';
    }

    if (isset($extra['is_synced'])) {
        $fields[] = 'is_synced = ?';
        $params[] = $extra['is_synced'];
        $types   .= 'i';
    }

    $fields[] = 'created_at = NOW()';

    $sql = "UPDATE sync_log_TTNplus SET " . implode(', ', $fields) . " WHERE id = ?";

    $params[] = $logId;
    $types   .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}


/**
 * ตรวจสอบว่ามีข้อมูลทัวร์นี้อยู่ในตาราง tour_online แล้วหรือยัง
 *
 * กติกาทางธุรกิจ:
 * - ใช้ tour_code จาก $log['tour_id']
 * - ใช้ wstour_code จาก $log['tour_code']
 * - wholesale_code กำหนดตายตัว = 32
 *
 * ฟังก์ชันนี้ใช้เพื่อตัดสินใจว่า
 * จะ INSERT หรือ UPDATE ข้อมูลทัวร์
 */
function tourExists(mysqli $conn, array $log): bool
{

    $sql = "
        SELECT 1
        FROM tour_online
        WHERE tour_code = ?
          AND wscode_tour = ?
          AND wholesale_code = 266
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ss',
        $log['tour_code'],
        $log['wscode_tour']
    );

    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

function updateTourError(mysqli $conn, int $code, string $note, string $error): void
{
    $errorNote = json_encode([
        'note' => $note,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s'),
        'source_file' => ''
    ]);

    $sql = "UPDATE tour_online 
            SET note = '" . $conn->real_escape_string($errorNote) . "'
            WHERE code = '" . $conn->real_escape_string((string)$code) . "'";

    $conn->query($sql);
}
