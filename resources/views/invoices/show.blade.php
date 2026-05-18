@extends('layouts.app')

@section('title', 'فاتورة #' . $invoice->id . ' — صيدلية')
@section('page-title', 'فاتورة صرف')
@section('page-subtitle', 'رقم الفاتورة: ' . $invoice->id)

@section('content')
<div class="card p-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 pb-6 border-b border-slate-200">
        <div>
            <h2 class="text-2xl font-extrabold text-slate-900">🧾 فاتورة صرف أدوية</h2>
            <p class="text-slate-500 text-sm mt-1">قسم الحسابات - مرسل الى حقوق مكافحة وعلاج الإدمان</p>
        </div>
        <div class="text-left">
            <div class="text-lg font-bold text-slate-900">#{{ $invoice->id }}</div>
            <div class="text-sm text-slate-500">{{ $invoice->created_at->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <!-- Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-slate-50 p-4 rounded-xl">
        <div>
            <span class="text-sm text-slate-500 block">اسم المريض</span>
            <span class="font-semibold text-slate-900">{{ $invoice->patient_name }}</span>
        </div>
        <div>
            <span class="text-sm text-slate-500 block">رقم التحويل</span>
            <span class="font-semibold text-slate-900">{{ $invoice->referral_number }}</span>
        </div>
        <div>
            <span class="text-sm text-slate-500 block">تم الصرف بواسطة</span>
            <span class="font-semibold text-slate-900">{{ $invoice->user->name }} ({{ $invoice->user->employee_code }})</span>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-wrap mb-6">
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الدواء</th>
                    <th>الوحدة</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="font-semibold">{{ $item->medicine->name }}</td>
                    <td>{{ $item->medicine->unitType->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price_at_dispense, 2) }} ج</td>
                    <td class="font-bold">{{ number_format($item->subtotal, 2) }} ج</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Total -->
    <div class="flex justify-between items-center pt-4 border-t border-slate-200">
        <div class="text-sm text-slate-500">
            عدد الأصناف: {{ $invoice->items->count() }}
        </div>
        <div class="text-2xl font-extrabold text-emerald-600">
            الإجمالي: {{ number_format($invoice->total, 2) }} ج
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-2 mt-6">
        <button onclick="printInvoice()" class="btn btn-primary">
            <i class="fas fa-print"></i> طباعة
        </button>
        <a href="{{ route('invoices.create') }}" class="btn btn-success">
            <i class="fas fa-plus"></i> فاتورة جديدة
        </a>
        <a href="{{ route('dashboard') }}" class="btn btn-ghost">
            <i class="fas fa-arrow-right"></i> العودة
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
function printInvoice() {
    window.print();
}
</script>
@endpush

@push('styles')
<style>
@media print {
    .sidebar, .topbar, .btn, form { display: none !important; }
    .card { box-shadow: none; border: 1px solid #e2e8f0; }
    body { background: white; }
}
</style>
@endpush
