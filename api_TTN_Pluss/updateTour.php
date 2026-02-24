<?php
function updateTour(mysqli $conn, array $data, int $code): bool
{

    $tour = $data['tour'];
    $periods = $data['period'];
    $detailDays = $data['detailDay'];
    $dateNow = DateNow();
    $dataTourEnable = checkTourEnable($conn, $code);

    if (!empty($tour)) {

        //$tourCode   = genTourCode($conn, (string) $tour['country_code'], (string) $tour['airline_iata']);

        $country  = getCountryCode($conn, $tour['country_code'] ?? '');

        $airlineId  = getAirlineCode($conn, $tour['airline_iata'] ?? '');
        $dataTourEnable  = checkTourEnable($conn, $code);
        $zoneCode   = $country['zone_code'];

        $file_pdf   = $tour['pdf_url'];

        $file_banner    = $tour['banner_url'];
        $first = reset($periods);
        $last  = end($periods);
        $sata_data_sale = $first['due_start'];
        $last_date = $last['due_end'];
        $end_date_sale = $last['due_start'];
        $tourUpdate = $data['updated_at'];
        $tour_code = $code;

        $data_update = [];
        //$data_update['country_code'] =  $country['country_code'] ?? '';
        //$data_update['zone_code'] = $zoneCode ?? '';
        //$data_update['enable'] = $dataTourEnable ? 'Y' : 'N';
        //$data_update['name'] =  $tour['tour_name'] ?? '';
        $data_update['periodtime'] = $tour['periodtime'] ?? '';
        $data_update['start_date'] =  $sata_data_sale ?? '';
        $data_update['end_date'] =  $end_date_sale ?? '';
        $data_update['c_newprice'] =  $tour['tour_price'] ?? '';
        //$data_update['shortcontent'] =  '';
        $data_update['start_date_sale'] =  $sata_data_sale ?? '';
        $data_update['last_date'] =  $last_date ?? '';
        $data_update['end_date_sale'] = $end_date_sale ?? '';
        //$data_update['airline_code'] = $airlineId ?? '';
        $data_update['wscode_tour'] = $tour['tour_code'] ?? '';
        $data_update['user_create'] = "API TTN Plus";
        $data_update['date_create'] = $dateNow;
        $data_update['user_update'] = 'API TTN Plus';
        $data_update['date_update'] = $dateNow;
        $data_update['change_tm'] = $dateNow;
        $data_update['api_updated_at'] = $tourUpdate;

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
           "sssssssssssss",
            $data_update['periodtime'],
            $data_update['start_date'],
            $data_update['end_date'],
            $data_update['c_newprice'],
            $data_update['start_date_sale'],
            $data_update['last_date'],
            $data_update['end_date_sale'],
            $data_update['wscode_tour'],
            $data_update['user_update'],
            $data_update['date_update'],
            $data_update['change_tm'],
            $data_update['api_updated_at'],
            $tour_code
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
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $stmt->close();

    // Insert ใหม่ในตาราง touronline_detailday

    if (!empty($detailDays) && is_array($detailDays)) {

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

        foreach ($detailDays as $detailRow) {

            if (empty($detailRow['day_no'])) {
                error_log('Missing day_no: ' . json_encode($detailRow));
                continue;
            }

            $tour_code   = (int)$code;
            $day_order   = (int)$detailRow['day_no'];
            $detail_day  = $detailRow['itinerary'] ?? '';
            $user_create = 'API TTN Plus';
            $date_create = $detailRow['updated_at'] ?? $dateNow;
            $user_update = 'API Superb';
            $date_update = $dateNow;

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
    } else {
        updateTourError($conn, $code, '3', 'no_day_data');
    }

    // ลบข้อมูลในตาราง touronline_period
    $stmt = $conn->prepare("DELETE FROM touronline_period WHERE tour_code = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $tour_code);
    $stmt->execute();
    $stmt->close();

    // Insert ใหม่ในตาราง touronline_period

    if (!empty($periods) && is_array($periods)) {
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

        foreach ($periods as $periodRow) {

            // === CAST TYPE ให้ชัด ===
            $bookingRaw   = (int)($periodRow['booking'] ?? 0);
            $availableRaw = (int)($periodRow['available'] ?? 0);

            $pstartdate = $periodRow['due_start'] ?? null;
            $penddate   = $periodRow['due_end'] ?? null;

            // === คำนวณที่นั่ง ===
            $seat    = $bookingRaw + $availableRaw;
            $balance = $availableRaw;

            // === สถานะ booking ===
            // 0 = ยังว่าง, 1 = เต็ม
            if ($availableRaw === 0) {
                $booking = 1;
            } else {
                $booking = 0;
            }


            $busno = 1;

            // === ราคา ===
            $adult  = (float)($periodRow['price_adult'] ?? 0);
            $child  = (float)($periodRow['price_child'] ?? 0);
            $child_n = 0.00;
            $inf    = 0.00;
            $single = (float)($periodRow['price_single'] ?? 0);
            $joinland = 0.00;

            // === ส่วนลด / คอม ===
            $discount_adult = 0.00;
            $discount_child = 0.00;
            $discount_childno = 0.00;
            $commision = 0.00;
            $commisionsale = 0.00;

            $warranty = $bookingRaw + $availableRaw;
            $faimai = 'N';
            $note = '';
            $pro = 'N';

            $user_create = 'API TTN Plus';
            $user_update = 'API TTN Plus';
            $date_create = $periodRow['updated_at'] ?? $dateNow;
            $date_update = $dateNow;

            $stmt->bind_param(
                'issidddddddddddiiissssssss',
                $code,          // i
                $pstartdate,      // s
                $penddate,        // s
                $busno,           // i
                $adult,           // d
                $child,           // d
                $child_n,         // d
                $inf,             // d
                $single,          // d
                $joinland,        // d
                $discount_adult,  // d
                $discount_child,  // d
                $discount_childno, // d
                $commision,       // d
                $commisionsale,   // d
                $seat,            // i
                $balance,         // i
                $warranty,        // i
                $booking,         // i
                $faimai,          // s
                $note,            // s
                $pro,             // s
                $user_create,     // s
                $user_update,     // s
                $date_create,     // s
                $date_update      // s
            );

            if (!$stmt->execute()) {
                throw new Exception("Error inserting period data: " . $stmt->error);
            }
        }
        $stmt->close();
        // === เคลียร์ memory หลังจบ 1 tour ===
        unset($stmt);
        unset($periods);
        unset($detailDays);
        unset($tourData);
        unset($apiResult);
        unset($bin);
        gc_collect_cycles();
    }  else {
        updateTourError($conn, $code, '4', 'no_peroid_data');
    }
    return true;
}
