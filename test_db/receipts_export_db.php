<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 能夠傳送錯誤訊息到前端
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

require_once 'db.ini';
require_once '../vendor/autoload.php';
require_once 'receipts_list_db.php';

global $dblink;
$dblink = pg_pconnect(DB_CONNECT);
if (!$dblink) {
    echo json_encode(['message' => '❌ 無法連接到資料庫: '. pg_last_error()]);
    exit;
}

function isForeignCurrency($data, $is_paid): bool {
    // unpaid 屬於外幣的情況
    $currency = $data['billing_currency'] ?? '';
    $isEnglishCurrency = in_array($currency, ['English (USD)', 'English (EUR)']);
    
    // paid 屬於外幣的情況
    $foreignLegal = $data['foreign_legal2'] ?? null;
    $foreignDisbs = $data['foreign_disbs2'] ?? null;
    $hasForeignValues = !is_null($foreignLegal) || !is_null($foreignDisbs);
    
    return (!$is_paid && $isEnglishCurrency) || ($is_paid && $hasForeignValues);
}

function recordReceiptTable($entity, $case_num, $deb_num, $sent, $receipt_date, $legal_services, $disbs, $wht, $note_legal, $is_foreign, $currency, $foreign_services, $foreign_disbs, $note_disbs, $uncheckedDisbsData, $receipt_num, $is_paid) {
    global $dblink;
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    // 根據幣別決定要存入的欄位
    if ($is_foreign) {
        // 部分銷帳
        if (isset($uncheckedDisbsData[$deb_num]) && is_array($uncheckedDisbsData[$deb_num])) {
            foreach ($uncheckedDisbsData[$deb_num] as $data) {
                $foreign_disbs -= $data['foreign_amount'];
            }
        }
        
        // 計算總金額
        $foreign_total = $foreign_disbs + $foreign_services;

        $query = "INSERT INTO receipt (
                    receipt_entity, case_num, deb_num, bills_sent, receipt_num, receipt_date,
                    legal_services, disbs, total, wht, currency, foreign_services, foreign_disbs, 
                    foreign_total, foreign_wht, note_legal, note_disbs, currency_status
                ) VALUES (
                    $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18
                )";

        $params = [$entity, $case_num, $deb_num, $sent, $receipt_num, $receipt_date, 0, 0, 0, 0, $currency, $foreign_services, $foreign_disbs, $foreign_total, $wht, $note_legal, $note_disbs, 2];
    } else {
        // 部分銷帳
        if (isset($uncheckedDisbsData[$deb_num]) && is_array($uncheckedDisbsData[$deb_num])) {
            foreach ($uncheckedDisbsData[$deb_num] as $data) {
                $disbs -= $data['amount'];
            }
        }
        
        // 計算總金額
        $total = $disbs + $legal_services;

        $query = "INSERT INTO receipt (
                    receipt_entity, case_num, deb_num, bills_sent, receipt_num, receipt_date,
                    legal_services, disbs, total, wht, foreign_services, foreign_disbs,
                    foreign_total, foreign_wht, note_legal, note_disbs, currency_status
                ) VALUES (
                    $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17
                )";

        $params = [$entity, $case_num, $deb_num, $sent, $receipt_num, $receipt_date, $legal_services, $disbs, $total, $wht, 0, 0, 0, 0, $note_legal, $note_disbs, 1];
    }

    $res = pg_query_params($dblink, $query, $params);
    if (!$res) {
        throw new Exception("新增 receipt 失敗：" . pg_last_error($dblink));
    }

    // 插入 receipt_disbs
    recordReceiptDisbsTable($deb_num, $receipt_num, $uncheckedDisbsData, $is_paid);
    
    return true;
}

function recordReceiptDisbsTable($deb_num, $receipt_num, $uncheckedDisbsData, $is_paid) {
    global $dblink;
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    $results = getReceiptsDetail($is_paid, $deb_num);

    if ($is_paid === 'true') {
        $insertQuery = "INSERT INTO receipt_disbs (disbs_pay_id, table_name, receipt_num) 
                        VALUES ($1, 1, $2)";
    } elseif ($is_paid === 'false') {
        $insertQuery = "INSERT INTO receipt_disbs (disbs_ref_id, table_name, receipt_num) 
                        VALUES ($1, 0, $2)";
    }

    foreach ($results as $result) {
        if (isset($uncheckedDisbsData[$deb_num]) &&
            in_array($result['id'], array_column($uncheckedDisbsData[$deb_num], 'id'))) {
            continue;
        }
        
        $params = [$result['id'], $receipt_num];
        $res = pg_query_params($dblink, $insertQuery, $params);
        
        if (!$res) {
            throw new Exception("新增 receipt_disbs 失敗：" . pg_last_error($dblink));
        }
    }
    return true;
}

function updateReceiptTable($receipt_num, $receipt_entity, $note_legal, $note_disbs) {
    global $dblink;
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    $query = "UPDATE receipt 
            SET receipt_entity = $2, note_legal = $3, note_disbs = $4
            WHERE receipt_num = $1";
    $params = [$receipt_num, $receipt_entity, $note_legal, $note_disbs];
    $res = pg_query_params($dblink, $query, $params);
    if (!$res) {
        throw new Exception("更新 receipt 失敗：" . pg_last_error($dblink));
    }
   
    return true;
}

// === 主處理 ===
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("只允許 POST 請求");
    }

    if (empty($_SESSION['dataArray'])) {
        throw new Exception("Session dataArray 不存在");
    }

    // 取得表單欄位
    $allData = $_POST['allData'] ?? '';
    $uncheckedDisbsData = $_POST['uncheckedDisbsData'] ?? '';
    $is_paid = $_POST['ispaid'] ?? '';
    $type = $_POST['type'] ?? '';

    // 將 JSON 格式轉換為 PHP 陣列
    $allData = json_decode($allData, true);
    $uncheckedDisbsData = json_decode($uncheckedDisbsData, true);

    pg_query($dblink, 'BEGIN');

    // 迴圈處理每筆資料
    foreach ($allData as $data) {
        // 取得 session 中對應的資料
        $index = $data['selectedData']['index'] ?? null;
        $session_data = $_SESSION['dataArray'][$index] ?? null;
        if (!$session_data) {
            throw new Exception("Session index 對應不到資料");
        }

        // 取得收據號碼
        $receipt_num_raw = $data['receiptNum'] ?? 1;

        // 執行 list
        if ($type === 'list') {
            $success = recordReceiptTable(
                $data['selectedData']['entity'],
                $session_data['case_num'],
                $session_data['deb_num'],
                $session_data['sent'],
                $_POST['receiptDate'] ?? date('Y-m-d'),
                (float)$session_data['legal_services'],
                (float)$session_data['disbs'],
                (float)(str_replace(',', '', $data['selectedData']['wht'])),
                $data['selectedData']['note_legal'],
                isForeignCurrency($session_data, $is_paid === 'true'),
                $session_data['currency2'],
                $session_data['foreign_legal2'],
                $session_data['foreign_disbs2'],
                $data['selectedData']['note_disbs'],
                $uncheckedDisbsData,
                $data['receiptNum'],
                $is_paid
            );
        } elseif ($type === 'edit') { // 執行 edit
            $success = updateReceiptTable(
                $session_data['receipt_num'],
                $data['selectedData']['receipt_entity'],
                $data['selectedData']['note_legal'],
                $data['selectedData']['note_disbs']
            );
        }

        if ($success !== true) {
            throw new Exception("未知錯誤：儲存失敗");
        }
    }

    pg_query($dblink, 'COMMIT');
    echo json_encode(['message' => '資料成功 Export']);
} catch (Exception $e) {
    if (isset($dblink)) {
        pg_query($dblink, 'ROLLBACK');
    }
    echo json_encode(['message' => '❗ 錯誤：' . $e->getMessage()]);
}
