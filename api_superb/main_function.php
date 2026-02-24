<?php

ob_start();
date_default_timezone_set("Asia/Bangkok");
function connectDB(): mysqli
{
    // saimebiz1
    //$host = 'localhost';
    //$user = 'siamebiz_prod';
    //$password = '8x9j7y?1K';
    //$database = 'siamebiz1_db';
    //$port = '3306';

    //$port = '3306'

    // teawnaidee
     $host = 'localhost';
     $user = 'siamebiz_stagging';
     $password = 'T^Vw_7R2rpetyrz6';
     $database = 'siamebiz_stagging';
     $port = '3306';

    // test localhost
    //$host = 'host.docker.internal';
    //$user = 'root';
    //$password = 'root';
    //$database = 'siamebiz1_db';
    //$port = '3307';

    $conn = new mysqli($host, $user, $password, $database, $port);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

function DateNow(): string
{
    return date('Y-m-d H:i:s');
}

function getAllTours(mysqli $conn): array
{
    $sql = "
        SELECT 
            tour_code,
            wscode_tour,
            api_updated_at
        FROM tour_online WHERE wholesale_code = 32 AND user_create = 'API Superb'
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
    $ids = [21, 31, 23, 24, 34, 18, 3, 1, 36, 28, 29, 25, 32, 35, 2, 17, 19];
    $baseUrl = 'https://superbholidayz.com/superb/apiweb.php?id=';

    $tours = []; // key = maincode

    foreach ($ids as $id) {
        $response = file_get_contents($baseUrl . $id);
        if ($response === false) {
            continue;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            continue;
        }

        foreach ($data as $row) {

            $maincode = $row['maincode'] ?? null;
            $pid = $row['pid'] ?? null;
            $lastModified = $row['Last-modified'] ?? null;

            if (!$maincode || !$pid || !$lastModified) {
                continue;
            }

            $currentTs = strtotime($lastModified);

            if (!isset($tours[$maincode])) {
                $tours[$maincode] = [
                    'maincode'       => $maincode,
                    'mainid'         => $row['mainid'] ?? null,
                    'api_updated_at' => $lastModified,
                    'api_ts'         => $currentTs,
                    'periods'        => []
                ];
            }

            $tours[$maincode]['periods'][$pid] = $row;

            if ($currentTs > $tours[$maincode]['api_ts']) {
                $tours[$maincode]['api_updated_at'] = $lastModified;
                $tours[$maincode]['api_ts'] = $currentTs;
            }
        }
    }

    return array_values($tours);
}


function compare(array $dbTours, array $apiTours): array
{
    $result = [];

    /**
     * 1️⃣ lookup DB ด้วย maincode
     */
    $dbLookup = [];
    foreach ($dbTours as $db) {
        $dbLookup[$db['tour_code']] = $db;
    }

    /**
     * 2️⃣ loop API (flat list)
     */
    foreach ($apiTours as $api) {

        if (empty($api['maincode'])) {
            continue;
        }

        $maincode = $api['maincode'];
        $mainid   = $api['mainid'] ?? null;
        $apiTime = $api['api_updated_at'] ?? null;
        $apiTs   = $apiTime ? strtotime($apiTime) : 0;

        // ❌ DB ว่าง หรือยังไม่มี maincode นี้ → INSERT
        if (!isset($dbLookup[$mainid])) {
            $result[] = [
                'tour_id'        => $mainid,
                'maincode'       => $maincode,
                'action'         => 'INSERT',
                'api_updated_at' => $apiTime,
                'data_json'      => json_encode(
                    $api,
                    JSON_UNESCAPED_UNICODE
                ),
                'note'           => 'db empty or tour not found'
            ];
            continue;
        }

        // ✔ DB มี → compare เวลา

        $dbTime = strtotime($dbLookup[$mainid]['api_updated_at'] ?? '');
        $action = ($apiTs > $dbTime) ? 'UPDATE' : 'SKIP';

        $result[] = [
            'tour_id'        => $mainid,
            'maincode'       => $maincode,
            'action'         => $action,
            'api_updated_at' => $apiTime,
            'data_json'      => json_encode(
                $api,
                JSON_UNESCAPED_UNICODE
            ),
            'note'           => 'compare api vs local'
        ];
    }

    return $result;
}

function logSync(
    mysqli $conn,
    array $item,
    string $actionType,
    int $isSynced
): void {

    // ✅ log ระดับ TOUR
    $tourId   = $item['tour_id']   ?? null;   // mainid จาก API
    $tourCode = $item['maincode'] ?? null;   // maincode จาก API

    if (!$tourId || !$tourCode) {
        return; // ข้าม quietly
    }

    // ✅ เวลาจาก API (ใช้ Last-modified เท่านั้น)
    $apiTime = $item['api_updated_at'] ?? null;

    $localTime = null; // ยังไม่ใช้ตอนนี้
    $note      = $item['note'] ?? null;

    // 🔥 raw json (มี pid / periods อยู่ข้างใน)
    $dataJson = $item['data_json'] ?? null;

    /**
     * 1️⃣ check log เดิม
     * key = tour_id + tour_code + action_type
     */
    $checkSql = "
        SELECT id, miss_api_count
        FROM sync_log_superb
        WHERE tour_id = ?
          AND tour_code = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('is', $tourId, $tourCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        // 2️⃣ UPDATE log เดิม
        $missCount = (int)$row['miss_api_count'];
        if ($actionType === 'MISS_API') {
            $missCount++;
        }

        $updateSql = "
            UPDATE sync_log_superb
            SET
                is_synced = ?,
                action_type = ?
                miss_api_count = ?,
                api_updated_at = ?,
                local_updated_at = ?,
                data_json = ?,
                note = ?,
                created_at = NOW()
            WHERE id = ?
        ";

        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param(
            'isissssi',
            $isSynced,
            $actionType,
            $missCount,
            $apiTime,
            $localTime,
            $dataJson,
            $note,
            $row['id']
        );
        $stmt->execute();
    } else {


        // 3️⃣ INSERT log ใหม่
        $missCount = ($actionType === 'MISS_API') ? 1 : 0;

        $insertSql = "
            INSERT INTO sync_log_superb
            (
                tour_id,
                tour_code,
                tour_create_date,
                is_synced,
                action_type,
                miss_api_count,
                api_updated_at,
                local_updated_at,
                data_json,
                note,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param(
            'ississssss',
            $tourId,
            $tourCode,
            $apiTime,
            $isSynced,
            $actionType,
            $missCount,
            $apiTime,
            $localTime,
            $dataJson,
            $note
        );
        $stmt->execute();
    }
}


function processLog(mysqli $conn, array $log): void
{

    try {

        switch ($log['action_type']) {
            case 'INSERT':
                // ขั้นตอน INSERT:
                // - ถ้ามีทัวร์แล้ว → SKIP และจบ
                // - ถ้า insert ล้มเหลว → เพิ่ม miss_api_count เพื่อ retry รอบถัดไป

                if (tourExists($conn, $log)) {
                    markLogDone(
                        $conn,
                        $log['id'],
                        'SKIP: tour already exists (tour_online)'
                    );
                    break;
                }

                try {
                    $data = setTourDataFromApiLog($conn, $log);
                    insertTour($conn, $data, $log['id']);
                    markLogDone($conn, $log['id'], 'INSERT success');
                } catch (Throwable $e) {

                    // insert ล้มเหลว (technical failure)
                    // ให้ retry ในรอบถัดไป
                    markLogFail(
                        $conn,
                        $log['id'],
                        'INSERT failed: ' . $e->getMessage(),
                        true // เพิ่ม miss_api_count
                    );
                    break;
                }

                break;


            case 'UPDATE':

                $tour_code = $log['tour_id'];
                $wholesale_code = '32';

                $code = null;
                $pdf  = null;
                $pict = null;

                $stmt = $conn->prepare("
                    SELECT code, filepdf, filebanner
                    FROM tour_online
                    WHERE tour_code = ? AND wholesale_code = ?
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

                    $data = setTourDataFromApiLog($conn, $log);
                    if (!$data) {
                        throw new Exception('Invalid tour data');
                    }

                    if ($pdf) delePDF($pdf);
                    if ($pict) delePict($pict);

                    updateTour($conn, $data, $code);

                    markLogDone($conn, $log['id'], 'UPDATE success');
                    $conn->commit();
                } catch (Exception $e) {

                    $conn->rollback();
                    markLogDone($conn, $log['id'], 'UPDATE failed: ' . $e->getMessage());
                }

                break;

            case 'SOFT_INACTIVE':
                // soft inactive / soft delete
                // inactiveTour($conn, $log);
                break;

            case 'SOLDOUT':
                $code = (string) $log['tour_id'];
                $tour_code = (string) $log['tour_code'];

                try {
                    $result = soldoutTour($conn, $code, $tour_code);

                    if ($result !== true) {
                        throw new Exception('soldoutTour failed');
                    }

                    markLogDone($conn, $log['id'], 'SOLDOUT success');
                } catch (Throwable $e) {
                    markLogDone(
                        $conn,
                        $log['id'],
                        'SOLDOUT failed: ' . $e->getMessage()
                    );
                }
                break;

            case 'SKIP':
                // ไม่ต้องทำอะไร แต่ถือว่าจบงาน
                break;

            case 'MISS_API':
                // API ไม่มา → ไม่ถือว่าสำเร็จ
                throw new Exception('API missing');

            case 'ERROR':
                // log นี้ถูก mark error ไว้แล้ว
                throw new Exception('Previous error state');

            default:
                throw new Exception('Unknown action: ' . $log['action_type']);
        }

        // ถ้ามาถึงตรงนี้ แปลว่าทำงานจบ
        markLogDone(
            $conn,
            $log['id'],
            $log['action_type'] . ' success'
        );
    } catch (Throwable $e) {

        $isApiError = in_array(
            $log['action_type'],
            ['MISS_API'],
            true
        );

        markLogFail(
            $conn,
            $log['id'],
            substr($e->getMessage(), 0, 250),
            $isApiError
        );
    }
}


function markLogDone(mysqli $conn, int $logId, string $note = 'DONE'): void
{
    $sql = "
        UPDATE sync_log_superb
        SET
            is_synced = 1,
            local_updated_at = NOW(),
            note = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $note, $logId);
    $stmt->execute();
    $stmt->close();
}

/**
 * บันทึกสถานะ log เมื่อ action ล้มเหลว
 *
 * - ใช้ $increaseMissApi = true เฉพาะกรณีที่เป็น technical failure
 *   และต้องการให้ระบบ retry ในรอบถัดไป
 * - กรณี business error หรือ error ถาวร ไม่ต้องเพิ่ม miss_api_count
 */
function markLogFail(
    mysqli $conn,
    int $logId,
    string $errorMessage,
    bool $increaseMissApi = false
): void {
    if ($increaseMissApi) {
        $sql = "
            UPDATE sync_log_superb
            SET
                is_synced = 0,
                miss_api_count = miss_api_count + 1,
                note = ?,
                local_updated_at = NOW()
            WHERE id = ?
        ";
    } else {
        $sql = "
            UPDATE sync_log_superb
            SET
                is_synced = 0,
                note = ?,
                local_updated_at = NOW()
            WHERE id = ?
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $errorMessage, $logId);
    $stmt->execute();
    $stmt->close();
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
        $log['tour_id'],
        $log['tour_code']
    );

    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

// หาจำนวนรถ
function getBusCount($pid)
{
    if (preg_match('/B(\d+)$/', $pid, $matches)) {
        return (int)$matches[1]; // เลขหลัง B
    }
    return 1; // ไม่มี B = 1 คัน
}

// เตรียม ข้อมูลทัวร์ ก่อน ส่งไป insert 

function setTourDataFromApiLog(mysqli $conn, array $log): array
{
    $decoded = json_decode($log['data_json'], true);

    if (!$decoded) {
        throw new Exception('Invalid data_json');
    }

    $dateNow = DateNow();

    // -----------------------------
    // periods
    // -----------------------------
    $periods = [];

    foreach ($decoded['periods'] as $pid => $periodData) {
        $bus =  getBusCount($periodData['pid']);
        $seat = 0;
        if (!empty($periodData['Size']) && preg_match('/\d+/', $periodData['Size'], $m)) {
            $seat = (int)$m[0];
        }
        $periods[] = [
            'pid'           => $periodData['pid'] ?? $pid,
            'mainid'        => $decoded['mainid'] ?? '',
            'maincode'      => $periodData['maincode'] ?? '',
            'country'       => $periodData['Country'] ?? '',
            'country_code'  => $periodData['country_code'] ?? '',
            'name_th'       => $periodData['title'] ?? '',
            'date_start'    => $periodData['Date'] ?? '',
            'date_end'      => $periodData['ENDDate'] ?? '',
            'day'           => (int)($periodData['day'] ?? 0),
            'night'         => (int)($periodData['night'] ?? 0),
            'airline'       => $periodData['Airline'] ?? '',
            'flight_code'   => $periodData['aey'] ?? '',
            'avbl'          => (int)($periodData['AVBL'] ?? 0),
            'seat'          => $seat ?? 0,
            'startingprice' => (float)($periodData['startingprice'] ?? 0),
            'price_adult'   => (float)($periodData['Adult'] ?? 0),
            'price_single'  => (float)($periodData['Single'] ?? 0),
            'deposit'       => (float)($periodData['Deposit'] ?? 0),
            'itinerary'     => $periodData["itinerary"] ?? '',
            'pdfURL'        => $periodData['pdf'] ?? '',
            'bannerURL'     => $periodData['banner'] ?? '',
            'booking'       => $periodData['Booking'] ?? '',
            'com'           => $periodData['com'] ?? 0,
            'child'         => $periodData['Chd+B'] ?? '',
            'child_n'       => $periodData['ChdNB'] ?? '',
            'bus'           => $bus ?? '',
            'created_at'    => $dateNow,
            'updated_at'    => $dateNow,
        ];
    }


    // detail day (ยังไม่พร้อม → ว่าง)
    // -----------------------------
    $shortcontent = '';
    $fullText = trim($periods[0]['itinerary']);

    $itinerary = [];

    /**
     * 1. แยก DAY 1 / DAY 2 ถ้ามี
     */
    preg_match_all(
        '/(?:DAY|วันที?่?)\s*(\d+)\s*(.*?)(?=(?:DAY|วันที?่?)\s*\d+|$)/siu',
        $fullText,
        $matches,
        PREG_SET_ORDER
    );


    /**
     * 2. ถ้าไม่เจอ DAY เลย → ถือว่าทั้งหมดคือ DAY 1
     */
    if (empty($matches)) {
        $itinerary[1] = $fullText;
    } else {
        foreach ($matches as $match) {
            $dayNumber = (int)$match[1];
            $itinerary[$dayNumber] = trim($match[2]);
        }
    }

    /**
     * 3. สร้าง detailDays
     */
    $detailDays = [];

    // ✅ ถ้าไม่มีคำว่า DAY หรือ วันที่ เลย
    if (!preg_match('/\bDAY\b|วันที่/iu', $fullText)) {
        $detailDays = [];
    } else {

        ksort($itinerary);

        $day = 1;

        foreach ($itinerary as $dayNum => $text) {
            if ($dayNum <= 0) continue;

            $detailDays[] = [
                'day_num'    => $day,
                'day_topics' => trim($text)
            ];
            $day++;
        }
    }


    // -----------------------------
    // tour main
    // -----------------------------
    $firstPeriod = reset($periods);
    $lastPeriod = end($periods);
    $startSale = $firstPeriod['date_start'];
    $endSale =  $lastPeriod['date_start'];
    $lastDate = $lastPeriod['date_end'];
    $airlineIATA = '';

    if (
        ($periods[0]['airline'] ?? '') === 'NOLOGO' &&
        ($periods[0]['flight_code'] ?? '') === ''
    ) {
        $airlineIATA = 'PKG';
    } else {
        $airlineIATA = extractIataCode($periods[0]['flight_code'] ?? '') ?: 'PKG';
    }

    $countryCode = $periods[0]['country_code'] ?? '';
    // periodtime
    $day   = $firstPeriod['day']   ?? '';
    $night = $firstPeriod['night'] ?? '';
    $periodtime = trim("{$day}วัน {$night}คืน");

    $tour = [
        'tour_code'       => $decoded['mainid'] ?? '',
        'country_code'    => $countryCode ?? '',
        'zone_code'       => $zoneCode ?? '',
        'tourcode'        => $tourCode ?? '',
        'name'            => $firstPeriod['name_th'] ?? '',
        'airline_iata'    => $airlineIATA,
        'periodtime'      => $periodtime,
        'c_newprice'      => $periods[0]['startingprice'] ?? '',
        'wsname'          => 'Superb',
        'wholesale_code'  => '32',
        'wscode_tour'     => $decoded['maincode'] ?? '',
        'shortcontent'    => $shortcontent,
        'start_date_sale' => $startSale,
        'end_date_sale'   => $endSale,
        'last_date'       => $lastDate,
        'date_create'     => $dateNow,
        'date_update'     => $dateNow,
        'change_tm'       => $dateNow,
        'pdfUrl'          => $periods[0]['pdfURL'] ?? '',
        'bannerUrl'       => $periods[0]['bannerURL'] ?? '',
        'api_updated_at'  => $decoded['api_updated_at'] ?? ''
    ];

    return [
        'tour'      => $tour,
        'detailDay' => $detailDays,
        'period'    => $periods,
    ];
}


// เป็น function เอาไว้แยก Iata code 
function extractIataCode(string $airline): string
{
    preg_match('/\(([^)]+)\)/', $airline, $matches);

    $code = $matches[1] ?? '';

    if ($code === '') {
        return '';
    }

    // ถ้ามีหลายสายการบิน เช่น MH+TR
    if (strpos($code, '+') !== false) {
        $parts = explode('+', $code);
        $code = trim($parts[0]); // เอาตัวหน้า
    }

    // ให้รับเฉพาะ IATA 2 ตัวอักษร
    return preg_match('/^[A-Z0-9]{2}$/', $code) ? $code : '';
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

    $fileName   = 'tour_' . $tourId . '.pdf';
    $uploadFile = $uploadDir . $fileName;

    // ===== Normalize URL (แก้ปัญหา URL ภาษาไทย) =====
    $originalUrl   = $filePathOrUrl;
    $normalizedUrl = normalizeUrl($filePathOrUrl);

    // เช็ค Google Drive
    if (strpos($normalizedUrl, 'drive.google.com') !== false) {
        $directUrl = getGoogleDriveDirectDownload($normalizedUrl);
        if ($directUrl) {
            $normalizedUrl = $directUrl;
        }
    }

    // เช็ค Google Docs
    if (strpos($normalizedUrl, 'docs.google.com/document') !== false) {
        $directUrl = getGoogleDocsPDFDownloadUrl($normalizedUrl);
        if ($directUrl) {
            $normalizedUrl = $directUrl;
        }
    }

    if (filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
        $urlPath = parse_url($normalizedUrl, PHP_URL_PATH);

        if ($urlPath) {
            $localPath = $_SERVER['DOCUMENT_ROOT'] . $urlPath;

            if (file_exists($localPath)) {
                if (!copy($localPath, $uploadFile)) {
                    $errorNote = json_encode([
                        'note' => '2',
                        'error' => 'pdf_copy_failed',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'source_file' => $localPath
                    ], JSON_UNESCAPED_UNICODE);

                    $sql = "UPDATE tour_online 
                        SET note = '" . $conn->real_escape_string($errorNote) . "'
                            
                        WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
                    $conn->query($sql);
                    return;
                }
            }
        }
    }

    if (filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {

        $ch = curl_init($normalizedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/pdf,application/octet-stream;q=0.9,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive'
            ]
        ]);

        $fileContent = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
        $finalUrl    = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $normalizedUrl;

        // ===== PATCH เดิมของคุณ (Drive shortcut) =====
        if (!($httpCode === 200 && isPdfResponse($contentType, (string)$fileContent, $finalUrl))) {
            $candidates = buildDirectPdfUrlsFromShortcut($finalUrl);
            foreach ($candidates as $directUrl) {
                [$c2, $ct2, $eff2, $body2] = simpleFetch($directUrl, $finalUrl);
                if ($c2 === 200 && isPdfResponse($ct2, (string)$body2, $eff2)) {
                    $httpCode    = $c2;
                    $contentType = $ct2;
                    $finalUrl    = $eff2;
                    $fileContent = $body2;
                    break;
                }
            }
        }

        if ($httpCode !== 200 || !isPdfResponse($contentType, (string)$fileContent, $finalUrl)) {
            $errorNote = json_encode([
                'note'         => '2',
                'error'        => 'pdf_download_failed',
                'timestamp'    => date('Y-m-d H:i:s'),
                'original_url' => $originalUrl,
                'normalized'   => $normalizedUrl,
                'http_code'    => $httpCode,
                'content_type' => $contentType,
                'finalUrl'     => $finalUrl
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $sql = "UPDATE tour_online 
                    SET note = '" . $conn->real_escape_string($errorNote) . "'
                    WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
            $conn->query($sql);
            return;
        }

        file_put_contents($uploadFile, $fileContent);
    } elseif (file_exists($filePathOrUrl)) {

        if (!copy($filePathOrUrl, $uploadFile)) {
            $errorNote = json_encode([
                'note' => '2',
                'error' => 'pdf_copy_failed',
                'timestamp' => date('Y-m-d H:i:s'),
                'source_file' => $filePathOrUrl
            ], JSON_UNESCAPED_UNICODE);

            $sql = "UPDATE tour_online 
                    SET note = '" . $conn->real_escape_string($errorNote) . "'
                    WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
            $conn->query($sql);
            return;
        }
    } else {
        $errorNote = json_encode([
            'note' => '2',
            'error' => 'pdf_file_not_found',
            'timestamp' => date('Y-m-d H:i:s'),
            'file_path' => $filePathOrUrl
        ], JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE tour_online 
                SET note = '" . $conn->real_escape_string($errorNote) . "'
                WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
        $conn->query($sql);
        return;
    }

    if (!file_exists($uploadFile) || filesize($uploadFile) === 0) {
        $errorNote = json_encode([
            'note' => '2',
            'error' => 'pdf_creation_failed',
            'timestamp' => date('Y-m-d H:i:s'),
            'upload_file' => $uploadFile
        ], JSON_UNESCAPED_UNICODE);

        $sql = "UPDATE tour_online 
                SET note = '" . $conn->real_escape_string($errorNote) . "'
                WHERE code = '" . $conn->real_escape_string((string)$tourId) . "'";
        $conn->query($sql);
        return;
    }

    $pdfMd5 = md5_file($uploadFile);

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

function uploadTourPicture(mysqli $conn, string $url, string|int $tourCode): bool
{
    if (empty($url)) {
        error_log("uploadTourPicture: empty url");
        return false;
    }

    ini_set('memory_limit', '256M');

    /* ---------- FETCH IMAGE ---------- */
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => false, // ⭐ server บางที่ SSL fail
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $bin = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($bin === false || $httpCode < 200 || $httpCode >= 300) {
        error_log("curl fail {$url} http={$httpCode} err={$curlErr}");
        return false;
    }

    if (stripos($contentType, 'image/') !== 0) {
        error_log("not image {$contentType}");
        return false;
    }

    $md5Hash = md5($bin);
    $enableHashCheck = false;
    /* ---------- GET OLD DATA ---------- */
    $stmt = $conn->prepare(
        "SELECT filebanner, hash_img FROM tour_online WHERE code = ? LIMIT 1"
    );
    $stmt->bind_param("s", $tourCode);
    $stmt->execute();
    $stmt->bind_result($oldFile, $oldHash);
    $stmt->fetch();
    $stmt->close();

    if (!empty($oldHash) && $oldHash === $md5Hash) {
        if ($enableHashCheck) {
            return true;
        }
    }

    /* ---------- CREATE IMAGE ---------- */
    $image = imagecreatefromstring($bin);
    unset($bin);

    if (!$image) {
        error_log("imagecreatefromstring failed");
        return false;
    }

    /* ---------- PATH ---------- */
    $uploadDir = realpath(__DIR__ . '/../') . '/imgtour/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return false;
    }

    $newFile = 'tour_' . $tourCode . '_' . time() . '.webp';
    $tmp = $uploadDir . $newFile . '.tmp';
    $dest = $uploadDir . $newFile;

    imagewebp($image, $tmp, 80);
    imagedestroy($image);

    if (!file_exists($tmp) || filesize($tmp) === 0) {
        return false;
    }

    rename($tmp, $dest);
    chmod($dest, 0644);

    /* ---------- DELETE OLD FILE ---------- */
    if (!empty($oldFile)) {
        $oldPath = $uploadDir . $oldFile;
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    /* ---------- UPDATE DB ---------- */
    $stmt = $conn->prepare(
        "UPDATE tour_online 
         SET filebanner = ?, hash_img = ?
         WHERE code = ?"
    );
    $stmt->bind_param("sss", $newFile, $md5Hash, $tourCode);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        error_log("update image but no row changed code={$tourCode}");
    }

    $stmt->close();
    return true;
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
    $uploadDir = '../programtour/';
    $file = $uploadDir . $pdf;
    if (file_exists($file)) {
        @unlink($file);
    }
}

// เป็น function เอาไว้ลบไฟล์ รูปทัวร์
function delePict(string $pict): void
{
    $uploadDir = '../imgtour/';
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
    $wholesale_code = '32';
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

function updateTourError(mysqli $conn, int $code, string $note, string $error): void
{
    $errorNote = json_encode([
        'note' => $note,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s'),
        'source_file' => ''
    ]);

    $sql = "UPDATE tour_online 
            SET note = '" . $conn->real_escape_string($errorNote) . "', 
                enable = 'N', 
                enable_api = 'N' 
            WHERE code = '" . $conn->real_escape_string((string)$code) . "'";

    $conn->query($sql);
}
