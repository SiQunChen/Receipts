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
    
    // --- 取得替換收據資料 (供後續交換使用) ---
    $queryReplacement = "SELECT * FROM receipt WHERE receipt_num = '" . pg_escape_string($replacement) . "' LIMIT 1";
    $resultReplacement = pg_query($dblink, $queryReplacement); // 執行查詢
    if (!$resultReplacement || pg_num_rows($resultReplacement) == 0) {
        pg_query($dblink, "ROLLBACK");
        alert("找不到替換編號 ($replacement)");
    }
    $replacementData = pg_fetch_assoc($resultReplacement);
    $replacement_record_id = intval($replacementData['id']);

    // 準備要複製的欄位 (排除 id、receipt_num、receipt_date)
    $fieldsToCopy = array(
        'receipt_entity', 'case_num', 'deb_num', 'bills_sent',
        'legal_services', 'disbs', 'total', 'wht',
        'note_legal', 'currency', 'foreign_services',
        'foreign_disbs', 'foreign_total', 'foreign_wht',
        'note_disbs', 'create_date', 'edit_date',
        'currency_status', 'deb_extra', 'payments_id'
    );

    // ==================================================================
    // --- 【步驟 0.5 - 新增】處理 receipt_disbs (代墊資料) ---
    // ==================================================================
    $escapedInvalidNum = pg_escape_string($invalid);
    $escapedReplacementNum = pg_escape_string($replacement);
    
    if ($change_action === 'delete') {
        // --- 刪除模式 ---
        
        // 1. 刪除 'replacement' (替換編號) 原有的代墊資料
        //    (因為替換編號的 receipt 紀錄將被 原編號 的資料覆蓋)
        $queryDeleteDisbs = "DELETE FROM receipt_disbs WHERE receipt_num = '$escapedReplacementNum'";
        $resultDeleteDisbs = pg_query($dblink, $queryDeleteDisbs);
        if (!$resultDeleteDisbs) {
            pg_query($dblink, "ROLLBACK");
            alert("刪除替換收據 ($replacement) 的代墊資料失敗: " . pg_last_error($dblink));
        }

        // 2. 將 'invalid' (原編號) 的代墊資料轉移給 'replacement' (替換編號)
        //    (因為 原編號 的 receipt 紀錄將被刪除)
        $queryUpdateDisbs = "UPDATE receipt_disbs SET receipt_num = '$escapedReplacementNum' WHERE receipt_num = '$escapedInvalidNum'";
        $resultUpdateDisbs = pg_query($dblink, $queryUpdateDisbs);
        if (!$resultUpdateDisbs) {
            pg_query($dblink, "ROLLBACK");
            alert("轉移原收據 ($invalid) 的代墊資料失敗: " . pg_last_error($dblink));
        }
    } else {
        // --- 保留模式 (交換) ---
        // (因為 receipt 紀錄的 *內容* 交換了, 所以 receipt_disbs 也要跟著交換)
        
        // 【修改】定義一個臨時的、唯一的編號 (長度必須 <= 15)
        // 使用一個幾乎不可能重複的固定字串
        $tempSwapValue = pg_escape_string('___TEMP_SWAP___'); // 剛好 15 個字元

        // 1. 將 'invalid' (原編號) 的代墊資料暫時標記
        $queryTempUpdate = "UPDATE receipt_disbs SET receipt_num = '$tempSwapValue' WHERE receipt_num = '$escapedInvalidNum'";
        $resultTempUpdate = pg_query($dblink, $queryTempUpdate);
        if (!$resultTempUpdate) {
            pg_query($dblink, "ROLLBACK");
            // 【修改】提供更精確的錯誤訊息
            alert("交換代墊資料失敗 (步驟1): " . pg_last_error($dblink));
        }
        $count1 = pg_affected_rows($resultTempUpdate); // 記錄影響的行數

        // 2. 將 'replacement' (替換編號) 的代墊資料指派給 'invalid' (原編號)
        $querySwapToInvalid = "UPDATE receipt_disbs SET receipt_num = '$escapedInvalidNum' WHERE receipt_num = '$escapedReplacementNum'";
        $resultSwapToInvalid = pg_query($dblink, $querySwapToInvalid);
        if (!$resultSwapToInvalid) {
            pg_query($dblink, "ROLLBACK");
            alert("交換代墊資料失敗 (步驟2): " . pg_last_error($dblink));
        }

        // 3. 將暫時標記的資料 (原 invalid 的) 指派給 'replacement' (替換編號)
        $querySwapToReplacement = "UPDATE receipt_disbs SET receipt_num = '$escapedReplacementNum' WHERE receipt_num = '$tempSwapValue'";
        $resultSwapToReplacement = pg_query($dblink, $querySwapToReplacement);
        if (!$resultSwapToReplacement) {
            pg_query($dblink, "ROLLBACK");
            alert("交換代墊資料失敗 (步驟3): " . pg_last_error($dblink));
        }
        $count3 = pg_affected_rows($resultSwapToReplacement); // 記錄影響的行數

        // 檢查行數是否匹配，確保交換的資料筆數一致
        if ($count1 != $count3) {
             pg_query($dblink, "ROLLBACK");
             alert("交換代墊資料失敗 (步驟3 - 行數不匹配: $count1 vs $count3)。交易已回滾。");
        }
    }
    // ==================================================================
    // --- 結束 receipt_disbs 處理 ---
    // ==================================================================


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
        // --- 保留模式 (交換資料並作廢) ---
        
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

    $message = ($change_action === 'delete') ? "資料轉換並刪除成功" : "資料轉換成功";
    alert($message);

    // 關閉資料庫連接
    pg_close($dblink);
    exit;
}
?>