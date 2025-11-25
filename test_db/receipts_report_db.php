<?php
ob_start();
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.ini';
require_once '../vendor/autoload.php';
require_once 'receipts_list_db.php';

// 能夠傳送錯誤訊息到前端
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

try {
    // 取得表單欄位
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedDataJson = $_POST['selectedData'] ?? '{}';
        $uncheckedDisbsDataJson = $_POST['uncheckedDisbsData'] ?? '{}';
        $indexListJson = $_POST['indexList'] ?? '{}';
        $language = $_POST['language'] ?? 'chinese';
        $is_paid = $_POST['ispaid'] ?? '';
        $receipt_num = $_POST['receiptNum'] ?? sprintf("R%s%s%04d", date('y'), date('m'), 1);
        $type = $_POST['type'] ?? '';
        $isMerged = $_POST['isMerged'] ?? false;

        // 將 JSON 格式轉換為 PHP 陣列
        $selectedData = json_decode($selectedDataJson, true);
        $uncheckedDisbsData = json_decode($uncheckedDisbsDataJson, true);
        if ($isMerged) {
            $indexList = json_decode($indexListJson, true);
        }

        // 取得 session 中對應的資料
        $index = $selectedData['index'];
        $session_data = $_SESSION['dataArray'][$index] ?? null;

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') { // create 資料
        $entity = urldecode($_GET['entity'] ?? '');
        $case_num = urldecode($_GET['case_num'] ?? '');
        $deb_num = urldecode($_GET['invoice'] ?? '');
        $currency = urldecode($_GET['currency'] ?? 'TWD');
        $isForeign = $currency !== 'TWD';
        $services = (float)($_GET['services'] ?? 0);
        $note_legal = urldecode($_GET['note_legal'] ?? '');
        $disbs = (float)($_GET['disbursements'] ?? 0);
        $note_disbs = urldecode($_GET['note_disbs'] ?? '');
        $wht = (float)($_GET['wht'] ?? 0);
        $is_paid = $_GET['status'] === 'paid' ? 'true' : 'false';
        $receipt_num = urldecode($_GET['receipt_num'] ?? '');
        $type = 'create';
        $language = urldecode($_GET['language'] ?? 'chinese');
        $official_fee = 0;
        $other_fee = 0;
        $total = $services + $disbs;
    } else {
        throw new Exception("不接受的請求");
    }

    // 執行 list
    if ($type === 'list') {
        // 取得欄位資料
        $entity = $selectedData['entity'];
        $receipt_date = $_POST['receiptDate'] ?? date('Y/n/j');
        $receipt_tax_id = $session_data['receipt_tax_id'];
        $case_num = $session_data['case_num'];
        $deb_num = $session_data['deb_num'];
        $note_legal = $selectedData['note_legal'];
        $note_disbs = $selectedData['note_disbs'];
        $wht = (float)str_replace(',', '', $selectedData['wht']);

        // 判斷是否為申請單號資料
        $is_split = isset($session_data['split_entity']) && $session_data['split_entity'] !== null;
   
        ### 判斷幣別
        // unpaid 屬於外幣的情況
        $isEnglishCurrency = in_array($session_data['billing_currency'], ['English (USD)', 'English (EUR)']);

        // paid 屬於外幣的情況
        $hasForeignValues = !is_null($session_data['foreign_legal2']) || !is_null($session_data['foreign_disbs2']);

        $isForeign = ($is_paid === 'false' && $isEnglishCurrency) || ($is_paid === 'true' && $hasForeignValues) ? true : false;

        if ($isMerged) {
            $services = 0;
            if ($isForeign) {
                $currency = $session_data['currency2'];
                foreach ($indexList as $i) {
                    $services += $_SESSION['dataArray'][$i]['foreign_legal2'];
                }
            } else {
                $currency = 'TWD';
                foreach ($indexList as $i) {
                    $services += $_SESSION['dataArray'][$i]['legal_services'];
                }
            }
        } else {
            if ($isForeign) {
                $currency = $session_data['currency2'];
                $services = $is_split ? $session_data['split_legal_services'] : $session_data['foreign_legal2'];
            } else {
                $currency = 'TWD';
                $services = $is_split ? $session_data['split_legal_services'] : $session_data['legal_services'];
            }
        }

        ### 取得代墊金額
        if ($is_split) {
            $official_fee = 0;
            $other_fee = 0;
            $disbs = $session_data['split_disbs'];
        } elseif ($isMerged) {
            $official_fee = 0;
            $other_fee = 0;

            // 遍歷所有 session 中的資料項目
            foreach ($indexList as $i) {
                // 1. 取得該筆項目所有的代墊明細
                $disbsDataArray = getReceiptsDetail($is_paid, $_SESSION['dataArray'][$i]['payments_id'], $_SESSION['dataArray'][$i]['deb_num']);
                
                // 2. 取得該筆項目「未勾選」的代墊項目 ID 陣列
                $uncheckedIds = array_column($uncheckedDisbsData[$deb_num] ?? [], 'id');

                // 3. 遍歷該筆項目的代墊明細
                foreach ($disbsDataArray as $disbs_data) {
                    // 如果該明細不在「未勾選」清單中，才進行加總
                    if (!in_array($disbs_data['id'], $uncheckedIds)) {
                        
                        // 根據是否為外幣，取得正確的金額
                        $fee = $isForeign ? (float)($disbs_data['foreign_amount2'] ?? 0) : (float)($disbs_data['ntd_amount'] ?? 0);
                        
                        // 判斷費用類別並累加至總和
                        if ($disbs_data['disb_name'] === 'Official Fee') {
                            $official_fee += $fee;
                        } else {
                            $other_fee += $fee;
                        }
                    }
                }
            }
            
            // 4. 計算合併後的代墊總額
            $disbs = $official_fee + $other_fee;

        } else {
            $official_fee = 0;
            $other_fee = 0;
            
            // 1. 取得該筆項目所有的代墊明細
            $disbsDataArray = getReceiptsDetail($is_paid, $session_data['payments_id'], $session_data['deb_num']);
            
            // 2. 取得該筆項目「未勾選」的代墊項目 ID 陣列
            $uncheckedIds = array_column($uncheckedDisbsData[$session_data['deb_num']] ?? [], 'id');

            // 3. 遍歷該筆項目的代墊明細
            foreach ($disbsDataArray as $disbs_data) {
                // 如果該明細不在「未勾選」清單中，才進行加總
                if (!in_array($disbs_data['id'], $uncheckedIds)) {
                    
                    // 根據是否為外幣，取得正確的金額
                    $fee = $isForeign ? (float)($disbs_data['foreign_amount2'] ?? 0) : (float)($disbs_data['ntd_amount'] ?? 0);
                    
                    // 判斷費用類別並累加
                    if ($disbs_data['disb_name'] === 'Official Fee') {
                        $official_fee += $fee;
                    } else {
                        $other_fee += $fee;
                    }
                }
            }

            // 4. 計算單筆的代墊總額
            $disbs = $official_fee + $other_fee;
        }

        // 計算總金額
        $total = $services + $disbs;
    } elseif ($type === 'edit') { // 執行 edit

        // 取得欄位資料
        $receipt_num = $session_data['receipt_num'];
        $entity = $selectedData['receipt_entity'];
        $receipt_date = date('Y/n/j');
        $receipt_tax_id = $session_data['receipt_tax_id'];
        $case_num = $session_data['case_num'];
        $deb_num = $session_data['deb_num'] . ($session_data['deb_extra'] ?? '');
        $note_legal = $selectedData['note_legal'];
        $note_disbs = $selectedData['note_disbs'];

        // 取得該筆所有代墊資料
        $disbsDataArray = getReceiptsDetail(null, 0, null, $receipt_num);

        // 判斷幣別
        $currency = $session_data['currency'];
        $isForeign = ($currency !== 'TWD');

        // 計算代墊明細
        $official_fee = 0;
        $other_fee = 0;

        foreach ($disbsDataArray as $disbs_data) {
            $fee = $isForeign ? $disbs_data['foreign_amount2'] : $disbs_data['ntd_amount'];
            
            if ($disbs_data['disb_name'] === 'Official Fee') {
                $official_fee += $fee;
            } else {
                $other_fee += $fee;
            }
        }

        // 根據幣別設定相關變數
        if ($isForeign) {
            $services = $session_data['foreign_services'];
            $disbs = $session_data['foreign_disbs'];
            $total = $session_data['foreign_total'];
            $wht = $session_data['foreign_wht'];
        } else {
            $services = $session_data['legal_services'];
            $disbs = $session_data['disbs'];
            $total = $session_data['total'];
            $wht = $session_data['wht'];
        }
    }

    // 格式化所有金額
    $decimals = $isForeign ? 2 : 0;
    $services = number_format($services, $decimals);
    $disbs = number_format($disbs, $decimals);
    $official_fee = number_format($official_fee, $decimals);
    $other_fee = number_format($other_fee, $decimals);
    $total = number_format($total, $decimals);
    $wht = number_format($wht, $decimals);

    ### 建立 TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(20, 15, 20);
    $pdf->setCellHeightRatio(1.5);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Header 圖片
    $pdf->Image(__DIR__ . "/../image/receipts_header_{$language}.jpg", 20, 10, 170);
    $pdf->Ln(18);

    // 標題
    $pdf->SetFont('msjhbd', '', 18);
    $pdf->Cell(0, 10, '收    據', 0, 1, 'C');
    $pdf->Ln(5);

    ### 基本資訊
    $pdf->SetFont('msjh', '', 12);
    $lineHeight = 6; // 行高
    $leftStartX = 30; // 左邊內容（謹致後方）的起始 X 座標
    $startY = $pdf->GetY(); // 儲存第一列的 Y 座標

    // 計算從哪邊開始靠右
    $rightMargin = 24; // 右邊邊界
    $totalWidth = 58; // 每行兩個 Cell 的總寬度 = 24 + 34
    $rightStartX = $pdf->getPageWidth() - $rightMargin - $totalWidth;

    // ======================================================
    // =========      第一階段：繪製所有左邊欄位      =========
    // ======================================================

    // --- 第 1 列 (左) ---
    $pdf->Cell(10, $lineHeight, '謹致', 0, 1);

    // --- 第 2 列 (左) ---
    $pdf->SetX($leftStartX); // 設定縮排
    $entityMaxWidth = $rightStartX - $pdf->GetX() - 2; // 計算可用寬度
    $entityLines = $pdf->MultiCell($entityMaxWidth, $lineHeight, $entity, 0, 'L', false, 1); // 使用 MultiCell 處理 $entity 並取得行數

    // --- 第 3 列 and 第 4 列 (左 - 條件式) ---
    $linesUsed = $entityLines; // 目前已使用的行數
    if (!empty($receipt_tax_id)) {
        $pdf->SetX($leftStartX);
        $pdf->Cell(22, $lineHeight, '統一編號：', 0, 0);
        $pdf->Cell(100, $lineHeight, $receipt_tax_id, 0, 1);
        $linesUsed++; // 繪製了統一編號，已用行數加 1
    }

    // 計算還需要補上多少行的高度
    $linesToAdvance = 3 - $linesUsed;
    if ($linesToAdvance > 0) {
        $pdf->Ln($lineHeight * $linesToAdvance);
    }

    // --- 第 5 列 (左) ---
    $pdf->Cell(30, $lineHeight, '茲收到下列費用，此據。', 0, 1);

    // 儲存左側內容結束後的 Y 座標，以便最後將游標移到正確位置
    $finalY = $pdf->GetY();


    // ======================================================
    // =========      第二階段：繪製所有右邊欄位      =========
    // ======================================================

    // --- 第 1 列 (右) ---
    $pdf->SetXY($rightStartX, $startY); // 跳到第一列的 Y 座標
    $pdf->Cell(24, $lineHeight, '收據號碼：', 0, 0);
    $pdf->Cell(34, $lineHeight, $receipt_num, 'B', 0);

    // --- 第 2 列 (右) ---
    $pdf->SetXY($rightStartX, $startY + $lineHeight); // 跳到第二列的 Y 座標
    $pdf->Cell(24, $lineHeight, '日　　期：', 0, 0);
    $pdf->Cell(34, $lineHeight, (new DateTime($receipt_date))->format('Y/n/j'), 'B', 0);

    // --- 第 3 列 (右) ---
    $pdf->SetXY($rightStartX, $startY + 2 * $lineHeight); // 跳到第三列的 Y 座標
    $pdf->Cell(24, $lineHeight, '本所案號：', 0, 0);
    $pdf->Cell(34, $lineHeight, $case_num, 'B', 0);

    // --- 第 4 列 (右) ---
    $pdf->SetXY($rightStartX, $startY + 3 * $lineHeight); // 跳到第四列的 Y 座標
    $pdf->Cell(24, $lineHeight, '帳單號碼：', 0, 0);
    $pdf->Cell(34, $lineHeight, $deb_num, 'B', 0);


    // --- 收尾 ---
    // 將游標移動到所有內容的最下方，以利後續內容的添加
    $pdf->SetY($finalY);
    $pdf->Ln(4);

    ### 表格
    // 1. 先算好可用寬度與欄位
    $margins = $pdf->getMargins();
    $usableWidth = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
    $w1 = $usableWidth * 0.6;    // 第一欄：60%
    $w2 = $usableWidth * 0.4;    // 第二欄：40%

    // 2. Header 列
    $pdf->SetFont('msjhbd', '', 12);
    $pdf->setCellHeightRatio(1.2);
    $hHeader = 12;
    $pdf->MultiCell($w1, $hHeader, '摘          要', 1, 'C', false, 0, '', '', true, 0, false, true, $hHeader, 'M');
    $pdf->MultiCell($w2, $hHeader, "金      額\n({$currency})", 1, 'C', false, 1, '', '', true, 0, false, true, $hHeader, 'M');
    $pdf->setCellHeightRatio(1.5);

    // 3. 內容列：把多行文字放在同一格，用 MultiCell
    // (1) 服務公費
    $content  = "\nⅠ、服務公費\n";
    $pdf->MultiCell($w1, 0, $content, 'LR', 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);

    $content  = "\n$services   \n";
    $pdf->MultiCell($w2, 0, $content, 'LR', 'R', false, 1, '', '', true, 0, false, true, 0, 'T', true);

    // (2) Services Note
    $pdf->SetFont('msjh', '', 12);
    $content = "        $note_legal\n\n\n";
    $pdf->MultiCell($w1, 0, $content, 'LR', 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);

    $content = "\n\n\n";
    $pdf->MultiCell($w2, 0, $content, 'LR', 'R', false, 1, '', '', true, 0, false, true, 0, 'T', true);

    // (3) 代墊費用
    $pdf->SetFont('msjhbd', '', 12);
    $content = "Ⅱ、代墊費用\n";
    $pdf->MultiCell($w1, 0, $content, 'LR', 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);

    $content = $disbs . "   \n";
    $pdf->MultiCell($w2, 0, $content, 'LR', 'R', false, 1, '', '', true, 0, false, true, 0, 'T', true);
    
    // (4) 代墊類型, note
    $pdf->SetFont('msjh', '', 12);
    $content = "";
    $buffer = "";
    if ($official_fee != 0) {
        $content .= "        政府規費\n";
    } else {
        $buffer .= "\n";
    }
    if ($other_fee != 0) {
        $content .= "        其他費用\n";
    } else {
        $buffer .= "\n";
    }
    $content .= "        $note_disbs\n\n\n$buffer";
    $pdf->MultiCell($w1, 0, $content, 'LRB', 'L', false, 0, '', '', true, 0, false, true, 0, 'T', true);

    // (5) 代墊詳細費用
    $content = "";
    $buffer = "";
    if ($official_fee != 0) {
        $content .= $official_fee . "   \n";
    } else {
        $buffer .= "\n";
    }
    if ($other_fee != 0) {
        $content .= $other_fee . "   \n";
    } else {
        $buffer .= "\n";
    }
    $content .= "\n\n\n$buffer";
    $pdf->MultiCell($w2, 0, $content, 'LRB', 'R', false, 1, '', '', true, 0, false, true, 0, 'T', true);

    // 4. 總計列
    $pdf->SetFont('msjhbd', '', 12);
    $hFooter = 10;
    // 左邊留白
    $pdf->Cell($w1, $hFooter, '', 'BLR', 0);
    // 右邊用兩個 Cell 分別做左標題、右數字
    $wLabel = $w2 * 0.5;
    $wValue = $w2 - $wLabel;
    $pdf->Cell($wLabel, $hFooter, '      總計', 'BL', 0, 'L');
    $pdf->Cell($wValue, $hFooter, $total . '   ', 'BR', 1, 'R');

    // 5. 扣繳稅款
    if ($is_paid === 'true' && !empty($wht)) {
        $pdf->Cell($w1, $hFooter, '', 'BLR', 0);
        $pdf->Cell($wLabel, $hFooter, '      扣繳稅款', 'BL', 0, 'L');
        $pdf->Cell($wValue, $hFooter, $wht . '   ', 'BR', 1, 'R');
    }

    // 備註文字
    if ($is_paid !== 'true') {
        $pdf->Ln(5);
        $pdf->MultiCell(0, 6, '本收據係應 貴公司要求於付款前先行開立，待本所實際收到 貴公司付款後始生效。', 0, 'L');
    }

    $pdf->Ln(3);
    $pdf->SetFont('msjh', '', 12);
    $pdf->MultiCell(0, 6, '國內公司、行號或機關團體支付服務費用，每次金額達新台幣20,000元以上者，請依所得稅法規定扣繳10%稅款，惟不包含代墊費用。', 0, 'L');

    // 公司資訊與印章
    // (1) 統一編號
    $pdf->Ln(8);
    $pdf->Cell(0, 6, '博仲法律事務所    統一編號：14539065', 0, 1);

    // (2) 印章 (因為有進行一些旋轉，故比較複雜)
    $image_file = __DIR__ . "/../image/receipts_seal.png";

    // 取得圖片原始尺寸以計算顯示高度
    list($original_width, $original_height) = @getimagesize($image_file);

    // 設定圖片顯示寬度與根據比例計算高度
    $display_width = 42;
    $display_height = $display_width * ($original_height / $original_width);

    // 定義圖片左上角放置位置
    $x_pos = 122;
    $y_pos = $pdf->GetY() - 6; // 從目前位置上移 6 單位

    // 計算旋轉中心點 (圖片中心)
    $center_x = $x_pos + ($display_width / 2);
    $center_y = $y_pos + ($display_height / 2);

    // 設定旋轉角度
    $rotation_angle = 0;

    // 開始、執行旋轉並放置圖片，結束變形
    $pdf->StartTransform();
    $pdf->Rotate($rotation_angle, $center_x, $center_y);
    $pdf->Image($image_file, $x_pos, $y_pos, $display_width, $display_height);
    $pdf->StopTransform();

    // (3) 地址
    $pdf->Cell(0, 6, '台北市中正區光復里六鄰重慶南路一段86號12樓', 0, 1);

    // (4) 電話
    $pdf->Cell(0, 6, '電話：(02)2311-2345  傳真：(02) 2311-2688', 0, 1);

    // Footer 圖片
    $pdf->Image(__DIR__ . "/../image/receipts_footer_{$language}.png", 20, 270, 170);

    // 輸出
    $filename = date('Ymd') . "_$receipt_num.pdf";
    ob_end_clean();
    $pdf->Output($filename, 'D');
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => '❗ 產生報表失敗：' . $e->getMessage()]);
}
?>