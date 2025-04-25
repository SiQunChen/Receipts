<?php
error_reporting(0);
//check if the session is started, if not, start session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once("db.ini");

function getReceipts($type, $case_num, $match_or_like, $invoice, $initial, $bills_year, $bills_month, $is_paid, $receipt_year, $receipt_month, $application_num)
{
    // 資料庫連接
    $dblink = @pg_pconnect(DB_CONNECT);
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
                $params[] = $invoice . '%';
                $param_index++;
            }

            // initial 條件
            if ($initial !== '') {
                $conditions[] = "bills.bills_case_manager = $" . $param_index;
                $params[] = $initial;
                $param_index++;
            }

            // 計算 sent 範圍
            if ($bills_year !== '' && $bills_month !== '') {
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
                            COALESCE(receipt_sum.disbs_sum) AS disbs_sum,
                            COALESCE(receipt_sum.foreign_disbs_sum) AS foreign_disbs_sum,
                            COALESCE(receipt_sum.services_sum) AS services_sum,
                            COALESCE(receipt_sum.foreign_services_sum) AS foreign_services_sum,
                            COALESCE(show_as_legal.show_sum) AS show_as_legal_total, 
                            COALESCE(show_as_legal.foreign_show_sum)  AS foreign_show_as_legal_total
                            FROM cases
                            LEFT JOIN bills ON cases.case_num = bills.case_num 
                            LEFT JOIN receipt_sum ON bills.deb_num=receipt_sum.deb_num
                            LEFT JOIN show_as_legal ON bills.deb_num=show_as_legal.deb_num 
                            WHERE $where_clause
                            AND NOT EXISTS( SELECT 1 
                                            FROM receipt_sum 
                                            WHERE bills.deb_num=receipt_sum.deb_num 
                                            AND bills.disbs = receipt_sum.disbs_sum
                                            AND bills.foreign_disbs2 = receipt_sum.foreign_disbs_sum
                                            AND bills.legal_services = receipt_sum.services_sum
                                            AND bills.foreign_legal2 = receipt_sum.foreign_services_sum)
                            ORDER BY sent";
            } elseif ($is_paid === 'paid') {
                $sql = "SELECT 
                            cases.case_num,
                            cases.wht_status,
                            cases.wht_model,
                            cases.wht_base,
                            bills.party_en_name_bills,
                            bills.billing_currency,
                            payments.method,
                            payments.legal_services,
                            payments.disbs,
                            payments.foreign_legal,
                            payments.foreign_disbs
                        FROM cases
                        LEFT JOIN bills ON cases.case_num = bills.case_num
                        LEFT JOIN payments ON bills.deb_num = payments.deb_num
                        WHERE $where_clause";
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

        // initial 條件
        if ($initial !== '') {
            $conditions[] = "bills.bills_case_manager = $" . $param_index;
            $params[] = $initial;
            $param_index++;
        }

        // 計算 receipt 範圍
        if ($receipt_year !== '' && $receipt_month !== '') {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $receipt_start = sprintf("%s-%s-01", $receipt_year, $receipt_month);
                $receipt_end = sprintf("%s-%s-%s", $receipt_year, $receipt_month, date("t", mktime(0, 0, 0, $receipt_month, 1, $receipt_year)));
            } 
            $conditions[] = "receipt.bills_sent >= $" . $param_index . " AND receipt.bills_sent <= $" . ($param_index + 1);
            $params[] = $receipt_start;
            $params[] = $receipt_end;
            $param_index += 2;
        } else {
            return("請輸入 Receipt Month");
        }

        // 組合條件
        $where_clause = count($conditions) > 0 ? implode(' AND ', $conditions) : '1=1'; // 如果沒有條件，使用 `1=1`

        $sql = "SELECT 
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
                    bills.bills_case_manager
                FROM receipt
                LEFT JOIN bills ON receipt.case_num = bills.case_num AND receipt.deb_num = bills.deb_num
                WHERE $where_clause
                ORDER BY receipt.receipt_num";
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

function getReceiptsDetail($deb_num) {
    // 資料庫連接
    $dblink = @pg_pconnect(DB_CONNECT);
    if (!$dblink) {
        die("無法連接到資料庫");
    }

    // SQL 查詢
    $sql = "SELECT id, case_num, \"date\", disb_name, ntd_amount, currency2, foreign_amount2 
            FROM disbursements 
            WHERE deb_num LIKE $1
            AND id NOT IN (SELECT disbs_ref_id FROM receipt_disbs)
            AND show_as_legal_service_flag = '-1'";

    // 使用參數化查詢以防止 SQL 注入
    $result = pg_query_params($dblink, $sql, [$deb_num]);
    if (!$result) {
        pg_close($dblink); // 關閉資料庫連接
        die("查詢失敗: " . pg_last_error($dblink));
    }

    // 取得結果
    $details = [];
    while ($row = pg_fetch_assoc($result)) {
        $details[] = $row;
    }

    pg_close($dblink); // 關閉資料庫連接
    return $details;
}
