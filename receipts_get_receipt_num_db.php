<?php
require_once("db.ini");
header('Content-Type: application/json');

function getNewReceiptNum($receipt_year, $receipt_month) {
    $dblink = @pg_connect(DB_CONNECT);
    if (!$dblink) {
        http_response_code(500);
        echo json_encode(["error" => "無法連接到資料庫"]);
        exit;
    }

    $sql = "SELECT receipt_num
            FROM receipt
            WHERE receipt_num LIKE $1
            ORDER BY receipt_num DESC
            LIMIT 1";
    $res = pg_query_params($dblink, $sql, [ "R{$receipt_year}{$receipt_month}%" ]);
    
    if ($res && pg_num_rows($res) > 0) {
        $latest = pg_fetch_result($res, 0, 'receipt_num');
        $nextSerial = (int) substr($latest, 5) + 1;
    } else {
        $nextSerial = 1;
    }

    echo json_encode(["receiptNum" => $nextSerial]);
}

// 接收 GET 參數
if (isset($_GET['receipt_year']) && isset($_GET['receipt_month'])) {
    $receipt_year = $_GET['receipt_year'];
    $receipt_month = $_GET['receipt_month'];
    getNewReceiptNum($receipt_year, $receipt_month);
} else {
    http_response_code(400);
    echo json_encode(["error" => "缺少必要參數：receipt_year 或 receipt_month"]);
}
?>