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

function getInvoicePeriod($date) {
    // 解析日期
    $dateObj = DateTime::createFromFormat('Y/m/d', $date);
    if (!$dateObj) {
        return "無效日期格式";
    }

    // 取得年份 (民國年)
    $year = (int)$dateObj->format('Y') - 1911;

    // 取得月份
    $month = (int)$dateObj->format('m');

    // 計算發票期數 (1-2, 3-4, 5-6, 7-8, 9-10, 11-12)
    $startMonth = $month - (($month - 1) % 2);
    $endMonth = $startMonth + 1;

    // 組合發票期數格式
    return sprintf("%d / %d-%d", $year, $startMonth, $endMonth);
}

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
    $spreadsheet->getDefaultStyle()->getFont()->setSize(12)->setName('Times New Roman');
    $sheet->getDefaultRowDimension()->setRowHeight(24.5);
    $sheet->freezePane('A3');

    $spreadsheet->getProperties()
        ->setCreator("SC")
        ->setLastModifiedBy("SC")
        ->setTitle("Receipts Report1")
        ->setSubject("Receipts Report1")
        ->setDescription("Receipts Report1")
        ->setKeywords("Receipts Report1")
        ->setCategory("Receipts Report1 file");

    ### enter title data
    $title = [
        "日期" => [],
        "收據編號" => [],
        "案號" => [],
        "請款單編號" => [],
        "收入金額" => ["Subtotal", "Tax", "Total"],
        "收款日" => [],
        "Payment" => ["Method"],
        "印花稅" => ["報繳月份"],
    ];
    
    ### display title
    // 設定字體樣式
    $titleFontStyle = [
        'font' => [
            'name' => '標楷體',
            'size' => 12,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    $sheet->getStyle("B1:K2")->applyFromArray($titleFontStyle);
    $sheet->getStyle('F2')->getFont()->setName('Times New Roman');
    $sheet->getStyle('G2')->getFont()->setName('Times New Roman');
    $sheet->getStyle('H2')->getFont()->setName('Times New Roman');
    $sheet->getStyle('J1')->getFont()->setName('Times New Roman');
    $sheet->getStyle('J2')->getFont()->setName('Times New Roman');

    // 實際欄寬會比這些欄寬小 0.6
    $col_widths = [
        'A' => 4.68,
        'B' => 6.6,
        'C' => 10.6,
        'D' => 14.6,
        'E' => 14.1,
        'F' => 10.6,
        'G' => 10.6,
        'H' => 10.6,
        'I' => 10.6,
        'J' => 10.6,
        'K' => 9.52
    ];
    foreach ($col_widths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }
    
    // 填入 title
    $row1 = 1;
    $row2 = 2;
    $colIndex = 2; // 起始欄位
    
    foreach ($title as $mainTitle => $subTitles) {
        $colStart = $colIndex;
    
        if (!empty($subTitles)) {
            // 合併第一列的欄位
            $colEnd = $colIndex + count($subTitles) - 1;
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($colStart) . "$row1:" . Coordinate::stringFromColumnIndex($colEnd) . "$row1");
            // 填入第二列的欄位
            foreach ($subTitles as $subTitle) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex) . "$row2", $subTitle);
                $colIndex++;
            }
        } else {
            // 合併 row1, row2
            $sheet->mergeCells(Coordinate::stringFromColumnIndex($colIndex) . "$row1:" . Coordinate::stringFromColumnIndex($colIndex) . "$row2");
            $colIndex++;
        }
        // 填入 mainTitle
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colStart) . "$row1", $mainTitle);
    }

    $excelRow = 3;
    $total_subtotal = 0;
    $total_tax = 0;
    $total_amount = 0;

    ### display content
    foreach ($selectedData as $i => $data) {
        $index = $data['index'];
        $data_array = $_SESSION['dataArray'][$index] ?? null;
        $new_receipt_num = getNewReceiptNum();

        ### display 日期
        $col = 2;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue($local, date("n/j"));

        ### display 收據編號
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValueExplicit($local, $new_receipt_num, DataType::TYPE_STRING);

        ### display 案號
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->setCellValueExplicit($local, $data_array['case_num'], DataType::TYPE_STRING);

        ### display 請款單編號
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->setCellValueExplicit($local, $data_array['deb_num'], DataType::TYPE_STRING);

        ### display Subtotal
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $subtotal = $data_array['legal_services'] + $data_array['disbs'];
        $total_subtotal += $subtotal;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($local, $subtotal);

        ### display Tax
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $tax = floatval(str_replace(',', '', $selectedData[$index]['wht']));
        $total_tax += $tax;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($local, $tax);

        ### display Total
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $amount = $subtotal + $tax;
        $total_amount += $amount;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->setCellValue($local, $amount);
        
        ### display 收款日
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue($local, $data_array['rec_date']);

        ### display Payment Method
        $col++;
        $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
        $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue($local, $data_array['method']);

        ### display 印花稅報繳月份
        $col++;
        if (!empty($data_array['rec_date'])) {
            $local = Coordinate::stringFromColumnIndex($col) . $excelRow;
            $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->setCellValueExplicit($local, getInvoicePeriod($data_array['rec_date']), DataType::TYPE_STRING);    
        }

        $excelRow++;
    }

    ### total
    $local = 'B' . $excelRow;
    $sheet->mergeCells("B$excelRow:E$excelRow");
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($local)->getFont()->setName('標楷體');
    $sheet->setCellValue($local, '合計');

    $local = 'F' . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($local, $total_subtotal);

    $local = 'G' . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($local, $total_tax);

    $local = 'H' . $excelRow;
    $sheet->getStyle($local)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle($local)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->setCellValue($local, $total_amount);

    ### 設定框線
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => '000000'],
            ],
        ],
    ];
    $len = count($title);
    $end = 'K' . $excelRow;
    $sheet->getStyle("B1:$end")->applyFromArray($styleArray);

    // 設定檔案名
    $filename = 'receipts_report1_' . date('Ymd') . '.xlsx';

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