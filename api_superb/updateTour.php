<?php
function updateTour(mysqli $conn, array $data, string $code): bool
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

        $zoneCode   = $country['zone_code'];

        $file_pdf   = $tour['pdfUrl'];

        $file_banner    = $tour['bannerUrl'];

        $apiUpdateAt = $tour['api_updated_at'];

        $tour_code = $code;

        $data_update = [];
        $data_update['country_code'] =  $country ?? '';
        $data_update['zone_code'] = $zoneCode ?? '';
        //$data_update['enable'] = $dataTourEnable ? 'Y' : 'N';
        $data_update['name'] =  $tour['name'] ?? '';
        $data_update['periodtime'] = $tour['periodtime'] ?? '';
        $data_update['start_date'] =  $dateNow;
        $data_update['end_date'] =  $dateNow;
        $data_update['c_newprice'] =  $tour['c_newprice'] ?? '';
        $data_update['shortcontent'] = $tour['shortcontent'] ?? '';
        $data_update['start_date_sale'] =  $tour['start_date_sale'] ?? '';
        $data_update['last_date'] =  $tour['last_date'] ?? '';
        $data_update['end_date_sale'] = $tour['end_date_sale'] ?? '';
        $data_update['airline_code'] = $airlineId ?? '';
        $data_update['wscode_tour'] = $tour['wscode_tour'] ?? '';
        $data_update['user_create'] = "API Superb";
        $data_update['date_create'] = $dateNow;
        $data_update['user_update'] = 'API Superb';
        $data_update['date_update'] = $dateNow;
        $data_update['change_tm'] = $dateNow;
        $data_update['api_updated_at'] = $apiUpdateAt;

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

            if (!isset($detailRow['day_num'])) {
                // log error ตรงนี้ได้เลย
                continue;
            }
       
            
            $day_order = $detailRow['day_num'] ?? '';
            $detail_day = $detailRow['day_topics'] ?? '';
            $user_create = 'API Superb';
            $date_create = $dateNow;
            $user_update = 'API Superb';
            $date_update = $dateNow;

            $stmt->bind_param(
                "sisssss",
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
            // เก็บค่าไว้ในตัวแปรก่อน
            $pstartdate = $periodRow['date_start'] ?? '';
            $penddate = $periodRow['date_end'] ?? '';
            $balance = 0;
            $booking = (int)($periodRow['booking']) ?? 0;
            if ($booking === 0) {
                $booking = 0;
                $balance = $periodRow['avbl'];
            }

            if ($booking === 4 || $booking === 14 || $booking === 15 || $booking === 16 || $booking === 20) {
                $booking = 1;
                $balance = 0; //บังคับเลย
            }

           
            $busno =  $periodRow['bus'] ?? 0;
            $adult = $periodRow['price_adult'] ?? 0.00;
            $child = $periodRow['child'] ?? 0.00;
            $child_n = $periodRow['child_n'] ?? 0.00;
            $inf = 0.00;
            $single = $periodRow['price_single'] ?? 0.00;
            $joinland = 0.00;
            $discount_adult = 0;
            $discount_child = 0.00;
            $discount_childno = 0.00;
            $commision = $periodRow['com'] ?? 0.00;
            $commisionsale = 0.00;
            $seat = $periodRow['seat'] ?? 0;
            $warranty = $seat;
            $faimai = 'N';
            $note = '';
            $pro = 'N';
            $user_create = 'API Superb';
            $user_update = 'API Superb';
            $date_create = $dateNow;
            $date_update = $dateNow;

            $stmt->bind_param(
                'ssssssssssssssssssssssssss',
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
    } else {
        updateTourError($conn, $code, '4', 'no_peroid_data');
    }

    return true;
}
