@extends('layouts.app')

@section('title', 'فاتورة الصرف — صيدلية')
@section('page-title', 'فاتورة الصرف')
@section('page-subtitle', 'اسم المريض + رقم التحويل يدوي')

@section('content')

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

            <input
                type="text"
                id="medicineSearch"
                class="input"
                placeholder="🔎 اختر الدواء..."
                autocomplete="off"
            >

            <div id="medicineResults" class="smart-search-results"></div>
        </div>

        <div class="w-32">
            <label class="label">الكمية</label>
            <input type="number" id="itemQuantity" class="input" value="1" min="1">
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

            <button type="button" onclick="saveInvoice()" class="btn btn-success">
                <i class="fas fa-save"></i>
                حفظ الفاتورة
            </button>

        </div>
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

    document.getElementById('itemQuantity').value = '1';

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
    background:#e8e8e8;
    font-weight:600;
	width:34%;
}

.col-headers th{
    background:#e0e0d8;
    font-weight:700;
    font-size:12px;
	width: 79%;
}

.data-row td{
    background:#fafaf8;
}

.data-row:nth-child(even) td{
    background:#f2f2ee;
}

.total-row td{
    background:#e8e8e0;
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

</script>
@endpush