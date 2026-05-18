@extends('layouts.app')

@section('title', 'أرصدة الأدوية — صيدلية')
@section('page-title', 'أرصدة الأدوية')
@section('page-subtitle', 'إدخال الرصيد الشهري')

@section('content')
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
        $monthName = $arabicMonths[$month] ?? $month;
    @endphp

    <!-- View Filter -->
    <div class="card p-5 mb-5 bg-sky-50 border-sky-100">
        <form method="GET" action="{{ route('stock.index') }}" class="flex items-end gap-3 flex-wrap">
            <div>
                <label class="label text-sky-800">عرض أرصدة شهر</label>
                <div class="flex gap-2">
                    <select name="stock_date" class="input min-w-[150px]" onchange="this.form.submit()">
                        @for($i = -6; $i <= 6; $i++)
                            @php $d = now()->addMonths($i)->startOfMonth(); @endphp
                            <option value="{{ $d->format('Y-m-d') }}" {{ $stockDate == $d->format('Y-m-d') ? 'selected' : '' }}>
                                {{ $arabicMonths[$d->month] }} {{ $d->year }}
                            </option>
                        @endfor
                    </select>
                    <button type="submit" class="btn btn-primary">عرض</button>
                </div>
            </div>
            <div class="text-sm text-slate-500 mb-2 mr-auto italic">
                <i class="fas fa-info-circle"></i> يمكنك التنقل بين الشهور لعرض العهدة السابقة أو المستقبلية.
            </div>
        </form>
    </div>

    <!-- Add Stock Form -->
    <div class="card p-5 mb-5">
        <div class="mb-4 text-slate-700 font-bold border-b pb-2">إضافة / تعديل رصيد صنف</div>
        <form method="POST" action="{{ route('stock.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            <div>
                <label class="label">تاريخ الرصيد</label>
                <input type="date" name="stock_date" class="input" value="{{ $stockDate }}" required>
            </div>
            <div>
                <label class="label">الدواء</label>
                <div class="relative">
                    <input type="text" id="medicineSearch" class="input" placeholder="🔎 ابحث عن الدواء..."
                        autocomplete="off">
                    <input type="hidden" name="medicine_id" id="medicineId">
                    <div id="medicineResults" class="smart-search-results"></div>
                </div>
            </div>
            <div>
                <label class="label">الكمية (رصيد أول الشهر)</label>
                <input type="number" name="quantity" class="input" min="0" placeholder="1000" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn btn-success w-full justify-center">
                    <i class="fas fa-save"></i> حفظ الرصيد
                </button>
            </div>
        </form>
    </div>


    <!-- Stock List -->
    <div class="card p-5">
        <h3 class="font-extrabold text-slate-900 mb-3">أرصدة شهر {{ $monthName }} {{ $year }}</h3>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>الدواء</th>
                        <th>الوحدة</th>
                        <th>رصيد أول الشهر (الكمية)</th>
                        <th>المنصرف</th>
                        <th>المتبقي</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stock as $item)
                        @php
                            $dispensed = App\Models\DispensedMedicine::where('user_id', auth()->id())
                                ->where('medicine_id', $item->medicine_id)
                                ->whereMonth('dispense_date', $item->stock_date->month)
                                ->whereYear('dispense_date', $item->stock_date->year)
                                ->sum('quantity');
                            $opening = $item->quantity + $dispensed;
                            $remaining = $opening - $dispensed;
                        @endphp
                        <tr>
                            <td class="font-semibold">{{ $item->medicine->name }}</td>
                            <td>{{ $item->medicine->unitType->name }}</td>
                            <td>{{ $opening }}</td>
                            <td>{{ $dispensed }}</td>
                            <td class="font-bold {{ $remaining < 100 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $remaining }}
                            </td>
                            <td>
                                <button onclick="editStock({{ $item->id }}, {{ $item->quantity }})" class="btn btn-ghost">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-500">
                                لا توجد أرصدة مسجلة لهذا الشهر
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Stock Modal -->
    <div id="editStockModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="card p-6 w-full max-w-sm mx-4">
            <h3 class="font-extrabold text-slate-900 mb-4">تعديل الرصيد</h3>
            <form id="editStockForm" method="POST">
                @csrf
                @method('PUT')
                <label class="label">الكمية</label>
                <input type="number" name="quantity" id="editStockQuantity" class="input mb-4" min="0" required>
                <div class="flex gap-2">
                    <button type="button" onclick="closeStockModal()" class="btn btn-ghost flex-1">إلغاء</button>
                    <button type="submit" class="btn btn-success flex-1">حفظ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Smart Search
        const medicineSearch = document.getElementById('medicineSearch');
        const medicineResults = document.getElementById('medicineResults');
        const medicineId = document.getElementById('medicineId');

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
                            medicineResults.innerHTML = `
                                    <div class="smart-search-item text-slate-500">
                                        <i class="fas fa-plus-circle ml-1"></i> "${query}" - اضغط Enter لإضافة دواء جديد
                                    </div>
                                `;
                        } else {
                            data.forEach(med => {
                                const div = document.createElement('div');
                                div.className = 'smart-search-item';
                                div.innerHTML = `
                                        <div class="font-semibold">${med.name}</div>
                                        <div class="text-xs text-slate-500">${med.unit_type?.name || ''} - سعر: ${med.price_hotline}</div>
                                    `;
                                div.onclick = () => selectMedicine(med);
                                medicineResults.appendChild(div);
                            });
                        }
                        medicineResults.classList.add('active');
                    });
            }, 300);
        });

        function selectMedicine(medicine) {
            medicineSearch.value = medicine.name;
            medicineId.value = medicine.id;
            medicineResults.classList.remove('active');
        }

        // Add new medicine on Enter if not found
        medicineSearch.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && medicineResults.querySelector('.text-slate-500')) {
                e.preventDefault();
                window.location.href = '{{ route('medicines.index') }}';
            }
        });

        // Close dropdown on outside click
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.relative')) {
                medicineResults.classList.remove('active');
            }
        });

        function editStock(id, quantity) {
            document.getElementById('editStockQuantity').value = quantity;
            document.getElementById('editStockForm').action = '{{ url('/stock') }}' + '/' + id;
            document.getElementById('editStockModal').classList.remove('hidden');
            document.getElementById('editStockModal').classList.add('flex');
        }

        function closeStockModal() {
            document.getElementById('editStockModal').classList.add('hidden');
            document.getElementById('editStockModal').classList.remove('flex');
        }
    </script>
@endpush