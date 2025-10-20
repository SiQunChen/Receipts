<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("db.ini");

function alert($message) {
    $redirectURL = "http://slashlaw-new/receipts.php";
    echo "<script>
            alert('$message');
            window.location.href = '$redirectURL';
          </script>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change']) && $_POST['change'] === 'change') {
    // 取得參數
    $invalid = $_POST['invalid'];
    $replacement = $_POST['replacement'];

    // 資料庫連接
    $dblink = pg_connect(DB_CONNECT);
    if (!$dblink) {
        alert("資料庫連接失敗: " . pg_last_error());
    }

    pg_query($dblink, "BEGIN");

    // 取得原收據資料
    $queryInvalid = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($invalid) . "' LIMIT 1";
    $resultInvalid = pg_query($dblink, $queryInvalid);
    if (!$resultInvalid || pg_num_rows($resultInvalid) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到原編號");
    }
    $invalidData = pg_fetch_assoc($resultInvalid);

    // 確認替換收據存在
    $queryReplacement = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($replacement) . "' LIMIT 1";
    $resultReplacement = pg_query($dblink, $queryReplacement);
    if (!$resultReplacement || pg_num_rows($resultReplacement) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到替換編號");
    }

    // 將原收據的 status 設為 0
    $queryUpdateInvalid = "UPDATE receipt SET status = 0 WHERE receipt_num = '" . pg_escape_string($invalid) . "'";
    $resultUpdateInvalid = pg_query($dblink, $queryUpdateInvalid);
    if (!$resultUpdateInvalid) {
        pg_query($dblink, "ROLLBACK");
        alert("更新原收據資料失敗: " . pg_last_error($dblink));
    }

    // 準備要複製的欄位 (排除 id、receipt_num)
    $fieldsToCopy = array(
        'receipt_entity', 'case_num', 'deb_num', 'bills_sent',
        'receipt_date', 'legal_services', 'disbs', 'total', 'wht',
        'note_legal', 'currency', 'foreign_services',
        'foreign_disbs', 'foreign_total', 'foreign_wht',
        'note_disbs', 'create_date', 'edit_date',
        'currency_status'
    );

    // 組合更新字串
    $setClauses = [];
    foreach ($fieldsToCopy as $field) {
        // 取得無效收據對應欄位的值
        $value = isset($invalidData[$field]) ? "'" . pg_escape_string($invalidData[$field]) . "'" : "NULL";
        $setClauses[] = "$field = $value";
    }

    // 確保替換收據 status 維持為 1
    $setClauses[] = "status = 1";

    $setClause = implode(", ", $setClauses);

    // 更新替換收據資料
    $queryUpdateReplacement = "UPDATE receipt SET $setClause WHERE receipt_num = '" . pg_escape_string($replacement) . "'";
    $resultUpdateReplacement = pg_query($dblink, $queryUpdateReplacement);
    if (!$resultUpdateReplacement) {
        pg_query($dblink, "ROLLBACK");
        alert("更新替換收據資料失敗: " . pg_last_error($dblink));
    }

    pg_query($dblink, "COMMIT");

    alert("資料儲存成功");

    // 關閉資料庫連接
    pg_close($dblink);
    exit;
}
?>
