<?php
function updateTour($conn, $detail, $tour_code): bool
{

    $dataTour = $detail['data'][0];
    $dataTourDay = $detail['data'][0]['tour_daily'];
    $dataTourPeriod = $detail['data'][0]['tour_period'];
    $dataTourEnable = checkTourEnable($conn, $tour_code);
    $apiUpdatedAt = $detail['tour_update'];
    $dateNow = DateNow();

    if (!empty($dataTour)) {

        $country = getCountryCode($conn, $dataTour['tour_country'][0]['country_code_2'] ?? '');
        $airlineId = getAirlineCode($conn, $dataTour['tour_airline']['airline_iata'] ?? '');
        $zoneCode = $country['zone_code'];
        $periodtime = $dataTour['tour_num_day'] . 'วัน' . ' ' . $dataTour['tour_num_night'] . 'คืน';
        $lastPeriod = end($dataTourPeriod);
        $last_date  = $lastPeriod['period_back'];
        $file_pdf   = $dataTour['tour_file']['file_pdf'];
        $file_banner    = $dataTour['tour_file']['file_banner'];
        $data_update = [];
        $data_update['country_code'] =  $country['country_code'] ?? '';
        $data_update['zone_code'] = $zoneCode ?? '';
        //$data_update['enable'] = $dataTourEnable ? 'Y' : 'N';
        //$data_update['name'] =  $dataTour['tour_name'] ?? '';
        $data_update['periodtime'] = $periodtime ?? '';
        $data_update['start_date'] = $dataTour['tour_date_min'] ?? $dateNow;
        $data_update['end_date'] = $dataTour['tour_date_max'] ?? $dateNow;
        $data_update['c_newprice'] =  $dataTour['tour_price_start'] ?? '';
        //$data_update['shortcontent'] = $dataTour['tour_description'] ?? '';
        $data_update['start_date_sale'] = $dataTour['tour_date_min'] ?? '';
        $data_update['last_date'] =  $last_date ?? '';
        $data_update['end_date_sale'] = $dataTour['tour_date_max'] ?? '';
        $data_update['airline_code'] = $airlineId ?? '';
        $data_update['wscode_tour'] = $dataTour['tour_code'] ?? '';
        $data_update['user_create'] = "API 2U Center";
        $data_update['date_create'] = $dateNow;
        $data_update['user_update'] = 'API 2U Center';
        $data_update['date_update'] = $dateNow;
        $data_update['change_tm'] = date('Y-m-d H:i:s');
        $data_update['api_updated_at'] = $apiUpdatedAt;


        //Update ข้อมูลในตาราง tour_online
        $stmt = $conn->prepare("
            UPDATE tour_online SET
                periodtime       = ?,
                start_date       = ?,
                end_date         = ?,
                c_newprice       = ?,
                start_date_sale  = ?,
                last_date        = ?,
                end_date_sale    = ?,
                airline_code     = ?,
                wscode_tour      = ?,
                user_update      = ?,
                date_update      = ?,
                change_tm        = ?,
                api_updated_at   = ?
            WHERE code = ?
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed (updateTour): " . $conn->error);
        }

        $stmt->bind_param(
            "ssssssssssssss", // แก้ไขชนิดข้อมูลให้ตรง (14 ตัวแปร)
            $data_update['periodtime'],
            $data_update['start_date'],
            $data_update['end_date'],
            $data_update['c_newprice'],
            $data_update['start_date_sale'],
            $data_update['last_date'],
            $data_update['end_date_sale'],
            $data_update['airline_code'],
            $data_update['wscode_tour'],
            $data_update['user_update'],
            $data_update['date_update'],
            $data_update['change_tm'],
            $data_update['api_updated_at'],
            $tour_code // เปลี่ยนชนิดเป็น string
        );

        if (!$stmt->execute()) {
            throw new Exception("Error updating tour_online: " . $stmt->error);
        }
        // อัพโหลดไฟล์ PDF ถ้ามี
        if (!empty($file_pdf)) {

            uploadTourPDF($conn, $file_pdf, $tour_code);
        }
        // อัพโหลดไฟล์แบนเนอร์ ถ้ามี
        if (!empty($file_banner)) {

            uploadTourPicture($conn, $file_banner, $tour_code);
        }
        $stmt->close();
    }

    // ลบข้อมูลในตาราง touronline_detailday
    $stmt = $conn->prepare("DELETE FROM touronline_detailday WHERE tour_code = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $tour_code);
    $stmt->execute();
    $stmt->close();

    // Insert ใหม่ในตาราง touronline_detailday

    if (!empty($dataTourDay) && is_array($dataTourDay)) {

        $stmt = $conn->prepare("
            INSERT INTO touronline_detailday (
                tour_code,
                day_order,
                detail_day,
                user_create,
                date_create,
                user_update,
                date_update
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        foreach ($dataTourDay as $detailRow) {
            $tour_code = $tour_code ?? '';
            $day_order = $detailRow['day_num'] ?? '';
            $detail_day = $detailRow['day_topics'] ?? '';
            $user_create = 'API 2U Center';
            $date_create = DateNow();
            $user_update = 'API 2U Center';
            $date_update = DateNow();

            $stmt->bind_param(
                "iisssss",
                $tour_code,
                $day_order,
                $detail_day,
                $user_create,
                $date_create,
                $user_update,
                $date_update
            );

            if (!$stmt->execute()) {
                throw new Exception("Error inserting detail data: " . $stmt->error);
            }
        }

        $stmt->close();
    }

    // ลบข้อมูลในตาราง touronline_period
    $stmt = $conn->prepare("DELETE FROM touronline_period WHERE tour_code = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $tour_code);
    $stmt->execute();
    $stmt->close();

    // Insert ใหม่ในตาราง touronline_period

    if (!empty($dataTourPeriod) && is_array($dataTourPeriod)) {
        $stmt = $conn->prepare("
        INSERT INTO touronline_period (
            tour_code, pstartdate, penddate, busno, adult, child, child_n, inf, single, joinland,
            discount_adult, discount_child, discount_childno, commision, commisionsale, seat, balance,
            warranty, booking, faimai, note, pro, user_create, user_update, date_create, date_update
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        foreach ($dataTourPeriod as $periodRow) {
            // เก็บค่าไว้ในตัวแปรก่อน
            $pstartdate = $periodRow['period_date'] ?? '';
            $penddate = $periodRow['period_back'] ?? '';
            $balance = $periodRow['period_available'];
            if ($balance === 0) {
                $booking = 1;
            } else {
                $booking = 0;
            }
            $busno = 1;
            $adult = $periodRow['period_price_start'] ?? 0.00;
            $child = 0.00;
            $child_n = 0.00;
            $inf = 0.00;
            $single = (is_numeric($periodRow['period_price_start']) && is_numeric($periodRow['period_rate_adult_sgl']))
                ? max(0, $periodRow['period_rate_adult_sgl'] - $periodRow['period_price_start'])
                : 0;


            $joinland = 0.00;
            $discount_adult = $periodRow['period_discount'][0]['discount_price'] ?? 0;
            $discount_child = 0.00;
            $discount_childno = 0.00;

            $commision = 0.00;
            foreach ($periodRow['period_rate'] as $item) {
                $commision = $item['rate_commission'];
            }
            $commisionsale = 0.00;

            $seat = $periodRow['period_quota'] ?? 0;
            $warranty = $periodRow['period_quota'] ?? 0;
            $faimai = 'N';
            $note = $periodRow['period_code'] ?? '';
            $pro = '';
            $user_create = 'API 2U Center';
            $user_update = 'API 2U Center';
            $date_create = DateNow();
            $date_update = DateNow();

            $stmt->bind_param(
                'isssssssssssssssssssssssss', // เพิ่ม s สำหรับ pro
                $tour_code,
                $pstartdate,
                $penddate,
                $busno,
                $adult,
                $child,
                $child_n,
                $inf,
                $single,
                $joinland,
                $discount_adult,
                $discount_child,
                $discount_childno,
                $commision,
                $commisionsale,
                $seat,
                $balance,
                $warranty,
                $booking,
                $faimai,
                $note,
                $pro,
                $user_create,
                $user_update,
                $date_create,
                $date_update
            );

            if (!$stmt->execute()) {
                throw new Exception("Error inserting period data: " . $stmt->error);
            }
        }
        $stmt->close();
    }

    return true;
}
