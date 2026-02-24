
<?php

/**
 * INSERT ข้อมูลทัวร์ลงตาราง tour_online
 *
 * - ใช้ข้อมูลจาก $log
 * - โยน exception หาก insert ไม่สำเร็จ
 * - ไม่จัดการ retry / log state
 */
function insertTour(mysqli $conn, array $data, string $log): string
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

        $file_pdf   = $tour['pdfUrl'];

        $file_banner = $tour['bannerUrl'];

        $apiUpdateAt = $tour['api_updated_at'];

        $tourId = 0;

        $data_insert = [];
        $data_insert['tour_code']        = $tour['tour_code'] ?? '';
        $data_insert['country_code']     = $country['country_code'] ?? '';
        $data_insert['zone_code']        = $zoneCode ?? '';
        $data_insert['enable']           = 'N';
        $data_insert['currency_code']    = 1;
        $data_insert['tourcode']         = $tourCode ?? '';
        $data_insert['name']             = $tour['name'] ?? '';
        $data_insert['periodtime']       = $tour['periodtime'] ?? '';
        $data_insert['start_date']       = $dateNow;
        $data_insert['end_date']         = $dateNow;
        $data_insert['n_hote']           = "";
        $data_insert['n_bag_weight']     = "";
        $data_insert['n_currency']       = "";
        $data_insert['n_saveprice']      = "";
        $data_insert['n_oriprice']       = "";
        $data_insert['n_newprice']       = "";
        $data_insert['c_newprice']       = $tour['c_newprice'] ?? '';
        $data_insert['countryname']      = "";
        $data_insert['shortcontent']     = $tour['shortcontent'] ?? '';
        $data_insert['wsname']           = 'Superb';
        $data_insert['wholesale_code']   = '32';
        $data_insert['hot_status']       = "N";
        $data_insert['other_status']     = "";
        $data_insert['start_date_sale']  = $tour['start_date_sale'] ?? '';
        $data_insert['last_date']        = $tour['last_date'] ?? '';
        $data_insert['end_date_sale']    = $tour['end_date_sale'] ?? '';
        $data_insert['view']             =  '0';
        $data_insert['enable_api']       = 'N';
        $data_insert['filepdf']          =  '';
        $data_insert['filebanner']       =  '';
        $data_insert['airlinename']      =  '';
        $data_insert['airline_code']     = $airlineId ?? '';
        $data_insert['wscode_tour']      = $tour['wscode_tour'] ?? '';
        $data_insert['credit_pro']       =  '0';
        $data_insert['pdf_status']       =  'N';
        $data_insert['faimaistatus']     =  'N';
        $data_insert['newbanner']        =  '';
        $data_insert['other_tour']       =  '0';
        $data_insert['description']      =  '';
        $data_insert['keywords']         =  '';
        $data_insert['landmark']         =  '';
        $data_insert['user_create']      = "API Superb";
        $data_insert['date_create']      = $dateNow;
        $data_insert['user_update']      = 'API Superb';
        $data_insert['date_update']      = $dateNow;
        $data_insert['change_tm']        = $dateNow;
        $data_insert['soldout']          = "N";
        $data_insert['note']             = "N";
        $data_insert['api_updated_at']   = $apiUpdateAt;

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

            if (!isset($detailRow['day_num'])) {
                // log error ตรงนี้ได้เลย
                continue;
            }
       
            $tour_code = $tourId ?? '';
            $day_order = $detailRow['day_num'] ?? '';
            $detail_day = $detailRow['day_topics'] ?? '';
            $user_create = 'API Superb';
            $date_create = $dateNow;
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

            $busno = $periodRow['bus'] ?? 0;
            $adult = $periodRow['price_adult'] ?? 0.00;
            $child = $periodRow['child'] ?? 0.00;
            $child_n = $periodRow['child_n'] ?? 0.00;
            $inf = 0.00;
            $single = $periodRow['price_single'] ?? 0.00;
            $joinland = 0.00;
            $discount_adult = 0;
            $discount_child = 0.00;
            $discount_childno = 0.00;
            $commision = $periodRow['price_single'] ?? 0.00;
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
                'isssssssssssssssssssssssss',
                $tourId,
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
        updateTourError($conn, $tourId, '4', 'no_peroid_data');
    }

    return true;
}
