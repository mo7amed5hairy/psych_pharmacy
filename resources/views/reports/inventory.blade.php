@extends('layouts.app')

@section('title', 'الجرد — صيدلية')
@section('page-title', 'الجرد')
@section('page-subtitle', 'من تاريخ — إلى تاريخ — حسب كود الموظف')

@section('content')
    <!-- Filter Form -->
    <div class="card p-5 mb-5">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="label">من تاريخ</label>
                <input type="date" name="from_date" class="input" value="{{ $fromDate }}">
            </div>
            <div>
                <label class="label">إلى تاريخ</label>
                <input type="date" name="to_date" class="input" value="{{ $toDate }}">
            </div>
            <div>
                <label class="label">الصنف</label>
                <div class="relative">
                    <input type="text" id="medicineSearch" class="input" placeholder="🔎 ابحث عن الدواء..."
                        autocomplete="off">
                    <input type="hidden" name="medicine_id" id="medicineId" value="{{ request('medicine_id') }}">
                    <div id="medicineResults" class="smart-search-results"></div>
                </div>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="fas fa-search"></i> عرض الجرد
                </button>
            </div>
        </form>

    </div>

    <!-- Totals -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
        <div class="stat" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)">
            <div class="l">إجمالي الرصيد</div>
            <div class="v">{{ number_format($totals['total_stock']) }}</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="l">إجمالي المنصرف</div>
            <div class="v">{{ number_format($totals['total_dispensed']) }}</div>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="l">إجمالي المتبقي</div>
            <div class="v">{{ number_format($totals['total_remaining']) }}</div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-extrabold text-slate-900">تفاصيل الجرد</h3>
            <button onclick="printInventory()" class="btn btn-ghost">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>

        <div class="table-wrap">
            <table class="data data-table" id="inventoryTable">
                <thead>
                    <tr>
                        <th>الصنف</th>
                        <th>الوحدة</th>
                        <th>الرصيد</th>
                        <th>المنصرف</th>
                        <th>المتبقي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inventory as $item)
                        <tr>
                            <td class="font-semibold">{{ $item->medicine->name }}</td>
                            <td>{{ $item->medicine->unitType->name }}</td>
                            <td>{{ $item->opening }}</td>
                            <td>{{ $item->dispensed }}</td>
                            <td class="font-bold {{ $item->remaining < 100 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $item->remaining }}
                            </td>
                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Smart Search for Medicine
        const medicineSearch = document.getElementById('medicineSearch');
        const medicineResults = document.getElementById('medicineResults');
        const medicineId = document.getElementById('medicineId');

        let searchTimeout;
        medicineSearch.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const query = this.value;
            medicineId.value = '';
            if (query.length < 2) {
                medicineResults.classList.remove('active');
                return;
            }
            searchTimeout = setTimeout(() => {
                fetch('{{ url('/medicines/search') }}?q=' + encodeURIComponent(query))
                    .then(res => res.json())
                    .then(data => {
                        medicineResults.innerHTML = '';
                        if (data.length === 0) {
                            medicineResults.innerHTML = '<div class="smart-search-item text-slate-500">لا يوجد دواء</div>';
                        } else {
                            data.forEach(med => {
                                const div = document.createElement('div');
                                div.className = 'smart-search-item';
                                div.innerHTML = '<div class="font-semibold">' + med.name + '</div><div class="text-xs text-slate-500">' + (med.unit_type?.name || '') + '</div>';
                                div.onclick = () => {
                                    medicineId.value = med.id;
                                    medicineSearch.value = med.name;
                                    medicineResults.classList.remove('active');
                                };
                                medicineResults.appendChild(div);
                            });
                        }
                        medicineResults.classList.add('active');
                    });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.relative')) {
                medicineResults.classList.remove('active');
            }
        });

        function printInventory() {
            const printWindow = window.open('', '_blank');
            const fromDate = '{{ $fromDate }}';
            const toDate = '{{ $toDate }}';
            const user = '{{ auth()->user()->name }} ({{ auth()->user()->employee_code }})';


            printWindow.document.write(`
                            <!DOCTYPE html>
                            <html dir="rtl">
                            <head>
                                <meta charset="utf-8">
                                <title>تقرير الجرد</title>
                                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
                                <style>
                                    * { font-family: 'Cairo', sans-serif; }
                                    body { padding: 20px; }
                                    h1 { text-align: center; margin-bottom: 10px; }
                                    .info { text-align: center; margin-bottom: 20px; color: #666; }
                                    table { width: 100%; border-collapse: collapse; }
                                    th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
                                    th { background: #f1f5f9; font-weight: 600; }
                                    .low { color: #dc2626; font-weight: bold; }
                                </style>
                            </head>
                            <body>
                                <h1>تقرير الجرد</h1>
                                <div class="info">من ${fromDate} إلى ${toDate}<br>الموظف: ${user}</div>
                                ${document.getElementById('inventoryTable').outerHTML}
                                <div style="margin-top: 20px; text-align: center; font-size: 12px; color: #999;">
                                    تم إصدار هذا التقرير بواسطة: {{ auth()->user()->name }} - {{ now()->format('Y-m-d H:i') }}
                                </div>
                                <script>window.onload = () => setTimeout(() => window.print(), 500);<\/script>
                            </body>
                            </html>
                        `);
            printWindow.document.close();
        }
        $(document).ready(function () {
            // Moved to app.js
        });
    </script>
@endpush