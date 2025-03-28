<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.ini';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function getNewReceiptNum() {
    $dblink = pg_pconnect(DB_CONNECT);
    if (!$dblink) {
        exit("無法連接到資料庫: " . pg_last_error());
    }

    // 取得當前年份與月份 (兩位數格式)
    $year = date('y');
    $month = date('m');
    $like_pattern = "R{$year}{$month}%";
    
    // 查詢當月最新的收據編號
    $query = "SELECT receipt_num FROM receipt WHERE receipt_num LIKE $1 ORDER BY receipt_num DESC LIMIT 1";
    $result = pg_query_params($dblink, $query, [$like_pattern]);

    // 計算新的流水號
    $latest_serial = ($result && pg_num_rows($result) > 0)
        ? (int)substr(pg_fetch_result($result, 0, 'receipt_num'), 5) + 1
        : 1;
    
    $new_receipt_num = sprintf("R%s%s%04d", $year, $month, $latest_serial);

    return $new_receipt_num;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['dataArray'])) {
        echo json_encode(['message' => 'Session dataArray 不存在']);
        exit;
    }
    $dataArray = $_SESSION['dataArray'];
    $selectedData = json_decode($_POST['selectedData'], true);
    $uncheckedDisbsData = json_decode($_POST['uncheckedDisbsData'], true);

    ### create excel report
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $spreadsheet->getDefaultStyle()->getFont()->setSize(12);
    $sheet->getDefaultColumnDimension()->setWidth(8.1);

    $spreadsheet->getProperties()
        ->setCreator("SC")
        ->setLastModifiedBy("SC")
        ->setTitle("Receipts Report2")
        ->setSubject("Receipts Report2")
        ->setDescription("Receipts Report2")
        ->setKeywords("Receipts Report2")
        ->setCategory("Receipts Report2 file");

    ### enter title data
    $title = array( "憑證名稱", "起訖號碼", "張數", "小計金額", "稅率", "應納稅額");

    ### display title
    $titleFontStyle = [
        'font' => [
            'name' => '新細明體',
            'size' => 10,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
        ],
    ];

    $col_char = array();

    // 實際欄寬會比這些欄寬小 0.6
    $col_widths = [
        'B' => 15.68,
        'C' => 9.93,
        'D' => 5.1,
        'E' => 10.93,
        'F' => 6.18,
        'G' => 9.52
    ];
    
    foreach ($title as $i => $text) {
        $col = Coordinate::stringFromColumnIndex($i + 2);
        array_push($col_char, $col);
        $cell = $col . '2';
    
        if (isset($col_widths[$col])) {
            $sheet->getColumnDimension($col)->setWidth($col_widths[$col]);
        }

        $sheet->setCellValue($cell, $text);
        $sheet->getStyle($cell)->applyFromArray($titleFontStyle);
    }    

    $excelRow = 3;
    $total_amount = 0;

    ### display content
    foreach ($selectedData as $i => $data) {
        $index = $data['index'];
        $data_array = $_SESSION['dataArray'][$index] ?? null;
        $new_receipt_num = getNewReceiptNum();

        ### display 憑證名稱
        $col = 0;
        $local = $col_char[$col] . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle($local)->getFont()->setName('新細明體')->setSize(10);
        $sheet->setCellValue($local, '銀錢收據(服務公費)');

        ### display 起訖號碼
        $col++;
        $local = $col_char[$col] . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle($local)->getFont()->setSize(10);
        $sheet->setCellValueExplicit($local, $new_receipt_num, DataType::TYPE_STRING);

        ### display 張數
        $col++;
        $local = $col_char[$col] . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getFont()->setSize(10);
        $sheet->setCellValue($local, 1); // 預設值 1

        ### display 小計金額
        $col++;
        $local = $col_char[$col] . $excelRow;
        $amount = $data_array['legal_services'] + $data_array['disbs'];
        $total_amount += $amount;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getFont()->setSize(10);
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($local, $amount);

        ### display 稅率
        $col++;
        $local = $col_char[$col] . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getFont()->setSize(10);
        $sheet->setCellValue($local, 0.004); // 設定實際數值為 0.004 (0.40%)
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('0.00%'); // 設定為百分比格式，保留 2 位小數

        ### display 應納稅額
        $col++;
        $local = $col_char[$col] . $excelRow;
        $tax = floor($amount * 0.004);
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getFont()->setSize(10);
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($local, $tax);

        $excelRow++;
    }

    ### total
    $local = $col_char[1] . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle($local)->getFont()->setSize(10);
    $sheet->setCellValue($local, 'Total');

    $local = $col_char[2] . $excelRow;
    $sheet->getStyle($col_char[2] . $excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getFont()->setSize(10);
    $sheet->setCellValue($col_char[2] . $excelRow, count($selectedData));

    $local = $col_char[3] . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getFont()->setSize(10);
    $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($local, $total_amount);

    $local = $col_char[5] . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getFont()->setSize(10);
    $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($local, floor($total_amount * 0.004));

    ### 設定框線
    $styleArray = [
        'borders' => [
            'outline' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];
    $len = count($title);
    $end = Coordinate::stringFromColumnIndex($len + 1) . $excelRow;
    $sheet->getStyle("B2:$end")->applyFromArray($styleArray);

    // 設定檔案名
    $filename = 'receipts_report2_' . date('Ymd') . '.xlsx';

    // 清空緩衝區，防止其他輸出
    ob_end_clean(); 

    // 設置 Header 來讓瀏覽器下載檔案
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    # 寫入 Excel 檔案到輸出流
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;
}
?>