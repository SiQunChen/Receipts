<?php
ob_start(); 
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.ini';
require_once '../vendor/autoload.php';

// 連接資料庫
global $dblink;
$dblink = pg_pconnect(DB_CONNECT);
if (!$dblink) {
    exit("無法連接到資料庫: " . pg_last_error());
}

/**
 * 記錄資料到 receipt 資料庫
 */
function invalid($dataArray, $selectedData) {
    global $dblink;

    if (!$dblink) {
        return "無法連接到資料庫";
    }

    try {
        foreach ($selectedData as $data) {
            $receipt_num = $dataArray[$data['index']]['receipt_num'] ?? null;
    
            if ($receipt_num) {
                $sql = "UPDATE receipt SET status = 0 WHERE receipt_num = $1";
                $result = pg_query_params($dblink, $sql, [$receipt_num]);
    
                if (!$result) {
                    throw new Exception("更新失敗: " . pg_last_error($dblink));
                }
            }
        }
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }    
}

// 處理 POST 請求
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 確保 session 存在
    if (empty($_SESSION['dataArray'])) {
        echo json_encode(['message' => 'Session dataArray 不存在或為空']);
        exit;
    }

    $action = $_POST['action'];
    $selectedData = json_decode($_POST['selectedData'], true);

    if ($action === 'invalid') {
        $result = invalid($_SESSION['dataArray'], $selectedData);
    } else {
        echo json_encode(['message' => '未提供有效的 action']);
        exit;
    }

    // 檢查結果
    if ($result === true) {
        $response = ['message' => '資料更新成功'];
    } else {
        $response = ['message' => "錯誤: " . $result];
    }

    echo json_encode($response);
}
