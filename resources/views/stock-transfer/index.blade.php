@extends('layouts.app')

@section('title', 'تحويل الأرصدة — صيدلية')
@section('page-title', 'تحويل الأرصدة')
@section('page-subtitle', 'تحويل رصيد الفترة المحددة إلى شهر جديد')

@section('content')
    @php
        $monthName = isset($targetMonth) ? ($months[(int) $targetMonth] ?? $targetMonth) : '';
    @endphp

    <!-- Transfer Form -->
    <div class="card p-5 mb-5 bg-sky-50 border-sky-100 sky-blue">
        <div class="mb-4 text-slate-700 font-bold border-b pb-2">
            <i class="fas fa-exchange-alt text-sky-600 ml-1"></i> تحويل الأرصدة إلى شهر جديد
        </div>
        <form method="POST" action="{{ route('stock-transfer.transfer') }}" id="transferForm">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="label text-sky-800">تاريخ من</label>
                    <input type="date" name="from" id="from" class="input" value="{{ $from }}" onchange="filterTable()" required>
                </div>
                <div>
                    <label class="label text-sky-800">تاريخ إلى</label>
                    <input type="date" name="to" id="to" class="input" value="{{ $to }}" onchange="filterTable()" required>
                </div>
                <div>
                    <label class="label text-sky-800">تحويل الرصيد لشهر</label>
                    <select name="month" id="month" class="input">
                        @foreach($months as $num => $name)
                            <option value="{{ $num }}" {{ $num == $targetMonth ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label text-sky-800">السنة</label>
                    <select name="year" id="year" class="input">
                        @foreach($years as $yr)
                            <option value="{{ $yr }}" {{ $yr == $targetYear ? 'selected' : '' }}>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-success w-full justify-center" onclick="return confirmTransfer()">
                        <i class="fas fa-arrow-right"></i> تحويل الأرصدة
                    </button>
                </div>
            </div>
            <div class="text-sm text-slate-500 mt-3">
                <i class="fas fa-info-circle"></i> سيتم تحويل <b>المتبقي</b> من كل صنف في الفترة المحددة إلى الشهر والسنة المختارين، لكل الأدوية الخاصة بك فقط.
            </div>
        </form>
    </div>

    <!-- Stock Table -->
    <div class="card p-5">
        <h3 class="font-extrabold text-slate-900 mb-3" id="periodLabel">
            أرصدة الأدوية في الفترة من {{ $from }} إلى {{ $to }}
        </h3>
        <div class="table-wrap">
            <table class="data" id="transferTable">
                <thead>
                    <tr>
                        <th>الدواء</th>
                        <th>الوحدة</th>
                        <th>رصيد أول الشهر (الكمية)</th>
                        <th>المنصرف</th>
                        <th>المتبقي</th>
                    </tr>
                </thead>
                <tbody id="transferTbody">
                    @foreach($rows as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['name'] }}</td>
                            <td>{{ $row['unit'] }}</td>
                            <td>{{ $row['opening'] }}</td>
                            <td>{{ $row['dispensed'] }}</td>
                            <td class="font-bold {{ $row['remaining'] < 100 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $row['remaining'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div id="emptyMessage" class="hidden text-center text-slate-400 py-6">
            <i class="fas fa-inbox text-3xl mb-2 opacity-30"></i>
            <div>لا توجد أرصدة أدوية في الفترة المحددة</div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let transferableRows = @json($rows);
        let hasTransferableData = transferableRows.length > 0;

        (function initTransferTable() {
            if (!window.$ || !window.$.fn || !window.$.fn.DataTable || typeof window.$('#transferTable').DataTable !== 'function') {
                setTimeout(initTransferTable, 50);
                return;
            }
            window.$('#transferTable').DataTable();
        })();

        function isDataTableActive(table) {
            if (!window.$ || !window.$.fn || !window.$.fn.DataTable) return false;
            try {
                return window.$.fn.DataTable.isDataTable(table);
            } catch (e) {
                return false;
            }
        }

        function destroyDataTable(table) {
            if (isDataTableActive(table)) {
                try {
                    window.$(table).DataTable().destroy();
                } catch (e) { /* ignore */ }
            }
        }

        function initDataTable(table) {
            if (window.$ && window.$.fn && window.$.fn.DataTable) {
                try {
                    window.$(table).DataTable();
                } catch (e) { /* ignore */ }
            }
        }

        function pad(n) {
            return String(n).padStart(2, '0');
        }

        function formatDate(d) {
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }

        function currentMonthRange() {
            const now = new Date();
            const first = new Date(now.getFullYear(), now.getMonth(), 1);
            return { from: formatDate(first), to: formatDate(now) };
        }

        function updatePeriodLabel(from, to) {
            document.getElementById('periodLabel').textContent = `أرصدة الأدوية في الفترة من ${from} إلى ${to}`;
        }

        function filterTable() {
            let from = document.getElementById('from').value;
            let to = document.getElementById('to').value;

            // If both dates were cleared, fall back to the current month range
            if (!from && !to) {
                const range = currentMonthRange();
                from = range.from;
                to = range.to;
                document.getElementById('from').value = from;
                document.getElementById('to').value = to;
            }

            if (!from || !to) return;

            updatePeriodLabel(from, to);

            const table = document.getElementById('transferTable');
            const tbody = document.getElementById('transferTbody');

            // Destroy DataTable first so a 1-cell placeholder row never confuses it
            destroyDataTable(table);

            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-slate-400 py-6">جاري التحميل...</td></tr>';

            fetch(`{{ url('/stock-transfer/filter') }}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, {
                    headers: { 'Accept': 'application/json' }
                })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(rows => {
                    tbody.innerHTML = '';
                    transferableRows = rows;
                    hasTransferableData = rows.length > 0;
                    if (rows.length > 0) {
                        rows.forEach(row => {
                            const tr = document.createElement('tr');
                            const remainingClass = row.remaining < 100 ? 'text-rose-600' : 'text-emerald-700';
                            tr.innerHTML = `
                                <td class="font-semibold">${row.name}</td>
                                <td>${row.unit}</td>
                                <td>${row.opening}</td>
                                <td>${row.dispensed}</td>
                                <td class="font-bold ${remainingClass}">${row.remaining}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }

                    initDataTable(table);
                })
                .catch(err => {
                    console.error(err);
                    transferableRows = [];
                    hasTransferableData = false;
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-rose-500 py-6">حدث خطأ أثناء التحميل، حاول مرة أخرى</td></tr>';
                });
        }

        function confirmTransfer() {
            const from = document.getElementById('from').value;
            const to = document.getElementById('to').value;

            if (!from || !to) {
                alert('يرجى تحديد تاريخ من وتاريخ إلى أولاً');
                return false;
            }

            const total = transferableRows.length;

            if (!hasTransferableData || total === 0) {
                alert('لا توجد أرصدة لتحويلها في الفترة المحددة');
                return false;
            }

            return confirm(`سوف يتم تحويل عدد ${total} من الأصناف. هل أنت متأكد؟`);
        }

        // Enter key navigation (same pattern as other pages)
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'SELECT')) return;
            if (e.target.tagName === 'BUTTON') return;
            e.preventDefault();
            const scope = document.querySelector('main') || document;
            const focusable = Array.from(scope.querySelectorAll('input:not([readonly]):not([type="hidden"]), select, button:not([disabled])'));
            const idx = focusable.indexOf(e.target);
            if (idx > -1 && idx < focusable.length - 1) {
                focusable[idx + 1].focus();
            }
        });
    </script>
@endpush
