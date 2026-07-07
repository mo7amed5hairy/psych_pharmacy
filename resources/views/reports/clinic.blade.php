@extends('layouts.app')

@section('title', 'العيادة الخارجية — أسعار الأدوية')
@section('page-title', 'العيادة الخارجية')
@section('page-subtitle', 'أسعار الأدوية عن شهر')

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
    $monthName = $arabicMonths[(int) $month] ?? $month;
@endphp

@section('content')
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

    <div class="card p-5">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h3 class="font-extrabold text-slate-900">
                منصرف العيادة - {{ $monthName }} {{ $year }}
            </h3>
            <button onclick="printReport()" class="btn btn-ghost">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>

        <div class="table-wrap">
            <table class="data data-table" id="clinicTable">
                <thead>
                    <tr>
                        <th>اسم الصنف</th>
                        <th>الوحدة</th>
                        <th>سعر الوحدة</th>
                        <th>الكمية المنصرفة</th>
                        <th>إجمالي المنصرف</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportRows as $row)
                        <tr>
                            <td class="font-semibold">{{ $row->name }}</td>
                            <td>{{ $row->unit }}</td>
                            <td class="text-center">{{ number_format($row->unit_price, 3) }}</td>
                            <td class="text-center">{{ $row->quantity }}</td>
                            <td class="text-center font-bold text-sky-700">{{ number_format($row->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 py-4">لا توجد بيانات للشهر المحدد</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-100 font-bold">
                    <tr>
                        <td colspan="4" class="text-left">الإجمالي المالي</td>
                        <td class="text-center text-sky-700">{{ number_format($grandTotal, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div class="stat" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)">
                <div class="l">الإجمالي المالي</div>
                <div class="v">{{ number_format($grandTotal, 2) }} ج.م</div>
            </div>
            <div class="stat" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <div class="l">عدد أيام الصرف الشهري</div>
                <div class="v">{{ $daysCount }} يوم</div>
            </div>
            <div class="stat" style="background:linear-gradient(135deg,#10b981,#059669)">
                <div class="l">عدد التذاكر الشهري</div>
                <div class="v">{{ $ticketsCount }} تذكرة</div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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

        function formatNumber(num) {
            return num.toLocaleString('en-US').replace(/,/g, ',');
        }

        function printReport() {
            const printWindow = window.open('', '_blank');
            const monthName = '{{ $monthName }}';
            const year = '{{ $year }}';
            const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';
            const logoUrl = '{{ asset('images/ain-shams-logo.jpg') }}';
            const daysCount = '{{ $daysCount }}';
            const ticketsCount = '{{ $ticketsCount }}';
            const grandTotal = {{ $grandTotal }};

            let rowsHtml = '';
            const tbody = document.querySelector('#clinicTable tbody');
            const rows = tbody.querySelectorAll('tr');

            rows.forEach(r => {
                const cells = r.querySelectorAll('td');
                if (cells.length < 5) return;
                const name = cells[0].textContent.trim();
                const unit = cells[1].textContent.trim();
                const price = cells[2].textContent.trim();
                const qty = cells[3].textContent.trim();
                const total = cells[4].textContent.trim();
                rowsHtml += `
                                    <tr>
                                        <td style="border:2px solid #000;padding:2px 5px;">${name}</td>
                                        <td style="border:2px solid #000;padding:2px 5px;text-align:center;">${unit}</td>
                                        <td style="border:2px solid #000;padding:2px 5px;text-align:left;">${price} ج.م.</td>
                                        <td style="border:2px solid #000;padding:2px 5px;text-align:center;">${qty}</td>
                                        <td style="border:2px solid #000;padding:2px 5px;text-align:left;">${total} ج.م.</td>
                                    </tr>`;
            });

            const grandTotalFormatted = formatNumber(grandTotal);

            printWindow.document.write(`
                                <!DOCTYPE html>
                                <html lang="ar" dir="rtl">
                                <head>
                                    <meta charset="UTF-8">
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                    <title>العيادة الخارجية - أسعار الأدوية</title>
                                    <style>
                                        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
                                        @page { size: A4; margin: 8mm; }
                                        body { font-family: 'Cairo', sans-serif; background: #fff; margin:0; padding:0; }
                                        @media print {
                                            body { background-color: #fff; }
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div style="max-width:90%;margin:30px auto;padding:0;display:flex;flex-direction:column;justify-content:space-between;">
                                        <div>
                                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                                                <div style="text-align:center;width:150px;">
                                                    <img src="${logoUrl}" alt="شعار جامعة عين شمس" style="width:50px;height:50px;margin:0 auto 4px;object-fit:contain;display:block;">
                                                    <p style="font-size:9px;font-weight:bold;line-height:1.2;margin:0;">مستشفيات جامعة عين شمس</p>
                                                    <p style="font-size:9px;font-weight:600;margin:0;">مركز الطب النفسي</p>
                                                </div>
                                                <div style="text-align:center;flex:1;padding-top:8px;">
                                                    <h1 style="font-size:15px;font-weight:bold;letter-spacing:1px;margin:0;">العيادة الخارجية</h1>
                                                    <h2 style="font-size:12px;font-weight:bold;margin:4px 0 0;">أسعار الأدوية عن شهر ${monthName}</h2>
                                                    <p style="font-size:10px;font-weight:bold;margin:2px 0 0;">${year}</p>
                                                </div>
                                                <div style="text-align:left;font-size:9px;color:#555;font-family:monospace;padding-top:4px;">
                                                    <p style="margin:0;">Designed by \\ Eng Mohamed Khairy</p>
                                                </div>
                                            </div>
                                            <table style="width:100%;border-collapse:collapse;border:2px solid #000;font-size:10px;text-align:right;">
                                                <thead>
                                                    <tr style="background:#f1f5f9;">
                                                        <th style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;width:35%;">اسم الصنف</th>
                                                        <th style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;width:12%;">الوحدة</th>
                                                        <th style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;width:18%;">سعر الوحدة</th>
                                                        <th style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;width:15%;">الكمية المنصرفة</th>
                                                        <th style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;width:20%;">إجمالي المنصرف</th>
                                                    </tr>
                                                </thead>
                                                <tbody style="font-weight:600;">
                                                    ${rowsHtml}
                                                    <tr style="border-top:2px solid #000;">
                                                        <td colspan="2" style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;background:#f1f5f9;">الإجمالي</td>
                                                        <td colspan="3" style="border:2px solid #000;padding:2px 5px;text-align:left;font-weight:bold;font-size:11px;">${grandTotalFormatted} ج.م.</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2" style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;">عدد أيام الصرف الشهري</td>
                                                        <td style="border:2px solid #000;padding:2px 5px;text-align:center;">${daysCount}</td>
                                                        <td colspan="2" style="border:2px solid #000;padding:2px 5px;text-align:right;font-size:10px;padding-right:10px;">يوم</td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="2" style="border:2px solid #000;padding:2px 5px;text-align:center;font-weight:bold;">عدد التذاكر الشهري</td>
                                                        <td style="border:2px solid #000;padding:2px 5px;text-align:center;">${ticketsCount}</td>
                                                        <td colspan="2" style="border:2px solid #000;padding:2px 5px;text-align:right;font-size:10px;padding-right:10px;">تذكرة</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding:0 20px;font-weight:bold;font-size:10px;">
                                            <div>مديرة الصيدلية</div>
                                            <div>رئيس مركز الطب النفسي</div>
                                        </div>
                                    </div>
                                    <script>window.onload = () => setTimeout(() => window.print(), 500);<\/script>
                                </body>
                                </html>
                            `);
            printWindow.document.close();
        }
    </script>
@endpush