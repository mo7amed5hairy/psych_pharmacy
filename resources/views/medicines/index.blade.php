@extends('layouts.app')

@section('title', 'قاموس الأدوية — صيدلية')
@section('page-title', 'قاموس الأدوية')
@section('page-subtitle', 'إدارة الأصناف والأسعار')

@section('content')
    <!-- Add/Edit Form -->
    <div class="card p-5 mb-5">
        <form id="medicineForm" method="POST" action="{{ route('medicines.store') }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" name="medicine_id" id="medicineId">

            <div id="medicineRowsContainer">
                <!-- First Row -->
                <div class="grid grid-cols-1 md:grid-cols-7 gap-3 medicine-row items-end">
                    <div class="md:col-span-2">
                        <label class="label">اسم الصنف</label>
                        <input type="text" name="medicines[0][name]" id="medicineName" class="input"
                            placeholder="مثال: باراسيتامول 500" required>
                    </div>
                    <div>
                        <label class="label">الوحدة</label>
                        <select name="medicines[0][unit_type_id]" id="unitTypeId" class="input" required>
                            @foreach($unitTypes as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">سعر الخط الساخن</label>
                        <input type="number" name="medicines[0][price_hotline]" id="priceHotline" class="input" step="0.01"
                            min="0" placeholder="1.25" required>
                    </div>
                    <div>
                        <label class="label">سعر الارتباط</label>
                        <input type="number" name="medicines[0][price_contract]" id="priceContract" class="input"
                            step="0.01" min="0" placeholder="0.95" required>
                    </div>
                    <div>
                        <label class="label">سعر العيادة</label>
                        <input type="number" name="medicines[0][price_clinic]" id="priceClinic" class="input" step="0.01"
                            min="0" placeholder="1.10" required>
                    </div>
                    <!-- Add Button for first row -->
                    <div class="flex items-center justify-center" id="addBtnContainer">
                        <button type="button" onclick="addMedicineRow()"
                            class="btn btn-primary w-full h-10 flex items-center justify-center" title="إضافة صنف آخر">
                            <i class="fas fa-plus text-lg"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex gap-2 justify-end">
                <button type="button" onclick="resetForm()" class="btn btn-ghost">مسح</button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> حفظ الأصناف
                </button>
            </div>
        </form>
    </div>

    <!-- Medicines List -->
    <div class="card p-5">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h3 class="font-extrabold text-slate-900">قاموس الأدوية</h3>
        </div>
        <div class="table-wrap">
            <table class="data data-table" id="medicinesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الصنف</th>
                        <th>الوحدة</th>
                        <th>خط ساخن</th>
                        <th>ارتباط</th>
                        <th>عيادة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($medicines as $index => $medicine)
                        <tr data-id="{{ $medicine->id }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="font-semibold">{{ $medicine->name }}</td>
                            <td>{{ $medicine->unitType->name }}</td>
                            <td>{{ number_format($medicine->price_hotline, 2) }}</td>
                            <td>{{ number_format($medicine->price_contract, 2) }}</td>
                            <td>{{ number_format($medicine->price_clinic, 2) }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <button type="button"
                                        onclick="editMedicine({{ $medicine->id }}, '{{ $medicine->name }}', {{ $medicine->unit_type_id }}, {{ $medicine->price_hotline }}, {{ $medicine->price_contract }}, {{ $medicine->price_clinic }})"
                                        class="btn btn-ghost">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="{{ route('medicines.destroy.post', $medicine) }}"
                                        class="inline delete-form">
                                        @csrf
                                        <button type="button" onclick="confirmDelete(this)" class="btn btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- DataTable will handle pagination -->
    </div>
@endsection

@push('scripts')
    <script>
        let medicineRowCount = 1;

        function editMedicine(id, name, unitTypeId, hotline, contract, clinic) {
            // Scroll to form
            document.getElementById('medicineForm').scrollIntoView({ behavior: 'smooth' });

            document.getElementById('medicineId').value = id;
            document.getElementById('medicineName').value = name;
            document.getElementById('unitTypeId').value = unitTypeId;
            document.getElementById('priceHotline').value = hotline;
            document.getElementById('priceContract').value = contract;
            document.getElementById('priceClinic').value = clinic;

            // Change names to single item (not array) for edit mode
            document.getElementById('medicineName').name = 'name';
            document.getElementById('unitTypeId').name = 'unit_type_id';
            document.getElementById('priceHotline').name = 'price_hotline';
            document.getElementById('priceContract').name = 'price_contract';
            document.getElementById('priceClinic').name = 'price_clinic';

            // Use POST route for update
            document.getElementById('medicineForm').action = `{{ url('/medicines') }}/${id}/update`;

            // Change button text
            document.querySelector('#medicineForm button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> تحديث الصنف';

            // Show loader on submit
            document.querySelector('#medicineForm button[type="submit"]').setAttribute('onclick', 'showLoader()');

            // Hide add button in edit mode
            document.getElementById('addBtnContainer').style.display = 'none';

            // Remove all additional rows
            const additionalRows = document.querySelectorAll('.medicine-row-additional');
            additionalRows.forEach(row => row.remove());
            medicineRowCount = 0;

            document.getElementById('medicineName').focus();
        }

        function resetForm() {
            document.getElementById('medicineId').value = '';
            document.getElementById('medicineName').value = '';
            document.getElementById('priceHotline').value = '';
            document.getElementById('priceContract').value = '';
            document.getElementById('priceClinic').value = '';

            // Reset names to medicines[0] for first row
            document.getElementById('medicineName').name = 'medicines[0][name]';
            document.getElementById('unitTypeId').name = 'medicines[0][unit_type_id]';
            document.getElementById('priceHotline').name = 'medicines[0][price_hotline]';
            document.getElementById('priceContract').name = 'medicines[0][price_contract]';
            document.getElementById('priceClinic').name = 'medicines[0][price_clinic]';

            document.getElementById('formMethod').value = 'POST';
            document.getElementById('medicineForm').action = '{{ route('medicines.store') }}';

            // Reset button text
            document.querySelector('#medicineForm button[type="submit"]').innerHTML = '<i class="fas fa-save"></i> حفظ الأصناف';

            // Show add button
            document.getElementById('addBtnContainer').style.display = 'flex';

            // Remove all additional rows
            const additionalRows = document.querySelectorAll('.medicine-row-additional');
            additionalRows.forEach(row => row.remove());
            medicineRowCount = 0; // Start from 0
        }

        function addMedicineRow() {
            medicineRowCount++;
            const container = document.getElementById('medicineRowsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'medicine-row-additional grid grid-cols-1 md:grid-cols-7 gap-3 mt-3 pt-3  border-slate-200 items-end';
            newRow.innerHTML = `
                <div class="md:col-span-2">
                    <input type="text" name="medicines[${medicineRowCount}][name]" class="input" placeholder="مثال: باراسيتامول 500" required>
                </div>
                <div>
                    <label class="label"></label>
                    <select name="medicines[${medicineRowCount}][unit_type_id]" class="input" required>
                        @foreach($unitTypes as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">  </label>
                    <input type="number" name="medicines[${medicineRowCount}][price_hotline]" class="input" step="0.01" min="0" placeholder="1.25" required>
                </div>
                <div>
                    <label class="label"> </label>
                    <input type="number" name="medicines[${medicineRowCount}][price_contract]" class="input" step="0.01" min="0" placeholder="0.95" required>
                </div>
                <div>
                    <label class="label"> </label>
                    <input type="number" name="medicines[${medicineRowCount}][price_clinic]" class="input" step="0.01" min="0" placeholder="1.10" required>
                </div>
                <div class="flex items-center justify-center">
                    <button type="button" onclick="removeMedicineRow(this)" class="btn btn-danger w-full h-10 flex items-center justify-center" title="حذف هذا الصف">
                        <i class="fas fa-trash text-lg"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }

        function removeMedicineRow(btn) {
            btn.closest('.medicine-row-additional').remove();
            // Reindex remaining rows
            const rows = document.querySelectorAll('.medicine-row-additional');
            rows.forEach((row, index) => {
                const inputs = row.querySelectorAll('input, select');
                const rowNum = index + 1; // Start from 1 because 0 is the first row
                inputs.forEach(input => {
                    const name = input.getAttribute('name');
                    if (name) {
                        const newName = name.replace(/medicines\[\d+\]/, `medicines[${rowNum}]`);
                        input.setAttribute('name', newName);
                    }
                });
                const label = row.querySelector('.label');
                if (label) {
                    label.textContent = label.textContent.replace(/\d+/, rowNum + 1);
                }
            });
            medicineRowCount = rows.length;
        }

        function confirmDelete(btn) {
            if (confirm('هل أنت متأكد من حذف هذا الدواء؟')) {
                showLoader();
                btn.closest('form').submit();
            }
        }

        $(document).ready(function () {
            $('.data-table').DataTable();
        });

    </script>
@endpush