@extends('layouts.app')

@section('title', 'الأدوية المنصرفة — صيدلية')
@section('page-title', 'الأدوية المنصرفة')
@section('page-subtitle', 'تسجيل وعرض الأدوية التي تم صرفها')

@section('content')
    <!-- Add Form -->
    <div class="card p-5 mb-5">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-extrabold text-slate-900">تسجيل صرف أدوية</h3>

            <button type="button" onclick="addDispensedRow()" class="btn btn-primary">
                <i class="fas fa-plus"></i> إضافة صف
            </button>
        </div>

        <form method="POST" action="{{ route('dispensed-medicines.store') }}" id="dispensedForm" class="no-loader">
            @csrf

            <div id="dispensedItemsContainer">

                <!-- First Row -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-2 dispensed-row">

                    <div class="col-span-2">
                        <label class="label">الرقم (يدوي)</label>
                        <input type="text" name="medicines[0][referral_number]" class="input referral-input"
                            placeholder="TR-1046">
                    </div>

                    <div class="col-span-2">
                        <label class="label">التاريخ</label>
                        <input type="date" name="medicines[0][dispense_date]" class="input" value="{{ date('Y-m-d') }}"
                            required>
                    </div>

                    <div class="col-span-5">
                        <label class="label">الصنف</label>
                        <div class="relative">
                            <input type="text" class="input medicine-search" placeholder="🔎 ابحث عن الدواء..."
                                autocomplete="off">
                            <input type="hidden" name="medicines[0][medicine_id]" class="medicine-id">
                            <div class="smart-search-results"></div>
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label class="label">الكمية</label>
                        <input type="number" name="medicines[0][quantity]" class="input" min="1" required>
                    </div>
                </div>

            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> حفظ الصرفية
                </button>
            </div>
        </form>
    </div>





    <!-- Dispensed Medicines List -->
    <div class="card p-5">

        <h3 class="font-extrabold text-slate-900 mb-4">
            سجل الأدوية المنصرفة
        </h3>

        <div class="table-wrap">
            <table class="data data-table">

                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الرقم</th>
                        <th>الدواء</th>
                        <th>الوحدة</th>
                        <th>الكمية</th>
                        <th>إجراء</th>
                        <th>تعديل</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($dispensedMedicines as $dispensed)
                        <tr>
                            <td>
                                {{ $dispensed->dispense_date->format('Y-m-d') }}
                            </td>
                            <td>
                                {{ $dispensed->referral_number ?? '-' }}
                            </td>
                            <td class="font-semibold">
                                {{ $dispensed->medicine->name }}
                            </td>
                            <td>
                                {{ $dispensed->medicine->unitType->name }}
                            </td>
                            <td>
                                {{ $dispensed->quantity }}
                            </td>
                            <td>
                                <form method="POST" action="{{ route('dispensed-medicines.destroy', $dispensed) }}"
                                    class="inline"
                                    onsubmit="event.preventDefault(); if(confirm('هل أنت متأكد من حذف هذا السجل؟')) { showLoader(); this.submit(); }">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger px-2 py-1 text-xs">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-warning px-2 py-1 text-xs" onclick="editDispensed(this)"
                                    data-id="{{ $dispensed->id }}" data-medicine_id="{{ $dispensed->medicine_id }}"
                                    data-medicine_name="{{ $dispensed->medicine->name }}"
                                    data-quantity="{{ $dispensed->quantity }}" data-date="{{ $dispensed->dispense_date }}"
                                    data-referral="{{ $dispensed->referral_number }}"
                                    data-disdate="{{ $dispensed->dispense_date  }}">
                                    <i class="fas fa-edit"></i>
                                </button>
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

        let editMode = false;
        let editId = null;
        let dispensedRowCount = 1;

        // ================= AJAX SUBMIT =================
        document.getElementById('dispensedForm').addEventListener('submit', async function (e) {

            e.preventDefault();

            const medicineIds = document.querySelectorAll('.medicine-id');

            for (let med of medicineIds) {
                if (!med.value) {
                    showToast('يجب اختيار الدواء من القائمة', 'error');
                    return;
                }
            }

            // Check all referral numbers for duplicates
            const refInputs = document.querySelectorAll('.referral-input');
            const refsToCheck = [];
            for (let inp of refInputs) {
                const val = inp.value.trim();
                if (val) refsToCheck.push(val);
            }

            if (refsToCheck.length > 0) {
                for (let ref of refsToCheck) {
                    try {
                        const resp = await fetch('{{ url('/check-referral-number') }}?number=' + encodeURIComponent(ref));
                        const data = await resp.json();
                        if (data.exists) {
                            const msg = data.next_available
                                ? `⚠ الرقم "${ref}" مستخدم من قبل — الرقم التالي المتاح: ${data.next_available}`
                                : `⚠ الرقم "${ref}" مستخدم من قبل`;
                            showToast(msg, 'error');
                            return;
                        }
                    } catch (e) {
                        // ignore network error, proceed anyway
                    }
                }
            }

            const formData = new FormData(this);

            const editUrl = '{{ url('/dispensed-medicines') }}';
            const url = editMode
                ? `${editUrl}/${editId}/update`
                : this.action;

            if (typeof showLoader === 'function') showLoader();

            fetch(url, {
                method: "POST",
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
                .then(async res => {

                    const text = await res.text();

                    let data;

                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error(text);
                        throw { message: 'Server returned invalid response (not JSON)' };
                    }

                    if (!res.ok) {
                        throw data;
                    }

                    return data;
                })
                .then(data => {

                    if (typeof hideLoader === 'function') hideLoader();

                    showToast(data.message, 'success');

                    // reset edit mode
                    editMode = false;
                    editId = null;

                    document.querySelector('#dispensedForm button[type="submit"]').innerHTML =
                        '<i class="fas fa-save"></i> حفظ الصرفية';

                    // reset form
                    this.reset();

                    document.getElementById('dispensedItemsContainer').innerHTML = '';
                    dispensedRowCount = 0;
                    addDispensedRow();


                    setTimeout(() => location.reload(), 2000);
                })
                .catch(error => {

                    if (typeof hideLoader === 'function') hideLoader();

                    showToast(error.message || 'حدث خطأ غير متوقع', 'error');
                });

        });


        // ================= TOAST =================
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');

            toast.className =
                `fixed top-5 right-5 px-4 py-3 rounded shadow-lg text-white z-50
                        ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;

            toast.innerText = message;

            document.body.appendChild(toast);

            setTimeout(() => toast.remove(), 3000);
        }


        // ================= SEARCH =================
        function setupMedicineSearch(inputElement, resultsElement, hiddenIdElement) {

            let searchTimeout;

            inputElement.addEventListener('input', function () {

                clearTimeout(searchTimeout);

                const query = this.value;

                hiddenIdElement.value = '';

                if (query.length < 2) {
                    resultsElement.classList.remove('active');
                    return;
                }

                searchTimeout = setTimeout(() => {

                    fetch('{{ url('/medicines/search') }}?q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(data => {

                            resultsElement.innerHTML = '';

                            if (!data.length) {
                                resultsElement.innerHTML =
                                    '<div class="smart-search-item text-rose-600">لا يوجد دواء</div>';
                            } else {

                                data.forEach(med => {

                                    const div = document.createElement('div');
                                    div.className = 'smart-search-item';

                                    div.innerHTML = `
                                                <div class="font-semibold">${med.name}</div>
                                                <div class="text-xs text-slate-500">
                                                    ${med.unit_type?.name || ''} - سعر: ${med.price_hotline}
                                                </div>
                                            `;

                                    div.onclick = () => selectMedicine(
                                        med,
                                        inputElement,
                                        hiddenIdElement,
                                        resultsElement
                                    );

                                    resultsElement.appendChild(div);
                                });
                            }

                            resultsElement.classList.add('active');
                        });

                }, 300);
            });
        }


        // ================= SELECT MEDICINE =================
        function selectMedicine(medicine, input, hidden, results) {

            hidden.value = medicine.id;
            input.value = medicine.name;
            results.classList.remove('active');
        }


        // ================= EDIT =================
        function editDispensed(btn) {

            editMode = true;
            editId = btn.dataset.id;

            document.getElementById('dispensedItemsContainer').innerHTML = '';
            dispensedRowCount = 0;

            const row = addDispensedRow();

            // IMPORTANT: تأخير بسيط عشان DOM يتبني
            setTimeout(() => {

                const el = document.querySelector('.dispensed-row');

                el.querySelector('input[name*="referral_number"]').value = btn.dataset.referral || '';
                el.querySelector('input[name*="quantity"]').value = btn.dataset.quantity || '';

                // 🔥 FIX DATE BUG (ده السبب بتاع مش شغال عندك)
                const date = btn.dataset.disdate || btn.dataset.date;
                el.querySelector('input[name*="dispense_date"]').value =
                    (date && date.length >= 10) ? date.substring(0, 10) : '';

                const input = el.querySelector('.medicine-search');
                const hidden = el.querySelector('.medicine-id');

                input.value = btn.dataset.medicine_name;
                hidden.value = btn.dataset.medicine_id;

                document.querySelector('#dispensedForm button[type="submit"]').innerHTML =
                    '<i class="fas fa-edit"></i> تعديل الصرفية';

            }, 50);
        }


        // ================= ADD ROW =================
        function setupReferralValidation(input) {
            let checkTimeout;
            input.addEventListener('blur', function () {
                clearTimeout(checkTimeout);
                const val = this.value.trim();
                if (!val) return;
                checkTimeout = setTimeout(() => {
                    fetch('{{ url('/check-referral-number') }}?number=' + encodeURIComponent(val))
                        .then(r => r.json())
                        .then(d => {
                            if (d.exists) {
                                const msg = d.next_available
                                    ? `⚠ الرقم "${val}" مستخدم من قبل — الرقم التالي المتاح: ${d.next_available}`
                                    : `⚠ الرقم "${val}" مستخدم من قبل`;
                                showToast(msg, 'error');
                                input.dataset.duplicate = 'true';
                            } else {
                                input.dataset.duplicate = '';
                            }
                        });
                }, 400);
            });
        }

        function addDispensedRow() {

            const index = dispensedRowCount++;

            const container = document.getElementById('dispensedItemsContainer');

            const row = document.createElement('div');

            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-4 mb-2 dispensed-row';

            row.innerHTML = `

                        <div class="col-span-2">
                            <input type="text" name="medicines[${index}][referral_number]" class="input referral-input">
                        </div>

                        <div class="col-span-2">
                            <input type="date" name="medicines[${index}][dispense_date]" class="input" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-span-5">
                            <div class="relative">
                                <input type="text" class="input medicine-search" placeholder="🔎 ابحث عن الدواء..." autocomplete="off">
                                <input type="hidden" name="medicines[${index}][medicine_id]" class="medicine-id">
                                <div class="smart-search-results"></div>
                            </div>
                        </div>

                        <div class="col-span-2">
                            <input type="number" name="medicines[${index}][quantity]" class="input" min="1" required>
                        </div>

                        <div class="col-span-1">
                            <button type="button" onclick="removeDispensedRow(this)" class="btn btn-danger mt-2">
                                حذف
                            </button>
                        </div>
                    `;

            container.appendChild(row);

            setupMedicineSearch(
                row.querySelector('.medicine-search'),
                row.querySelector('.smart-search-results'),
                row.querySelector('.medicine-id')
            );

            const refInput = row.querySelector('.referral-input');
            if (refInput) setupReferralValidation(refInput);

            return row;
        }


        // ================= REMOVE =================
        function removeDispensedRow(button) {
            button.closest('.dispensed-row').remove();
        }


        // ================= INIT =================
        document.addEventListener('DOMContentLoaded', function () {

            const input = document.querySelector('.medicine-search');
            const results = document.querySelector('.smart-search-results');
            const hidden = document.querySelector('.medicine-id');

            if (input) {
                setupMedicineSearch(input, results, hidden);
            }

            document.querySelectorAll('.referral-input').forEach(el => {
                if (!el.dataset.validationSetup) {
                    setupReferralValidation(el);
                    el.dataset.validationSetup = 'true';
                }
            });
        });


        // ================= CLOSE DROPDOWNS =================
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.relative')) {
                document.querySelectorAll('.smart-search-results')
                    .forEach(r => r.classList.remove('active'));
            }
        });

        $(document).ready(function () {
            $('.data-table').DataTable();
        });

    </script>

    </script>
@endpush