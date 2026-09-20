@extends('layouts.app')

@section('title', 'كشف المنصرف الشهري — صيدلية')
@section('page-title', 'كشف المنصرف الشهري')
@section('page-subtitle', 'إجمالي المنصرف لكل صنف حسب رقم التحويل')

@php
    $arabicMonths = [
        1 => 'يناير',
        2 => 'فبراير',
        3 => 'مارس',
        4 => 'إبريل',
        5 => 'مايو',
        6 => 'يونيو',
        7 => 'يوليو',
        8 => 'أغسطس',
        9 => 'سبتمبر',
        10 => 'أكتوبر',
        11 => 'نوفمبر',
        12 => 'ديسمبر'
    ];
    $monthName = isset($month) ? ($arabicMonths[(int) $month] ?? $month) : '';
    $colsCount = count($columns) + 2;
@endphp

@section('content')
    <!-- Filter Form -->
    <div class="card p-5 mb-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="label">الشهر</label>
                <select name="month" class="input" onchange="this.form.submit()">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                            {{ $arabicMonths[$i] }}
                        </option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="label">السنة</label>
                <select name="year" class="input" onchange="this.form.submit()">
                    @for($i = now()->year; $i >= now()->year - 2; $i--)
                        <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-filter"></i> عرض
                </button>
            </div>
        </form>

    </div>

    <!-- Width Control Panel -->
    <div class="card p-5 mb-5" id="widthControlCard">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-extrabold text-slate-900">
                <i class="fas fa-arrows-alt-h ml-1 text-sky-600"></i> التحكم في حجم الخانات (الطباعة)
            </h3>
            <button type="button" onclick="toggleWidthControl()" class="btn btn-ghost text-xs">
                <i class="fas fa-eye-slash"></i> إخفاء
            </button>
        </div>
        <p class="text-xs text-slate-500 mb-3">
            اضبط عرض الخانات بالبكسل — خانة "الأرقام" تسري على كل خانات الأرقام مرة واحدة — ثم اضغط "تطبيق الأبعاد" أو
            "طباعة".
        </p>
        <div class="flex flex-wrap gap-3" id="widthInputs">
            <div>
                <label class="label text-xs">الصنف</label>
                <input type="number" class="input col-width" data-col="0" style="width:90px" value="90" min="10" max="1000">
            </div>
            <div>
                <label class="label text-xs">الأرقام</label>
                <input type="number" class="input col-width" id="colWidthNumbers" data-col="numbers" style="width:90px"
                    value="70" min="10" max="1000">
            </div>
            <div>
                <label class="label text-xs">الإجمالي</label>
                <input type="number" class="input col-width" data-col="{{ count($columns) + 1 }}" style="width:90px"
                    value="70" min="10" max="1000">
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="button" onclick="applyWidths()" class="btn btn-primary">
                <i class="fas fa-check"></i> تطبيق الأبعاد
            </button>
            <button type="button" onclick="resetWidths()" class="btn btn-ghost">إعادة الضبط</button>
        </div>
    </div>

    <!-- Monthly Report Table -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h3 class="font-extrabold text-slate-900">
                إجمالي المنصرف لكل صنف - {{ $monthName }} {{ $year }}
            </h3>
            <div class="flex gap-2">
                <button onclick="printReport()" class="btn btn-ghost">
                    <i class="fas fa-print"></i> طباعة
                </button>
                <button onclick="exportExcel()" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> تصدير Excel
                </button>
            </div>
        </div>

        <div class="table-wrap" style="overflow-x:auto;">
            <table class="data" id="monthlyTable" style="table-layout:fixed; width:100%;">
                <colgroup id="monthlyColgroup">
                    <col style="width:90px">
                    @foreach($columns as $col)
                        <col style="width:70px">
                    @endforeach
                    <col style="width:70px">
                </colgroup>
                <thead>
                    <tr>
                        <th>الصنف</th>
                        @foreach($columns as $col)
                            <th class="text-center">
                                {{ $col->label }}
                                @if(!empty($col->sub ?? null))
                                    <div class="text-xs font-normal text-white mt-0.5">{{ $col->sub }}</div>
                                @endif
                            </th>
                        @endforeach
                        <th class="text-center font-bold">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @php $grandTotal = 0; @endphp
                    @foreach($medicines as $medicine)
                        @php
                            $total = 0;
                            $medPivot = $pivot[$medicine->id] ?? [];
                        @endphp
                        <tr>
                            <td class="font-semibold">{{ $medicine->name }}</td>
                            @foreach($columns as $col)
                                @php
                                    $qty = $medPivot[$col->key] ?? 0;
                                    $total += $qty;
                                @endphp
                                <td class="text-center {{ $qty > 0 ? 'font-semibold' : 'text-slate-300' }}">
                                    {{ $qty > 0 ? $qty : '-' }}
                                </td>
                            @endforeach
                            <td class="text-center font-bold text-sky-700">{{ $total }}</td>
                            @php $grandTotal += $total; @endphp
                        </tr>
                    @endforeach

                </tbody>
                <tfoot class="bg-slate-100 font-bold">
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="text-left text-sm">الإجمالي العام</td>
                        <td class="text-center font-bold text-sky-800">{{ $grandTotal }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function initMonthlyTable() {
            if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined' || typeof $('#monthlyTable').DataTable !== 'function') {
                setTimeout(initMonthlyTable, 50);
                return;
            }
            $('#monthlyTable').DataTable({
                responsive: false,
                paging: true,
                pageLength: 10,
                searching: false,
                info: true,
                autoWidth: false,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']],
                language: {
                    "sProcessing": "جاري التحميل...",
                    "sLengthMenu": "أظهر _MENU_ مدخلات",
                    "sZeroRecords": "لم يعثر على أية سجلات",
                    "sInfo": "إظهار _START_ إلى _END_ من أصل _TOTAL_ مدخل",
                    "sInfoEmpty": "يعرض 0 إلى 0 من أصل 0 سجل",
                    "sInfoFiltered": "(منتقاة من مجموع _MAX_ مُدخل)",
                    "oPaginate": {
                        "sFirst": "الأول",
                        "sPrevious": "السابق",
                        "sNext": "التالي",
                        "sLast": "الأخير"
                    }
                }
            });
        })();

        // Enter key navigation
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || (e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && e.target.tagName !== 'BUTTON')) return;
            if (e.target.tagName === 'BUTTON') return;
            e.preventDefault();
            const scope = document.querySelector('main') || document;
            const focusable = Array.from(scope.querySelectorAll('input:not([readonly]), select:not([disabled]), button:not([disabled])'));
            const idx = focusable.indexOf(e.target);
            if (idx > -1 && idx < focusable.length - 1) {
                focusable[idx + 1].focus();
            }
        });

        // function printReport() {
        //     const dt = $('#monthlyTable').DataTable();
        //     const savedLen = dt.page.len();
        //     dt.page.len(-1).draw();

        //     setTimeout(function () {
        //         const printWindow = window.open('', '_blank');
        //         const monthYear = '{{ $monthName }} {{ $year }}';
        //         const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';

        //         const table = document.getElementById('monthlyTable');

        //         let theadHtml = '';
        //         table.querySelectorAll('thead tr').forEach(function (row) {
        //             theadHtml += '<tr>' + row.innerHTML + '</tr>';
        //         });

        //         let tbodyHtml = '';
        //         table.querySelectorAll('tbody tr').forEach(function (row) {
        //             tbodyHtml += '<tr>' + row.innerHTML + '</tr>';
        //         });

        //         let tfootHtml = '';
        //         if (table.querySelector('tfoot')) {
        //             tfootHtml = '<tr>' + table.querySelector('tfoot tr').innerHTML + '</tr>';
        //         }

        //         dt.page.len(savedLen).draw();

        //         printWindow.document.write(`
        //                             <!DOCTYPE html>
        //                             <html dir="rtl">
        //                             <head>
        //                                 <meta charset="utf-8">
        //                                 <title>كشف المنصرف الشهري</title>
        //                                 <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
        //                                 <style>
        //                                     @page { size: A4 landscape; margin: 5mm; }
        //                                     * { font-family: 'Cairo', sans-serif; box-sizing: border-box; }
        //                                     html, body { height: auto; overflow: visible; }
        //                                     body { padding: 0; margin: 0; }
        //                                     h1 { text-align: center; margin-bottom: 2px; margin-top: 0; font-size: 14px; }
        //                                     .info { text-align: center; margin-bottom: 6px; color: #555; font-size: 10px; }
        //                                     table { width: 100%; border-collapse: collapse; table-layout: auto; page-break-inside: auto; }
        //                                     tr { page-break-inside: avoid; page-break-after: auto; }
        //                                     th, td { border: 1px solid #000; padding: 2px 4px; text-align: center; font-size: 10px; white-space: nowrap; }
        //                                     th:first-child, td:first-child { text-align: right; white-space: normal; min-width: 80px; }
        //                                     th:last-child, td:last-child { white-space: normal; }
        //                                     th { background: #f1f5f9; font-weight: 700; }
        //                                     tfoot td { font-weight: bold; background: #f8fafc; }
        //                                     .footer { text-align: center; margin-top: 6px; font-size: 8px; color: #999; }
        //                                     thead { display: table-header-group; }
        //                                     tfoot { display: table-footer-group; }
        //                                 </style>
        //                             </head>
        //                             <body>
        //                                 <h1>كشف المنصرف الشهري</h1>
        //                                 <div class="info">${monthYear} — الموظف: ${user}</div>
        //                                 <table>
        //                                     <thead>${theadHtml}</thead>
        //                                     <tbody>${tbodyHtml}</tbody>
        //                                     ${tfootHtml ? '<tfoot>' + tfootHtml + '</tfoot>' : ''}
        //                                 </table>
        //                                 <div class="footer">
        //                                     تم إصدار هذا التقرير بواسطة: {{ auth()->user()->name }} — {{ now()->format('Y-m-d H:i') }}
        //                                 </div>
        //                                 <script>window.onload = function(){ setTimeout(function(){ window.print(); }, 600); };<\/script>
        //                             </body>
        //                             </html>
        //                         `);
        //         printWindow.document.close();
        //     }, 150);
        // }



        function printReport() {

            // =========================================================
            // فتح نافذة الطباعة
            // =========================================================

            const printWindow = window.open('', '_blank');

            if (!printWindow) {
                alert('يرجى السماح بالنوافذ المنبثقة من المتصفح حتى يمكن الطباعة.');
                return;
            }


            // =========================================================
            // الحصول على DataTable
            // =========================================================

            const dt = $('#monthlyTable').DataTable();

            const savedLen = dt.page.len();


            // =========================================================
            // نظهر جميع الصفوف مؤقتًا
            // حتى نضمن أن الطباعة تأخذ كل البيانات
            // =========================================================

            dt.page.len(-1).draw();


            // ننتظر DataTables حتى تنتهي من تحديث الجدول
            setTimeout(function () {

                try {

                    const monthYear = @json($monthName . ' ' . $year);
                    const user = @json(auth()->user()->name . ' (' . auth()->user()->employee_code . ')');
                    const issuedBy = @json(auth()->user()->name);
                    const issuedAt = @json(now()->format('Y-m-d H:i'));


                    const table = document.getElementById('monthlyTable');


                    // =====================================================
                    // قراءة Header الأصلي
                    // =====================================================

                    const headerRow = table.querySelector('thead tr');

                    if (!headerRow) {
                        throw new Error('لم يتم العثور على رأس الجدول.');
                    }


                    const headerCells = Array.from(
                        headerRow.querySelectorAll('th')
                    );


                    // =====================================================
                    // قراءة جميع صفوف البيانات
                    // =====================================================

                    const bodyRows = Array.from(
                        table.querySelectorAll('tbody tr')
                    ).filter(function (row) {

                        // تجاهل أي صف فارغ
                        return row.querySelectorAll('td').length > 1;

                    });


                    // =====================================================
                    // قراءة Footer
                    // =====================================================

                    const footerRow = table.querySelector('tfoot tr');


                    // =====================================================
                    // عدد الأعمدة
                    //
                    // أول عمود = الصنف
                    // آخر عمود = الإجمالي
                    // وما بينهما = أرقام التحويلات
                    // =====================================================

                    const totalColumns = headerCells.length;

                    const numberColumns = totalColumns - 2;


                    // =====================================================
                    // عدد أرقام التحويلات في كل صفحة
                    //
                    // 96 رقم:
                    //
                    // 1  -> 24
                    // 25 -> 48
                    // 49 -> 72
                    // 73 -> 96
                    // =====================================================

                    const columnsPerPage = 24;


                    const horizontalPages = Math.ceil(
                        numberColumns / columnsPerPage
                    );


                    // =====================================================
                    // بناء صفحات الطباعة
                    // =====================================================

                    let pagesHtml = '';


                    for (
                        let pageIndex = 0;
                        pageIndex < horizontalPages;
                        pageIndex++
                    ) {


                        // -------------------------------------------------
                        // بداية ونهاية أرقام التحويلات في الصفحة
                        // -------------------------------------------------

                        const startNumber =
                            pageIndex * columnsPerPage + 1;

                        const endNumber =
                            Math.min(
                                startNumber + columnsPerPage - 1,
                                numberColumns
                            );


                        // -------------------------------------------------
                        // Header الصفحة
                        // -------------------------------------------------

                        let pageHeaderHtml = '<tr>';


                        // خانة الصنف
                        pageHeaderHtml += `
                                                                <th class="medicine-header">
                                                                    ${headerCells[0].innerHTML}
                                                                </th>
                                                            `;


                        // أعمدة الأرقام
                        for (
                            let colIndex = startNumber;
                            colIndex <= endNumber;
                            colIndex++
                        ) {

                            pageHeaderHtml += `
                                                                    <th class="number-header">
                                                                        ${headerCells[colIndex].innerHTML}
                                                                    </th>
                                                                `;

                        }


                        // خانة الإجمالي
                        pageHeaderHtml += `
                                                                <th class="total-header">
                                                                    ${headerCells[totalColumns - 1].innerHTML}
                                                                </th>
                                                            `;


                        pageHeaderHtml += '</tr>';


                        // -------------------------------------------------
                        // Body الصفحة
                        // -------------------------------------------------

                        let pageBodyHtml = '';


                        bodyRows.forEach(function (row) {

                            const cells = Array.from(
                                row.querySelectorAll('td')
                            );


                            // لو الصف غير صحيح نتجاهله
                            if (cells.length < 2) {
                                return;
                            }


                            let rowHtml = '<tr>';


                            // ------------------------------------------------
                            // اسم الصنف
                            // ------------------------------------------------

                            rowHtml += `
                                                                    <td class="medicine-name">
                                                                        ${cells[0].innerHTML}
                                                                    </td>
                                                                `;


                            // ------------------------------------------------
                            // أرقام التحويلات الخاصة بهذه الصفحة
                            // ------------------------------------------------

                            for (
                                let colIndex = startNumber;
                                colIndex <= endNumber;
                                colIndex++
                            ) {

                                if (cells[colIndex]) {

                                    rowHtml += `
                                                                            <td class="number-cell">
                                                                                ${cells[colIndex].innerHTML}
                                                                            </td>
                                                                        `;

                                }

                            }


                            // ------------------------------------------------
                            // الإجمالي
                            // ------------------------------------------------

                            rowHtml += `
                                                                    <td class="total-cell">
                                                                        ${cells[cells.length - 1].innerHTML}
                                                                    </td>
                                                                `;


                            rowHtml += '</tr>';


                            pageBodyHtml += rowHtml;

                        });


                        // -------------------------------------------------
                        // Footer
                        // -------------------------------------------------

                        let pageFooterHtml = '';


                        if (footerRow) {

                            const footerCells = Array.from(
                                footerRow.querySelectorAll('td, th')
                            );


                            if (footerCells.length > 0) {

                                pageFooterHtml = `
                                                                        <tfoot>
                                                                            <tr>

                                                                                <td
                                                                                    colspan="${(endNumber - startNumber + 1) + 1}"
                                                                                    class="grand-total-label"
                                                                                >
                                                                                    ${footerCells[0].innerHTML}
                                                                                </td>

                                                                                <td class="grand-total-value">
                                                                                    ${footerCells.length > 1
                                        ? footerCells[footerCells.length - 1].innerHTML
                                        : ''
                                    }
                                                                                </td>

                                                                            </tr>
                                                                        </tfoot>
                                                                    `;

                            }

                        }


                        // -------------------------------------------------
                        // الصفحة
                        // -------------------------------------------------

                        pagesHtml += `

                                                                <section class="print-page">

                                                                    <div class="report-header">

                                                                        <div class="report-title">
                                                                            كشف المنصرف الشهري
                                                                        </div>

                                                                        <div class="report-info">
                                                                            ${monthYear}
                                                                            —
                                                                            الموظف: ${user}
                                                                        </div>

                                                                        <div class="range-info">
                                                                            التحويلات من
                                                                            <strong>${startNumber}</strong>
                                                                            إلى
                                                                            <strong>${endNumber}</strong>
                                                                        </div>

                                                                    </div>


                                                                    <table class="report-table">

                                                                        <thead>
                                                                            ${pageHeaderHtml}
                                                                        </thead>

                                                                        <tbody>
                                                                            ${pageBodyHtml}
                                                                        </tbody>

                                                                        ${pageFooterHtml}

                                                                    </table>


                                                                    <div class="print-footer">

                                                                        تم إصدار هذا التقرير بواسطة:
                                                                        ${issuedBy}

                                                                        —
                                                                        ${issuedAt}

                                                                        —
                                                                        صفحة
                                                                        ${pageIndex + 1}
                                                                        من
                                                                        ${horizontalPages}

                                                                    </div>

                                                                </section>

                                                            `;

                    }


                    // =====================================================
                    // CSS + HTML
                    // =====================================================

                    printWindow.document.write(`

                                                            <!DOCTYPE html>

                                                            <html
                                                                lang="ar"
                                                                dir="rtl"
                                                            >

                                                            <head>

                                                                <meta charset="utf-8">

                                                                <title>
                                                                    كشف المنصرف الشهري
                                                                </title>


                                                                <link
                                                                    href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap"
                                                                    rel="stylesheet"
                                                                >


                                                                <style>


                                                                    /* =========================================
                                                                       A4 Landscape
                                                                       ========================================= */

                                                                    @page {

                                                                        size: A4 landscape;

                                                                        margin: 7mm;

                                                                    }


                                                                    /* =========================================
                                                                       General
                                                                       ========================================= */

                                                                    * {

                                                                        box-sizing: border-box;

                                                                        font-family:
                                                                            'Cairo',
                                                                            Arial,
                                                                            Tahoma,
                                                                            sans-serif;

                                                                    }


                                                                    html,
                                                                    body {

                                                                        width: 100%;

                                                                        margin: 0;

                                                                        padding: 0;

                                                                        direction: rtl;

                                                                        background: #fff;

                                                                        color: #000;

                                                                    }


                                                                    body {

                                                                        font-size: 9px;

                                                                    }


                                                                    /* =========================================
                                                                       كل مجموعة أعمدة = صفحة
                                                                       ========================================= */
                .print-page {
                    width: 100%;
                    max-width: 100%;
                    overflow: hidden;

                    page-break-after: always;
                }

                .print-page:last-child {
                    page-break-after: auto;
                }


                                                                    .print-page:last-child {

                                                                        page-break-after: auto;

                                                                        break-after: auto;

                                                                    }


                                                                    /* =========================================
                                                                       Header التقرير
                                                                       ========================================= */

                                                                    .report-header {

                                                                        text-align: center;

                                                                        margin-bottom: 5px;

                                                                    }


                                                                    .report-title {

                                                                        font-size: 15px;

                                                                        font-weight: 700;

                                                                        margin-bottom: 2px;

                                                                    }


                                                                    .report-info {

                                                                        font-size: 9px;

                                                                        color: #333;

                                                                        margin-bottom: 2px;

                                                                    }


                                                                    .range-info {

                                                                        font-size: 9px;

                                                                        font-weight: 700;

                                                                        margin-bottom: 5px;

                                                                    }


                                                                    /* =========================================
                                                                       Table
                                                                       ========================================= */

                                                                   .report-table {

                        width: 100%;

                        max-width: 100%;

                        border-collapse: collapse;

                        table-layout: fixed;

                        page-break-inside: auto;

                        break-inside: auto;

                    }


                                                                    /* تكرار رأس الجدول في حالة
                                                                       كسر الصفحة رأسيًا */

                                                                    .report-table thead {

                                                                        display: table-header-group;

                                                                    }


                                                                    .report-table tbody {

                                                                        display: table-row-group;

                                                                    }


                                                                    .report-table tfoot {

                                                                        display: table-row-group;

                                                                    }


                                                                    /* لا نكسر الصف نفسه */

                                                                    .report-table tr {

                                                                        page-break-inside: avoid;

                                                                        break-inside: avoid;

                                                                    }


                                                                    /* =========================================
                                                                       Cells
                                                                       ========================================= */

                                                                    .report-table th,
                                                                    .report-table td {

                                                                        border: 1px solid #000;

                                                                        padding: 3px;

                                                                        height: 21px;

                                                                        font-size: 9px;

                                                                        line-height: 1.2;

                                                                        vertical-align: middle;

                                                                    }


                                                                    /* =========================================
                                                                       الصنف
                                                                       ========================================= */

                                                                  .medicine-header,
                            .medicine-name {
                                width: 42mm;
                            }


                                                                    .medicine-header {

                                                                        text-align: center;

                                                                        font-size: 10px !important;

                                                                        font-weight: 700;

                                                                    }


                                                                    .medicine-name {

                                                                        text-align: right;

                                                                        font-weight: 600;

                                                                        white-space: normal;

                                                                        word-break: break-word;

                                                                        padding-right: 5px !important;

                                                                        padding-left: 5px !important;

                                                                    }


                                                                    /* =========================================
                                                                       أرقام التحويلات
                                                                       ========================================= */
                                    .number-header,
                                    .number-cell {

                                        width: 8.3mm;
                                        min-width: 8.3mm;
                                        max-width: 8.3mm;

                                        text-align: center;

                                    }


                                                                    .number-header {

                                                                        height: 30px;

                                                                        font-size: 9px !important;

                                                                        font-weight: 700;

                                                                        white-space: nowrap;

                                                                        padding: 2px !important;

                                                                    }


                                                                    .number-cell {

                                                                        font-size: 9px !important;

                                                                        font-weight: 600;

                                                                        white-space: nowrap;

                                                                        padding: 3px 2px !important;

                                                                    }


                                                                    /* =========================================
                                                                       الإجمالي
                                                                       ========================================= */
                        .total-header,
                        .total-cell {

                            width: 18mm;
                            min-width: 18mm;
                            max-width: 18mm;

                                                                        text-align: center;

                                                                    }


                                                                    .total-header {

                                                                        font-size: 10px !important;

                                                                        font-weight: 700;

                                                                    }


                                                                    .total-cell {

                                                                        font-size: 10px !important;

                                                                        font-weight: 700;

                                                                    }


                                                                    /* =========================================
                                                                       Sub Header
                                                                       ========================================= */

                                                                    .sub-header {

                                                                        font-size: 6px;

                                                                        font-weight: 400;

                                                                        line-height: 1;

                                                                        margin-top: 2px;

                                                                        white-space: normal;

                                                                    }


                                                                    /* =========================================
                                                                       الإجمالي العام
                                                                       ========================================= */

                                                                    .grand-total-label {

                                                                        text-align: left !important;

                                                                        font-size: 10px !important;

                                                                        font-weight: 700;

                                                                        background: #eeeeee !important;

                                                                        -webkit-print-color-adjust: exact;

                                                                        print-color-adjust: exact;

                                                                    }


                                                                    .grand-total-value {

                                                                        text-align: center;

                                                                        font-size: 10px !important;

                                                                        font-weight: 700;

                                                                        background: #eeeeee !important;

                                                                        -webkit-print-color-adjust: exact;

                                                                        print-color-adjust: exact;

                                                                    }


                                                                    /* =========================================
                                                                       Footer
                                                                       ========================================= */

                                                                    .print-footer {

                                                                        text-align: center;

                                                                        font-size: 7px;

                                                                        color: #555;

                                                                        margin-top: 5px;

                                                                    }


                                                                    /* =========================================
                                                                       Print
                                                                       ========================================= */

                                                                    @media print {

                                                                        html,
                                                                        body {

                                                                            width: 100%;

                                                                            margin: 0;

                                                                            padding: 0;

                                                                        }


                                                                        .print-page {

                                                                            page-break-after: always;

                                                                            break-after: page;

                                                                        }


                                                                        .print-page:last-child {

                                                                            page-break-after: auto;

                                                                            break-after: auto;

                                                                        }

                                                                    }


                                                                </style>

                                                            </head>


                                                            <body>

                                                                ${pagesHtml}


                                                                <script>

                                                                    window.onload = function () {

                                                                        setTimeout(function () {

                                                                            window.focus();

                                                                            window.print();

                                                                        }, 800);

                                                                    };


                                                                    window.onafterprint = function () {

                                                                        setTimeout(function () {

                                                                            window.close();

                                                                        }, 300);

                                                                    };

                                                                <\/script>

                                                            </body>

                                                            </html>

                                                        `);


                    printWindow.document.close();


                } catch (error) {

                    console.error('Print Error:', error);

                    printWindow.close();

                    alert(
                        'حدث خطأ أثناء تجهيز التقرير للطباعة. راجع Console.'
                    );

                }


                // =====================================================
                // إعادة DataTables إلى حالتها الأصلية
                // =====================================================

                dt.page.len(savedLen).draw();


            }, 300);

        }



        function getColWidths() {
            const totalCols = document.querySelectorAll('#monthlyColgroup col').length;
            const read = (col) => {
                const inp = document.querySelector(`.col-width[data-col="${col}"]`);
                const w = parseInt(inp ? inp.value : '') || 70;
                return Math.max(10, Math.min(1000, w));
            };
            const nameW = read(0);
            const numbersW = read('numbers');
            const totalW = read({{ count($columns) + 1 }});
            const widths = [nameW];
            for (let i = 0; i < {{ count($columns) }}; i++) widths.push(numbersW);
            widths.push(totalW);
            return widths;
        }

        // الخط ثابت ولا يتغير مع عرض الخانة — فقط مساحة الخانة هي التي تتغير
        const TABLE_FONT_SIZE = '10px';

        function applyWidths() {
            const widths = getColWidths();
            const table = document.getElementById('monthlyTable');
            const colgroup = document.getElementById('monthlyColgroup');
            const cols = colgroup ? colgroup.querySelectorAll('col') : [];

            // Resize the actual cells via <col> elements (colgroup) — forces real column widths
            cols.forEach((col, i) => {
                if (widths[i] !== undefined) {
                    col.style.width = widths[i] + 'px';
                }
            });

            // Keep the text at a constant readable size — only the cell area changes
            table.querySelectorAll('th, td').forEach(cell => {
                cell.style.fontSize = TABLE_FONT_SIZE;
            });

            showToast('تم تطبيق الأبعاد على الجدول');
        }

        function resetWidths() {
            document.querySelectorAll('.col-width').forEach(inp => {
                if (inp.dataset.col == 0) inp.value = 90;
                else inp.value = 70;
            });
            // Reset colgroup to defaults too
            const cols = document.querySelectorAll('#monthlyColgroup col');
            cols.forEach((col, i) => {
                col.style.width = (i === 0 ? 90 : 70) + 'px';
            });
            applyWidths();
        }

        function toggleWidthControl() {
            const card = document.getElementById('widthControlCard');
            card.classList.toggle('hidden');
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className =
                `fixed top-5 right-5 px-4 py-3 rounded shadow-lg text-white z-50 ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
            toast.innerText = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        }

        function exportExcel() {
            const monthName = '{{ $monthName }} {{ $year }}';
            const table = document.getElementById('monthlyTable');
            const headerCells = table.querySelectorAll('thead tr th');
            const headers = [];
            // first th is "الصنف", last th is "الإجمالي", middle are referral numbers
            for (let i = 0; i < headerCells.length; i++) {
                headers.push(headerCells[i].textContent.trim());
            }

            let excelHtml = '<table border="1" style="border-collapse:collapse;font-family:Arial;direction:rtl">';
            // Header row
            excelHtml += '<thead><tr>';
            excelHtml += '<th style="background:#f1f5f9;padding:6px">الشهر</th>';
            for (let h of headers) {
                excelHtml += '<th style="background:#f1f5f9;padding:6px">' + h + '</th>';
            }
            excelHtml += '</tr></thead><tbody>';

            // Data rows
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            for (let r of rows) {
                const cells = r.querySelectorAll('td');
                if (cells.length === 0) continue;
                if (cells.length === 1) { // empty / no data
                    continue;
                }
                excelHtml += '<tr>';
                excelHtml += '<td style="padding:4px;text-align:center">' + monthName + '</td>';
                for (let c of cells) {
                    excelHtml += '<td style="padding:4px;text-align:center">' + c.innerHTML + '</td>';
                }
                excelHtml += '</tr>';
            }
            excelHtml += '</tbody></table>';

            const blob = new Blob(['\uFEFF' + excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'monthly_report_{{ $month }}_{{ $year }}.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
        }
    </script>
@endpush