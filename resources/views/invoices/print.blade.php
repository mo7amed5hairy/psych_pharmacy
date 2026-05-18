<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <title>فاتورة صرف رقم {{ $invoice->referral_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #d0d0d0;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            font-family: 'Cairo', Arial, sans-serif;
            padding: 30px 0;
        }

        .page {
            background: #f5f5f0;
            width: 720px;
            padding: 30px 35px 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: flex-start;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .logo-area {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 3px;
        }

        .logo-area img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        .clinic-name {
            font-size: 13px;
            font-weight: 700;
            color: #222;
        }

        .clinic-sub {
            font-size: 10px;
            color: #444;
        }

        /* TITLE */
        .invoice-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 8px 0 4px;
            color: #111;
        }

        .designer {
            font-size: 9px;
            color: #888;
            margin-bottom: 8px;
            direction: ltr;
            text-align: left;
        }

        /* TABLE */
        .table-wrapper {
            border: 2.5px solid #000;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            color: #111;
        }

        th,
        td {
            border: 1.5px solid #000 !important;
            padding: 6px 10px;
            text-align: revert;
        }

        .date-row td {
            background: #e8e8e8;
            font-weight: 600;
            width: 34%;
        }

        .col-headers th {
            background: #e0e0d8;
            font-weight: 700;
            font-size: 12px;
            width: 79%;
        }

        .data-row td {
            background: #fafaf8;
        }

        .data-row:nth-child(even) td {
            background: #f2f2ee;
        }

        .total-row td {
            background: #e8e8e0;
            font-weight: 700;
            font-size: 13px;
        }

        .total-row .total-label {
            text-align: right;
            padding-right: 20px;
            font-size: 12px;
            letter-spacing: 1px;
            border-left: 0px !important;
        }

        /* FOOTER */
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            font-size: 12px;
            font-weight: 600;
            color: #222;
            padding: 0 10px;
        }

        .footer span {
            text-align: center;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .page {
                box-shadow: none;
                width: 100%;
            }

            table,
            th,
            td {
                border: 1.5px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .total-row .total-label {
                border-left: 0px !important;
            }
        }
    </style>
</head>

<body>

    <div class="page">
        <!-- HEADER -->
        <div class="header">
            <div class="logo-area">
                <img src="{{ asset('images/ain-shams-logo.jpg') }}" alt="مستشفيات جامعة عين شمس">
                <div class="clinic-name">قسم الحسابات</div>
                <div class="clinic-sub">مرسل الى حقوق مكافحة وعلاج الإدمان</div>
            </div>
        </div>

        <!-- TITLE -->
        <div class="invoice-title">
            فاتورة رقم {{ $invoice->referral_number }}
        </div>

        <div class="designer">
            Designed by Eng Mohamed Khairy
        </div>

        <!-- TABLE -->
        <div class="table-wrapper">
            <table>
                <tr class="date-row">
                    <td>الاسم</td>
                    <td colspan="2">{{ $invoice->patient_name }}</td>
                    <td>تاريخ الصرف</td>
                    <td>{{ $invoice->created_at->format('Y-m-d') }}</td>
                </tr>

                <tr class="col-headers">
                    <th colspan="2">الصنف</th>
                    <th>سعر القرص</th>
                    <th>المصرف</th>
                    <th>القيمة</th>
                </tr>

                @foreach($invoice->items as $item)
                    <tr class="data-row">
                        <td colspan="2">{{ $item->medicine->name }}</td>
                        <td colspan="1">ج.م {{ number_format($item->price_at_dispense, 3) }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="total-row">
                    <td colspan="2" class="total-label">الإجمالي</td>
                    <td colspan="2" style="border-right-width: 0 !important;">ج.م</td>
                    <td>{{ number_format($invoice->total, 2) }}</td>
                </tr>
            </table>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <span class="invoice-title">قسم الحسابات</span>
            <span class="invoice-title">مدير الشئون المالية والإدارية</span>
            <span class="invoice-title">مدير الصيدلية</span>
        </div>
    </div>

    <script>
        window.onload = () => {
            setTimeout(() => window.print(), 400);
        };
    </script>

</body>

</html>