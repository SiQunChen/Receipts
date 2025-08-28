$(document).ready(function() {
    // 初始化分頁
    loadListData();

    // 關閉頁面、重整等動作時清除資料
    window.addEventListener("beforeunload", function() {
        localStorage.clear();
    });
});

// 加載查詢頁面的數據
function loadListData() {
    $.ajax({
        url: 'receipts_ajax.php',
        dataType: 'json',
        success: function(response) {
            // console.log(response.dataArray);
            updateTable(response.dataArray);
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
        }
    });
}

// 更新表格內容
function updateTable(dataArray) {
    let tbody = '';
    let is_paid = ('billing_currency' in (dataArray[0])) ? false : true;

    dataArray.forEach((data, index) => {
        let entity, deb_num, services, disbs, total, currency, wht;
        let uncheckedId = [];
        let redColor = '';
        let disable = '';

        // 判斷該申請單號是否進行過分割
        if ('split_entity' in data && data.split_entity !== null) {
            disable = "style='pointer-events: none; opacity: 0.6;'";
            entity = data.split_entity;
            deb_num = `${data.deb_num}${data.split_deb_num}`;
            services = data.split_legal_services;
            disbs = data.split_disbs;
            total = services + disbs;

            if (data.billing_currency === 'English (USD)' || data.billing_currency === 'English (EUR)') {
                currency = data.currency2;
                if (data.wht_status === '1') {
                    const amount = Number(data.wht_base) === '1' ? services : total;
                    wht = amount >= Number(data.wht_model) 
                        ? (amount * 0.1).toFixed(2).toLocaleString()
                        : '0.00';
                } else {
                    wht = '0.00';
                }
                services = services.toFixed(2).toLocaleString();
                disbs = disbs.toFixed(2).toLocaleString();
                total = total.toFixed(2).toLocaleString();
            } else {
                currency = 'TWD';
                if (data.wht_status === '1') {
                    const amount = Number(data.wht_base === '1' ? services : total);
                    wht = amount >= Number(data.wht_model) ? Math.floor(amount * 0.1).toLocaleString() : '0';
                } else {
                    wht = 0;
                }
                services = services.toLocaleString();
                disbs = disbs.toLocaleString();
                total = total.toLocaleString();
            }
        } else {
            entity = data.party_en_name_bills;
            deb_num = data.deb_num;

            // 檢查 Local Storage 是否有未勾選的項目
            const localStorageKey = `uncheckedItems_${data.deb_num}`;
            const uncheckedItems = JSON.parse(localStorage.getItem(localStorageKey)) || [];
            let totalUncheckedValue = 0;

            // unpaid 屬於外幣的情況
            const isEnglishCurrency = ['English (USD)', 'English (EUR)'].includes(data.billing_currency);

            // paid 屬於外幣的情況
            const hasForeignValues = [data.foreign_legal2, data.foreign_disbs2].some(value => value != null);

            // 判斷金額格式與計算 WHT
            if ((!is_paid && isEnglishCurrency) || (is_paid && hasForeignValues)) {
                services = Number(data.foreign_legal2);
                disbs = Number(data.foreign_disbs2);
                total = services + disbs;
                currency = data.currency2;

                if (uncheckedItems.length > 0) {
                    // 取得 local storage 的資料
                    uncheckedId = uncheckedItems.map(item => item.id);
                    totalUncheckedValue = uncheckedItems.reduce((sum, item) => sum + item.foreign_amount, 0);

                    // 從 disbs 和 total 減去未勾選金額
                    disbs -= totalUncheckedValue;
                    total -= totalUncheckedValue;
                }

                // 計算 wht
                if (!is_paid) {
                    if (data.wht_status === '1') {
                        const amount = Number(data.wht_base) === '1' ? services : total;
                        wht = amount >= Number(data.wht_model) 
                            ? (amount * 0.1).toFixed(2).toLocaleString()
                            : '0.00';
                    } else {
                        wht = '0.00';
                    }
                } else {
                    wht = Number(data.holding_tax).toFixed(2).toLocaleString();
                }

                // 調整格式
                services = services.toFixed(2).toLocaleString();
                disbs = disbs.toFixed(2).toLocaleString();
                total = total.toFixed(2).toLocaleString();
            } else {
                services = Number(data.legal_services);
                disbs = Number(data.disbs);
                total = services + disbs;
                currency = 'TWD';

                if (uncheckedItems.length > 0) {
                    // 取得 local storage 的資料
                    uncheckedId = uncheckedItems.map(item => item.id);
                    totalUncheckedValue = uncheckedItems.reduce((sum, item) => sum + item.amount, 0);

                    // 從 disbs 和 total 減去未勾選金額
                    disbs -= totalUncheckedValue;
                    total -= totalUncheckedValue;    
                }

                // 計算 wht
                if (!is_paid) {
                    if (data.wht_status === '1') {
                        const amount = Number(data.wht_base === '1' ? services : total);
                        wht = amount >= Number(data.wht_model) ? Math.floor(amount * 0.1).toLocaleString() : '0';
                    } else {
                        wht = '0';
                    }
                } else {
                    wht = data.with_tax.toLocaleString();
                }

                // 調整格式
                services = services.toLocaleString();
                disbs = disbs.toLocaleString();
                total = total.toLocaleString();
            }

            // 顯示部分銷帳後的 disbs
            redColor = data.disbs_sum != null ? "style='color: red;'" : "";
        }
        
        // 生成表格行
        tbody += `
            <tr>
                <td class='text-center'>
                    <input type='checkbox' name='row_check_box[${index}]' value='${index}' style='width: calc(100%)'>
                </td>
                <td class='text-center'>
                    <a href='receipts_detail.php?is_paid=${is_paid}&deb_num=${deb_num}&currency=${currency}&id=${uncheckedId.join(',')}' class='btn-sm btn-info btn-r15' ${disable} data-toggle='modal' data-target='#modal-id'>
                        <i class='glyphicon glyphicon-th-list'></i>
                    </a>
                </td>
                <td class='text-center'>
                    <textarea name='party_en_name_bills[${index}]' style='width: calc(100%);' rows='3'>${entity}</textarea>
                </td>
                <td class='text-left'>${data.case_num}</td>
                <td class='text-center'>${deb_num}</td>
                <td class='text-right' style='max-width: 150px'>
                    <span ${redColor}>${services}</span>
                    <input type='text' name='note_legal[${index}]' style='width: calc(100%)'>
                </td>
                <td class='text-right' style='max-width: 150px'>
                    <span ${redColor}>${disbs}</span>
                    <input type='text' name='note_disbs[${index}]' style='width: calc(100%)'>
                </td>
                <td class='text-right'>
                    ${total}<br>${currency}
                </td>
                <td class='text-center' style='max-width: 100px'>
                    <input type='text' name='wht[${index}]' value='${wht}' style='width: calc(100%)'>
                </td>
                <td class='text-center'>${data.sent}</td>
            </tr>`;
    });

    var tableElement = document.querySelector('#list_table');
    if (tableElement) {
        tableElement.innerHTML = tbody;
    }
}