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

    // --- 取得原收據資料 ---
    $queryInvalid = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($invalid) . "' LIMIT 1";
    $resultInvalid = pg_query($dblink, $queryInvalid);
    if (!$resultInvalid || pg_num_rows($resultInvalid) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到原編號 ($invalid)");
    }
    $invalidData = pg_fetch_assoc($resultInvalid);
    $invalid_record_id = $invalidData['id'];

    // --- 刪除模式：檢查是否為最後一筆資料 ---
    if ($change_action === 'delete') {
        // 查詢目前資料表中 ID 最大的那筆資料
        $queryMaxId = "SELECT id FROM receipt ORDER BY id DESC LIMIT 1";
        $resultMaxId = pg_query($dblink, $queryMaxId);
        
        if (!$resultMaxId || pg_num_rows($resultMaxId) == 0) {
            pg_query($dblink, "ROLLBACK");
            alert("無法獲取資料表最後一筆資料ID");
        }
        
        $maxIdData = pg_fetch_assoc($resultMaxId);
        $maxId = $maxIdData['id'];

        // 比較原收據的 id 是否為最大的 id
        if ($invalid_record_id !== $maxId) {
            pg_query($dblink, "ROLLBACK");
            alert("原編號 ($invalid) 不是資料庫中最後一筆資料 (ID: $invalid_record_id vs MaxID: $maxId)，無法執行刪除，僅能作廢。");
            exit; 
        }
    }
    
    // --- 【修改】取得替換收據資料 (供後續交換使用) ---
    $queryReplacement = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($replacement) . "' LIMIT 1";
    $resultReplacement = pg_query($dblink, $queryReplacement); // 執行查詢
    if (!$resultReplacement || pg_num_rows($resultReplacement) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到替換編號 ($replacement)");
    }
    // 【新增】將替換編號的資料存起來
    $replacementData = pg_fetch_assoc($resultReplacement);
    // 【新增】取得替換編號的 ID
    $replacement_record_id = intval($replacementData['id']);

    // 準備要複製的欄位 (排除 id、receipt_num、receipt_date)
    $fieldsToCopy = array(
        'receipt_entity', 'case_num', 'deb_num', 'bills_sent',
        'legal_services', 'disbs', 'total', 'wht',
        'note_legal', 'currency', 'foreign_services',
        'foreign_disbs', 'foreign_total', 'foreign_wht',
        'note_disbs', 'create_date', 'edit_date',
        'currency_status'
    );

    // --- 【步驟 1】將 '原編號' 資料更新到 '替換編號' 紀錄中 ---
    $setClausesReplacement = [];
    foreach ($fieldsToCopy as $field) {
        // 來源是 $invalidData
        $value = isset($invalidData[$field]) ? "'" . pg_escape_string($invalidData[$field]) . "'" : "NULL";
        $setClausesReplacement[] = "$field = $value";
    }
    // 確保替換收據 status 維持為 1 (有效)
    $setClausesReplacement[] = "status = 1";
    $setClauseForReplacement = implode(", ", $setClausesReplacement);

    // 更新替換收據資料 (使用 ID 更新更安全)
    $queryUpdateReplacement = "UPDATE receipt SET $setClauseForReplacement WHERE id = " . $replacement_record_id;
    $resultUpdateReplacement = pg_query($dblink, $queryUpdateReplacement);
    if (!$resultUpdateReplacement) {
        pg_query($dblink, "ROLLBACK");
        alert("更新替換收據資料失敗: " . pg_last_error($dblink));
    }

    // --- 【步驟 2】根據 $change_action 決定作廢或刪除 '原編號' 紀錄 ---
    if ($change_action === 'delete') {
        // --- 刪除模式 ---
        // 刪除原收據
        $queryDeleteInvalid = "DELETE FROM receipt WHERE id = " . $invalid_record_id;
        $resultDeleteInvalid = pg_query($dblink, $queryDeleteInvalid);
        if (!$resultDeleteInvalid) {
            pg_query($dblink, "ROLLBACK");
            alert("刪除原收據資料失敗: " . pg_last_error($dblink));
        }

        // --- 重製 ID 序列 ---
        $queryNewMaxId = "SELECT COALESCE(MAX(id), 0) AS new_max_id FROM receipt";
        $resultNewMaxId = pg_query($dblink, $queryNewMaxId);
        if (!$resultNewMaxId) {
            pg_query($dblink, "ROLLBACK");
            alert("無法獲取刪除後的最大ID: " . pg_last_error($dblink));
        }
        $newMaxIdData = pg_fetch_assoc($resultNewMaxId);
        $newMaxId = intval($newMaxIdData['new_max_id']);
        $nextIdToSet = $newMaxId + 1;

        $queryResetSeq = "ALTER SEQUENCE receipt_id_seq RESTART WITH " . $nextIdToSet;
        $resultResetSeq = pg_query($dblink, $queryResetSeq);
        if (!$resultResetSeq) {
            pg_query($dblink, "ROLLBACK");
            alert("重製 ID 序列失敗: " . pg_last_error($dblink));
        }

    } else {
        // --- 【修改】保留模式 (交換資料並作廢) ---
        
        // 1. 準備 '替換編號' 的資料
        $setClausesInvalid = [];
        foreach ($fieldsToCopy as $field) {
            // 來源是 $replacementData
            $value = isset($replacementData[$field]) ? "'" . pg_escape_string($replacementData[$field]) . "'" : "NULL";
            $setClausesInvalid[] = "$field = $value";
        }

        // 2. 將 '原編號' 狀態設為 0 (作廢)
        $setClausesInvalid[] = "status = 0"; 
        $setClauseForInvalid = implode(", ", $setClausesInvalid);

        // 3. 更新 '原編號' 紀錄 (使用 ID 更新)
        $queryUpdateInvalid = "UPDATE receipt SET $setClauseForInvalid WHERE id = " . $invalid_record_id;
        $resultUpdateInvalid = pg_query($dblink, $queryUpdateInvalid);
        if (!$resultUpdateInvalid) {
            pg_query($dblink, "ROLLBACK");
            alert("更新(作廢)原收據資料失敗: " . pg_last_error($dblink));
        }
    }

    // 提交交易
    pg_query($dblink, "COMMIT");

    // 【修改】根據操作顯示不同的成功訊息
    $message = ($change_action === 'delete') ? "資料轉換並刪除成功" : "資料轉換成功";
    alert($message);

    // 關閉資料庫連接
    pg_close($dblink);
    exit;
}
?>