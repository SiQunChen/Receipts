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
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Fatal Error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

use TCPDF;

try {
    // 取得表單欄位
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedDataJson = $_POST['selectedData'] ?? '';
        $uncheckedDisbsDataJson = $_POST['uncheckedDisbsData'] ?? '';
        $language = $_POST['language'] ?? 'chinese';
        $is_paid = $_POST['ispaid'] ?? '';
        $receipt_num = $_POST['receiptNum'] ?? sprintf("R%s%s%04d", date('y'), date('m'), 1);
        $type = $_POST['type'] ?? '';

        // 將 JSON 格式轉換為 PHP 陣列
        $selectedData = json_decode($selectedDataJson, true);
        $uncheckedDisbsData = json_decode($uncheckedDisbsDataJson, true);

        // 取得 session 中對應的資料
        $index = $selectedData['index'];
        $session_data = $_SESSION['dataArray'][$index] ?? null;

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $entity = urldecode($_GET['entity'] ?? '');
        $case_num = urldecode($_GET['case_num'] ?? '');
        $deb_num = urldecode($_GET['invoice'] ?? '');
        $currency = urldecode($_GET['currency'] ?? 'TWD');
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
        throw new Exception("只接受 POST 請求");
    }

    // 執行 list
    if ($type === 'list') {
        // 取得欄位資料
        $entity = $selectedData['entity'];
        $sent = $_POST['sent'] ?? $session_data['sent'];
        $receipt_tax_id = $session_data['receipt_tax_id'];
        $case_num = $session_data['case_num'];
        $deb_num = $session_data['deb_num'];
        $note_legal = $selectedData['note_legal'];
        $note_disbs = $selectedData['note_disbs'];
        $wht = (float)str_replace(',', '', $selectedData['wht']);
   
        ### 判斷幣別
        // unpaid 屬於外幣的情況
        $isEnglishCurrency = in_array($session_data['billing_currency'], ['English (USD)', 'English (EUR)']);

        // paid 屬於外幣的情況
        $hasForeignValues = !is_null($session_data['foreign_legal2']) || !is_null($session_data['foreign_disbs2']);

        $isForeign = ($is_paid === 'false' && $isEnglishCurrency) || ($is_paid === 'true' && $hasForeignValues) ? true : false;

        if ($isForeign) {
            $currency = $session_data['currency2'];
            $services = $session_data['foreign_legal2'];
        } else {
            $currency = 'TWD';
            $services = $session_data['legal_services'];
        }

        ### 取得代墊金額
        // 取得該筆所有代墊資料
        $disbsDataArray = getReceiptsDetail($is_paid, $session_data['deb_num']);

        // 取得該筆未勾選的代墊資料
        $uncheckedDisbs = $uncheckedDisbsData[$session_data['deb_num']] ?? null;

        // 計算未勾選的代墊金額
        $uncheckedDisbsAmount = 0;
        if ($uncheckedDisbs !== null) {
            foreach ($uncheckedDisbs as $rowUncheckedDisbs) {
                $uncheckedDisbsAmount += $isForeign ? $rowUncheckedDisbs['foreign_amount'] : $rowUncheckedDisbs['amount'];
            }
        }

        // 扣除未勾選金額後的代墊總額
        $disbs = $isForeign ? $session_data['foreign_disbs2'] : $session_data['disbs'];
        $disbs -= $uncheckedDisbsAmount;

        // 計算代墊明細
        $official_fee = 0;
        $other_fee = 0;
        $uncheckedIds = array_column($uncheckedDisbs ?? [], 'id');

        foreach ($disbsDataArray as $disbs_data) {
            if (!in_array($disbs_data['id'], $uncheckedIds)) {
                $fee = $isForeign ? $disbs_data['foreign_amount2'] : $disbs_data['ntd_amount'];
                if ($disbs_data['disb_name'] === 'Official Fee') {
                    $official_fee += $fee;
                } else {
                    $other_fee += $fee;
                }
            }
        }

        // 計算總金額
        $total = $services + $disbs;
    } elseif ($type === 'edit') { // 執行 edit

        // 取得欄位資料
        $receipt_num = $session_data['receipt_num'];
        $entity = $session_data['receipt_entity'];
        $sent = $session_data['bills_sent'];
        $receipt_tax_id = $session_data['receipt_tax_id'];
        $case_num = $session_data['case_num'];
        $deb_num = $session_data['deb_num'];
        $note_legal = $selectedData['note_legal'];
        $note_disbs = $selectedData['note_disbs'];

        // 取得該筆所有代墊資料
        $disbsDataArray = getReceiptsDetail(null, null, $receipt_num);

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
    // 計算從哪邊開始靠右
    $rightMargin = 24; // 右邊邊界
    $totalWidth = 56; // 每行兩個 Cell 的總寬度 = 28 + 28
    $startX = $pdf->getPageWidth() - $rightMargin - $totalWidth;

    // 第一列
    // 左邊
    $pdf->SetFont('msjh', '', 12);
    $pdf->Cell(10, 6, '謹致', 0, 0);
    // 右邊
    $pdf->SetX($startX);
    $pdf->Cell(28, 6, '收據號碼：', 0, 0);
    $pdf->SetFont('msjh', '', 10);
    $pdf->Cell(28, 6, $receipt_num, 'B', 1);

    // 第二列
    // 左邊，因為英文、數字的字體 12 視覺上比中文 12 還要大，故會根據每個字元不同做調整
    $pdf->SetFont('msjh', '', 12);
    $pdf->Cell(10, 6, '', 0, 0);
    foreach (mb_str_split($entity) as $char) {
        // 判斷是否為中文字（Unicode 範圍 \x{4e00}-\x{9fff}）
        if (preg_match('/\p{Han}/u', $char)) {
            $pdf->SetFont('msjh', '', 12); // 中文用 12pt
        } else {
            $pdf->SetFont('msjh', '', 10); // 非中文用 10pt
        }

        // 輸出每個字元
        $pdf->Cell($pdf->GetStringWidth($char), 6, $char, 0, 0);
    }
    // 右邊
    $pdf->SetFont('msjh', '', 12);
    $pdf->SetX($startX);
    $pdf->Cell(28, 6, '日　　期：', 0, 0);
    $pdf->SetFont('msjh', '', 10);
    $pdf->Cell(28, 6, (new DateTime($sent))->format('Y/n/j'), 'B', 1);

    // 第三列
    // 左邊
    if (!empty($receipt_tax_id)) {
        $pdf->SetFont('msjh', '', 12);
        $pdf->Cell(10, 6, '', 0, 0);
        $pdf->Cell(22, 6, '統一編號：', 0, 0);
        $pdf->SetFont('msjh', '', 10);
        $pdf->Cell(100, 7, $receipt_tax_id, 0, 0);
    }
    // 右邊
    $pdf->SetFont('msjh', '', 12);
    $pdf->SetX($startX);
    $pdf->Cell(28, 6, '本所案號：', 0, 0);
    $pdf->SetFont('msjh', '', 10);
    $pdf->Cell(28, 6, $case_num, 'B', 1);

    // 第四列
    $pdf->SetFont('msjh', '', 12);
    $pdf->SetX($startX);
    $pdf->Cell(28, 6, '帳單號碼：', 0, 0);
    $pdf->SetFont('msjh', '', 10);
    $pdf->Cell(28, 6, $deb_num, 'B', 1);

    // 第五列
    $pdf->SetFont('msjh', '', 12);
    $pdf->Cell(30, 6, '茲收到下列費用，此據。', 0, 1);

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
    $image_file = __DIR__ . "/../image/receipts_seal.jpg";

    // 取得圖片原始尺寸以計算顯示高度
    list($original_width, $original_height) = @getimagesize($image_file);

    // 設定圖片顯示寬度與根據比例計算高度
    $display_width = 50;
    $display_height = $display_width * ($original_height / $original_width);

    // 定義圖片左上角放置位置
    $x_pos = 122;
    $y_pos = $pdf->GetY() - 6; // 從目前位置上移 6 單位

    // 計算旋轉中心點 (圖片中心)
    $center_x = $x_pos + ($display_width / 2);
    $center_y = $y_pos + ($display_height / 2);

    // 設定旋轉角度：順時針 0.3 度 (TCPDF 逆時針，故為 -0.3)
    $rotation_angle = -0.3;

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
    $filename = "大藍收據_" . date('Ymd') . ".pdf";
    ob_end_clean();
    $pdf->Output($filename, 'D');
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['message' => '❗ 產生報表失敗：' . $e->getMessage()]);
}
?>