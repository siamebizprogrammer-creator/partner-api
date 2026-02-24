<?php

function getTourAll(): array
{
    $url = 'https://api.kaikongservice.com/api/v1/tours/search';
    $token = 'eyJhbGciOiJIUzUxMiJ9.eyJhcGlfaWQiOjYxLCJhZ2VudF9pZCI6NzU2LCJ1c2VyX2lkIjo3NTYsImNvbXBhbnlfdGgiOiLguJrguKPguLTguKnguLHguJcg4LiV4Lil4Liy4LiU4LiX4Lix4Lin4Lij4LmMIOC4iOC4s-C4geC4seC4lCIsImNvbXBhbnlfZW4iOiJUQUxBRFRPVVIgQ08uLExURC4ifQ.ARMyRoSOOtZcdhRg0sF2EtM_zjjfjfSzzjCjJ6T8FKA038PHTP1_u3yxp30KZuvQDVUXUMPjAvG98UoEWrtt_g';

    $ch = curl_init($url);
    $body = [
        "limit_page" => 500
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $token
        ],
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => json_encode($body)
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception("Curl Error: " . curl_error($ch));
    }

    unset($ch);

    // Convert JSON → array
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }

    return $data;
}


function getDetail(string $code): array
{

    $base = 'https://api.kaikongservice.com/api/v1/tours/detail/';
    $url = $base . urlencode($code);
    $token = 'eyJhbGciOiJIUzUxMiJ9.eyJhcGlfaWQiOjYxLCJhZ2VudF9pZCI6NzU2LCJ1c2VyX2lkIjo3NTYsImNvbXBhbnlfdGgiOiLguJrguKPguLTguKnguLHguJcg4LiV4Lil4Liy4LiU4LiX4Lix4Lin4Lij4LmMIOC4iOC4s-C4geC4seC4lCIsImNvbXBhbnlfZW4iOiJUQUxBRFRPVVIgQ08uLExURC4ifQ.ARMyRoSOOtZcdhRg0sF2EtM_zjjfjfSzzjCjJ6T8FKA038PHTP1_u3yxp30KZuvQDVUXUMPjAvG98UoEWrtt_g';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true, // ใช้ GET สำหรับ endpoint detail
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'x-api-key: ' . $token
        ],
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);

    // เอา info ก่อนตรวจ error
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if (curl_errno($ch)) {
        $err = 'Curl Error: ' . curl_error($ch);
        unset($ch);
        throw new RuntimeException($err);
    }

    unset($ch);

    // ถ้า server ตอบ non-2xx -> โยน exception พร้อม snippet ของ response เพื่อดีบัก
    if ($httpCode < 200 || $httpCode >= 300) {
        $snippet = substr($response ?? '', 0, 2000);
        throw new RuntimeException("HTTP {$httpCode} from API. Content-Type: {$contentType}. Response snippet: {$snippet}");
    }

    // decode JSON เป็น associative array
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $snippet = substr($response ?? '', 0, 2000);
        throw new RuntimeException('JSON Decode Error: ' . json_last_error_msg() . ". Response snippet: {$snippet}");
    }

    return $data;
}
