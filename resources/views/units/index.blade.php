@extends('layouts.app')

@section('title', 'أنواع الوحدات — صيدلية')
@section('page-title', 'أنواع الوحدات')
@section('page-subtitle', 'إضافة / تعديل / حذف')

@section('content')
    <!-- Hidden element to store base URL -->
    <div id="baseUrl" data-url="{{ url('') }}" style="display:none;"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Add Unit Form -->
        <div class="card p-5">
            <h3 class="font-extrabold text-slate-900 mb-3">إضافة نوع وحدة</h3>
            <form method="POST" action="{{ route('units.store') }}">
                @csrf
                <label class="label">اسم الوحدة</label>
                <input type="text" name="name" class="input mb-3" placeholder="مثال: قرص" required>
                <button type="submit" class="btn btn-success w-full justify-center">
                    <i class="fas fa-plus"></i> إضافة
                </button>
            </form>
        </div>

        <!-- Units List -->
        <div class="card p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h3 class="font-extrabold text-slate-900">الوحدات المسجلة</h3>
            </div>

            <div class="table-wrap">
                <table class="data data-table">
                    <thead>
                        <tr>
                            <th>اسم الوحدة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unitTypes as $unit)
                            <tr>
                                <td class="font-bold text-slate-800">{{ $unit->name }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        <button onclick="editUnit({{ $unit->id }}, '{{ $unit->name }}')"
                                            class="btn btn-ghost px-2 py-1 text-xs">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="{{ route('units.destroy.post', $unit) }}" class="inline"
                                            onsubmit="event.preventDefault(); if(confirm('هل أنت متأكد من حذف هذه الوحدة؟')) { showLoader(); this.submit(); }">
                                            @csrf
                                            <button type="submit" class="btn btn-danger px-2 py-1 text-xs">
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
        </div>


    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="card p-6 w-full max-w-sm mx-4">
            <h3 class="font-extrabold text-slate-900 mb-4">تعديل الوحدة</h3>
            <div id="editError" class="hidden mb-3 p-2 bg-rose-50 border border-rose-200 rounded text-rose-800 text-sm">
            </div>
            <form id="editForm" method="POST" class="no-loader">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <label class="label">اسم الوحدة</label>
                <input type="text" name="name" id="editUnitName" class="input mb-4" required>
                <div class="flex gap-2">
                    <button type="button" onclick="closeModal()" class="btn btn-ghost flex-1">إلغاء</button>
                    <button type="submit" class="btn btn-success flex-1">حفظ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function editUnit(id, name) {
            const baseUrl = document.getElementById('baseUrl').getAttribute('data-url');
            document.getElementById('editUnitName').value = name;
            document.getElementById('editForm').action = baseUrl + '/units/' + id + '/update';
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
            console.log('Edit URL:', document.getElementById('editForm').action);
        }

        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        // Close modal on outside click
        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
        $(document).ready(function () {
            // Moved to app.js
        });
    </script>
@endpush