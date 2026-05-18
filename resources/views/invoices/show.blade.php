@extends('layouts.app')

@section('title', 'فاتورة #' . $invoice->id . ' — صيدلية')
@section('page-title', 'فاتورة صرف')
@section('page-subtitle', 'رقم الفاتورة: ' . $invoice->id)

@section('content')
    <div class="mb-5 flex justify-between items-center no-print">
        <div>
            <a href="{{ route('invoices.index') }}" class="btn btn-ghost">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </a>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="btn btn-primary">
                <i class="fas fa-print"></i> فتح للطباعة
            </a>
            <a href="{{ route('invoices.create') }}" class="btn btn-success">
                <i class="fas fa-plus"></i> فاتورة جديدة
            </a>
        </div>
    </div>

    <div class="invoice-premium-wrapper page shadow-lg rounded-xl overflow-hidden mx-auto"
        style="width: 720px; background: #f5f5f0; padding: 30px 35px 40px;">
        <!-- HEADER -->
        <div class="header"
            style="display: flex; justify-content: flex-start; align-items: flex-start; margin-bottom: 10px;">
            <div class="logo-area" style="display: flex; flex-direction: column; align-items: flex-start; gap: 3px;">
                <img src="{{ asset('images/ain-shams-logo.jpg') }}" alt="logo"
                    style="width: 70px; height: 70px; object-fit: contain;">
                <div class="clinic-name" style="font-size: 13px; font-weight: 700; color: #222;">قسم الحسابات</div>
                <div class="clinic-sub" style="font-size: 10px; color: #444;">مرسل الى حقوق مكافحة وعلاج الإدمان</div>
            </div>
        </div>

        <!-- TITLE -->
        <div class="invoice-title"
            style="text-align: center; font-size: 16px; font-weight: 700; margin: 8px 0 4px; color: #111;">
            فاتورة رقم {{ $invoice->referral_number }}
        </div>

        <div class="designer" style="font-size: 9px; color: #888; margin-bottom: 8px; direction: ltr; text-align: left;">
            Designed by Eng Mohamed Khairy
        </div>

        <!-- TABLE -->
        <div class="table-wrapper" style="border: 2.5px solid #000; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; color: #111;">
                <tr class="date-row">
                    <td
                        style="border: 1.5px solid #000; padding: 6px 10px; background: #e8e8e8; font-weight: 600; width: 34%;">
                        الاسم</td>
                    <td colspan="2" style="border: 1.5px solid #000; padding: 6px 10px;">{{ $invoice->patient_name }}</td>
                    <td style="border: 1.5px solid #000; padding: 6px 10px; background: #e8e8e8; font-weight: 600;">تاريخ
                        الصرف</td>
                    <td style="border: 1.5px solid #000; padding: 6px 10px;">{{ $invoice->created_at->format('Y-m-d') }}
                    </td>
                </tr>

                <tr class="col-headers" style="background: #e0e0d8; font-weight: 700;">
                    <th colspan="2"
                        style="border: 1.5px solid #000; padding: 6px 10px; font-size: 12px; text-align: right;">الصنف</th>
                    <th style="border: 1.5px solid #000; padding: 6px 10px; font-size: 12px;">سعر القرص</th>
                    <th style="border: 1.5px solid #000; padding: 6px 10px; font-size: 12px;">المصرف</th>
                    <th style="border: 1.5px solid #000; padding: 6px 10px; font-size: 12px;">القيمة</th>
                </tr>

                @foreach($invoice->items as $item)
                    <tr class="data-row" style="background: #fafaf8;">
                        <td colspan="2" style="border: 1.5px solid #000; padding: 6px 10px;">{{ $item->medicine->name }}</td>
                        <td colspan="1" style="border: 1.5px solid #000; padding: 6px 10px; text-align: center;">ج.م
                            {{ number_format($item->price_at_dispense, 3) }}</td>
                        <td style="border: 1.5px solid #000; padding: 6px 10px; text-align: center;">{{ $item->quantity }}</td>
                        <td style="border: 1.5px solid #000; padding: 6px 10px; font-weight: bold; text-align: center;">
                            {{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="total-row" style="background: #e8e8e0; font-weight: 700; font-size: 13px;">
                    <td colspan="2" class="total-label"
                        style="border: 1.5px solid #000; border-left: 0; padding: 6px 10px; text-align: right; padding-right: 20px;">
                        الإجمالي</td>
                    <td colspan="2"
                        style="border: 1.5px solid #000; border-right: 0; padding: 6px 10px; text-align: center;">ج.م</td>
                    <td style="border: 1.5px solid #000; padding: 6px 10px; text-align: center;">
                        {{ number_format($invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="footer"
            style="display: flex; justify-content: space-between; margin-top: 30px; font-size: 12px; font-weight: 600; color: #222; padding: 0 10px;">
            <span style="text-align: center;">قسم الحسابات</span>
            <span style="text-align: center;">مدير الشئون المالية والإدارية</span>
            <span style="text-align: center;">مدير الصيدلية</span>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');

        .invoice-premium-wrapper * {
            font-family: 'Cairo', Arial, sans-serif;
        }

        @media print {

            .sidebar,
            .topbar,
            .no-print,
            .btn {
                display: none !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
            }

            body {
                background: white !important;
                padding: 0 !important;
            }

            .invoice-premium-wrapper {
                box-shadow: none !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 20px !important;
            }
        }
    </style>
@endpush