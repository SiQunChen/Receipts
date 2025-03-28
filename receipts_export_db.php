<?php
ob_start(); 
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.ini';
require_once '../vendor/autoload.php';
require_once 'receipts_list_db.php';

global $dblink;
$dblink = pg_pconnect(DB_CONNECT);
if (!$dblink) {
    exit("無法連接到資料庫: " . pg_last_error());
}

/**
 * 記錄資料到 receipt 資料庫
 */
function recordReceiptTable($entity, $case_num, $wht_status, $wht_model, $wht_base, $deb_num, $sent, $legal_services, $disbs, $wht, $note_legal, $billing_currency, $currency, $foreign_services, $foreign_disbs, $note_disbs, $uncheckedDisbsData, $disbs_sum, $foreign_disbs_sum) {
    global $dblink;

    if (!$dblink) {
        return "無法連接到資料庫";
    }

    pg_query($dblink, "BEGIN");

    try {
        // 取得當前年份與月份 (兩位數格式)
        $year = date('y');
        $month = date('m');
        $like_pattern = "R{$year}{$month}%";
        
        // 查詢當月最新的收據編號
        $query = "SELECT receipt_num FROM receipt WHERE receipt_num LIKE $1 ORDER BY receipt_num DESC LIMIT 1";
        $result = pg_query_params($dblink, $query, [$like_pattern]);

        // 計算新的流水號
        $latest_serial = ($result && pg_num_rows($result) > 0)
            ? (int)substr(pg_fetch_result($result, 0, 'receipt_num'), 5) + 1
            : 1;
        
        $new_receipt_num = sprintf("R%s%s%04d", $year, $month, $latest_serial);

        // 正在部分銷帳
        if (isset($uncheckedDisbsData[$deb_num])) {
            foreach ($uncheckedDisbsData[$deb_num] as $data) {
                $disbs -= $data['amount'];
                $foreign_disbs -= $data['foreign_amount'];
            }
        }

        // 先前已部分銷帳，資料庫已存在資料
        if ($disbs_sum !== null) {
            $disbs -= $disbs_sum;
            $foreign_disbs -= $foreign_disbs_sum;
            $legal_services = 0;
            $foreign_services = 0;
        }

        $total = $disbs + $legal_services;
        $foreign_total = $foreign_disbs + $foreign_services;

        // 根據幣別決定要存入的欄位
        if ($billing_currency == 'English (USD)' || $billing_currency == 'English (EUR)') {
            // 計算 wht
            $foreign_wht = $wht;
            if ($wht_status === '1') {
                $amount = ($wht_base === '1') ? $legal_services : $total;
                $wht = ($amount >= $wht_model) ? floor($amount * 0.1) : 0;
            } else {
                $wht = 0;
            }

            $insertQuery = "INSERT INTO receipt (
                receipt_entity, case_num, deb_num, bills_sent, receipt_num, receipt_date,
                legal_services, disbs, total, wht, currency, foreign_services, foreign_disbs, 
                foreign_total, foreign_wht, note_legal, note_disbs, currency_status
            ) VALUES (
                $1, $2, $3, $4, $5, CURRENT_DATE, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17
            )";

            $params = [$entity, $case_num, $deb_num, $sent, $new_receipt_num, $legal_services, $disbs, $total, $wht, $currency, $foreign_services, $foreign_disbs, $foreign_total, $foreign_wht, $note_legal, $note_disbs, 2];
        } else {
            // 計算 foreign_wht
            if ($wht_status === '1') {
                $amount = ($wht_base === '1') ? $foreign_services : $foreign_total;
                $foreign_wht = ($amount >= $wht_model) ? floor($amount * 0.1 * 100) / 100 : 0;
            } else {
                $foreign_wht = 0;
            }

            $insertQuery = "INSERT INTO receipt (
                receipt_entity, case_num, deb_num, bills_sent, receipt_num, receipt_date,
                legal_services, disbs, total, wht, foreign_services, foreign_disbs,
                foreign_total, foreign_wht, note_legal, note_disbs, currency_status
            ) VALUES (
                $1, $2, $3, $4, $5, CURRENT_DATE, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16
            )";

            $params = [$entity, $case_num, $deb_num, $sent, $new_receipt_num, $legal_services, $disbs, $total, $wht, $foreign_services, $foreign_disbs, $foreign_total, $foreign_wht, $note_legal, $note_disbs, 1];
        }

        $insertResult = pg_query_params($dblink, $insertQuery, $params);
        if (!$insertResult) {
            throw new Exception("新增 receipt 失敗: " . pg_last_error($dblink));
        }

        // 插入 receipt_disbs
        $receiptDisbsResult = recordReceiptDisbsTable($deb_num, $new_receipt_num, $uncheckedDisbsData);
        if ($receiptDisbsResult !== true) {
            throw new Exception("新增 receipt_disbs 失敗: " . $receiptDisbsResult);
        }

        pg_query($dblink, "COMMIT");
        return true;

    } catch (Exception $e) {
        pg_query($dblink, "ROLLBACK");
        return $e->getMessage();
    }
}

/**
 * 記錄資料到 receipt_disbs 資料庫
 */
function recordReceiptDisbsTable($deb_num, $receipt_num, $uncheckedDisbsData) {
    global $dblink;

    if (!$dblink) {
        return "無法連接到資料庫";
    }

    $results = getReceiptsDetail($deb_num);

    foreach ($results as $result) {
        if (isset($uncheckedDisbsData[$deb_num]) && 
            in_array($result['id'], array_column($uncheckedDisbsData[$deb_num], 'id'))) {
            continue;
        }

        $insertQuery = "INSERT INTO receipt_disbs (disbs_ref_id, receipt_num) VALUES ($1, $2)";
        $params = [$result['id'], $receipt_num];
        $insertResult = pg_query_params($dblink, $insertQuery, $params);

        if (!$insertResult) {
            return "新增 receipt_disbs 失敗: " . pg_last_error($dblink);
        }
    }

    return true;
}

// 處理 POST 請求
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['dataArray'])) {
        echo json_encode(['message' => 'Session dataArray 不存在']);
        exit;
    }

    $hasError = false;
    $selectedData = json_decode($_POST['selectedData'], true);
    $uncheckedDisbsData = json_decode($_POST['uncheckedDisbsData'], true);

    foreach ($selectedData as $data) {
        $index = $data['index'];
        $data_array = $_SESSION['dataArray'][$index] ?? null;
        
        if (!$data_array) {
            continue; // 跳過不存在的索引
        }
        
        $result = recordReceiptTable(
            $data['entity'],
            $data_array['case_num'],
            $data_array['wht_status'],
            $data_array['wht_model'],
            $data_array['wht_base'],
            $data_array['deb_num'],
            $data_array['sent'],
            $data_array['legal_services'],
            $data_array['disbs'],
            floatval(str_replace(',', '', $data['wht'])),
            $data['note_legal'],
            $data_array['billing_currency'],
            $data_array['currency2'],
            $data_array['foreign_legal2'],
            $data_array['foreign_disbs2'],
            $data['note_disbs'], 
            $uncheckedDisbsData,
            $data_array['disbs_sum'],
            $data_array['foreign_disbs_sum']
        );

        if ($result !== true) {
            $hasError = true;
            break;
        }
    }

    if ($hasError) {
        $response = [
            'message' => "錯誤: " . $result
        ];
    } else {
        $response = [
            'message' => '資料新增成功'
        ];
    }
    
    echo json_encode($response);
}
