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
    $colsCount = count($referralNumbers) + 2;
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

        <div class="table-wrap">
            <table class="data data-table" id="monthlyTable">
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
        function printReport() {
            const printWindow = window.open('', '_blank');
            const monthYear = '{{ $monthName }} {{ $year }}';
            const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';

            printWindow.document.write(`
                                    <!DOCTYPE html>
                                    <html dir="rtl">
                                    <head>
                                        <meta charset="utf-8">
                                        <title>كشف المنصرف الشهري</title>
                                        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
                                        <style>
                                            * { font-family: 'Cairo', sans-serif; }
                                            body { padding: 20px; }
                                            h1 { text-align: center; margin-bottom: 10px; margin-top:0;}
                                            .info { text-align: center; margin-bottom: 20px; color: #666; }
                                            table { width: 100%; border-collapse: collapse; font-size: 11px; }
                                            th, td { border: 1px solid #ddd; padding: 4px; text-align: center; }
                                            th { background: #f1f5f9; font-weight: 600; }
                                            .total { font-weight: bold; background: #dbeafe; }
                                        </style>
                                    </head>
                                    <body>
                                        <h1>كشف المنصرف الشهري</h1>
                                        <div class="info">${monthYear} - الموظف: ${user}</div>
                                        ${document.getElementById('monthlyTable').outerHTML}
                                        <div style="margin-top: 20px; text-align: center; font-size: 12px; color: #999;">
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