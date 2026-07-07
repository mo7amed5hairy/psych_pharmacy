@extends('layouts.app')

@section('title', 'فاتورة الصرف — صيدلية')
@section('page-title', 'فاتورة الصرف')
@section('page-subtitle', 'اسم المريض + رقم التحويل يدوي')

@section('content')

    <!-- Multi Invoice Toggle -->
    <div class="card p-4 mb-5 flex items-center gap-4 flex-wrap">
        <button type="button" id="multiModeBtn" onclick="toggleMultiMode()" class="btn btn-primary">
            <i class="fas fa-layer-group"></i> أضف فواتير اليوم
        </button>
        <div id="multiInputArea" class="flex hidden items-center gap-3">
            <input type="date" id="multiDate" class="input w-40" value="{{ date('Y-m-d') }}">
            <input type="number" id="multiCount" class="input w-24" placeholder="العدد" min="1">
            <button type="button" onclick="generateMultiInvoices()" class="btn btn-success">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    <!-- Single Invoice Mode -->
    <div id="singleInvoiceArea">
        <!-- Patient Info -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
            <div class="card p-4">
                <label class="label">رقم التحويل (يدوي)</label>
                <input type="text" id="referralNumber" class="input" placeholder="TR-1046">
            </div>

            <div class="card p-4">
                <label class="label">التاريخ</label>
                <input type="date" id="invoiceDate" class="input" value="{{ date('Y-m-d') }}">
            </div>

            <div class="card p-4">
                <label class="label">اسم المريض (يدوي)</label>
                <input type="text" id="patientName" class="input" placeholder="مثال: محمد علي">
            </div>
        </div>

        <!-- Add Item -->
        <div class="card p-5 mb-5">
            <div class="flex items-end gap-3 flex-wrap">

                <div class="flex-1 min-w-[200px] relative">
                    <label class="label">الدواء</label>

                    <input type="text" id="medicineSearch" class="input" placeholder="🔎 اختر الدواء..." autocomplete="off">

                    <div id="medicineResults" class="smart-search-results"></div>
                </div>

                <div class="w-32">
                    <label class="label">الكمية</label>
                    <input type="number" id="itemQuantity" class="input" placeholder="الكمية" min="1">
                </div>

                <div class="w-36">
                    <label class="label">سعر الخط الساخن</label>
                    <input type="text" id="itemPrice" class="input bg-slate-100" readonly>
                </div>

                <button type="button" onclick="addItem()" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    أضف للفاتورة
                </button>

            </div>
        </div>

        <!-- Invoice Items -->
        <div class="card p-5">

            <div class="table-wrap mb-4">
                <table class="data" id="itemsTable">
                    <thead>
                        <tr>
                            <th>اسم المريض</th>
                            <th>تاريخ الصرف</th>
                            <th>الصنف</th>
                            <th>السعر</th>
                            <th>الكمية المنصرفة</th>
                            <th>القيمة الإجمالية</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody id="itemsList"></tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t pt-4">

                <div>
                    <div class="text-sm text-slate-500">
                        إجمالي الفاتورة (الكمية × سعر الخط الساخن)
                    </div>

                    <div class="text-3xl font-extrabold text-emerald-600" id="totalAmount">
                        0.00 ج
                    </div>
                </div>

                <div class="flex gap-2">

                    <button type="button" onclick="printInvoice()" class="btn btn-ghost">
                        <i class="fas fa-print"></i>
                        طباعة
                    </button>

                    <button type="button" onclick="saveInvoice()" class="btn btn-success" id="singleSaveBtn">
                        <i class="fas fa-save"></i>
                        حفظ الفاتورة
                    </button>

                </div>
            </div>

        </div>
    </div>

    <!-- Multi Invoice Container -->
    <div id="multiInvoiceContainer" class="hidden"></div>

    <!-- Grand Total (multi mode) -->
    <div id="multiGrandTotal" class="hidden mt-4">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <h3 class="font-extrabold text-slate-900 text-xl">الإجمالي الكلي لجميع الفواتير</h3>
                <div class="text-3xl font-extrabold text-emerald-600">
                    <span id="grandTotalAmount">0.00</span> ج
                </div>
            </div>
        </div>
    </div>

    <!-- Save All (multi mode) -->
    <div id="multiSaveArea" class="hidden mt-5">
        <div class="card p-5">
            <button type="button" onclick="saveAllInvoices()" class="btn btn-success w-full justify-center text-lg py-4">
                <i class="fas fa-save"></i> حفظ كل الفواتير
            </button>
        </div>
    </div>
@endsection

@push('scripts')
    <script>

        let selectedMedicine = null;
        let invoiceItems = [];
        let totalAmount = 0;

        const medicineSearch = document.getElementById('medicineSearch');
        const medicineResults = document.getElementById('medicineResults');

        let searchTimeout;

        medicineSearch.addEventListener('input', function () {

            clearTimeout(searchTimeout);

            const query = this.value;

            if (query.length < 2) {
                medicineResults.classList.remove('active');
                return;
            }

            searchTimeout = setTimeout(() => {

                fetch(`{{ url('/medicines/search') }}?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {

                        medicineResults.innerHTML = '';

                        if (data.length === 0) {

                            medicineResults.innerHTML =
                                '<div class="smart-search-item text-rose-600">لا يوجد دواء بهذا الاسم</div>';

                        } else {

                            data.forEach(med => {

                                const div = document.createElement('div');

                                div.className = 'smart-search-item';

                                div.innerHTML = `
                                                        <div class="font-semibold">${med.name}</div>
                                                        <div class="text-xs text-slate-500">
                                                            ${(med.unit_type?.name || '')}
                                                            - سعر الخط الساخن: ${med.price_hotline} ج
                                                        </div>
                                                    `;

                                div.onclick = () => selectMedicineItem(med);

                                medicineResults.appendChild(div);

                            });

                        }

                        medicineResults.classList.add('active');

                    });

            }, 300);

        });

        function selectMedicineItem(medicine) {

            selectedMedicine = medicine;

            medicineSearch.value = medicine.name;

            document.getElementById('itemPrice').value = medicine.price_hotline;

            medicineResults.classList.remove('active');

            document.getElementById('itemQuantity').focus();

        }

        document.addEventListener('click', function (e) {

            if (!e.target.closest('.relative')) {
                medicineResults.classList.remove('active');
            }

        });

        function addItem() {

            if (!selectedMedicine) {
                alert('الرجاء اختيار دواء أولاً');
                return;
            }

            const quantity = parseInt(document.getElementById('itemQuantity').value);

            if (quantity < 1) {
                alert('الكمية يجب أن تكون 1 على الأقل');
                return;
            }

            const price = parseFloat(selectedMedicine.price_hotline);

            const subtotal = quantity * price;

            const item = {
                medicine_id: selectedMedicine.id,
                name: selectedMedicine.name,
                quantity: quantity,
                price: price,
                subtotal: subtotal
            };

            invoiceItems.push(item);

            totalAmount += subtotal;

            renderItems();

            resetItemForm();

        }

        function renderItems() {

            const tbody = document.getElementById('itemsList');

            tbody.innerHTML = '';

            const patientName =
                document.getElementById('patientName').value.trim() || '-';

            const invoiceDate =
                document.getElementById('invoiceDate').value || '-';

            invoiceItems.forEach((item, index) => {

                const row = document.createElement('tr');

                row.innerHTML = `
                                        <td>${patientName}</td>
                                        <td>${invoiceDate}</td>
                                        <td>${item.name}</td>
                                        <td>${item.price.toFixed(2)}</td>
                                        <td>${item.quantity}</td>
                                        <td class="font-bold">${item.subtotal.toFixed(2)}</td>

                                        <td>
                                            <button
                                                type="button"
                                                onclick="removeItem(${index})"
                                                class="btn btn-danger"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    `;

                tbody.appendChild(row);

            });

            document.getElementById('totalAmount').textContent =
                totalAmount.toFixed(2) + ' ج';

        }

        function removeItem(index) {

            totalAmount -= invoiceItems[index].subtotal;

            invoiceItems.splice(index, 1);

            renderItems();

        }

        function resetItemForm() {

            selectedMedicine = null;

            medicineSearch.value = '';

            document.getElementById('itemQuantity').value = '';

            document.getElementById('itemPrice').value = '';

            medicineSearch.focus();

        }

        function printInvoice() {

            if (invoiceItems.length === 0) {
                alert('لا توجد أصناف للطباعة');
                return;
            }

            const printWindow = window.open('', '_blank');

            const patientName =
                document.getElementById('patientName').value || 'غير محدد';

            const referralNumber =
                document.getElementById('referralNumber').value || 'غير محدد';

            const date =
                document.getElementById('invoiceDate').value;

            let itemsHtml = '';

            invoiceItems.forEach((item) => {

                itemsHtml += `
                                <tr class="data-row">

                                    <td colspan="2">
                                        ${item.name}
                                    </td>

                                    <td colspan="1">
                                        ج.م ${item.price.toFixed(3)}
                                    </td>

                                    <td>
                                        ${item.quantity}
                                    </td>

                                    <td>
                                        ${item.subtotal.toFixed(2)}
                                    </td>

                                </tr>
                            `;

            });

            const logoUrl = '{{ asset('images/ain-shams-logo.jpg') }}';

            const printContent = `
                            <!DOCTYPE html>

                            <html lang="ar" dir="rtl">

                            <head>

                            <meta charset="utf-8">

                            <title>فاتورة صرف</title>

                            <style>

                            @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');

                            *{
                                margin:0;
                                padding:0;
                                box-sizing:border-box;
                            }

                            body{
                                background:#d0d0d0;
                                display:flex;
                                justify-content:center;
                                align-items:flex-start;
                                min-height:100vh;
                                font-family:'Cairo', Arial, sans-serif;
                                padding:30px 0;
                            }

                            .page{
                                background:#f5f5f0;
                                width:720px;
                                padding:30px 35px 40px;
                                box-shadow:0 4px 20px rgba(0,0,0,0.3);
                            }

                            /* HEADER */

                            .header{
                                display:flex;
                                justify-content:flex-start;
                                align-items:flex-start;
                                margin-bottom:10px;
                            }

                            .logo-area{
                                display:flex;
                                flex-direction:column;
                                align-items:flex-start;
                                gap:3px;
                            }

                            .logo-area img{
                                width:70px;
                                height:70px;
                                object-fit:contain;
                            }

                            .clinic-name{
                                font-size:13px;
                                font-weight:700;
                                color:#222;
                            }

                            .clinic-sub{
                                font-size:10px;
                                color:#444;
                            }

                            /* TITLE */

                            .invoice-title{
                                text-align:center;
                                font-size:16px;
                                font-weight:700;
                                margin:8px 0 4px;
                                color:#111;
                            }

                            .designer{
                                font-size:9px;
                                color:#888;
                                margin-bottom:8px;
                                direction:ltr;
                                text-align:left;
                            }

                            /* TABLE */

                            .table-wrapper{
                                border:2.5px solid #000;
                                overflow:hidden;
                            }

                            table{
                                width:100%;
                                border-collapse:collapse;
                                font-size:12.5px;
                                color:#111;
                            }

                            th,
                            td{
                                border:1.5px solid #000 !important;
                                padding:6px 10px;
                                text-align:revert;
                            }

                            .date-row td{
                            background:#fff;
                            font-weight:600;
                            width:34%;
                        }

                        .col-headers th{
                            background:#fff;
                            font-weight:700;
                            font-size:12px;
                            width: 79%;
                        }

                        .data-row td{
                            background:#fff;
                        }

                        .data-row:nth-child(even) td{
                            background:#fff;
                        }

                        .total-row td{
                            background:#fff;
                            font-weight:700;
                            font-size:13px;
                        }

                        .total-row .total-label{
                            text-align:right;
                            padding-right:20px;
                            font-size:12px;
                            letter-spacing:1px;
                            border-left: 0px !important;
                        }

                            /* FOOTER */

                            .footer{
                                display:flex;
                                justify-content:space-between;
                                margin-top:30px;
                                font-size:12px;
                                font-weight:600;
                                color:#222;
                                padding:0 10px;
                            }

                            .footer span{
                                text-align:center;
                            }

                            @media print {

                                body{
                                    background:none;
                                    padding:0;
                                }

                                .page{
                                    box-shadow:none;
                                }

                                table,
                                th,
                                td{
                                    border:1.5px solid #000 !important;
                                    -webkit-print-color-adjust: exact;
                                    print-color-adjust: exact;
                                }
                            .total-row .total-label{
                                border-left: 0px !important;
                            }





                            }

                            </style>

                            </head>

                            <body>

                            <div class="page">

                                <!-- HEADER -->

                                <div class="header">

                                    <div class="logo-area">

                                        <img
                                            src="${logoUrl}"
                                            alt="مستشفيات جامعة عين شمس"
                                        >

                                        <div class="clinic-name">
                                            قسم الحسابات
                                        </div>

                                        <div class="clinic-sub">
                                            مرسل الى حقوق مكافحة وعلاج الإدمان
                                        </div>

                                    </div>

                                </div>

                                <!-- TITLE -->

                                <div class="invoice-title">
                                    فاتورة رقم ${referralNumber}
                                </div>

                                <div class="designer">
                                    Designed by Eng Mohamed Khairy
                                </div>

                                <!-- TABLE -->
                            <div class="table-wrapper">
                                <table>

                                    <tr class="date-row">

                                        <td>الاسم</td>

                                        <td colspan="2">
                                            ${patientName}
                                        </td>

                                        <td>تاريخ الصرف</td>

                                        <td>
                                            ${date}
                                        </td>

                                    </tr>

                                    <tr class="col-headers">

                                        <th colspan="2">الصنف</th>

                                        <th>سعر القرص</th>

                                        <th>المصرف</th>

                                        <th>القيمة</th>

                                    </tr>

                                    ${itemsHtml}

                                    <tr class="total-row">

                                        <td colspan="2" class="total-label">
                                            الإجمالي
                                        </td>

                                        <td colspan="2" style="border-right-width: 0 !important;">ج.م</td>

                                        <td>
                                            ${totalAmount.toFixed(2)}
                                        </td>

                                    </tr>

                                </table>
                            </div>
                                <!-- FOOTER -->

                                <div class="footer">

                                    <span class="invoice-title">قسم الحسابات</span>

                                    <span class="invoice-title">مدير الشئون المالية والإدارية</span>

                                    <span class="invoice-title">مدير الصيدلية</span>

                                </div>

                            </div>

                            <script>
                            window.onload = () => {
                                setTimeout(() => window.print(), 400);
                            };
                            <\/script>

                            </body>
                            </html>
                            `;

            printWindow.document.write(printContent);

            printWindow.document.close();
        }

        function saveInvoice() {

            if (invoiceItems.length === 0) {
                alert('الرجاء إضافة أصناف للفاتورة أولاً');
                return;
            }

            const referralNumber = document.getElementById('referralNumber').value.trim();
            const patientName = document.getElementById('patientName').value.trim();
            const date = document.getElementById('invoiceDate').value;

            if (!referralNumber) { alert('الرجاء إدخال رقم التحويل'); return; }

            const data = {
                patient_name: patientName || '-',
                referral_number: referralNumber,
                invoice_date: date,
                items: invoiceItems.map(item => ({
                    medicine_id: item.medicine_id,
                    quantity: item.quantity
                }))
            };

            ajaxRequest('{{ route('invoices.store') }}', {
                method: 'POST',
                body: JSON.stringify(data)
            })
                .then(res => {
                    if (res.redirected) {
                        window.location.href = res.url;
                        return;
                    }
                    return res.json();
                })
                .then(result => {
                    if (result && result.errors) {
                        let msg = 'حدثت الأخطاء التالية:\n';
                        for (let k in result.errors) msg += '- ' + result.errors[k].join('\n') + '\n';
                        alert(msg);
                    } else if (result && result.redirect) {
                        window.location.href = result.redirect;
                    } else if (result && result.message) {
                        alert(result.message);
                    }
                })

                .catch(err => {
                    console.error(err);
                });
        }

        // ===================== MULTI-INVOICE MODE =====================

        let currentMode = 'single';
        let multiInvoiceData = [];
        let multiSearchTimeouts = {};

        function toggleMultiMode() {
            const inputArea = document.getElementById('multiInputArea');
            inputArea.classList.toggle('hidden');
            if (!inputArea.classList.contains('hidden')) {
                document.getElementById('multiCount').focus();
            }
        }

        function generateMultiInvoices() {
            const count = parseInt(document.getElementById('multiCount').value);
            if (!count || count < 1) {
                alert('الرجاء إدخال عدد صحيح للفواتير');
                return;
            }

            currentMode = 'multi';
            multiInvoiceData = [];

            document.getElementById('singleInvoiceArea').classList.add('hidden');
            document.getElementById('multiSaveArea').classList.remove('hidden');
            document.getElementById('multiGrandTotal').classList.remove('hidden');
            const modeBtn = document.getElementById('multiModeBtn');
            modeBtn.disabled = true;
            modeBtn.classList.add('opacity-50', 'cursor-not-allowed');

            const selectedDate = document.getElementById('multiDate').value;

            const container = document.getElementById('multiInvoiceContainer');
            container.classList.remove('hidden');
            container.innerHTML = '';

            for (let i = 0; i < count; i++) {
                const idx = i;
                multiInvoiceData[idx] = {
                    referralNumber: '', patientName: '',
                    date: selectedDate,
                    items: [], selectedMedicine: null
                };

                const card = document.createElement('div');
                card.className = 'card p-6 mb-5 border-2 border-slate-300 shadow-sm rounded-xl';
                card.innerHTML = `
                            <div class="flex items-center justify-between mb-4 pb-3 border-b">
                                <h4 class="font-extrabold text-slate-900 text-lg">فاتورة رقم ${idx + 1}</h4>
                                <button type="button" onclick="printMultiInvoice(${idx})" class="btn btn-ghost py-1 px-3 text-xs">
                                    <i class="fas fa-print"></i> طباعة
                                </button>
                            </div>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="label">رقم التحويل</label>
                                    <input type="text" class="input multi-ref" data-idx="${idx}" placeholder="TR-1046">
                                </div>
                                <div>
                                    <label class="label">التاريخ</label>
                                    <input type="date" class="input multi-date" data-idx="${idx}" value="${selectedDate}">
                                </div>
                                <div>
                                    <label class="label">اسم المريض</label>
                                    <input type="text" class="input multi-patient" data-idx="${idx}" placeholder="مثال: محمد علي">
                                </div>
                            </div>
                            <div class="flex items-end gap-3 flex-wrap mb-4">
                                <div class="flex-1 min-w-[200px] relative">
                                    <label class="label">الدواء</label>
                                    <input type="text" class="input multi-med-search" data-idx="${idx}" placeholder="🔎 اختر الدواء..." autocomplete="off">
                                    <div class="smart-search-results multi-med-results" data-idx="${idx}"></div>
                                </div>
                                <div class="w-24">
                                    <label class="label">الكمية</label>
                                    <input type="number" class="input multi-qty" data-idx="${idx}" placeholder="الكمية" min="1">
                                </div>
                                <div class="w-32">
                                    <label class="label">السعر</label>
                                    <input type="text" class="input bg-slate-100 multi-price" data-idx="${idx}" readonly>
                                </div>
                                <button type="button" onclick="multiAddItem(${idx})" class="btn btn-primary mt-5">
                                    <i class="fas fa-plus"></i> إضافة
                                </button>
                            </div>
                            <div style="overflow-x: auto;">
                                <table class="data text-sm" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>اسم المريض</th><th>التاريخ</th><th>الصنف</th><th>السعر</th><th>الكمية</th><th>القيمة</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody class="multi-items-tbody" data-idx="${idx}"></tbody>
                                </table>
                            </div>
                            <div class="flex items-center justify-between border-t pt-3 mt-3">
                                <div>
                                    <span class="text-sm text-slate-500">الإجمالي: </span>
                                    <span class="font-extrabold text-emerald-600 multi-total" data-idx="${idx}">0.00</span> ج
                                </div>
                            </div>
                        `;
                container.appendChild(card);
            }

            attachMultiSearchEvents();
            attachMultiDateSync();
        }

        function attachMultiDateSync() {
            document.getElementById('multiDate').addEventListener('change', function () {
                const newDate = this.value;
                document.querySelectorAll('#multiInvoiceContainer .multi-date').forEach(input => {
                    input.value = newDate;
                });
            });
        }

        function attachMultiSearchEvents() {
            document.querySelectorAll('.multi-med-search').forEach(input => {
                const idx = parseInt(input.getAttribute('data-idx'));
                input.addEventListener('input', function () {
                    clearTimeout(multiSearchTimeouts[idx]);
                    const query = this.value;
                    const resultsEl = this.closest('.relative').querySelector('.multi-med-results');
                    if (query.length < 2) { resultsEl.classList.remove('active'); return; }
                    multiSearchTimeouts[idx] = setTimeout(() => {
                        fetch(`{{ url('/medicines/search') }}?q=${encodeURIComponent(query)}`)
                            .then(res => res.json())
                            .then(data => {
                                resultsEl.innerHTML = '';
                                if (data.length === 0) {
                                    resultsEl.innerHTML = '<div class="smart-search-item text-rose-600">لا يوجد دواء بهذا الاسم</div>';
                                } else {
                                    data.forEach(med => {
                                        const div = document.createElement('div');
                                        div.className = 'smart-search-item';
                                        div.innerHTML = '<div class="font-semibold">' + med.name + '</div><div class="text-xs text-slate-500">' + (med.unit_type?.name || '') + ' - سعر الخط الساخن: ' + med.price_hotline + ' ج</div>';
                                        div.onclick = () => {
                                            multiInvoiceData[idx].selectedMedicine = med;
                                            input.value = med.name;
                                            input.closest('.card').querySelector('.multi-price').value = med.price_hotline;
                                            resultsEl.classList.remove('active');
                                            input.closest('.card').querySelector('.multi-qty').focus();
                                        };
                                        resultsEl.appendChild(div);
                                    });
                                }
                                resultsEl.classList.add('active');
                            });
                    }, 300);
                });
            });
        }

        function multiAddItem(idx) {
            const card = document.querySelectorAll('#multiInvoiceContainer .card')[idx];
            if (!card) return;
            const med = multiInvoiceData[idx].selectedMedicine;
            if (!med) { alert('الرجاء اختيار دواء أولاً'); return; }
            const qtyInput = card.querySelector('.multi-qty');
            const quantity = parseInt(qtyInput.value);
            if (quantity < 1) { alert('الكمية يجب أن تكون 1 على الأقل'); return; }
            const price = parseFloat(med.price_hotline);
            multiInvoiceData[idx].items.push({
                medicine_id: med.id, name: med.name, quantity: quantity, price: price, subtotal: quantity * price
            });
            multiInvoiceData[idx].selectedMedicine = null;
            card.querySelector('.multi-med-search').value = '';
            qtyInput.value = '';
            card.querySelector('.multi-price').value = '';
            card.querySelector('.multi-med-search').focus();
            multiRenderItems(idx);
        }

        function multiRemoveItem(idx, itemIndex) {
            multiInvoiceData[idx].items.splice(itemIndex, 1);
            multiRenderItems(idx);
        }

        function multiRenderItems(idx) {
            const card = document.querySelectorAll('#multiInvoiceContainer .card')[idx];
            if (!card) return;
            const tbody = card.querySelector('.multi-items-tbody');
            const patientVal = card.querySelector('.multi-patient').value.trim() || '-';
            const dateVal = card.querySelector('.multi-date').value || '-';
            multiInvoiceData[idx].referralNumber = card.querySelector('.multi-ref').value.trim();
            multiInvoiceData[idx].patientName = patientVal;
            multiInvoiceData[idx].date = dateVal;
            tbody.innerHTML = '';
            let total = 0;
            multiInvoiceData[idx].items.forEach((item, i) => {
                total += item.subtotal;
                const row = document.createElement('tr');
                row.innerHTML = '<td>' + patientVal + '</td><td>' + dateVal + '</td><td>' + item.name + '</td><td>' + item.price.toFixed(3) + '</td><td><input type="number" class="input py-1 px-1 w-16 text-center" value="' + item.quantity + '" min="0" onchange="updateMultiItemQty(' + idx + ', ' + i + ', this.value)" style="padding:2px 4px;font-size:0.8rem;"></td><td class="font-bold">' + item.subtotal.toFixed(2) + '</td><td><button type="button" onclick="multiRemoveItem(' + idx + ', ' + i + ')" class="btn btn-danger py-1 px-2 text-xs"><i class="fas fa-trash"></i></button></td>';
                tbody.appendChild(row);
            });
            card.querySelector('.multi-total').textContent = total.toFixed(2);
            updateGrandTotal();
        }

        function updateMultiItemQty(idx, itemIndex, newQty) {
            const qty = parseInt(newQty);
            if (isNaN(qty) || qty < 0) return;
            multiInvoiceData[idx].items[itemIndex].quantity = qty;
            multiInvoiceData[idx].items[itemIndex].subtotal = qty * multiInvoiceData[idx].items[itemIndex].price;
            multiRenderItems(idx);
        }

        function updateGrandTotal() {
            let grandTotal = 0;
            for (let idx = 0; idx < multiInvoiceData.length; idx++) {
                const items = multiInvoiceData[idx]?.items || [];
                items.forEach(it => { grandTotal += it.subtotal; });
            }
            document.getElementById('grandTotalAmount').textContent = grandTotal.toFixed(2);
        }

        function printMultiInvoice(idx) {
            const data = multiInvoiceData[idx];
            if (!data || data.items.length === 0) { alert('لا توجد أصناف للطباعة'); return; }
            const card = document.querySelectorAll('#multiInvoiceContainer .card')[idx];
            const patientName = card.querySelector('.multi-patient').value || 'غير محدد';
            const refNum = card.querySelector('.multi-ref').value || 'غير محدد';
            const date = card.querySelector('.multi-date').value;
            let itemsHtml = '';
            data.items.forEach(item => {
                itemsHtml += '<tr class="data-row"><td colspan="2">' + item.name + '</td><td colspan="1">ج.م ' + item.price.toFixed(3) + '</td><td>' + item.quantity + '</td><td>' + item.subtotal.toFixed(2) + '</td></tr>';
            });
            let total = data.items.reduce((sum, it) => sum + it.subtotal, 0);
            const logoUrl = '{{ asset('images/ain-shams-logo.jpg') }}';
            const printContent = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>فاتورة صرف</title><style>@import url(\'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap\');*{margin:0;padding:0;box-sizing:border-box}body{background:#d0d0d0;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;font-family:\'Cairo\',Arial,sans-serif;padding:30px 0}.page{background:#f5f5f0;width:720px;padding:30px 35px 40px;box-shadow:0 4px 20px rgba(0,0,0,0.3)}.header{display:flex;justify-content:flex-start;align-items:flex-start;margin-bottom:10px}.logo-area{display:flex;flex-direction:column;align-items:flex-start;gap:3px}.logo-area img{width:70px;height:70px;object-fit:contain}.clinic-name{font-size:13px;font-weight:700;color:#222}.clinic-sub{font-size:10px;color:#444}.invoice-title{text-align:center;font-size:16px;font-weight:700;margin:8px 0 4px;color:#111}.designer{font-size:9px;color:#888;margin-bottom:8px;direction:ltr;text-align:left}.table-wrapper{border:2.5px solid #000;overflow:hidden}table{width:100%;border-collapse:collapse;font-size:12.5px;color:#111}th,td{border:1.5px solid #000 !important;padding:6px 10px;text-align:revert}.date-row td{background:#fff;font-weight:600;width:34%}.col-headers th{background:#fff;font-weight:700;font-size:12px}.data-row td{background:#fff}.total-row td{background:#fff;font-weight:700;font-size:13px}.total-row .total-label{text-align:right;padding-right:20px;font-size:12px;letter-spacing:1px;border-left:0 !important}.footer{display:flex;justify-content:space-between;margin-top:30px;font-size:12px;font-weight:600;color:#222;padding:0 10px}.footer span{text-align:center}@media print{body{background:none;padding:0}.page{box-shadow:none}table,th,td{border:1.5px solid #000 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.total-row .total-label{border-left:0 !important}}</style></head><body><div class="page"><div class="header"><div class="logo-area"><img src="' + logoUrl + '" alt="مستشفيات جامعة عين شمس"><div class="clinic-name">قسم الحسابات</div><div class="clinic-sub">مرسل الى حقوق مكافحة وعلاج الإدمان</div></div></div><div class="invoice-title">فاتورة رقم ' + refNum + '</div><div class="designer">Designed by Eng Mohamed Khairy</div><div class="table-wrapper"><table><tr class="date-row"><td>الاسم</td><td colspan="2">' + patientName + '</td><td>تاريخ الصرف</td><td>' + date + '</td></tr><tr class="col-headers"><th colspan="2">الصنف</th><th>سعر القرص</th><th>المصرف</th><th>القيمة</th></tr>' + itemsHtml + '<tr class="total-row"><td colspan="2" class="total-label">الإجمالي</td><td colspan="2" style="border-right-width:0 !important;">ج.م</td><td>' + total.toFixed(2) + '</td></tr></table></div><div class="footer"><span>قسم الحسابات</span><span>مدير الشئون المالية والإدارية</span><span>مدير الصيدلية</span></div></div><script>window.onload=()=>{setTimeout(()=>window.print(),400)}<\/script></body></html>';
            const w = window.open('', '_blank');
            w.document.write(printContent);
            w.document.close();
        }

        function saveAllInvoices() {
            const cards = document.querySelectorAll('#multiInvoiceContainer .card');
            let allData = [];
            for (let idx = 0; idx < cards.length; idx++) {
                const card = cards[idx];
                const ref = card.querySelector('.multi-ref').value.trim();
                const patient = card.querySelector('.multi-patient').value.trim();
                const date = card.querySelector('.multi-date').value;
                const items = multiInvoiceData[idx] ? multiInvoiceData[idx].items : [];
                if (!ref) { alert('الرجاء إدخال رقم التحويل للفاتورة رقم ' + (idx + 1)); return; }
                if (items.length === 0) { alert('الرجاء إضافة أصناف للفاتورة رقم ' + (idx + 1)); return; }
                allData.push({
                    patient_name: patient || '-',
                    referral_number: ref,
                    invoice_date: date,
                    items: items.filter(it => it.quantity > 0).map(it => ({ medicine_id: it.medicine_id, quantity: it.quantity }))
                });
            }

            const saveBtn = document.querySelector('#multiSaveArea .btn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            let saved = 0, errors = [];

            function saveNext(i) {
                if (i >= allData.length) {
                    if (errors.length > 0) {
                        alert('تم حفظ ' + saved + ' فاتورة بنجاح.\nالأخطاء:\n' + errors.join('\n'));
                    } else {
                        alert('تم حفظ جميع الفواتير بنجاح (' + saved + ')');
                        window.location.href = '{{ route('invoices.index') }}';
                    }
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save"></i> حفظ كل الفواتير';
                    return;
                }
                fetch('{{ route('invoices.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify(allData[i])
                })
                    .then(res => { if (res.redirected) { saved++; saveNext(i + 1); return; } return res.json(); })
                    .then(result => {
                        if (result && result.errors) {
                            let msg = 'فاتورة ' + (i + 1) + ': ';
                            for (let k in result.errors) msg += result.errors[k].join(', ');
                            errors.push(msg);
                        } else { saved++; }
                        saveNext(i + 1);
                    })
                    .catch(err => { errors.push('فاتورة ' + (i + 1) + ': ' + err.message); saveNext(i + 1); });
            }
            saveNext(0);
        }

        // ===================== ENTER KEY NAVIGATION =====================

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON')) return;

            // If medicine search results visible, select first
            if (e.target.tagName === 'INPUT') {
                const results = e.target.parentElement?.querySelector('.smart-search-results');
                if (results?.classList.contains('active') && results.children.length > 0) {
                    results.children[0].click();
                    e.preventDefault();
                    return;
                }
            }

            // On a button - let browser fire click, then move focus to next element
            if (e.target.tagName === 'BUTTON') {
                return; // Let default click happen, don't interfere
            }

            e.preventDefault();
            const area = document.getElementById('singleInvoiceArea');
            const container = document.getElementById('multiInvoiceContainer');
            let scope;
            if (area && !area.classList.contains('hidden')) {
                scope = area;
            } else if (container && !container.classList.contains('hidden')) {
                scope = e.target.closest('.card.border-2') || container;
            } else {
                return;
            }
            const focusable = Array.from(scope.querySelectorAll('input:not([readonly]), button:not([disabled])'));
            const idx = focusable.indexOf(e.target);
            if (idx > -1 && idx < focusable.length - 1) {
                focusable[idx + 1].focus();
            }
        });
    </script>

@endpush