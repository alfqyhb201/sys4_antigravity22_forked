<div dir="rtl" class="receipt-view-container">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap');

        .receipt-view-container {
            --primary-color: #50277c; /* البنفسجي الخاص بالهوية */
            --secondary-color: #dc7530; /* البرتقالي الخاص بالهوية */
            --bg-light: #fbfafc;
            --text-dark: #2d2635;
            --text-muted: #72687d;
            --border-color: #e6e0eb;
            background-color: #ffffff;
            max-width: 820px;
            margin: 0 auto;
            border-radius: 8px;
            overflow: hidden;
            color: var(--text-dark);
            line-height: 1.6;
            font-family: 'Cairo', sans-serif;
            box-shadow: 0 4px 20px rgba(80, 39, 124, 0.06);
        }

        .receipt-view-container * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
        }

        /* ترويسة السند المرفقة */
        .receipt-view-container .receipt-header-img {
            width: 100%;
            padding: 20px 50px;
            display: block;
            border-bottom: 3px solid var(--secondary-color);
        }

        /* جسم السند الداخلي بالتتابع الرسمي */
        .receipt-view-container .receipt-body {
            padding: 40px;
        }

        /* --- جدول تفاصيل السند العلوي --- */
        .receipt-view-container .top-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }

        /* تنسيق خلايا الجدول العلوي */
        .receipt-view-container .top-meta-table td {
            padding: 14px 20px;
            vertical-align: middle;
        }

        /* خلايا الجانب الأيمن */
        .receipt-view-container .meta-td-right {
            text-align: right;
            width: 35%;
            border-left: 1px solid var(--border-color);
        }

        /* الخلية الوسطى */
        .receipt-view-container .meta-td-center {
            text-align: center;
            width: 30%;
        }

        .receipt-view-container .receipt-main-title {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 800;
            border-bottom: 3px solid var(--secondary-color);
            display: inline-block;
            padding-bottom: 2px;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }

        /* خلايا الجانب الأيسر */
        .receipt-view-container .meta-td-left {
            text-align: right;
            width: 35%;
            border-right: 1px solid var(--border-color);
        }

        .receipt-view-container .meta-field-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
        }

        .receipt-view-container .meta-field-value {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 13px;
        }

        /* --- قسم تفاصيل السند النصي (الوصف والبيان المالي) --- */
        .receipt-view-container .receipt-statement-box {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-right: 4px solid var(--primary-color);
            border-radius: 6px;
            padding: 24px;
            margin-bottom: 30px;
            font-size: 15px;
            line-height: 1.8;
        }

        .receipt-view-container .statement-row {
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
        }

        .receipt-view-container .statement-row:last-child {
            margin-bottom: 0;
        }

        .receipt-view-container .statement-label {
            color: var(--text-muted);
            font-weight: 700;
            width: 140px;
            flex-shrink: 0;
        }

        .receipt-view-container .statement-value {
            color: var(--text-dark);
            font-weight: 800;
        }

        .receipt-view-container .statement-value.amount-value {
            color: var(--secondary-color);
            font-size: 18px;
        }

        .receipt-view-container .statement-value.client-value {
            color: var(--primary-color);
            font-size: 16px;
        }

        /* --- جدول تفاصيل الدفع السفلي --- */
        .receipt-view-container .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .receipt-view-container .details-table th {
            background-color: var(--primary-color);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            padding: 12px 15px;
            text-align: right;
        }

        .receipt-view-container .details-table th:first-child {
            border-top-right-radius: 6px;
        }

        .receipt-view-container .details-table th:last-child {
            border-top-left-radius: 6px;
        }

        .receipt-view-container .details-table td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-dark);
        }

        .receipt-view-container .details-table tbody tr:nth-child(even) {
            background-color: #faf9fc;
        }

        /* --- قسم التواقيع والاعتماد للجهات الرسمية --- */
        .receipt-view-container .signatures-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }

        .receipt-view-container .signature-col {
            text-align: center;
            width: 40%;
        }

        .receipt-view-container .signature-placeholder {
            width: 140px;
            height: 1px;
            background-color: var(--text-muted);
            margin: 15px auto 5px;
        }

        .receipt-view-container .signature-text {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* الشريط السفلي المكمل للهوية */
        .receipt-view-container .receipt-bottom-accent {
            height: 8px;
            background: linear-gradient(90deg, var(--primary-color) 70%, var(--secondary-color) 30%);
            width: 100%;
        }

        /* زر الطباعة */
        .receipt-view-container .print-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto 30px;
            padding: 10px 28px;
            background: linear-gradient(135deg, var(--primary-color), #7c3db5);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.2s;
            box-shadow: 0 3px 10px rgba(80, 39, 124, 0.3);
        }

        .receipt-view-container .print-btn:hover {
            opacity: 0.88;
        }

        /* تهيئة القالب للطباعة الاحترافية */
        @media print {
            .receipt-view-container {
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
            .receipt-view-container .receipt-body {
                padding: 30px !important;
            }
            .receipt-view-container .print-btn {
                display: none !important;
            }
            .receipt-view-container * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <!-- الترويسة المرفقة -->
    <img class="receipt-header-img" src="{{ asset('images/11.jpg') }}" alt="True Media Advertising Header">

    <!-- جسم السند الداخلي -->
    <div class="receipt-body">
        @php
            $currencyName = $record->invoice?->contract?->currency?->currency_name ?? $record->client->currency?->currency_name ?? 'ريال يمني';
            $currencyCode = $record->invoice?->contract?->currency?->currency ?? $record->client->currency?->currency ?? 'YER';
            $decimals = $currencyCode === 'USD' ? 2 : 0;

            $currencySymbol = match ($currencyCode) {
                'USD' => '$',
                'YER' => 'ر.ي',
                default => $currencyCode,
            };
            $isUSD = $currencyCode === 'USD';

            $paymentMethod = \App\Filament\Enums\PaymentGateway::tryFrom($record->payment_method)?->getLabel() ?? $record->payment_method;
        @endphp

        <!-- جدول تفاصيل السند العلوي -->
        <table class="top-meta-table">
            <tr>
                <!-- اليمين: رقم السند -->
                <td class="meta-td-right">
                    <span class="meta-field-label">رقم السند:</span>
                    <span class="meta-field-value">#{{ $record->id }}</span>
                </td>

                <!-- الوسط: ممتد وعريض عمودياً -->
                <td class="meta-td-center">
                    <h1 class="receipt-main-title">سند قبض مالي</h1>
                </td>

                <!-- اليسار: التاريخ -->
                <td class="meta-td-left">
                    <span class="meta-field-label">التاريخ:</span>
                    <span class="meta-field-value">{{ $record->receipt_date->format('Y/m/d') }}</span>
                </td>
            </tr>
        </table>

        <!-- بيان السند النصي المتناسق -->
        <div class="receipt-statement-box">
            <div class="statement-row">
                <span class="statement-label">استلمنا من السادة:</span>
                <span class="statement-value client-value">{{ $record->client->company }} المحترمين</span>
            </div>
            <div class="statement-row">
                <span class="statement-label">مبلغاً وقدره:</span>
                <span class="statement-value amount-value">
                    {!! $isUSD ? '$' : '' !!}{{ number_format($record->amount, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                </span>
            </div>
            <div class="statement-row">
                <span class="statement-label">فقط لا غير:</span>
                <span class="statement-value" style="font-style: italic; font-size: 14px;">
                    {{ number_format($record->amount, $decimals) }} {{ $currencyName }} لا غير.
                </span>
            </div>
            <div class="statement-row">
                <span class="statement-label">وذلك مقابل:</span>
                <span class="statement-value">
                    @if($record->invoice)
                        سداد جزء/كامل الفاتورة رقم (#{{ $record->invoice->invoice_number }})
                    @else
                        إيداع في المحفظة المالية للعميل
                    @endif
                    @if($record->notes)
                         - {{ $record->notes }}
                    @endif
                </span>
            </div>
        </div>

        <!-- جدول تفاصيل الدفع الملحق -->
        <table class="details-table">
            <thead>
                <tr>
                    <th style="width: 25%;">طريقة الدفع</th>
                    <th style="width: 25%;">رقم المرجع / الحوالة</th>
                    <th style="width: 25%;">الفاتورة المرتبطة</th>
                    <th style="width: 25%;">الموظف المسؤول</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>{{ $paymentMethod }}</strong></td>
                    <td>{{ $record->reference_number ?? 'لا يوجد' }}</td>
                    <td>{{ $record->invoice?->invoice_number ? '#' . $record->invoice->invoice_number : 'إيداع محفظة' }}</td>
                    <td>{{ $record->createdBy?->name ?? 'غير محدد' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- قسم التواقيع والاعتماد للجهات الرسمية -->
        <div class="signatures-section">
            <div class="signature-col">
                <div class="signature-placeholder"></div>
                <span class="signature-text">توقيع المستلم (أمين الصندوق)</span>
            </div>
            <div class="signature-col">
                <div class="signature-placeholder"></div>
                <span class="signature-text">الختم والاعتماد (ترو ميديا)</span>
            </div>
        </div>

        <!-- زر الطباعة -->
        <button class="print-btn" onclick="
            var content = document.querySelector('.receipt-view-container').outerHTML;
            var win = window.open('', '_blank', 'width=900,height=700');
            win.document.write('<!DOCTYPE html><html lang=\'ar\' dir=\'rtl\'><head><meta charset=\'UTF-8\'><title>سند قبض</title><link rel=\'stylesheet\' href=\'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap\'><style>body{margin:0;padding:20px;background:#fff;font-family:Cairo,sans-serif;}.print-btn{display:none!important;}</style></head><body>' + content + '<script>window.onload=function(){window.print();window.close();}<\/script></body></html>');
            win.document.close();
        ">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            طباعة سند القبض
        </button>

    </div>

    <!-- شريط الزينة السفلي المكمل للهوية -->
    <div class="receipt-bottom-accent"></div>
</div>
