@extends('layouts.app')

@section('title', 'قائمة الفواتير — صيدلية')
@section('page-title', 'قائمة الفواتير')
@section('page-subtitle', 'عرض وتحميل فواتير الصرف الخاصة بك')

@section('content')
    <div class="card p-5">
        <div class="table-wrap mb-4">
            <table class="data">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>رقم التحويل</th>
                        <th>اسم المريض</th>
                        <th>عدد الأصناف</th>
                        <th>التاريخ</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="font-bold text-slate-700">#{{ $invoice->id }}</td>
                            <td><span class="pill pill-blue">{{ $invoice->referral_number }}</span></td>
                            <td>{{ $invoice->patient_name }}</td>
                            <td>{{ $invoice->items->count() }}</td>
                            <td class="text-xs text-slate-500">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost py-1 px-3 text-xs">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank"
                                        class="btn btn-primary py-1 px-3 text-xs">
                                        <i class="fas fa-print"></i> طباعة
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                <i class="fas fa-file-invoice text-3xl mb-2 opacity-20"></i>
                                <div>لا توجد فواتير مسجلة حالياً</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $invoices->links() }}
        </div>
    </div>
@endsection