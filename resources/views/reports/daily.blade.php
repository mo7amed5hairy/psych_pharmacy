@extends('layouts.app')

@section('title', 'كشف المنصرف اليومي — صيدلية')
@section('page-title', 'كشف المنصرف اليومي')
@section('page-subtitle', 'إجمالي المنصرف لكل صنف حسب رقم التحويل')

@php
    $colsCount = count($referralNumbers) + 2;
@endphp

@section('content')
    <!-- Filter Form -->
    <div class="card p-5 mb-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="label">التاريخ</label>
                <input type="date" name="date" class="input" value="{{ $date }}" onchange="this.form.submit()">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-filter"></i> عرض
                </button>
            </div>
        </form>

    </div>

    <!-- Daily Report Table -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h3 class="font-extrabold text-slate-900">
                إجمالي المنصرف لكل صنف - {{ $date }}
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

        <div class="table-wrap">
            <table class="data" id="dailyTable">
                <thead>
                    <tr>
                        <th>الصنف</th>
                        @foreach($referralNumbers as $refNum)
                            <th class="text-center">{{ $refNum }}</th>
                        @endforeach
                        <th class="text-center font-bold">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($medicines as $medicine)
                        @php
                            $total = 0;
                            $medPivot = $pivot[$medicine->id] ?? [];
                        @endphp
                        <tr>
                            <td class="font-semibold">{{ $medicine->name }}</td>
                            @foreach($referralNumbers as $refNum)
                                @php
                                    $qty = $medPivot[$refNum] ?? 0;
                                    $total += $qty;
                                @endphp
                                <td class="text-center {{ $qty > 0 ? 'font-semibold' : 'text-slate-300' }}">
                                    {{ $qty > 0 ? $qty : '-' }}
                                </td>
                            @endforeach
                            <td class="text-center font-bold text-sky-700">{{ $total }}</td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function initDailyTable() {
            if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined' || typeof $('#dailyTable').DataTable !== 'function') {
                setTimeout(initDailyTable, 50);
                return;
            }
            $('#dailyTable').DataTable({
                responsive: false,
                paging: true,
                pageLength: 10,
                searching: false,
                info: true,
                autoWidth: false,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'الكل']]
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
            const date = '{{ $date }}';
            const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';

            const table = document.getElementById('dailyTable');
            const cleanWidths = (el) => {
                el.style.width = '';
                el.style.minWidth = '';
                el.style.maxWidth = '';
                el.removeAttribute('width');
            };
            const thead = table.querySelector('thead').cloneNode(true);
            thead.querySelectorAll('th').forEach(cleanWidths);
            const theadHtml = thead.innerHTML;
            const tbody = table.querySelector('tbody').cloneNode(true);
            tbody.querySelectorAll('td').forEach(cleanWidths);
            const tbodyHtml = tbody.innerHTML;

            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <title>كشف المنصرف اليومي</title>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
                    <style>
                        @page { size: A4 landscape; margin: 5mm; }
                        * { font-family: 'Cairo', sans-serif; box-sizing: border-box; }
                        body { padding: 0; margin:0; }
                        h1 { text-align: center; margin: 2px 0; font-size: 15px; }
                        .info { text-align: center; margin-bottom: 5px; color: #666; font-size: 11px; }
                        #fullTable { border-collapse: collapse; font-size: 15px; line-height: 1.2; width: max-content; }
                        table.chunk { border-collapse: collapse; font-size: 15px; line-height: 1.2; margin: 0 auto; }
                        th, td { border: 1px solid #000; padding: 2px 4px; text-align: center; white-space: nowrap; }
                        th:first-child, td:first-child, td.name { text-align: right; white-space: normal; word-break: break-word; }
                        th { background: #f1f5f9; font-weight: 700; }
                        tfoot td { font-weight: bold; background: #f8fafc; }
                        .pagebreak { page-break-before: always; }
                        .footer { text-align: center; margin-top: 5px; font-size: 9px; color: #999; }
                    </style>
                </head>
                <body>
                    <h1>كشف المنصرف اليومي</h1>
                    <div class="info">${date} - الموظف: ${user}</div>
                    <div id="fullWrap">
                        <table id="fullTable">
                            <thead>${theadHtml}</thead>
                            <tbody>${tbodyHtml}</tbody>
                        </table>
                    </div>
                    <div id="chunks"></div>
                    <div class="footer">
                        تم إصدار هذا التقرير بواسطة: {{ auth()->user()->name }} - {{ now()->format('Y-m-d H:i') }}
                    </div>
                    <script>
                        window.onload = function () {
                            var full = document.getElementById('fullTable');
                            var ths = full.querySelectorAll('thead tr th');
                            var n = ths.length - 2;
                            if (n > 0) {
                                var nameW = ths[0].getBoundingClientRect().width;
                                var totalW = ths[n + 1].getBoundingClientRect().width;
                                var numericW = ths[1].getBoundingClientRect().width;
                                var available = 1060;
                                var perPage = Math.max(1, Math.floor((available - nameW - totalW) / numericW));

                                var labels = [];
                                for (var i = 1; i <= n; i++) labels.push(ths[i].textContent.trim());

                                var rows = [];
                                var trs = full.querySelectorAll('tbody tr');
                                for (var r = 0; r < trs.length; r++) {
                                    var tds = trs[r].querySelectorAll('td');
                                    if (tds.length !== ths.length) continue;
                                    var qtys = [];
                                    for (var j = 1; j < tds.length - 1; j++) qtys.push(tds[j].textContent.trim());
                                    rows.push({ name: tds[0].textContent.trim(), qtys: qtys, total: tds[tds.length - 1].textContent.trim() });
                                }

                                var html = '';
                                for (var start = 0; start < labels.length; start += perPage) {
                                    var end = Math.min(start + perPage, labels.length);
                                    var t = '<table class="chunk"><thead><tr><th>الصنف</th>';
                                    for (var c = start; c < end; c++) t += '<th>' + labels[c] + '</th>';
                                    t += '<th>الإجمالي</th></tr></thead><tbody>';
                                    for (var rr = 0; rr < rows.length; rr++) {
                                        var row = rows[rr];
                                        t += '<tr><td class="name">' + row.name + '</td>';
                                        for (var cc = start; cc < end; cc++) t += '<td>' + (row.qtys[cc] !== undefined ? row.qtys[cc] : '-') + '</td>';
                                        t += '<td>' + row.total + '</td></tr>';
                                    }
                                    t += '</tbody></table>';
                                    html += t;
                                    if (end < labels.length) html += '<div class="pagebreak"></div>';
                                }
                                document.getElementById('chunks').innerHTML = html;
                            }
                            var fw = document.getElementById('fullWrap');
                            if (fw) fw.style.display = 'none';
                            setTimeout(function () { window.print(); }, 200);
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function exportExcel() {
            const date = '{{ $date }}';
            const table = document.getElementById('dailyTable');
            const headerCells = table.querySelectorAll('thead tr th');
            const headers = [];
            for (let i = 0; i < headerCells.length; i++) {
                headers.push(headerCells[i].textContent.trim());
            }

            let excelHtml = '<table border="1" style="border-collapse:collapse;font-family:Arial;direction:rtl">';
            excelHtml += '<thead><tr>';
            excelHtml += '<th style="background:#f1f5f9;padding:6px">التاريخ</th>';
            for (let h of headers) {
                excelHtml += '<th style="background:#f1f5f9;padding:6px">' + h + '</th>';
            }
            excelHtml += '</tr></thead><tbody>';

            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            for (let r of rows) {
                const cells = r.querySelectorAll('td');
                if (cells.length === 0) continue;
                if (cells.length === 1) continue;
                excelHtml += '<tr>';
                excelHtml += '<td style="padding:4px;text-align:center">' + date + '</td>';
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
            downloadLink.download = 'daily_report_{{ $date }}.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
        }
    </script>
@endpush