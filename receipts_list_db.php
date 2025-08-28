<?php
error_reporting(0);
//check if the session is started, if not, start session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once("db.ini");

function getReceipts($type, $case_num, $match_or_like, $invoice, $is_paid, $initial, $bills_year, $bills_month, $payment_method, $payment_start, $payment_end, $receipt_year, $receipt_month, $application_num)
{
    // 資料庫連接
    $dblink = @pg_connect(DB_CONNECT);
    if (!$dblink) {
        return("無法連接到資料庫");
    }

    // 初始化條件陣列和參數陣列
    $conditions = [];
    $params = [];
    $param_index = 1; // 用於動態管理參數索引

    if ($type === 'list') {
        // 優先查詢申請單號
        if ($application_num !== '') {
            $sql = "WITH 
                    receipt_sum AS (
                        SELECT 
                            deb_num,
                            SUM(disbs) AS disbs_sum,
                            SUM(foreign_disbs) AS foreign_disbs_sum
                        FROM receipt
                        GROUP BY deb_num
                    ), 
                    show_as_legal AS ( 
                        SELECT 
                            deb_num,  
                            SUM(ntd_amount) AS show_sum,
                            SUM(foreign_amount2) AS foreign_show_sum 
                        FROM disbursements 
                        WHERE show_as_legal_service_flag='1' 
                        GROUP BY deb_num  
                    ) 
                    SELECT 
                        cases.case_num,
                        cases.wht_status,
                        cases.wht_model,
                        cases.wht_base,
                        bills.party_en_name_bills,
                        bills.billing_currency,
                        bills.deb_num,
                        bills.legal_services + COALESCE(show_as_legal.show_sum,0) AS legal_services, 
                        bills.disbs - COALESCE(show_as_legal.show_sum,0) AS disbs, 
                        bills.foreign_legal2 + COALESCE(show_as_legal.foreign_show_sum,0) AS foreign_legal2, 
                        bills.foreign_disbs2 - COALESCE(show_as_legal.foreign_show_sum,0) AS foreign_disbs2,
                        bills.total,
                        bills.foreign_total2,
                        bills.currency2,
                        bills.sent,
                        receipt_sec_deb.split_entity,
                        receipt_sec_deb.split_deb_num,
                        receipt_sec_deb.split_legal_services,
                        receipt_sec_deb.split_disbs,
                        COALESCE(receipt_sum.disbs_sum) AS disbs_sum,
                        COALESCE(receipt_sum.foreign_disbs_sum) AS foreign_disbs_sum,
                        COALESCE(show_as_legal.show_sum) AS show_as_legal_total, 
                        COALESCE(show_as_legal.foreign_show_sum)  AS foreign_show_as_legal_total
                        FROM cases
                        LEFT JOIN bills ON cases.case_num = bills.case_num 
                        LEFT JOIN receipt_sum ON bills.deb_num=receipt_sum.deb_num
                        LEFT JOIN receipt_sec_deb ON bills.deb_num=receipt_sec_deb.deb_num
                        LEFT JOIN show_as_legal ON bills.deb_num=show_as_legal.deb_num 
                        WHERE receipt_sec_deb.sec_id=$application_num
                        AND NOT EXISTS( SELECT 1 
                                        FROM receipt_sum 
                                        WHERE bills.deb_num=receipt_sum.deb_num 
                                        AND bills.disbs = receipt_sum.disbs_sum
                                        AND bills.foreign_disbs2 = receipt_sum.foreign_disbs_sum)
                        ORDER BY sent";
        } else {
            // case_num 條件
            if ($case_num !== '') {
                if ($match_or_like === 'match') {
                    $conditions[] = "cases.case_num = $" . $param_index;
                    $params[] = $case_num;
                    $param_index++;
                } elseif ($match_or_like === 'like') {
                    $conditions[] = "cases.case_num LIKE $" . $param_index;
                    $params[] = $case_num . '%';
                    $param_index++;
                }
            }

            // invoice 條件
            if ($invoice !== '') {
                $conditions[] = "bills.deb_num LIKE $" . $param_index;
                $params[] = $invoice;
                $param_index++;
            }

            // initial 條件
            if ($is_paid === 'unpaid' && $initial !== '') {
                $conditions[] = "bills.bills_case_manager = $" . $param_index;
                $params[] = $initial;
                $param_index++;
            }

            // 計算 sent 範圍
            if ($is_paid === 'unpaid' && $bills_year !== '' && $bills_month !== '') {
                // 如果使用 List 查詢，則尋找當月資料
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    $sent_start = sprintf("%s-%s-01", $bills_year, $bills_month);
                    $sent_end = sprintf("%s-%s-%s", $bills_year, $bills_month, date("t", mktime(0, 0, 0, $bills_month, 1, $bills_year)));
                } else { // 如果是網站初始畫面則顯示當天資料
                    $sent_start = date('Y-m-d');
                    $sent_end = date('Y-m-d');
                }
                
                $conditions[] = "sent >= $" . $param_index . " AND sent <= $" . ($param_index + 1);
                $params[] = $sent_start;
                $params[] = $sent_end;
                $param_index += 2;
            }

            // payment method 條件
            if ($is_paid === 'paid' && $payment_method !== '') {
                $conditions[] = "payments.method = $" . $param_index;
                $params[] = $payment_method;
                $param_index++;
            }

            // 計算 rec_date 範圍
            if ($is_paid === 'paid' && $payment_start !== '') {     
                $conditions[] = "rec_date >= $" . $param_index . " AND rec_date <= $" . ($param_index + 1);
                $params[] = $payment_start;
                $params[] = $payment_end;
                $param_index += 2;
            }

            // 組合條件
            $where_clause = count($conditions) > 0 ? implode(' AND ', $conditions) : '1=1'; // 如果沒有條件，使用 `1=1`

            // 根據 $is_paid 決定查詢內容
            if ($is_paid === 'unpaid') {
                $sql = "WITH receipt_sum AS (
                            SELECT 
                                deb_num,
                                SUM(disbs) AS disbs_sum,
                                SUM(foreign_disbs) AS foreign_disbs_sum,
                                SUM(legal_services) AS services_sum,
                                SUM(foreign_services) AS foreign_services_sum
                            FROM receipt
                            WHERE status = '1'
                            GROUP BY deb_num
                        ),
                        show_as_legal AS ( 
                            SELECT 
                                deb_num,  
                                SUM(ntd_amount) AS show_sum,
                                SUM(foreign_amount2) AS foreign_show_sum 
                            FROM disbursements 
                            WHERE show_as_legal_service_flag='1' 
                            GROUP BY deb_num  
                        ) 
                        SELECT 
                            cases.case_num,
                            cases.wht_status,
                            cases.wht_model,
                            cases.wht_base,
                            cases.receipt_tax_id,
                            bills.party_en_name_bills,
                            bills.billing_currency,
                            bills.deb_num,
                            bills.legal_services - COALESCE(receipt_sum.services_sum,0) + COALESCE(show_as_legal.show_sum,0) AS legal_services, 
                            bills.disbs - COALESCE(receipt_sum.disbs_sum,0) - COALESCE(show_as_legal.show_sum,0) AS disbs, 
                            bills.foreign_legal2 - COALESCE(receipt_sum.foreign_services_sum,0) + COALESCE(show_as_legal.foreign_show_sum,0) AS foreign_legal2, 
                            bills.foreign_disbs2 - COALESCE(receipt_sum.foreign_disbs_sum,0) - COALESCE(show_as_legal.foreign_show_sum,0) AS foreign_disbs2,
                            bills.total,
                            bills.foreign_total2,
                            bills.currency2,
                            bills.sent,
                            COALESCE(receipt_sum.disbs_sum) AS disbs_sum
                        FROM cases
                        LEFT JOIN bills ON cases.case_num = bills.case_num 
                        LEFT JOIN receipt_sum ON bills.deb_num = receipt_sum.deb_num
                        LEFT JOIN show_as_legal ON bills.deb_num = show_as_legal.deb_num 
                        WHERE $where_clause
                        AND NOT EXISTS( SELECT 1 
                                        FROM receipt_sum 
                                        WHERE bills.deb_num = receipt_sum.deb_num 
                                        AND 
                                            ((bills.disbs - COALESCE(show_as_legal.show_sum,0)) = receipt_sum.disbs_sum 
                                            OR (bills.foreign_disbs2 - COALESCE(show_as_legal.foreign_show_sum,0)) = receipt_sum.foreign_disbs_sum)
                                        AND 
                                            ((bills.legal_services + COALESCE(show_as_legal.show_sum,0)) = receipt_sum.services_sum 
                                            OR (bills.foreign_legal2 + COALESCE(show_as_legal.foreign_show_sum,0)) = receipt_sum.foreign_services_sum))
                        ORDER BY sent";
            } elseif ($is_paid === 'paid') {
                $sql = "WITH receipt_sum AS (
                            SELECT 
                                deb_num,
                                SUM(disbs) AS disbs_sum,
                                SUM(foreign_disbs) AS foreign_disbs_sum,
                                SUM(legal_services) AS services_sum,
                                SUM(foreign_services) AS foreign_services_sum
                            FROM receipt
                            WHERE status = '1'
                            GROUP BY deb_num
                        )
                        SELECT 
                            cases.case_num,
                            cases.receipt_tax_id,
                            bills.party_en_name_bills,
                            bills.deb_num,
                            bills.sent,
                            payments.method,
                            payments.with_tax,
                            payments.holding_tax,
                            payments.legal_services - COALESCE(receipt_sum.services_sum,0) AS legal_services,
                            payments.disbs - COALESCE(receipt_sum.disbs_sum,0) AS disbs,
                            payments.foreign_legal - COALESCE(receipt_sum.foreign_services_sum,0) as foreign_legal2,
                            payments.foreign_disbs - COALESCE(receipt_sum.foreign_disbs_sum,0) as foreign_disbs2,
                            payments.currency as currency2,
                            COALESCE(receipt_sum.disbs_sum) AS disbs_sum
                        FROM cases
                        LEFT JOIN bills ON cases.case_num = bills.case_num 
                        LEFT JOIN receipt_sum ON bills.deb_num = receipt_sum.deb_num
                        LEFT JOIN payments ON bills.deb_num = payments.deb_num
                        WHERE $where_clause
                        AND NOT EXISTS( SELECT 1 
                                        FROM receipt_sum 
                                        WHERE payments.deb_num = receipt_sum.deb_num 
                                        AND (payments.disbs = receipt_sum.disbs_sum OR payments.foreign_disbs = receipt_sum.foreign_disbs_sum)
                                        AND (payments.legal_services = receipt_sum.services_sum OR payments.foreign_legal = receipt_sum.foreign_services_sum))
                        ORDER BY deb_num";
            } else {
                return("無效的 is_paid 值");
            }
        }
    } elseif ($type === 'edit') {
        // case_num 條件
        if ($case_num !== '') {
            if ($match_or_like === 'match') {
                $conditions[] = "receipt.case_num = $" . $param_index;
                $params[] = $case_num;
                $param_index++;
            } elseif ($match_or_like === 'like') {
                $conditions[] = "receipt.case_num LIKE $" . $param_index;
                $params[] = $case_num . '%';
                $param_index++;
            }
        }

        // invoice 條件
        if ($invoice !== '') {
            $conditions[] = "receipt.deb_num LIKE $" . $param_index;
            $params[] = $invoice . '%';
            $param_index++;
        }

        // 計算 receipt 範圍
        if ($receipt_year !== '' && $receipt_month !== '') {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $receipt_start = sprintf("%s-%s-01", $receipt_year, $receipt_month);
                $receipt_end = sprintf("%s-%s-%s", $receipt_year, $receipt_month, date("t", mktime(0, 0, 0, $receipt_month, 1, $receipt_year)));
            } 
            $conditions[] = "receipt.receipt_date >= $" . $param_index . " AND receipt.receipt_date <= $" . ($param_index + 1);
            $params[] = $receipt_start;
            $params[] = $receipt_end;
            $param_index += 2;
        } else {
            return("請輸入 Receipt Month");
        }

        // 組合條件
        $where_clause = count($conditions) > 0 ? implode(' AND ', $conditions) : '1=1'; // 如果沒有條件，使用 `1=1`

        // 相同 receipt_num 的資料要合併金額
        $sql = "WITH RankedData AS (
                    -- 步驟 1: 選取所有需要的欄位，並加上一個排序編號
                    SELECT
                        receipt.receipt_entity,
                        receipt.case_num,
                        receipt.deb_num,
                        receipt.bills_sent,
                        receipt.receipt_num,
                        receipt.legal_services,
                        receipt.disbs,
                        receipt.total,
                        receipt.wht,
                        receipt.note_legal,
                        receipt.currency,
                        receipt.foreign_services,
                        receipt.foreign_disbs,
                        receipt.foreign_total,
                        receipt.foreign_wht,
                        receipt.note_disbs,
                        receipt.status,
                        bills.bills_case_manager,
                        cases.receipt_tax_id,
                        ROW_NUMBER() OVER(PARTITION BY receipt.receipt_num ORDER BY receipt.deb_num ASC) as rn
                    FROM
                        receipt
                    LEFT JOIN
                        bills ON receipt.case_num = bills.case_num AND receipt.deb_num = bills.deb_num
                    LEFT JOIN
                        cases ON receipt.case_num = cases.case_num
                    WHERE
                        $where_clause
                )
                -- 步驟 2: 從 CTE 中查詢最終結果
                SELECT
                    MAX(CASE WHEN rn = 1 THEN receipt_entity END) AS receipt_entity,
                    MAX(CASE WHEN rn = 1 THEN case_num END) AS case_num,
                    MAX(CASE WHEN rn = 1 THEN deb_num END) AS deb_num,
                    MAX(CASE WHEN rn = 1 THEN bills_sent END) AS bills_sent,
                    receipt_num,
                    MAX(CASE WHEN rn = 1 THEN note_legal END) AS note_legal,
                    MAX(CASE WHEN rn = 1 THEN currency END) AS currency,
                    MAX(CASE WHEN rn = 1 THEN note_disbs END) AS note_disbs,
                    MAX(CASE WHEN rn = 1 THEN status END) AS status,
                    MAX(CASE WHEN rn = 1 THEN bills_case_manager END) AS bills_case_manager,
                    MAX(CASE WHEN rn = 1 THEN receipt_tax_id END) AS receipt_tax_id,

                    SUM(legal_services) AS legal_services,
                    SUM(disbs) AS disbs,
                    SUM(total) AS total,
                    SUM(wht) AS wht,
                    SUM(foreign_services) AS foreign_services,
                    SUM(foreign_disbs) AS foreign_disbs,
                    SUM(foreign_total) AS foreign_total,
                    SUM(foreign_wht) AS foreign_wht
                FROM
                    RankedData
                GROUP BY
                    receipt_num
                ORDER BY
                    receipt_num;";
    }

    // 執行查詢
    $result = pg_query_params($dblink, $sql, $params);

    if (!$result) {
        return("查詢失敗: " . pg_last_error($dblink));
    }

    // 取得結果
    $receipts = [];
    while ($row = pg_fetch_assoc($result)) {
        $receipts[] = $row;
    }

    pg_close($dblink); // 關閉資料庫連接

    if (empty($receipts) && $sent_start !== date('Y-m-d')) {
        return '無資料';
    }

    return $receipts;
}

function getReceiptsDetail($is_paid, $deb_num, $receipt_num = null) {
    // 資料庫連接
    $dblink = @pg_connect(DB_CONNECT);
    if (!$dblink) {
        die("無法連接到資料庫");
    }

    $details = [];

    // === SQL 查詢 ===
    // 執行 list
    if ($is_paid !== null) {
        if ($is_paid === 'true') {
            $sql = "SELECT disbs_payments_id as id, 
                            case_num, 
                            \"date\", 
                            disb_name, 
                            amount as ntd_amount, 
                            currency as currency2, 
                            foreign_amount as foreign_amount2
                    FROM disbs_payments 
                    WHERE deb_num LIKE $1
                    AND disbs_payments_id NOT IN (
                        SELECT d.disbs_pay_id
                        FROM receipt_disbs d
                        JOIN (
                            SELECT r.receipt_num
                            FROM receipt r
                            WHERE r.status = 1
                            ORDER BY r.deb_num ASC
                            LIMIT 1
                        ) r1 ON d.receipt_num = r1.receipt_num
                    )";
        } elseif ($is_paid === 'false') {
            $sql = "SELECT id, case_num, \"date\", disb_name, ntd_amount, currency2, foreign_amount2 
                    FROM disbursements 
                    WHERE deb_num LIKE $1
                    AND id NOT IN (
                        SELECT d.disbs_ref_id
                        FROM receipt_disbs d
                        JOIN (
                            SELECT r.receipt_num
                            FROM receipt r
                            WHERE r.status = 1
                            ORDER BY r.deb_num ASC
                            LIMIT 1
                        ) r1 ON d.receipt_num = r1.receipt_num
                    )
                    AND show_as_legal_service_flag = '-1'";
        }

        // 使用參數化查詢以防止 SQL injection
        $result = pg_query_params($dblink, $sql, [$deb_num]);
        if (!$result) {
            pg_close($dblink); // 關閉資料庫連接
            die("查詢失敗: " . pg_last_error($dblink));
        }

        // 取得結果
        while ($row = pg_fetch_assoc($result)) {
            $details[] = $row;
        }
    } else { // 執行 edit
        // 取得該收據號碼在 receipt TABLE 裡所有代墊資料
        $sql = "SELECT disbs_ref_id, disbs_pay_id, table_name
                FROM receipt_disbs 
                WHERE receipt_num = $1";
        $disbs_result = pg_query_params($dblink, $sql, [$receipt_num]);

        if (!$disbs_result) {
            pg_close($dblink);
            die("查詢失敗: " . pg_last_error($dblink));
        }

        // 將查詢結果轉換為陣列
        $disbs_array = [];
        while ($row = pg_fetch_assoc($disbs_result)) {
            $disbs_array[] = $row;
        }

        // 檢查是否有資料
        if (empty($disbs_array)) {
            // 沒有找到資料的處理
            pg_close($dblink);
            return $details; // 或其他適當的處理
        }

        // 根據 table_name 決定查詢的資料表、欄位和 SQL
        if ($disbs_array[0]['table_name'] == 0) {
            $id_field = 'disbs_ref_id';
            $sql = "SELECT id, case_num, \"date\", disb_name, ntd_amount, currency2, foreign_amount2 
                    FROM disbursements 
                    WHERE id = $1";
        } else {
            $id_field = 'disbs_pay_id';
            $sql = "SELECT disbs_payments_id as id, 
                        case_num, 
                        \"date\", 
                        disb_name, 
                        amount as ntd_amount, 
                        currency as currency2, 
                        foreign_amount as foreign_amount2 
                    FROM disbs_payments 
                    WHERE disbs_payments_id = $1";
        }

        // 查詢詳細資料
        foreach ($disbs_array as $disbs) {
            $result = pg_query_params($dblink, $sql, [$disbs[$id_field]]);
            
            if (!$result) {
                pg_close($dblink);
                die("查詢失敗: " . pg_last_error($dblink));
            }
            
            while ($row = pg_fetch_assoc($result)) {
                $details[] = $row;
            }
        }
    }

    pg_close($dblink); // 關閉資料庫連接
    return $details;
}
?>