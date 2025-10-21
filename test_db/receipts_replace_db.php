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
    $change_action = $_POST['change_action'] ?? 'keep';

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
    // 取得原收據的資料庫 'id'，以便後續檢查與操作
    $invalid_record_id = intval($invalidData['id']);

    // 刪除模式：檢查是否為最後一筆資料 ---
    if ($change_action === 'delete') {
        // 查詢目前資料表中 ID 最大的那筆資料
        $queryMaxId = "SELECT id FROM receipt ORDER BY id DESC LIMIT 1";
        $resultMaxId = pg_query($dblink, $queryMaxId);
        
        if (!$resultMaxId || pg_num_rows($resultMaxId) == 0) {
            pg_query($dblink, "ROLLBACK");
            alert("無法獲取資料表最後一筆資料ID");
        }
        
        $maxIdData = pg_fetch_assoc($resultMaxId);
        $maxId = intval($maxIdData['id']);

        // 比較原收據的 id 是否為最大的 id
        if ($invalid_record_id !== $maxId) {
            pg_query($dblink, "ROLLBACK");
            // 如果不是最後一筆，提示使用者無法刪除
            alert("原編號 ($invalid) 不是資料庫中最後一筆資料，無法執行刪除。");
            exit; // 確保中斷
        }
    }

    // 確認替換收據存在
    $queryReplacement = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($replacement) . "' LIMIT 1";
    $resultReplacement = pg_query($dblink, $queryReplacement);
    if (!$resultReplacement || pg_num_rows($resultReplacement) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到替換編號");
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

    // --- 【修改】根據 $change_action 決定作廢或刪除 ---
    if ($change_action === 'delete') {
        // 刪除原收據 (使用 'id' 進行刪除最安全)
        $queryDeleteInvalid = "DELETE FROM receipt WHERE id = " . $invalid_record_id;
        $resultDeleteInvalid = pg_query($dblink, $queryDeleteInvalid);
        if (!$resultDeleteInvalid) {
            pg_query($dblink, "ROLLBACK");
            alert("刪除原收據資料失敗: " . pg_last_error($dblink));
        }
    } else {
        // 保留原收據，僅將 status 設為 0 (原邏輯)
        // (使用 'id' 進行更新最安全)
        $queryUpdateInvalid = "UPDATE receipt SET status = 0 WHERE id = " . $invalid_record_id;
        $resultUpdateInvalid = pg_query($dblink, $queryUpdateInvalid);
        if (!$resultUpdateInvalid) {
            pg_query($dblink, "ROLLBACK");
            alert("更新原收據資料失敗: " . pg_last_error($dblink));
        }
    }
    // --- 【修改】結束 ---

    pg_query($dblink, "COMMIT");

    // 【修改】根據操作顯示不同的成功訊息
    $message = ($change_action === 'delete') ? "資料替換並刪除成功" : "資料儲存成功";
    alert($message);

    // 關閉資料庫連接
    pg_close($dblink);
    exit;
}
?>