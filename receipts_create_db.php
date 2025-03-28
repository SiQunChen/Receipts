<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("db.ini");

function getEntity($case_num, $match_or_like) {
    // 資料庫連接
    $dblink = @pg_pconnect(DB_CONNECT);
    if (!$dblink) {
        return("無法連接到資料庫");
    }

    // 初始化查詢結果
    $resultData = [];

    // 如果 case_num 不為空，執行查詢
    if ($case_num !== '') {
        if ($match_or_like === 'match') {
            // 查詢條件
            $sql = "SELECT case_num, party_en_name_billing FROM cases WHERE case_num = $1";
            $params = [$case_num];

            // 執行查詢
            $result = pg_query_params($dblink, $sql, $params);
            if (!$result) {
                return("查詢失敗: " . pg_last_error($dblink));
            }

            // 取得查詢結果
            while ($row = pg_fetch_assoc($result)) {
                $resultData[] = $row;
            }
        } else {
            return("需要預帶的案號不能使用 Like 選項");
        }
    }

    // 關閉資料庫連接
    pg_close($dblink);

    // 返回結果
    return $resultData;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_receipt']) && $_POST['create_receipt'] === 'create_receipt') {
    // 取得參數
    $receipt_entity = $_POST['entity'];
    $case_num = $_POST['case_num'];
    $deb_num = $_POST['invoice'];
    $currency = $_POST['currency'];
    $legal_services = $_POST['services'];
    $note_legal = $_POST['note_legal'];
    $disbs = $_POST['disbursements'];
    $note_disbs = $_POST['note_disbs'];
    $wht = $_POST['wht'];

    // 資料庫連接
    $dblink = @pg_pconnect(DB_CONNECT);
    if (!$dblink) {
        return("資料庫連接失敗: " . pg_last_error());
    }

    // 取得當前年份與月份
    $year = date('y'); // 取得兩位數年份
    $month = date('m'); // 取得兩位數月份

    // 查詢資料庫中今年的最新流水號
    $query = "SELECT receipt_num FROM receipt 
              WHERE receipt_num LIKE $1 
              ORDER BY receipt_num DESC LIMIT 1";
    $like_pattern = "R{$year}{$month}%"; 
    $result = pg_query_params($dblink, $query, [$like_pattern]);

    if ($result && pg_num_rows($result) > 0) {
        $latest_receipt_num = pg_fetch_result($result, 0, 'receipt_num');
        // 提取流水號部分，例如 R24120001 -> 1
        $latest_serial = (int)substr($latest_receipt_num, 5) + 1;
    } else {
        $latest_serial = 1; // 如果無記錄，從 1 開始
    }

    // 格式化新的 receipt_num
    $new_receipt_num = sprintf("R%s%s%04d", $year, $month, $latest_serial);

    // 根據幣別決定要存入的欄位
    if ($currency == 'TWD') {
        // TWD 幣別，存入一般欄位
        $sql = "INSERT INTO receipt (
                    receipt_num,
                    receipt_date,
                    receipt_entity, 
                    case_num, 
                    deb_num, 
                    currency,
                    legal_services,
                    disbs,
                    total,
                    note_legal,
                    note_disbs,
                    wht
                ) VALUES (
                    $1, CURRENT_DATE, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11
                )";

        $result = pg_query_params($dblink, $sql, [
            $new_receipt_num,
            $receipt_entity,
            $case_num,
            $deb_num,
            $currency,
            $legal_services,
            $disbs,
            $legal_services + $disbs,
            $note_legal,
            $note_disbs,
            $wht
        ]);
    } else {
        // 非 TWD 幣別，存入 foreign 欄位
        $sql = "INSERT INTO receipt (
                    receipt_num,
                    receipt_date,
                    receipt_entity,
                    case_num,
                    deb_num,
                    currency,
                    foreign_services,
                    foreign_disbs,
                    foreign_total,
                    note_legal,
                    note_disbs,
                    currency_status,
                    foreign_wht
                ) VALUES (
                    $1, CURRENT_DATE, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12
                )";

        $result = pg_query_params($dblink, $sql, [
            $new_receipt_num,
            $receipt_entity,
            $case_num,
            $deb_num,
            $currency,
            $legal_services,
            $disbs,
            $legal_services + $disbs,
            $note_legal,
            $note_disbs,
            2,
            $wht
        ]);
    }

    if (!$result) {
        $message = '資料儲存失敗：' . addslashes(pg_last_error($dblink));
    } else {
        $message = '資料儲存成功';
    }

    echo "<script>
            alert('$message');
            window.location.href = 'http://slashlaw-new/receipts.php';
        </script>";

    pg_close($dblink); // 關閉資料庫連接
    exit;
}
?>
