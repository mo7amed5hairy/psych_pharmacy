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
            <table class="data data-table" id="dailyTable">
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

            printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
                <head>
                    <meta charset="utf-8">
                    <title>كشف المنصرف اليومي</title>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
                    <style>
                        * { font-family: 'Cairo', sans-serif; box-sizing: border-box; }
                        body { padding: 15px; }
                        h1 { text-align: center; margin-bottom: 5px; margin-top:0;}
                        .info { text-align: center; margin-bottom: 15px; color: #666; font-size: 12px; }
                        table { width: 30% !important; margin: 0 auto; border-collapse: collapse; font-size: .7rem !important; table-layout: auto; }
                        th, td { border: 1px solid #000; padding: 2px; text-align: center; }
                        th:first-child, td:first-child { text-align: right; width: 51px !important; }
                        th:nth-child(2), td:nth-child(2) { width: 15px !important; }
                        th:nth-child(3), td:nth-child(3) { width: 15px !important; }
                        th:nth-child(4), td:nth-child(4) { width: 15px !important; }
                        th:not(:first-child), td:not(:first-child) { width: 35px; white-space: nowrap; }
                        .total { font-weight: bold; background: #fff; }
                    </style>
                </head>
                <body>
                    <h1>كشف المنصرف اليومي</h1>
                    <div class="info">${date} - الموظف: ${user}</div>
                    ${document.getElementById('dailyTable').outerHTML}
                    <div style="margin-top: 15px; text-align: center; font-size: 10px; color: #999;">
                        تم إصدار هذا التقرير بواسطة: {{ auth()->user()->name }} - {{ now()->format('Y-m-d H:i') }}
                    </div>
                    <script>window.onload = () => setTimeout(() => window.print(), 500);<\/script>
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