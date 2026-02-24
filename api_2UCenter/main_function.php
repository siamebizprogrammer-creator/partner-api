<?php
date_default_timezone_set("Asia/Bangkok");
function connectDB(): mysqli
{
    // saimebiz1
    $host = 'localhost';
    $user = 'siamebiz_prod';
    $password = '8x9j7y?1K';
    $database = 'siamebiz1_db';
    $port = '3306';

    // teawnaidee
    // $host = 'localhost';
    // $user = 'teawnaid_db';
    // $password = '557Fhap$6';
    // $database = 'teawnaid_db';

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

    $conn->query("SET time_zone = '+07:00'");

    return $conn;
}

function DateNow(): string
{
    return date('Y-m-d H:i:s');
}


function getTour($conn)
{
    $result = $conn->query("SELECT code, tour_code, tourcode, wscode_tour, date_create, change_tm, api_updated_at FROM tour_online WHERE wholesale_code = 293 AND user_update = 'API 2U Center'");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


function getTourDetail(array $result): string
{
    foreach ($result as &$data) {
        $data['code'] = $data['code'] ?? '';
    }

    return json_encode($result, JSON_UNESCAPED_UNICODE);
}

function insertSyncLog2U($conn, $tour_id, $tour_code, $tour_date): void
{
    $log = checkTourCodeLog($conn, $tour_id);
    if ($log)  return;
    $date_create = DateNow();
    $is_synced = 0;
    $action = "INSERT";
    $note = "";

    $stmt = $conn->prepare("
        INSERT INTO sync_log_2UCenter 
        (tour_id, tour_code, tour_create_date, is_synced, action_type, created_at, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // i = int, s = string
    $stmt->bind_param(
        "sssisss",
        $tour_id,
        $tour_code,
        $tour_date,
        $is_synced,
        $action,
        $date_create,
        $note
    );

    $stmt->execute();
}

function updateSyncLog2U($conn, $tour_id, $tour_date_update, $action, $tour_code): void
{

    $log = checkTourCodeLog($conn, $tour_id);
    if ($log) {

        $date_update = DateNow();
        $is_synced = 0;

        $stmt = $conn->prepare("
        UPDATE sync_log_2UCenter
        SET 
            tour_date_update = ?, 
            is_synced = ?, 
            action_type = ?, 
            updated_at = ?
        WHERE tour_id = ?
    ");

        if (! $stmt) {
            error_log("Prepare failed: " . $conn->error);
            return;
        }

        // s = string, i = int, s = string, s = string, s = string
        $stmt->bind_param(
            "sisss",
            $tour_date_update,
            $is_synced,
            $action,
            $date_update,
            $tour_id
        );

        if (! $stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    } else {
        $is_synced = 0;
        $action = "UPDATE";
        $date_create = DateNow();
        $note = '';

        $stmt = $conn->prepare("
        INSERT INTO sync_log_2UCenter
        (tour_id, tour_code, is_synced, action_type, created_at, note)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
        // i = int, s = string
        $stmt->bind_param(
            "isisss",
            $tour_id,
            $tour_code,
            $is_synced,
            $action,
            $date_create,
            $note
        );

        if ($stmt->execute()) {
            echo "Insert OK";
        } else {
            echo "Error: " . $stmt->error;
        }
    }
}

function updateSyncLog2UDelete($conn, $tour_id, $action, $tour_code): void
{
    $log = checkTourCodeLog($conn, $tour_id);
    if ($log) {
        $date_update = DateNow();
        $is_synced = 0;

        $stmt = $conn->prepare("
        UPDATE sync_log_2UCenter
        SET tour_id = ?,
            is_synced = ?,
            action_type = ?,
            updated_at = ?
        WHERE tour_code = ?
        ");

        if (! $stmt) {
            error_log("Prepare failed: " . $conn->error);
            return;
        }

        // s = string, i = int, s = string, s = string, s = string
        $stmt->bind_param(
            "sisss",
            $tour_id,
            $is_synced,
            $action,
            $date_update,
            $tour_code
        );

        if (! $stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
        }

        $stmt->close();
    } else {
        $is_synced = 0;
        $action = "DELETE";
        $date_create = DateNow();
        $note = '';

        $stmt = $conn->prepare("
        INSERT INTO sync_log_2UCenter
        (tour_id, tour_code, is_synced, action_type, created_at, note)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
        // i = int, s = string
        $stmt->bind_param(
            "ssisss",
            $tour_id,
            $tour_code,
            $is_synced,
            $action,
            $date_create,
            $note
        );

        if ($stmt->execute()) {
            echo "Insert OK";
        } else {
            echo "Error: " . $stmt->error;
        }
    }
}


function checkTourCode($conn, $tour_code)
{
    $sql = "SELECT wscode_tour, change_tm, api_updated_at
            FROM tour_online 
            WHERE tour_code = '$tour_code'
            AND wholesale_code = 293
            AND user_create = 'API 2U Center'";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return false;
}

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

function checkTourCodeLog($conn, $tour_id)
{
    $stmt = $conn->prepare("
        SELECT * FROM sync_log_2UCenter WHERE tour_id = ? LIMIT 1
    ");
    $stmt->bind_param("s", $tour_id);
    $stmt->execute();

    $result = $stmt->get_result();

    return ($result->num_rows > 0) ? $result->fetch_assoc() : false;
}

function getSyncLog2U(mysqli $conn): array
{
    $sql = "SELECT * FROM sync_log_2UCenter WHERE is_synced = 0 ORDER BY created_at ASC LIMIT 5";
    $result = $conn->query($sql);

    if ($result === false) {
        error_log("Query failed (getSyncLog2U): " . $conn->error);
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];

    if ($result instanceof mysqli_result) {
        $result->free();
    }

    return $rows;
}

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

function getLastDate(mysqli $conn, $tour_date_max, $tour_num_night): string
{
    $date = new DateTime($tour_date_max);
    $date->modify("+{$tour_num_night} day");
    $tour_date_back = $date->format("Y-m-d");
    return $tour_date_back;
}

function updateSyncLog2UFinish(mysqli $conn, $code, $tour_code): void
{

    $dateUpdate = DateNow();
    $stmt = $conn->prepare(
        "UPDATE sync_log_2UCenter SET is_synced = 1, updated_at = ?, note = ''  WHERE tour_id = ? AND tour_code = ?"
    );
    $stmt->bind_param("sis", $dateUpdate, $code, $tour_code);
    $stmt->execute();
    $stmt->close();
}

function updataSyncLog2UError(mysqli $conn, $code, $tour_code, $error): void
{
    $dateUpdate = DateNow();
    $stmt = $conn->prepare(
        "UPDATE sync_log_2UCenter SET is_synced = 1, updated_at = ?, note = ? WHERE tour_id = ? AND tour_code = ?"
    );
    $stmt->bind_param("ssis", $dateUpdate, $error, $code, $tour_code);
    $stmt->execute();
    $stmt->close();
}

function deleteTour(mysqli $conn, $code, $tour_code): bool
{
    $wholesale_code = '293';
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

function delePDF(string $pdf): void
{
    $uploadDir = '../programtour/';
    $file = $uploadDir . $pdf;
    if (file_exists($file)) {
        @unlink($file);
    }
}

function delePict(string $pict): void
{
    $uploadDir = '../imgtour/';
    $file = $uploadDir . $pict;
    if (file_exists($file)) {
        @unlink($file);
    }
}
