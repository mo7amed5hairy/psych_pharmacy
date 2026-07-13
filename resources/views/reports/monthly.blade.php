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
    $colsCount = count($dates) + 2;
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
            <table class="data" id="monthlyTable">
                <thead>
                    <tr>
                        <th>الصنف</th>
                        @foreach($dates as $day)
                            <th class="text-center">{{ $day->label }}</th>
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
                            @foreach($dates as $day)
                                @php
                                    $qty = $medPivot[$day->date] ?? 0;
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
                        <td colspan="{{ count($dates) + 1 }}" class="text-left text-sm">الإجمالي العام</td>
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

        function printReport() {
            const printWindow = window.open('', '_blank');
            const monthYear = '{{ $monthName }} {{ $year }}';
            const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';

            const table = document.getElementById('monthlyTable');
            // Clean DataTable-injected widths from thead cells
            const thead = table.querySelector('thead').cloneNode(true);
            thead.querySelectorAll('th').forEach(th => {
                th.style.width = '';
                th.style.minWidth = '';
            });
            const theadHtml = thead.innerHTML;
            const tbodyHtml = table.querySelector('tbody').innerHTML;
            let tfootHtml = '';
            const tfoot = table.querySelector('tfoot');
            if (tfoot) tfootHtml = tfoot.innerHTML;

            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <title>كشف المنصرف الشهري</title>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
                    <style>
                        @page { size: A4 landscape; margin: 8mm; }
                        * { font-family: 'Cairo', sans-serif; box-sizing: border-box; }
                        body { padding: 0; margin:0; }
                        h1 { text-align: center; margin-bottom: 3px; margin-top:0; font-size:14px; }
                        .info { text-align: center; margin-bottom: 6px; color: #666; font-size: 10px; }
                        table { width: 100%; border-collapse: collapse; font-size: 8px; }
                        th, td { border: 1px solid #000; padding: 2px 3px; text-align: center; }
                        th:first-child, td:first-child { text-align: right; min-width: 70px; }
                        th { background: #f1f5f9; font-weight: 700; }
                        tfoot td { font-weight: bold; background: #f8fafc; }
                        .footer { text-align: center; margin-top: 6px; font-size: 8px; color: #999; }
                    </style>
                </head>
                <body>
                    <h1>كشف المنصرف الشهري</h1>
                    <div class="info">${monthYear} - الموظف: ${user}</div>
                    <table>
                        <thead>${theadHtml}</thead>
                        <tbody>${tbodyHtml}</tbody>
                        <tfoot>${tfootHtml}</tfoot>
                    </table>
                    <div class="footer">
                        تم إصدار هذا التقرير بواسطة: {{ auth()->user()->name }} - {{ now()->format('Y-m-d H:i') }}
                    </div>
                    <script>window.onload = () => setTimeout(() => window.print(), 500);<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
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