<?php

function insertTour($conn, $detail, $tour_code): bool
{
    $dataTour = $detail['data'][0];
    $dataTourDay = $detail['data'][0]['tour_daily'];
    $dataTourPeriod =  $detail['data'][0]['tour_period'];
    $apiUpdatedAt = $detail['tour_update'];
    if (!empty($dataTour)) {

        $tourCode   = genTourCode($conn, (string) $dataTour['tour_country'][0]['country_code_2'], (string) $dataTour['tour_airline']['airline_iata']);

        $country  = getCountryCode($conn, $dataTour['tour_country'][0]['country_code_2'] ?? '');

        $airlineId  = getAirlineCode($conn, $dataTour['tour_airline']['airline_iata'] ?? '');

        $zoneCode   = $country['zone_code'];

        $periodtime = $dataTour['tour_num_day'] . 'วัน' . ' ' . $dataTour['tour_num_night'] . 'คืน';

        $lastPeriod = end($dataTourPeriod);

        $last_date  = $lastPeriod['period_back'];

        $file_pdf   = $dataTour['tour_file']['file_pdf'];

        $file_banner    = $dataTour['tour_file']['file_banner'];

        $tourId = 0;


        $data_insert = [];
        $data_insert['tour_code']        = $dataTour['tour_id'] ?? '';
        $data_insert['country_code']     = $country['country_code'] ?? '';
        $data_insert['zone_code']        = $zoneCode ?? '';
        $data_insert['enable']           = 'N';
        $data_insert['currency_code']    = 1;
        $data_insert['tourcode']         = $tourCode ?? '';
        $data_insert['name']             = $dataTour['tour_name'] ?? '';
        $data_insert['periodtime']       = $periodtime ?? '';
        $data_insert['start_date']       = $dataTour['tour_date_min'] ?? date('Y-m-d');
        $data_insert['end_date']         = $dataTour['tour_date_max'] ?? date('Y-m-d');
        $data_insert['n_hote']           = "";
        $data_insert['n_bag_weight']     = "";
        $data_insert['n_currency']       = "";
        $data_insert['n_saveprice']       = "";
        $data_insert['n_oriprice']       = "";
        $data_insert['n_newprice']       = "";
        $data_insert['c_newprice']       = $dataTour['tour_price_start'] ?? '';
        $data_insert['countryname']       = "";
        $data_insert['shortcontent']     = $dataTour['tour_description'] ?? '';
        $data_insert['wsname']           = '2U center';
        $data_insert['wholesale_code']   = '293';
        $data_insert['hot_status']       = "N";
        $data_insert['other_status']       = "";
        $data_insert['start_date_sale']  = $dataTour['tour_date_min'] ?? '';
        $data_insert['last_date']        = $last_date ?? '';
        $data_insert['end_date_sale']    = $dataTour['tour_date_max'] ?? '';
        $data_insert['view']          =  '0';
        $data_insert['enable_api']       = 'N';
        $data_insert['filepdf']          =  '';
        $data_insert['filebanner']       =  '';
        $data_insert['airlinename']      =  '';
        $data_insert['airline_code']     = $airlineId ?? '';
        $data_insert['wscode_tour']      = $dataTour['tour_code'] ?? '';
        $data_insert['credit_pro']          =  '0';
        $data_insert['pdf_status']          =  'N';
        $data_insert['faimaistatus']          =  'N';
        $data_insert['newbanner']          =  '';
        $data_insert['other_tour']          =  '0';
        $data_insert['description']          =  '';
        $data_insert['keywords']          =  '';
        $data_insert['landmark']          =  '';
        $data_insert['user_create']      = "API 2U Center";
        $data_insert['date_create']      = DateNow();
        $data_insert['user_update']      = 'API 2U Center';
        $data_insert['date_update']      = DateNow();
        $data_insert['change_tm']        = DateNow();
        $data_insert['soldout']        = "N";
        $data_insert['note']        = "N";
        $data_insert['api_updated_at'] = $apiUpdatedAt;
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
            $tour_code = $tourId ?? '';
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


    // insert periods

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
            $adult = is_numeric($periodRow['period_price_start']) ?? 0.00;
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
            $pro = 'N';
            $user_create = 'API 2U Center';
            $user_update = 'API 2U Center';
            $date_create = DateNow();
            $date_update = DateNow();

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
    }
    return true;
}
