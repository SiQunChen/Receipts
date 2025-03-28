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
    <link rel="stylesheet" href="css/winkler-from.css">
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

    <body>

        <!-- 側邊搜尋內容 -->
        <div id="sidebar-wrapper">
            <div class="sidebar-nav">

                <!-- 搜尋條件內容 -->
                <div class="search-con">
                    <div class="heading">
                        <h2>Receipts</h2>
                    </div>

                    <form method="POST" ACTION="receipts.php" role="form">
                        <div class="form-group">
                            <label class="col-half">Case Number</label>
                            <input type="text" class="col-half" name="case_number">
                        </div>

                        <div class="form-group">
                            <label class="col-half">Match</label>
                            <input type="radio" name="match_or_like" id="match" value="match" checked>

                            <label class="col-half">Like</label>
                            <input type="radio" name="match_or_like" id="like" value="like">
                        </div>

                        <div class="form-group">
                            <label class="col-half">Invoice</label>
                            <input type="text" class="col-half" name="invoice">
                        </div>

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

                        <div class="form-group">
                            <label class="col-half">Unpaid</label>
                            <input type="radio" name="is_paid" id="unpaid" value="unpaid" checked>

                            <label class="col-half">Paid</label>
                            <input type="radio" name="is_paid" id="paid" value="paid">
                        </div>

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

                        <div class="s-form-bot">
                            <button type="submit" name="list" value="list" style="margin-bottom: 12px;">List</button>
                            <br>&nbsp;&nbsp;&nbsp;&nbsp;
                            <button type="submit" name="create" value="create"
                                style="background-color:rgb(91, 149, 183);">
                                預開
                            </button>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <button type="submit" name="edit" value="edit" style="background-color:rgb(123, 154, 172);">
                                Edit
                            </button>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <button type="submit" name="change" value="change"
                                style="background-color:rgb(181, 197, 207);">
                                Change
                            </button>
                        </div>
                    </form>
                </div>

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
                                $case_num = $_POST['case_number'] ?? '';
                                $match_or_like = $_POST['match_or_like'] ?? '';
                                $invoice = $_POST['invoice'] ?? '';
                                $initial = $_POST['initial'] ?? '';
                                $bills_year = $_POST['bills_year'] ?? '';
                                $bills_month = $_POST['bills_month'] ?? '';
                                $is_paid = $_POST['is_paid'] ?? '';
                                $receipt_year = $_POST['receipt_year'] ?? '';
                                $receipt_month = $_POST['receipt_month'] ?? '';
                                $application_num = $_POST['application_num'] ?? '';

                                // 取得資料
                                $dataArray = getReceipts(
                                    'list',
                                    $case_num,
                                    $match_or_like,
                                    $invoice,
                                    $initial,
                                    $bills_year,
                                    $bills_month,
                                    $is_paid,
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
                                } else {
                                    echo 'Search : ' . $bills_year . '-' . $bills_month;
                                }

                                $_SESSION['dataArray'] = $dataArray;

                                // Debug 輸出
                                // print_r($dataArray);

                                echo '<button type="button" id="export_list_btn">Export</button>';
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
                                $case_num = $_POST['case_number'] ?? '';
                                $match_or_like = $_POST['match_or_like'] ?? '';
                                $invoice = $_POST['invoice'] ?? '';
                                $initial = $_POST['initial'] ?? '';
                                $bills_year = $_POST['bills_year'] ?? '';
                                $bills_month = $_POST['bills_month'] ?? '';
                                $receipt_year = $_POST['receipt_year'] ?? '';
                                $receipt_month = $_POST['receipt_month'] ?? '';
                                $application_num = '';

                                // 取得資料
                                $dataArray = getReceipts(
                                    'edit',
                                    $case_num,
                                    $match_or_like,
                                    $invoice,
                                    $initial,
                                    $bills_year,
                                    $bills_month,
                                    null,
                                    $receipt_year,
                                    $receipt_month,
                                    $application_num
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
                                echo '<button type="button" id="invalid_edit_btn">Invalid</button>';
                            }
                            // 按下 "Change" 按鈕的情況
                            elseif (isset($_POST['change'])) {
                                echo 'Change';
                            }
                        }
                        // 處理 GET 請求或初始狀態
                        else {
                            // 預設參數
                            $case_num = '';
                            $match_or_like = '';
                            $invoice = '';
                            $initial = '';
                            $bills_year = date("Y");
                            $bills_month = date("m");
                            $is_paid = 'unpaid';
                            $receipt_year = '';
                            $receipt_month = '';
                            $application_num = '';

                            // 取得資料
                            $dataArray = getReceipts(
                                'list',
                                $case_num,
                                $match_or_like,
                                $invoice,
                                $initial,
                                $bills_year,
                                $bills_month,
                                $is_paid,
                                $receipt_year,
                                $receipt_month,
                                $application_num
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
                        }
                        ?>
                    </h3>
                </div>

                <!-- 根據不同的 post 參數決定要顯示的區塊 -->
                <?php if ($_SERVER["REQUEST_METHOD"] !== "POST" || isset($_POST['list'])): ?>
                    <!-- 列表畫面：使用 table 顯示資料 -->
                    <div class="table-responsive">
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
                    </div>
                    
                <?php elseif (isset($_POST['create'])): ?>
                    <!-- 新增資料畫面：顯示新增表單 -->
                    <div class="winkler-sc-custom-page">
                        <form method="POST" action="test_db/receipts_create_db.php">
                            <div class="winkler-sc-receipts-create-form-container">
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="entity">Entity<span
                                            class="winkler-sc-required-indicator">*</span></label>
                                    <?php
                                    if (empty($preset_data)) {
                                        echo "<input type='text' id='entity' name='entity' required>";
                                    } else {
                                        $entityValue = htmlspecialchars($preset_data[0]['party_en_name_billing'], ENT_QUOTES, 'UTF-8');
                                        echo "<input type='text' id='entity' name='entity' value='$entityValue' required>";
                                    }
                                    ?>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="case_num">Case Number<span
                                            class="winkler-sc-required-indicator">*</span></label>
                                    <?php
                                    if (empty($preset_data)) {
                                        echo "<input type='text' id='case_num' name='case_num' required>";
                                    } else {
                                        $caseNumValue = htmlspecialchars($preset_data[0]['case_num'], ENT_QUOTES, 'UTF-8');
                                        echo "<input type='text' id='case_num' name='case_num' value='$caseNumValue' required>";
                                    }
                                    ?>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="invoice">Invoice</label>
                                    <input type="text" id="invoice" name="invoice">
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="currency">Currency<span
                                            class="winkler-sc-required-indicator">*</span></label>
                                    <select id="currency" name="currency" required>
                                        <option selected>TWD</option>
                                        <option>USD</option>
                                        <option>EUR</option>
                                    </select>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="services">Services<span
                                            class="winkler-sc-required-indicator">*</span></label>
                                    <input type="text" id="services" name="services" required>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="note_legal">Note Legal</label>
                                    <input type="text" id="note_legal" name="note_legal">
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="disbursements">Disbursements<span
                                            class="winkler-sc-required-indicator">*</span></label>
                                    <input type="text" id="disbursements" name="disbursements" required>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="note_disbs">Note Disbs</label>
                                    <input type="text" id="note_disbs" name="note_disbs">
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                    <label for="wht">WHT<span class="winkler-sc-required-indicator">*</span></label>
                                    <input type="text" id="wht" name="wht" required>
                                </div>
                                <div class="winkler-sc-receipts-create-form-group">
                                </div>
                            </div>
                            <div class="winkler-sc-form-button-container">
                                <button type="submit" name="create_receipt" value="create_receipt">Export</button>
                            </div>
                        </form>
                    </div>

                <?php elseif (isset($_POST['edit'])): ?>
                    <!-- 列表畫面：使用 table 顯示資料 -->
                    <div class="table-responsive">
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

                                    $note_legal = $data['note_legal'] !== '' ? "value={$data['note_legal']}" : '';
                                    $note_disbs = $data['note_disbs'] !== '' ? "value={$data['note_disbs']}" : '';
                                    $status = $data['status'] === '1' ? 
                                        "<td class='text-center'>有效</td>" : 
                                        "<td class='text-center' style='color: red;'>作廢</td>";

                                    echo "
                                        <tr>
                                            <td class='text-center'>
                                                <input type='checkbox' name='row_check_box[$i]' value='$i' style='width: calc(100%)'>
                                            </td>
                                            <td class='text-left'>{$data['receipt_num']}</td>
                                            <td class='text-left'>{$data['receipt_entity']}</td>
                                            <td class='text-left'>{$data['case_num']}</td>
                                            <td class='text-center'>{$data['deb_num']}</td>
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
                    </div>

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

                        <div class="winkler-sc-form-button-container">
                            <button type="submit" name="change" value="change">Change</button>
                        </div>
                    </form>
                <?php endif; ?>
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
            function handleExportForm(event, type) {
                event.preventDefault(); // 防止表單提交

                var selectedRowsData = [];

                if (type === 'list') {
                    // 遍歷所有 row_check_box 的 checkbox
                    document.querySelectorAll("input[name^='row_check_box']").forEach(function (checkbox) {
                        if (checkbox.checked) {
                            var rowIndex = checkbox.value; // 取得 index
                            var row = checkbox.closest("tr"); // 取得該 checkbox 所在的 <tr>

                            // 依照 index 取得對應欄位值
                            var entityField = row.querySelector(`textarea[name="party_en_name_bills[${rowIndex}]"]`);
                            var noteLegalField = row.querySelector(`input[name="note_legal[${rowIndex}]"]`);
                            var noteDisbsField = row.querySelector(`input[name="note_disbs[${rowIndex}]"]`);
                            var whtField = row.querySelector(`input[name="wht[${rowIndex}]"]`);

                            // 組成一個物件，記錄該列所有需要傳送的資料
                            var rowData = {
                                index: rowIndex,
                                entity: entityField ? entityField.value : "",
                                note_legal: noteLegalField ? noteLegalField.value : "",
                                note_disbs: noteDisbsField ? noteDisbsField.value : "",
                                wht: whtField ? whtField.value : ""
                            };

                            selectedRowsData.push(rowData);
                        }
                    });

                    // 處理 localStorage 的資料
                    var uncheckedDisbsData = {};
                    Object.keys(localStorage).forEach(function (key) {
                        if (key.startsWith("uncheckedItems_")) {
                            let debNum = key.replace("uncheckedItems_", ""); // 提取 deb_num
                            let items = JSON.parse(localStorage.getItem(key));
                            uncheckedDisbsData[debNum] = items;
                        }
                    });

                    // 透過 AJAX 發送請求
                    const files = [
                        "/test_db/receipts_report_1_db.php",
                        "/test_db/receipts_report_2_db.php",
                        "/test_db/receipts_report_3_db.php",
                        // "/test_db/receipts_export_db.php"
                    ];

                    let formData = new FormData();
                    formData.append("selectedData", JSON.stringify(selectedRowsData));
                    formData.append("uncheckedDisbsData", JSON.stringify(uncheckedDisbsData));

                    // 迭代每個檔案路徑，分別發送 POST 請求
                    files.forEach(file => {
                        fetch(file, {
                            method: "POST",
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`下載失敗: ${file}`);
                            }
                            return response.blob(); 
                        })
                        .then(blob => {
                            let url = window.URL.createObjectURL(blob);
                            let a = document.createElement("a");
                            a.href = url;
                            a.download = file.split('/').pop().replace('.php', '.xlsx'); // 設定下載的檔名
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        })
                        .catch(error => {
                            console.error("發生錯誤：", error);
                            alert("下載 " + file + " 時發生錯誤！");
                        });
                    });
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

                    fetch("/test_db/receipts_edit_btn_db.php", {
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
        </script>
    </body>

</html>