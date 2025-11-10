<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['dataArray'])) {
    // 取得Session中儲存的資料
    $dataArray = isset($_SESSION['dataArray']) ? $_SESSION['dataArray'] : [];

    // 回傳JSON資料
    echo json_encode([
        'dataArray' => $dataArray,
    ]);
} else {
    echo json_encode([
        'dataArray' => 'No data found in session',
    ]);
}
?>
