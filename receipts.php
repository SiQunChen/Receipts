<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Receipts</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/winkler.css">
    <link rel="stylesheet" href="css/winkler-rwd.css">
    <link rel="stylesheet" href="css/left-search.css">
    <link rel="stylesheet" href="css/winkler-sc.css">

    <!--[if lt IE 9]>
   <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
   <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
</head>

<body data-spy="scroll" data-target=".amanda-nav">
    <?php
    session_start();
    require_once("menu.php");
    ?>

    <!-- 側邊搜尋內容 -->
    <div id="sidebar-wrapper">
        <div class="sidebar-nav">

            <!-- 搜尋條件內容 -->
            <div class="search-con">
                <div class="heading">
                    <h2>Receipts</h2>
                </div>

                <form method="POST" action="receipts.php" role="form">
                    <!-- 共用欄位: Case Number、Match/Like、Invoice、is_paid -->
                    <div class="form-group">
                        <label class="col-half">Case Number</label>
                        <input type="text" class="col-half" name="case_number">
                    </div>
                    <div class="form-group">
                        <label class="col-half" style="width: 100%;">
                            <input type="radio" name="match_or_like" id="match" value="match" checked>
                            <label for="match" style="margin-right: 13px;">Match</label>

                            <input type="radio" name="match_or_like" id="like" value="like">
                            <label for="like">Like</label>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="col-half">Invoice</label>
                        <input type="text" class="col-half" name="invoice">
                    </div>
                    <div class="form-group">
                        <label class="col-half" style="width: 100%;">
                            <input type="radio" name="is_paid" id="unpaid" value="unpaid" checked>
                            <label for="unpaid" style="margin-right: 5px;">Unpaid</label>

                            <input type="radio" name="is_paid" id="paid" value="paid">
                            <label for="paid">Paid</label>
                        </label>
                    </div>

                    <!-- ------ Unpaid-only 區塊 ------ -->
                    <div id="unpaidFields">
                        <div class="form-group">
                            <label class="col-half">Case Manager</label>
                            <input type="text" class="col-half" name="initial">
                        </div>
                        <div class="form-group">
                            <label class="col-half">Bills Month</label>
                            <select name="bills_year">
                                <?php
                                $year = date("Y");
                                echo "<option>" . $year - 1 . "</option>"; // 去年
                                echo "<option selected>" . $year . "</option>"; // 今年
                                echo "<option>" . $year + 1 . "</option>"; // 明年
                                ?>
                            </select>
                            <select name="bills_month">
                                <?php
                                for ($month = 1; $month <= 12; $month++) {
                                    if ($month == date("m")) {
                                        echo "<option selected>" . sprintf("%02d", $month) . "</option>";
                                    } else {
                                        echo "<option>" . sprintf("%02d", $month) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <!-- ------ Paid-only 區塊 (預設隱藏) ------ -->
                    <div id="paidFields" style="display: none;">
                        <div class="form-group">
                            <label class="col-half">Payment Method</label>
                            <input type="text" class="col-half" name="method">
                        </div>
                        <div class="form-group">
                            <label class="col-half">Start Date</label>
                            <input type="date" class="col-half" name="start_date" min="2019-01-01" max="2100-01-01"
                                value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="col-half">End Date</label>
                            <input type="date" class="col-half" name="end_date" min="2019-01-01" max="2100-01-01">
                        </div>
                    </div>

                    <!-- 共用欄位: Receipt Month、申請單號 -->
                    <div class="form-group">
                        <label class="col-half">Receipt Month</label>
                        <select name="receipt_year">
                            <?php
                            echo "<option selected> </option>";
                            echo "<option>" . $year . "</option>"; // 今年
                            echo "<option>" . $year + 1 . "</option>"; // 明年
                            ?>
                        </select>
                        <select name="receipt_month">
                            <?php
                            echo "<option selected> </option>";
                            for ($month = 1; $month <= 12; $month++) {
                                echo "<option>" . sprintf("%02d", $month) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="col-half">申請單號</label>
                        <input type="text" class="col-half" name="application_num">
                    </div>

                    <!-- 按鈕區 -->
                    <div class="s-form-bot">
                        <button type="submit" name="list" value="list" style="margin-bottom: 12px;">List</button>
                        <br>&nbsp;&nbsp;&nbsp;&nbsp;
                        <button type="submit" name="create" value="create" style="background-color:rgb(91, 149, 183);">
                            預開
                        </button>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <button type="submit" name="edit" value="edit" style="background-color:rgb(123, 154, 172);">
                            Edit
                        </button>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <button type="submit" name="change" value="change" style="background-color:rgb(181, 197, 207);">
                            Change
                        </button>
                    </div>
                </form>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const unpaidFields = document.getElementById('unpaidFields');
                    const paidFields = document.getElementById('paidFields');
                    const paidRadios = document.querySelectorAll('input[name="is_paid"]');

                    function togglePaidFields() {
                        if (this.value === 'paid' && this.checked) {
                            unpaidFields.style.display = 'none';
                            paidFields.style.display = 'block';
                        } else if (this.value === 'unpaid' && this.checked) {
                            paidFields.style.display = 'none';
                            unpaidFields.style.display = 'block';
                        }
                    }

                    paidRadios.forEach(radio => {
                        radio.addEventListener('change', togglePaidFields);
                    });
                });
            </script>

            <!-- 搜尋條件內容結束 -->

            <!-- 頁籤 -->

            <div class="search-btn">
                <div class="sidebar-colse">

                    <!-- search.js控製申縮的id在這 -->
                    <a id="menu-close" href="#" class="btn btn-default btn-lg btn-winkier toggle">
                        <i class="glyphicon glyphicon-search">Receipts</i>
                    </a>

                </div>
            </div>

            <!-- 頁籤結束 -->

            <div class="clear"></div>
        </div>
    </div>

    <!-- 側邊搜尋內容結束-->

    <!--搜尋內容開始-->

    <div id="winkler-container"><!-- 這裡跟著變動大小的div -->
        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>
                    <?php
                    require_once('test_db/receipts_list_db.php');
                    require_once('test_db/receipts_create_db.php');

                    // 顯示提示並跳轉函數
                    function showAlertAndRedirect($message, $url = 'http://slashlaw-new/receipts.php')
                    {
                        echo "<script>
                                    alert('" . json_encode($message) . "'); 
                                    window.location.href = '$url';
                                </script>";
                        exit;
                    }

                    // 處理 POST 請求
                    if ($_SERVER["REQUEST_METHOD"] == "POST") {
                        // 按下 "List" 按鈕的情況
                        if (isset($_POST['list'])) {
                            // 蒐集輸入數據
                            $case_num = $_POST['case_number'];
                            $match_or_like = $_POST['match_or_like'];
                            $invoice = $_POST['invoice'];
                            $is_paid = $_POST['is_paid'];
                            $initial = $_POST['initial'];
                            $bills_year = $_POST['bills_year'];
                            $bills_month = $_POST['bills_month'];
                            $payment_method = $_POST['method'];
                            $payment_start = $_POST['start_date'];
                            $payment_end = $_POST['end_date'] !== '' ? $_POST['end_date'] : $payment_start;
                            $receipt_year = $_POST['receipt_year'];
                            $receipt_month = $_POST['receipt_month'];
                            $application_num = $_POST['application_num'];

                            // 判斷 receipt_year, receipt_month 有效性
                            function isReceiptDateValid(int $receipt_year, int $receipt_month) {
                                $receipt_date_str = sprintf('%04d-%02d', $receipt_year, $receipt_month);

                                // --- 計算有效的月份範圍 ---
                                // 1. 計算往前推一個月和兩個月的日期
                                $date_minus_1 = (new DateTime())->modify('-1 month')->format('Y-m'); // 格式為 YYYY-MM
                                $date_minus_2 = (new DateTime())->modify('-2 months')->format('Y-m');

                                // 2. 計算往後推一個月和兩個月的日期
                                $date_plus_1 = (new DateTime())->modify('+1 month')->format('Y-m');
                                $date_plus_2 = (new DateTime())->modify('+2 months')->format('Y-m');

                                // 建立一個包含所有有效年月的陣列
                                $valid_months = [
                                    $date_minus_2, // 前兩個月
                                    $date_minus_1, // 前一個月
                                    $date_plus_1,  // 後一個月
                                    $date_plus_2   // 後兩個月
                                ];

                                // --- 進行判斷 ---
                                if (in_array($receipt_date_str, $valid_months)) {
                                    return true;
                                } else {
                                    return false;
                                }
                            }

                            if (!empty($receipt_year) && !empty($receipt_month) && 
                                !isReceiptDateValid($receipt_year, $receipt_month)) {
                                showAlertAndRedirect('Receipt 日期必須為當前日期前後兩個月(不含當前月份)');
                            }

                            // 取得資料
                            $dataArray = getReceipts(
                                'list',
                                $case_num,
                                $match_or_like,
                                $invoice,
                                $is_paid,
                                $initial,
                                $bills_year,
                                $bills_month,
                                $payment_method,
                                $payment_start,
                                $payment_end,
                                $receipt_year,
                                $receipt_month,
                                $application_num
                            );

                            // 檢查資料有效性
                            if (!is_array($dataArray)) {
                                showAlertAndRedirect($dataArray);
                            }

                            if ($application_num !== '') {
                                echo '申請單號 : ' . $application_num;
                                echo "&nbsp;&nbsp;&nbsp;&nbsp;";
                                echo 'Receipt : ' . $receipt_year . '-' . $receipt_month;
                            } elseif ($is_paid === 'unpaid') {
                                echo 'Search : ' . $bills_year . '-' . $bills_month;
                                echo "&nbsp;&nbsp;&nbsp;&nbsp;";
                                echo 'Receipt : ' . $receipt_year . '-' . $receipt_month;
                            } else {
                                echo 'Search : ' . $payment_start . '~' . $payment_end;
                                echo "&nbsp;&nbsp;&nbsp;&nbsp;";
                                echo 'Receipt : ' . $receipt_year . '-' . $receipt_month;
                            }

                            $_SESSION['dataArray'] = $dataArray;

                            // Debug 輸出
                            // print_r($dataArray);
                    
                            echo '<button type="button" id="export_list_btn">Export</button>';
                            echo '<select name="lang" id="lang" style="float:right; margin-right:15px;">
                                    <option value="chinese" selected>中文</option>
                                    <option value="english">英文</option>
                                  </select>';
                            if ($is_paid === 'paid') {
                                echo '<input type="checkbox" id="merge" name="merge" style="width:20px; margin-right:15px; float:right; transform: scale(1.8);">';
                                echo '<div style="margin-right: 5px; float: right;">Merge</div>';
                            }
                        }
                        // 按下 "預開" 按鈕的情況
                        elseif (isset($_POST['create'])) {
                            // 是否需要預帶 Case num, Entity
                            $case_num = $_POST['case_number'] ?? '';
                            $match_or_like = $_POST['match_or_like'] ?? '';
                            $preset_data = getEntity($case_num, $match_or_like);

                            // 檢查資料有效性
                            if (!is_array($preset_data)) {
                                showAlertAndRedirect($preset_data);
                            }

                            echo '預開';
                        }
                        // 按下 "Edit" 按鈕的情況
                        elseif (isset($_POST['edit'])) {
                            // 蒐集輸入數據
                            $case_num = $_POST['case_number'];
                            $match_or_like = $_POST['match_or_like'];
                            $invoice = $_POST['invoice'];
                            $receipt_year = $_POST['receipt_year'];
                            $receipt_month = $_POST['receipt_month'];

                            // 取得資料
                            $dataArray = getReceipts(
                                'edit',
                                $case_num,
                                $match_or_like,
                                $invoice,
                                '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                $receipt_year,
                                $receipt_month,
                                ''
                            );

                            // 檢查資料有效性
                            if (!is_array($dataArray)) {
                                showAlertAndRedirect($dataArray);
                            }

                            // 顯示查詢日期
                            echo 'Search : ' . $receipt_year . '-' . $receipt_month;

                            $_SESSION['dataArray'] = $dataArray;

                            // Debug 輸出
                            // print_r($dataArray);
                    
                            echo '<button type="button" id="export_edit_btn">Export</button>';
                            echo '<button type="button" id="invalid_edit_btn" style="margin-right: 15px;">Invalid</button>';
                            echo '<select name="lang" id="lang" style="float:right; margin-right:15px;">
                                    <option value="chinese" selected>中文</option>
                                    <option value="english">英文</option>
                                  </select>';
                        }
                        // 按下 "Change" 按鈕的情況
                        elseif (isset($_POST['change'])) {
                            echo 'Change';
                        }
                    }
                    // 處理 GET 請求或初始狀態
                    else {
                        // 取得資料
                        $dataArray = getReceipts(
                            'list',
                            '',
                            '',
                            '',
                            'unpaid',
                            '',
                            date("Y"),
                            date("m"),
                            '',
                            '',
                            '',
                            '',
                            '',
                            ''
                        );

                        // 檢查資料有效性
                        if (!is_array($dataArray)) {
                            showAlertAndRedirect($dataArray);
                        }

                        // 顯示查詢日期
                        echo 'Search : ' . date('Y-m-d');

                        $_SESSION['dataArray'] = $dataArray;

                        // Debug 輸出
                        // print_r($dataArray);
                    
                        echo '<button type="button" id="export_list_btn">Export</button>';
                        echo '<select name="lang" id="lang" style="float:right; margin-right:15px;">
                                <option value="chinese" selected>中文</option>
                                <option value="english">英文</option>
                              </select>';
                    }
                    ?>
                </h3>
            </div>

            <!-- 根據不同的 post 參數決定要顯示的區塊 -->
            <div class="table-responsive">
                <?php if ($_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST['list'])): ?>
                    <!-- 列表的表單包覆整個表格 -->
                    <table class="table hv1-table table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">
                                    <input type="checkbox" name="select_all" style="width:100%;"
                                        onchange="toggleAll(this, 'row_check_box')">
                                </th>
                                <th class="text-center">Detail</th>
                                <th class="text-center">Entity</th>
                                <th class="text-center">Case Num</th>
                                <th class="text-center">Invoice</th>
                                <th class="text-center">Services</th>
                                <th class="text-center">Disbs</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">WHT</th>
                                <th class="text-center">Sent</th>
                            </tr>
                        </thead>
                        <tbody id="list_table">
                            <!-- 表格內容將由 JavaScript 動態生成 -->
                        </tbody>
                    </table>

                <?php elseif (isset($_POST['create'])): ?>
                    <!-- 新增資料畫面：顯示新增表單 -->
                    <form method="POST" action="test_db/receipts_create_db.php">
                        <div class="form-horizontal">
                            <!-- Entity & Case Number -->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="entity" class="col-md-2 control-label">Entity</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <?php
                                        if (empty($preset_data)) {
                                            echo "<input type='text' id='entity' name='entity' class='form-control' required>";
                                        } else {
                                            $entityValue = htmlspecialchars($preset_data[0]['party_en_name_billing'], ENT_QUOTES, 'UTF-8');
                                            echo "<input type='text' id='entity' name='entity' class='form-control' value='$entityValue' required>";
                                        }
                                        ?>
                                    </div>
                                </div>

                                <label for="case_num" class="col-md-2 control-label">Case Number</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <?php
                                        if (empty($preset_data)) {
                                            echo "<input type='text' id='case_num' name='case_num' class='form-control'>";
                                        } else {
                                            $caseNumValue = htmlspecialchars($preset_data[0]['case_num'], ENT_QUOTES, 'UTF-8');
                                            echo "<input type='text' id='case_num' name='case_num' class='form-control' value='$caseNumValue'>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice & Currency -->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="invoice" class="col-md-2 control-label">Invoice</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <input type="text" id="invoice" name="invoice" class="form-control">
                                    </div>
                                </div>

                                <label for="invoice" class="col-md-2 control-label">Currency</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <select id="currency" name="currency" class="form-control" required>
                                            <option value="TWD" selected>TWD</option>
                                            <option value="USD">USD</option>
                                            <option value="EUR">EUR</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Services & Note Legal -->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="services" class="col-md-2 control-label">Services</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <input type="number" step="any" id="services" name="services" class="form-control"
                                            value="0" required>
                                    </div>
                                </div>

                                <label for="note_legal" class="col-md-2 control-label">Note Legal</label>
                                <div class="col-md-4">
                                    <input type="text" id="note_legal" name="note_legal" class="form-control">
                                </div>
                            </div>

                            <!-- Disbursements & Note Disbs -->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="disbursements" class="col-md-2 control-label">Disbursements</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <input type="number" step="any" id="disbursements" name="disbursements"
                                            class="form-control" value="0" required>
                                    </div>
                                </div>

                                <label for="note_disbs" class="col-md-2 control-label">Note Disbs</label>
                                <div class="col-md-4">
                                    <input type="text" id="note_disbs" name="note_disbs" class="form-control">
                                </div>
                            </div>

                            <!-- WHT & Status-->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="wht" class="col-md-2 control-label">WHT</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <input type="number" step="any" id="wht" name="wht" class="form-control" value="0"
                                            required>
                                    </div>
                                </div>

                                <label for="status" class="col-md-2 control-label">Status</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <select id="status" name="status" class="form-control" required>
                                            <option value="paid" selected>Paid</option>
                                            <option value="unpaid">Unpaid</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- language -->
                            <div class="form-group" style="margin-right: 0px;">
                                <label for="language" class="col-md-2 control-label">Language</label>
                                <div class="col-md-4">
                                    <div class="form-inline">
                                        <select id="language" name="language" class="form-control" required>
                                            <option value="chinese" selected>中文</option>
                                            <option value="english">英文</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div style="text-align: center">
                                <button type="submit" name="create_receipt" value="create_receipt" class="btn btn-primary">
                                    Export
                                </button>
                            </div>
                        </div>
                    </form>

                <?php elseif (isset($_POST['edit'])): ?>
                    <!-- 列表的表單包覆整個表格 -->
                    <table class="table hv1-table table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">
                                    <input type="checkbox" name="select_all" style="width:100%;"
                                        onchange="toggleAll(this, 'row_check_box')">
                                </th>
                                <th class="text-center">Receipt Num</th>
                                <th class="text-center">Entity</th>
                                <th class="text-center">Case Num</th>
                                <th class="text-center">Invoice</th>
                                <th class="text-center">Services</th>
                                <th class="text-center">Disbs</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">WHT</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="edit">
                            <?php
                            foreach ($dataArray as $i => $data) {
                                if ($data['currency'] === 'TWD') {
                                    $legal_services = $data['legal_services'];
                                    $disbs = $data['disbs'];
                                    $total = $data['total'];
                                    $wht = $data['wht'];
                                } else {
                                    $legal_services = $data['foreign_services'];
                                    $disbs = $data['foreign_disbs'];
                                    $total = $data['foreign_total'];
                                    $wht = $data['foreign_wht'];
                                }

                                $receipt_entity = htmlspecialchars($data['receipt_entity'], ENT_QUOTES);
                                $note_legal = $data['note_legal'] !== '' 
                                                ? 'value="' . htmlspecialchars($data['note_legal'], ENT_QUOTES) . '"' 
                                                : '';
                                $note_disbs = $data['note_disbs'] !== '' 
                                                ? 'value="' . htmlspecialchars($data['note_disbs'], ENT_QUOTES) . '"' 
                                                : '';
                                $status = $data['status'] === '1' ?
                                    "<td class='text-center'>有效</td>" :
                                    "<td class='text-center' style='color: red;'>作廢</td>";

                                echo "
                                    <tr>
                                        <td class='text-center'>
                                            <input type='checkbox' name='row_check_box[$i]' value='$i' style='width: calc(100%)'>
                                        </td>
                                        <td class='text-left'>{$data['receipt_num']}</td>
                                        <td class='text-left'>
                                            <textarea name='receipt_entity[$i]' rows='3'>$receipt_entity</textarea>
                                        </td>
                                        <td class='text-left'>{$data['case_num']}</td>
                                        <td class='text-center'>{$data['deb_num']}{$data['deb_extra']}</td>
                                        <td class='text-right' style='max-width: 150px'>
                                            $legal_services<br>
                                            <input type='text' name='note_legal[$i]' $note_legal style='width: calc(100%)'>
                                        </td>
                                        <td class='text-right' style='max-width: 150px'>
                                            $disbs<br>
                                            <input type='text' name='note_disbs[$i]' $note_disbs style='width: calc(100%)'>
                                        </td>
                                        <td class='text-right'>
                                            $total<br>{$data['currency']}
                                        </td>
                                        <td class='text-right'>$wht</td>
                                        $status
                                    </tr>";
                            }
                            ?>
                        </tbody>
                    </table>

                <?php elseif (isset($_POST['change'])): ?>
                    <!-- 作廢/替換畫面：顯示對應表單 -->
                    <form method="POST" action="test_db/receipts_replace_db.php">
                        <div class="winkler-sc-receipts-change-form-container">
                            <div class="winkler-sc-receipts-change-form-group">
                                <label for="invalid" class="form-label">原編號</label>
                                <input type="text" id="invalid" name="invalid" class="form-control">
                            </div>

                            <i class="glyphicon glyphicon-arrow-right"></i>

                            <div class="winkler-sc-receipts-change-form-group">
                                <label for="replacement" class="form-label">替換編號</label>
                                <input type="text" id="replacement" name="replacement" class="form-control">
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 20px; font-size: 16px;">
                            <label style="margin-right: 30px; font-weight: normal; cursor: pointer;">
                                <input type="radio" name="change_action" id="keepData" value="keep" checked 
                                       style="vertical-align: middle; margin-right: 5px; transform: scale(1.3); cursor: pointer;">
                                保留原編號資料
                            </label>
                            <label style="font-weight: normal; cursor: pointer;">
                                <input type="radio" name="change_action" id="deleteData" value="delete" 
                                       style="vertical-align: middle; margin-right: 5px; transform: scale(1.3); cursor: pointer;">
                                刪除原編號資料
                            </label>
                        </div>

                        <div class="winkler-sc-form-button-container">
                            <button type="submit" name="change" value="change" class="btn btn-primary" id="change_submit_btn">Change</button>
                        </div>
                    </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // 全選/取消全選函數
        function toggleAll(source, control_check_box) {
            var checkboxes = document.querySelectorAll(`input[type="checkbox"][name^="${control_check_box}"]`);
            for (var i = 0, n = checkboxes.length; i < n; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            // 處理 Export 函數：僅處理勾選的列，並收集該列的各欄位資料
            async function handleExportForm(event, type) {
                event.preventDefault();

                // 常數定義
                const CONFIG = {
                    URLS: {
                        PDF: "/test_db/receipts_report_db.php",
                        EXPORT: "/test_db/receipts_export_db.php",
                        RECEIPT_NUM: "/test_db/receipts_get_receipt_num_db.php"
                    }
                };

                // 統一下載處理
                async function downloadFile(url, formData) {
                    try {
                        const response = await fetch(url, { method: "POST", body: formData });
                        const contentType = response.headers.get("Content-Type") || "";

                        // 處理 JSON 回應
                        if (contentType.includes("application/json")) {
                            return handleJsonResponse(await response.text());
                        }

                        // 處理 PDF 下載
                        if (contentType.includes("application/pdf")) {
                            return handlePdfDownload(response);
                        }

                        // 非預期的回應類型
                        const errorText = await response.text();
                        alert(`⚠️ 報表產生失敗，伺服器回應非 PDF。\n\n${errorText}`);
                        
                    } catch (err) {
                        alert(`❌ 發生錯誤：${err.message}`);
                        console.error(err);
                    }
                }

                // 處理 JSON 回應
                function handleJsonResponse(text) {
                    if (!text.trim()) {
                        throw new Error("伺服器沒有回傳任何資料（空白 JSON）");
                    }

                    try {
                        const json = JSON.parse(text);
                        if (json.message?.includes("成功")) {
                            alert(json.message);
                            location.reload();
                        } else {
                            throw new Error(json.message || "操作失敗");
                        }
                    } catch (e) {
                        throw new Error(`JSON 格式解析失敗，回傳內容：${text}`);
                    }
                }

                // 處理 PDF 下載
                async function handlePdfDownload(response) {
                    const contentDisp = response.headers.get("Content-Disposition") || "";
                    const match = contentDisp.match(/filename\*?=(?:UTF-8''|")?([^;"']+)/i);

                    if (!match) {
                        throw new Error("無法取得檔案名稱");
                    }

                    const filename = decodeURIComponent(match[1].trim());
                    const blob = await response.blob();
                    
                    // 建立下載連結
                    const link = document.createElement("a");
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;
                    
                    // 觸發下載
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(link.href);
                }

                // 取得選中的資料
                function getSelectedRowsData(type) {
                    const selected = [];
                    const checkboxes = document.querySelectorAll("input[name^='row_check_box']:checked");

                    checkboxes.forEach(checkbox => {
                        const rowIndex = checkbox.value;
                        const row = checkbox.closest("tr");
                        const rowData = { index: rowIndex };

                        // 根據類型取得不同欄位
                        const fieldNames = type === 'list' 
                            ? ['party_en_name_bills', 'note_legal', 'note_disbs', 'wht']
                            : ['receipt_entity', 'note_legal', 'note_disbs'];

                        fieldNames.forEach(field => {
                            const element = row.querySelector(
                                field === 'party_en_name_bills' || field === 'receipt_entity'
                                        ? `textarea[name="${field}[${rowIndex}]"]`
                                        : `input[name="${field}[${rowIndex}]"]`
                            );
                            rowData[field === 'party_en_name_bills' ? 'entity' : field] = element?.value || "";
                        });

                        selected.push(rowData);
                    });

                    if (selected.length === 0) {
                        alert('請先勾選至少一筆資料後再 Export');
                    }

                    return selected;
                }

                // 取得未勾選的支出項目
                function getUncheckedDisbsData() {
                    const data = {};
                    Object.keys(localStorage).forEach(key => {
                        if (key.startsWith("uncheckedItems_")) {
                            const debNum = key.replace("uncheckedItems_", "");
                            data[debNum] = JSON.parse(localStorage.getItem(key));
                        }
                    });
                    return JSON.stringify(data);
                }

                // 檢查收據年月是否為當前年月，如果不是則設定 receipt_date 為該月最後一天
                function getReceiptDateIfNeeded(receipt_year, receipt_month) {
                    const today = new Date();
                    const currentFullYear = today.getFullYear(); // 直接取四位數年份
                    const currentMonth = today.getMonth() + 1; // 直接取數字月份 (1-12)

                    // 1. 將傳入的 receipt_year 標準化為四位數
                    let fullReceiptYear = parseInt(receipt_year, 10);
                    if (fullReceiptYear < 100) {
                        fullReceiptYear += 2000;
                    }

                    const receiptMonth = parseInt(receipt_month, 10);

                    // 2. 用標準化的數字直接比較
                    if (fullReceiptYear !== currentFullYear || receiptMonth !== currentMonth) {
                        // 3. 取得該月最後一天 (月份參數需為 receiptMonth，因為 day=0 會自動取前一個月)
                        const lastDay = new Date(fullReceiptYear, receiptMonth, 0);

                        // 4. 安全地格式化日期
                        const year = lastDay.getFullYear();
                        const month = (lastDay.getMonth() + 1).toString().padStart(2, '0');
                        const day = lastDay.getDate().toString().padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    }

                    return null; // 如果是當前年月，回傳 null
                }

                // 取得收據號碼的後面數字部分
                async function fetchReceiptNumber() {
                    let receipt_year, receipt_month;
                    
                    // 從頁面提取日期
                    const h3Element = document.querySelector('.all-heading h3');
                    
                    if (h3Element) {
                        const text = h3Element.textContent;
                        const receiptMatch = text.match(/Receipt\s*:\s*(\d{4})-(\d{2})/);
                        
                        if (receiptMatch) {
                            // 找到日期，使用頁面上的日期
                            receipt_year = receiptMatch[1].slice(-2);
                            receipt_month = receiptMatch[2]; 
                        } else {
                            // 找不到日期格式，使用當天日期
                            const today = new Date();
                            receipt_year = today.getFullYear().toString().slice(-2); // 取後兩位
                            receipt_month = (today.getMonth() + 1).toString().padStart(2, '0'); // 月份補零
                        }
                    } else {
                        // 找不到元素，使用當天日期
                        const today = new Date();
                        receipt_year = today.getFullYear().toString().slice(-2); // 取後兩位
                        receipt_month = (today.getMonth() + 1).toString().padStart(2, '0'); // 月份補零
                    }
                    
                    // 調用API (使用GET請求)
                    try {
                        const url = `${CONFIG.URLS.RECEIPT_NUM}?receipt_year=${receipt_year}&receipt_month=${receipt_month}`;
                        const res = await fetch(url);
                        
                        if (!res.ok) {
                            throw new Error(`HTTP error! status: ${res.status}`);
                        }
                        
                        const data = await res.json();
                        const receiptNum = Number(data.receiptNum);
                        
                        return {receipt_year, receipt_month, receiptNum};
                    } catch (error) {
                        const errorMessage = error.message || '未知錯誤';
                        alert(`API調用失敗: ${errorMessage}`);
                        throw error;
                    }
                }

                // 主要執行流程
                try {
                    const selectedRowsData = getSelectedRowsData(type);
                    if (selectedRowsData.length === 0) return;

                    // 取得設定值
                    const language = document.getElementById('lang')?.value || 'zh';
                    const isMergeExist = document.getElementById('merge');
                    const isMerged = isMergeExist && isMergeExist.checked;

                    // 準備 list 類型專用資料
                    let receipt_year, receipt_month, receiptNum, receipt_date;
                    let uncheckedDisbsData = "{}";

                    if (type === 'list') {
                        const receiptData = await fetchReceiptNumber();
                        receipt_year = receiptData.receipt_year;
                        receipt_month = receiptData.receipt_month;
                        receiptNum = receiptData.receiptNum;
                        receipt_date = getReceiptDateIfNeeded(receipt_year, receipt_month);
                        uncheckedDisbsData = getUncheckedDisbsData();
                    }

                    // 步驟 1: 下載 PDF
                    if (isMerged) {
                        const aggregatedData = JSON.parse(JSON.stringify(selectedRowsData[0]));
                        let totalWht = 0;
                        let indexList = [];

                        for (let i = 0; i < selectedRowsData.length; i++) {
                            const currentRow = selectedRowsData[i];

                            // 取得所有 index
                            indexList.push(currentRow.index);

                            // 加總 wht
                            const whtString = String(currentRow.wht).replace(/,/g, '');
                            const whtValue = parseFloat(whtString) || 0;
                            totalWht += whtValue;
                        }
                        aggregatedData.wht = totalWht.toLocaleString('en-US');

                        const formData = new FormData();
                        formData.append("selectedData", JSON.stringify(aggregatedData));
                        formData.append("language", language);
                        formData.append("type", type);
                        formData.append("isMerged", true);
                        formData.append("indexList", JSON.stringify(indexList)); 
                        formData.append("uncheckedDisbsData", uncheckedDisbsData);
                        formData.append("ispaid", isMergeExist ? "true" : "false");
                        formData.append("receiptNum", `R${receipt_year}${receipt_month}${receiptNum.toString().padStart(4, '0')}`);
                        if (receipt_date) formData.append("receiptDate", receipt_date);
                        
                        await downloadFile(CONFIG.URLS.PDF, formData);
                    } else {
                        for (let i = 0; i < selectedRowsData.length; i++) {
                            const formData = new FormData();
                            formData.append("selectedData", JSON.stringify(selectedRowsData[i]));
                            formData.append("language", language);
                            formData.append("type", type);

                            if (type === 'list') {
                                formData.append("uncheckedDisbsData", uncheckedDisbsData);
                                formData.append("ispaid", isMergeExist ? "true" : "false");
                                let currentReceiptNum = receiptNum + i;
                                formData.append("receiptNum", `R${receipt_year}${receipt_month}${currentReceiptNum.toString().padStart(4, '0')}`);
                                if (receipt_date) formData.append("receiptDate", receipt_date);
                            }

                            await downloadFile(CONFIG.URLS.PDF, formData);
                        }
                    }

                    // 步驟 2: 準備並提交所有資料到資料庫
                    const allDataForExport = selectedRowsData.map((dataItem, i) => {
                        const exportData = {
                            selectedData: dataItem
                        };

                        if (type === 'list') {
                            let currentReceiptNum = receiptNum + (isMerged ? 0 : i);
                            exportData.receiptNum = `R${receipt_year}${receipt_month}${currentReceiptNum.toString().padStart(4, '0')}`;
                        }

                        return exportData;
                    });

                    // 一次性寫入資料庫
                    const exportFormData = new FormData();
                    exportFormData.append("allData", JSON.stringify(allDataForExport));
                    exportFormData.append("uncheckedDisbsData", uncheckedDisbsData);
                    exportFormData.append("ispaid", isMergeExist ? "true" : "false");
                    exportFormData.append("type", type);
                    if (receipt_date != null) {
                        exportFormData.append("receiptDate", receipt_date);
                    }
                    await downloadFile(CONFIG.URLS.EXPORT, exportFormData);

                } catch (error) {
                    console.error('Export failed:', error);
                    alert(`匯出失敗：${error.message}`);
                }
            }

            // 為 export_list_btn 綁定點擊事件
            let exportListBtn = document.getElementById("export_list_btn");
            if (exportListBtn) {
                exportListBtn.addEventListener("click", function (e) {
                    handleExportForm(e, "list");
                });
            }

            // 為 export_edit_btn 綁定提交事件
            let exportEditBtn = document.getElementById("export_edit_btn");
            if (exportEditBtn) {
                exportEditBtn.addEventListener("click", function (e) {
                    handleExportForm(e, "edit");
                });
            }

            // 為 invalid_edit_btn 綁定提交事件
            let invalidEditBtn = document.getElementById("invalid_edit_btn");
            if (invalidEditBtn) {
                invalidEditBtn.addEventListener("click", function (e) {
                    e.preventDefault(); // 防止表單提交

                    var selectedData = [];

                    // 遍歷所有 row_check_box 的 checkbox
                    document.querySelectorAll("input[name^='row_check_box']").forEach(function (checkbox) {
                        if (checkbox.checked) {
                            var rowIndex = checkbox.value;
                            selectedData.push({ index: rowIndex });
                        }
                    });

                    // 透過 AJAX 發送請求
                    var formData = new FormData();
                    formData.append("action", "invalid");
                    formData.append("selectedData", JSON.stringify(selectedData));

                    fetch("/test_db/receipts_edit_db.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        location.reload();
                    })
                    .catch(error => {
                        alert("發生錯誤，請稍後再試！");
                        console.error("發生錯誤：", error);
                    });
                });
            }
        });
    </script>

    <!-- Modal -->

    <div class="modal fade" id="modal-id">
        <div class="modal-dialog modal-width">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title">Debit Note</h4>
                </div>
                <div class="modal-body">
                    Loading...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-info" data-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>

    <!--End Modal -->




    <script src="https://code.jquery.com/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="/js/nav-topfix.js"></script>
    <script type='text/javascript' src="/js/search.js"></script>
    <script src="receipts_ajax.js"></script>

    <script type="text/javascript">
        $('nav').affix({
            offset: {
                top: 50,
            }
        })
        $(document.body).on('hidden.bs.modal', function () {
            $('#myModal').removeData('bs.modal')
        });

        //Edit SL: more universal
        $(document).on('hidden.bs.modal', function (e) {
            $(e.target).removeData('bs.modal');
        });

        // ======== 您需要新增的程式碼在這裡 ========
        // 使用 jQuery 的 document ready 函數
        $(document).ready(function() {
            
            // 1. 透過 ID 找到 "Change" 按鈕
            $('#change_submit_btn').on('click', function(event) {
                
                // 2. 檢查 "刪除" 選項 (ID: deleteData) 是否被勾選
                if ($('#deleteData').is(':checked')) {
                    
                    // 3. 如果勾選了，跳出確認視窗
                    var confirmed = confirm("您確定要刪除原編號資料嗎？");
                    
                    // 4. 如果使用者按下 "取消" (confirmed 為 false)
                    if (!confirmed) {
                        // 停止點擊事件的預設行為 (即停止表單提交)
                        event.preventDefault(); 
                    }
                }
                // 如果是 "保留" 或使用者按下 "確定"，點擊事件會正常觸發，表單會提交
            });
        });
        // ======== 新增程式碼結束 ========

    </script>
</body>

</html>