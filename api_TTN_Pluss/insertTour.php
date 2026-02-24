
<?php

/**
 * INSERT ข้อมูลทัวร์ลงตาราง tour_online
 *
 * - ใช้ข้อมูลจาก $log
 * - โยน exception หาก insert ไม่สำเร็จ
 * - ไม่จัดการ retry / log state
 */
function insertTour(mysqli $conn, array $data): string
{

    $tour = $data['tour'];
    $periods = $data['period'];
    $detailDays = $data['detailDay'];
    $dateNow = DateNow();

    if (!empty($tour)) {

        $tourCode   = genTourCode($conn, (string) $tour['country_code'], (string) $tour['airline_iata']);

        $country  = getCountryCode($conn, $tour['country_code'] ?? '');

        $airlineId  = getAirlineCode($conn, $tour['airline_iata'] ?? '');

        $zoneCode   = $country['zone_code'];

        $file_pdf   = $tour['pdf_url'];
        $tourUpdate = $data['updated_at'];
        $file_banner    = $tour['banner_url'];
        $first = reset($periods);
        $last  = end($periods);
        $sata_data_sale = $first['due_start'];
        $last_date = $last['due_end'];
        $end_date_sale = $last['due_start'];
        $tourId = 0;

        $data_insert = [];
        $data_insert['tour_code']        = $tour['tour_id'] ?? '';
        $data_insert['country_code']     = $country['country_code'] ?? '';
        $data_insert['zone_code']        = $zoneCode ?? '';
        $data_insert['enable']           = 'N';
        $data_insert['currency_code']    = 1;
        $data_insert['tourcode']         = $tourCode ?? '';
        $data_insert['name']             = $tour['tour_name'] ?? '';
        $data_insert['periodtime']       = $tour['periodtime'] ?? '';
        $data_insert['start_date']       = $sata_data_sale ?? '';
        $data_insert['end_date']         = $end_date_sale ?? '';
        $data_insert['n_hote']           = "";
        $data_insert['n_bag_weight']     = "";
        $data_insert['n_currency']       = "";
        $data_insert['n_saveprice']      = "";
        $data_insert['n_oriprice']       = "";
        $data_insert['n_newprice']       = "";
        $data_insert['c_newprice']       = $tour['tour_price'] ?? '';
        $data_insert['countryname']      = "";
        $data_insert['shortcontent']     = '';
        $data_insert['wsname']           = 'TTN Plus';
        $data_insert['wholesale_code']   = '266';
        $data_insert['hot_status']       = "N";
        $data_insert['other_status']     = "";
        $data_insert['start_date_sale']  = $sata_data_sale;
        $data_insert['last_date']        = $last_date;
        $data_insert['end_date_sale']    = $end_date_sale;
        $data_insert['view']             =  '0';
        $data_insert['enable_api']       = 'N';
        $data_insert['filepdf']          =  '';
        $data_insert['filebanner']       =  '';
        $data_insert['airlinename']      =  '';
        $data_insert['airline_code']     = $airlineId ?? '';
        $data_insert['wscode_tour']      = $tour['tour_code'] ?? '';
        $data_insert['credit_pro']       =  '0';
        $data_insert['pdf_status']       =  'N';
        $data_insert['faimaistatus']     =  'N';
        $data_insert['newbanner']        =  '';
        $data_insert['other_tour']       =  '0';
        $data_insert['description']      =  '';
        $data_insert['keywords']         =  '';
        $data_insert['landmark']         =  '';
        $data_insert['user_create']      = "API TTN Plus";
        $data_insert['date_create']      = $dateNow;
        $data_insert['user_update']      = 'API TTN Plus';
        $data_insert['date_update']      = $dateNow;
        $data_insert['change_tm']        = $dateNow;
        $data_insert['soldout']          = "N";
        $data_insert['note']             = "N";
        $data_insert['api_updated_at']   = $tourUpdate;

        foreach ($data_insert as $k => $v) {
            $data_insert[$k] = $conn->real_escape_string($v);
        }

        $sql = "
            INSERT INTO tour_online (
                tour_code,
                country_code,
                zone_code,
                enable,
                currency_code,
                tourcode,
                name,
                periodtime,
                start_date,
                end_date,
                n_hotel,
                n_bag_weight,
                n_currency,
                n_saveprice,
                n_oriprice,
                n_newprice,
                c_newprice,
                countryname,
                shortcontent,
                wsname,
                wholesale_code,
                hot_status,
                other_status,
                start_date_sale,
                last_date,
                end_date_sale,
                enable_api,
                view,
                filepdf,
                filebanner,
                airlinename,
                airline_code,
                wscode_tour,
                credit_pro,
                pdf_status,
                faimaistatus,
                newbanner,
                other_tour,
                description,
                keywords,
                landmark,
                user_create,
                date_create,
                user_update,
                date_update,
                change_tm,
                soldout,
                note,
                api_updated_at
            ) VALUES (
                '{$data_insert['tour_code']}',
                '{$data_insert['country_code']}',
                '{$data_insert['zone_code']}',
                '{$data_insert['enable']}',
                '{$data_insert['currency_code']}',
                '{$data_insert['tourcode']}',
                '{$data_insert['name']}',
                '{$data_insert['periodtime']}',
                '{$data_insert['start_date']}',
                '{$data_insert['end_date']}',
                '{$data_insert['n_hote']}',
                '{$data_insert['n_bag_weight']}',
                '{$data_insert['n_currency']}',
                '{$data_insert['n_saveprice']}',
                '{$data_insert['n_oriprice']}',
                '{$data_insert['n_newprice']}',
                '{$data_insert['c_newprice']}',
                '{$data_insert['countryname']}',
                '{$data_insert['shortcontent']}',
                '{$data_insert['wsname']}',
                '{$data_insert['wholesale_code']}',
                '{$data_insert['hot_status']}',
                '{$data_insert['other_status']}',
                '{$data_insert['start_date_sale']}',
                '{$data_insert['last_date']}',
                '{$data_insert['end_date_sale']}',
                '{$data_insert['enable_api']}',
                '{$data_insert['view']}',
                '{$data_insert['filepdf']}',
                '{$data_insert['filebanner']}',
                '{$data_insert['airlinename']}',
                '{$data_insert['airline_code']}',
                '{$data_insert['wscode_tour']}',
                '{$data_insert['credit_pro']}',
                '{$data_insert['pdf_status']}',
                '{$data_insert['faimaistatus']}',
                '{$data_insert['newbanner']}',
                '{$data_insert['other_tour']}',
                '{$data_insert['description']}',
                '{$data_insert['keywords']}',
                '{$data_insert['landmark']}',
                '{$data_insert['user_create']}',
                '{$data_insert['date_create']}',
                '{$data_insert['user_update']}',
                '{$data_insert['date_update']}',
                '{$data_insert['change_tm']}',
                '{$data_insert['soldout']}',
                '{$data_insert['note']}',
                '{$data_insert['api_updated_at']}'
            )
        ";

        $result = $conn->query($sql);
        if ($result === TRUE) {
            $tourId = $conn->insert_id;


            // อัพโหลดไฟล์ PDF ถ้ามี
            if (!empty($file_pdf)) {

                uploadTourPDF($conn, $file_pdf, $tourId);
            }
            // อัพโหลดไฟล์แบนเนอร์ ถ้ามี
            if (!empty($file_banner)) {

                uploadTourPicture($conn, $file_banner, $tourId);
            }
        }
    }

    // insert detailsDay
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

            $tour_code   = (int)$tourId;
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
        updateTourError($conn, $tourId, '3', 'no_day_data');
    }


    // insert periods

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
            $commision = (float)($periodRow['commission'] ?? 0);
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
                $tourId,          // i
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
    } else {
        updateTourError($conn, $tourId, '4', 'no_peroid_data');
    }

    return $tourId;
}
