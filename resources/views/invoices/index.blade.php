@extends('layouts.app')

@section('title', 'قائمة الفواتير — صيدلية')
@section('page-title', 'قائمة الفواتير')
@section('page-subtitle', 'عرض وتحميل فواتير الصرف الخاصة بك')

@section('content')
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <h3 class="font-extrabold text-slate-900">قائمة الفواتير</h3>
        </div>

        <div class="table-wrap mb-4">
            <table class="data data-table">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>رقم التحويل</th>
                        <th>اسم المريض</th>
                        <th>عدد الأصناف</th>
                        <th>إجمالى المصروف (كمية)</th>
                        <th>إجمالى المصروف (مبلغ)</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="font-bold text-slate-700">#{{ $invoice->id }}</td>
                            <td><span class="pill pill-blue">{{ $invoice->referral_number }}</span></td>
                            <td>{{ $invoice->patient_name }}</td>
                            <td>{{ $invoice->items->count() }}</td>
                            <td>{{ $invoice->items->sum('quantity') }}</td>
                            <td class="font-bold text-emerald-600">{{ number_format($invoice->total, 2) }}</td>
                            <td class="text-xs text-slate-500">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost py-1 px-3 text-xs">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                    <button type="button" data-invoice="{{ base64_encode(json_encode($invoice)) }}" class="btn btn-ghost py-1 px-3 text-xs btn-edit-invoice">
                                        <i class="fas fa-edit"></i> تعديل
                                    </button>
                                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank"
                                        class="btn btn-primary py-1 px-3 text-xs">
                                        <i class="fas fa-print"></i> طباعة
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editInvoiceModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="card p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-slate-900">تعديل الفاتورة</h3>
                <button type="button" onclick="closeEditModal()" class="btn btn-ghost p-1">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="editError" class="hidden mb-3 p-2 bg-rose-50 border border-rose-200 rounded text-rose-800 text-sm"></div>

            <form id="editInvoiceForm" method="POST" class="no-loader">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="label">اسم المريض</label>
                        <input type="text" name="patient_name" id="editPatientName" class="input">
                    </div>
                    <div>
                        <label class="label">رقم التحويل</label>
                        <input type="text" name="referral_number" id="editReferralNumber" class="input" required>
                    </div>
                    <div>
                        <label class="label">تاريخ الفاتورة</label>
                        <input type="date" name="invoice_date" id="editInvoiceDate" class="input">
                    </div>
                </div>

                <div class="border-t pt-4 mb-4">
                    <div class="flex items-center gap-3 mb-4 flex-wrap">
                        <div class="flex-1 min-w-[200px] relative">
                            <label class="label">إضافة صنف</label>
                            <input type="text" id="editMedicineSearch" class="input" placeholder="🔎 ابحث عن دواء..." autocomplete="off">
                            <div id="editMedicineResults" class="smart-search-results"></div>
                        </div>
                        <div class="w-24">
                            <label class="label">الكمية</label>
                            <input type="number" id="editItemQuantity" class="input" placeholder="الكمية" min="1">
                        </div>
                        <div class="w-32">
                            <label class="label">السعر</label>
                            <input type="text" id="editItemPrice" class="input bg-slate-100" readonly>
                        </div>
                        <button type="button" onclick="addEditItem()" class="btn btn-primary mt-5">
                            <i class="fas fa-plus"></i> إضافة
                        </button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="data text-sm" style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>الصنف</th>
                                    <th>الكمية</th>
                                    <th>السعر</th>
                                    <th>القيمة</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="editItemsList"></tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t pt-4">
                    <div class="text-lg font-bold">
                        الإجمالي: <span id="editTotalAmount" class="text-emerald-600">0.00</span> ج
                    </div>
                    <div class="flex gap-2">
                        <button type="button" onclick="closeEditModal()" class="btn btn-ghost">إلغاء</button>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> حفظ التعديلات</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden base URL for JS -->
    <div id="baseUrl" data-url="{{ url('') }}" style="display:none;"></div>
@endsection

@push('scripts')
    <script>
        let editSelectedMedicine = null;
        let editItems = [];
        let editInvoiceId = null;

        // Medicine search in edit modal
        const editMedicineSearch = document.getElementById('editMedicineSearch');
        const editMedicineResults = document.getElementById('editMedicineResults');
        let editSearchTimeout;

        editMedicineSearch.addEventListener('input', function () {
            clearTimeout(editSearchTimeout);
            const query = this.value;
            if (query.length < 2) {
                editMedicineResults.classList.remove('active');
                return;
            }
            editSearchTimeout = setTimeout(() => {
                fetch(`{{ url('/medicines/search') }}?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        editMedicineResults.innerHTML = '';
                        if (data.length === 0) {
                            editMedicineResults.innerHTML = '<div class="smart-search-item text-rose-600">لا يوجد دواء بهذا الاسم</div>';
                        } else {
                            data.forEach(med => {
                                const div = document.createElement('div');
                                div.className = 'smart-search-item';
                                div.innerHTML = `
                                    <div class="font-semibold">${med.name}</div>
                                    <div class="text-xs text-slate-500">${med.unit_type?.name || ''} - سعر الخط الساخن: ${med.price_hotline} ج</div>
                                `;
                                div.onclick = () => selectEditMedicine(med);
                                editMedicineResults.appendChild(div);
                            });
                        }
                        editMedicineResults.classList.add('active');
                    });
            }, 300);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.relative')) {
                editMedicineResults.classList.remove('active');
            }
        });

        function selectEditMedicine(medicine) {
            editSelectedMedicine = medicine;
            editMedicineSearch.value = medicine.name;
            document.getElementById('editItemPrice').value = medicine.price_hotline;
            editMedicineResults.classList.remove('active');
            document.getElementById('editItemQuantity').focus();
        }

        function addEditItem() {
            if (!editSelectedMedicine) {
                alert('الرجاء اختيار دواء أولاً');
                return;
            }
            const quantity = parseInt(document.getElementById('editItemQuantity').value);
            if (quantity < 1) {
                alert('الكمية يجب أن تكون 1 على الأقل');
                return;
            }
            const price = parseFloat(editSelectedMedicine.price_hotline);
            const subtotal = quantity * price;
            editItems.push({
                medicine_id: editSelectedMedicine.id,
                name: editSelectedMedicine.name,
                quantity: quantity,
                price: price,
                subtotal: subtotal
            });
            renderEditItems();
            editSelectedMedicine = null;
            editMedicineSearch.value = '';
            document.getElementById('editItemQuantity').value = '';
            document.getElementById('editItemPrice').value = '';
            editMedicineSearch.focus();
        }

        function removeEditItem(index) {
            editItems.splice(index, 1);
            renderEditItems();
        }

        function renderEditItems() {
            const tbody = document.getElementById('editItemsList');
            tbody.innerHTML = '';
            let total = 0;
            editItems.forEach((item, index) => {
                total += item.subtotal;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.name}</td>
                    <td><input type="number" class="input py-1 px-2 w-20 text-center" value="${item.quantity}" min="0" onchange="updateEditItemQty(${index}, this.value)"></td>
                    <td>${item.price.toFixed(3)}</td>
                    <td class="font-bold subtotal-${index}">${item.subtotal.toFixed(2)}</td>
                    <td>
                        <button type="button" onclick="removeEditItem(${index})" class="btn btn-danger py-1 px-2 text-xs">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            document.getElementById('editTotalAmount').textContent = total.toFixed(2);
        }

        function updateEditItemQty(index, newQty) {
            const qty = parseInt(newQty);
            if (isNaN(qty) || qty < 0) return;
            editItems[index].quantity = qty;
            editItems[index].subtotal = qty * editItems[index].price;
            renderEditItems();
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-edit-invoice');
            if (!btn) return;
            const invoice = JSON.parse(atob(btn.getAttribute('data-invoice')));
            editInvoiceId = invoice.id;

            const baseUrl = document.getElementById('baseUrl').getAttribute('data-url');
            document.getElementById('editInvoiceForm').action = baseUrl + '/invoices/' + invoice.id + '/update';

            document.getElementById('editPatientName').value = invoice.patient_name;
            document.getElementById('editReferralNumber').value = invoice.referral_number;
            document.getElementById('editInvoiceDate').value = invoice.created_at ? invoice.created_at.substring(0, 10) : '';

            editItems = (invoice.items || []).map(item => ({
                medicine_id: item.medicine_id,
                name: item.medicine ? item.medicine.name : 'دواء#' + item.medicine_id,
                quantity: item.quantity,
                price: parseFloat(item.price_at_dispense),
                subtotal: parseFloat(item.subtotal || (item.quantity * item.price_at_dispense))
            }));

            renderEditItems();

            document.getElementById('editInvoiceModal').classList.remove('hidden');
            document.getElementById('editInvoiceModal').classList.add('flex');
        });

        function closeEditModal() {
            document.getElementById('editInvoiceModal').classList.add('hidden');
            document.getElementById('editInvoiceModal').classList.remove('flex');
            document.getElementById('editError').classList.add('hidden');
        }

        document.getElementById('editInvoiceModal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        document.getElementById('editInvoiceForm').addEventListener('submit', function (e) {
            e.preventDefault();
            if (editItems.length === 0) {
                alert('الرجاء إضافة صنف واحد على الأقل');
                return;
            }
            const form = this;
            const errorDiv = document.getElementById('editError');
            errorDiv.classList.add('hidden');

            const data = {
                patient_name: document.getElementById('editPatientName').value.trim() || '-',
                referral_number: document.getElementById('editReferralNumber').value.trim(),
                invoice_date: document.getElementById('editInvoiceDate').value,
                items: editItems.map(item => ({
                    medicine_id: item.medicine_id,
                    quantity: item.quantity
                }))
            };

            if (!data.referral_number) {
                alert('الرجاء إدخال رقم التحويل');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(res => {
                if (res.redirected) {
                    window.location.href = res.url;
                    return;
                }
                return res.json();
            })
            .then(result => {
                if (result && result.errors) {
                    let msg = '';
                    for (let k in result.errors) msg += '- ' + result.errors[k].join('\n') + '\n';
                    errorDiv.textContent = msg;
                    errorDiv.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات';
                } else if (result && result.message) {
                    closeEditModal();
                    location.reload();
                }
            })
            .catch(err => {
                console.error(err);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> حفظ التعديلات';
            });
        });

        // Enter key navigation in edit modal
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON')) return;
            const modal = document.getElementById('editInvoiceModal');
            if (modal.classList.contains('hidden') || !e.target.closest('#editInvoiceForm')) return;

            if (e.target.tagName === 'INPUT') {
                const results = e.target.parentElement?.querySelector('.smart-search-results');
                if (results?.classList.contains('active') && results.children.length > 0) {
                    results.children[0].click();
                    e.preventDefault();
                    return;
                }
            }

            if (e.target.tagName === 'BUTTON') return;

            e.preventDefault();
            const focusable = Array.from(modal.querySelectorAll('input:not([readonly]), button:not([disabled])'));
            const idx = focusable.indexOf(e.target);
            if (idx > -1 && idx < focusable.length - 1) {
                focusable[idx + 1].focus();
            }
        });
    </script>
@endpush
