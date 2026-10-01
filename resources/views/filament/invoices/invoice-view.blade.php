<div dir="rtl" class="invoice-view-container">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap');

        .invoice-view-container {
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

        .invoice-view-container * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
        }

        /* ترويسة الفاتورة المرفقة */
        .invoice-view-container .invoice-header-img {
            width: 100%;
            /* padding: 20px 50px; */
            display: block;
            border-bottom: 3px solid var(--secondary-color);
        }

        /* جسم الفاتورة الداخلي بالتتابع الرسمي */
        .invoice-view-container .invoice-body {
            padding: 40px;
        }

        /* --- جدول تفاصيل الفاتورة العلوي الجديد --- */
        .invoice-view-container .top-meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }

        /* تنسيق خلايا الجدول العلوي */
        .invoice-view-container .top-meta-table td {
            padding: 14px 20px;
            vertical-align: middle;
        }

        /* خلايا الجانب الأيمن (العملة وطريقة الدفع) */
        .invoice-view-container .meta-td-right {
            text-align: right;
            width: 35%;
            border-left: 1px solid var(--border-color);
        }

        /* الخلية الوسطى الممتدة لصفين (فاتورة مبيعات) */
        .invoice-view-container .meta-td-center {
            text-align: center;
            width: 30%;
        }

        .invoice-view-container .invoice-main-title {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 800;
            border-bottom: 3px solid var(--secondary-color);
            display: inline-block;
            padding-bottom: 2px;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }

        /* خلايا الجانب الأيسر (رقم الفاتورة والتاريخ) */
        .invoice-view-container .meta-td-left {
            text-align: right;
            width: 35%;
            border-right: 1px solid var(--border-color);
        }

        /* خط فاصل أفقي للصف الأول */
        .invoice-view-container .border-bottom-cell {
            border-bottom: 1px solid var(--border-color);
        }

        .invoice-view-container .meta-field-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
        }

        .invoice-view-container .meta-field-value {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 13px;
        }

        /* --- شريط الاسم الممتد تحتهن مباشرة --- */
        .invoice-view-container .client-name-banner {
            background-color: var(--bg-light);
            border-right: 4px solid var(--primary-color);
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border-color);
            border-right: 4px solid var(--primary-color);
        }

        .invoice-view-container .client-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 700;
            margin-left: 10px;
        }

        .invoice-view-container .client-value {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary-color);
        }

        .invoice-view-container .client-badge-touch {
            background-color: var(--secondary-color);
            color: #ffffff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 3px;
            font-weight: 600;
        }

        /* --- جدول المواد والخدمات الرئيسي --- */
        .invoice-view-container .official-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .invoice-view-container .official-table th {
            background-color: var(--primary-color);
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            padding: 12px 15px;
            text-align: right;
        }

        .invoice-view-container .official-table th:first-child {
            border-top-right-radius: 6px;
        }

        .invoice-view-container .official-table th:last-child {
            border-top-left-radius: 6px;
            text-align: left;
        }

        .invoice-view-container .official-table td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-dark);
        }

        .invoice-view-container .official-table td:last-child {
            text-align: left;
            font-weight: 700;
        }

        .invoice-view-container .td-hash {
            width: 8%;
        }

        .invoice-view-container .hash-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 22px;
            text-align: center;
            border-radius: 50%;
            background-color: #fff4ed;
            color: var(--secondary-color);
            font-weight: 700;
            font-size: 11px;
            border: 1px solid #ffebd9;
        }

        .invoice-view-container .official-table tbody tr:nth-child(even) {
            background-color: #faf9fc;
        }

        /* --- قسم الإجماليات والشروط المعتاد --- */
        .invoice-view-container .invoice-bottom-grid {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .invoice-view-container .terms-block {
            width: 48%;
            font-size: 12px;
            color: var(--text-muted);
            border-right: 2px solid var(--border-color);
            padding-right: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .invoice-view-container .terms-block-title {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .invoice-view-container .totals-block {
            width: 40%;
        }

        .invoice-view-container .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 5px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
        }

        .invoice-view-container .totals-row.grand-total-row {
            border: 2px dashed var(--secondary-color);
            border-radius: 6px;
            background-color: #fffaf7;
            padding: 12px 15px;
            margin-top: 10px;
            font-weight: 800;
            font-size: 16px;
            color: var(--primary-color);
        }

        .invoice-view-container .totals-row.grand-total-row span:last-child {
            color: var(--secondary-color);
        }

        /* --- قسم التواقيع والاعتماد للجهات الرسمية --- */
        .invoice-view-container .signatures-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
        }

        .invoice-view-container .signature-col {
            text-align: center;
            width: 40%;
        }

        .invoice-view-container .signature-placeholder {
            width: 140px;
            height: 1px;
            background-color: var(--text-muted);
            margin: 15px auto 5px;
        }

        .invoice-view-container .signature-text {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* الشريط السفلي المكمل للهوية */
        .invoice-view-container .invoice-bottom-accent {
            height: 8px;
            background: linear-gradient(90deg, var(--primary-color) 70%, var(--secondary-color) 30%);
            width: 100%;
        }

        /* زر الطباعة */
        .invoice-view-container .print-btn {
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

        .invoice-view-container .print-btn:hover {
            opacity: 0.88;
        }

        /* تهيئة القالب للطباعة الاحترافية */
        @media print {
            .invoice-view-container {
                box-shadow: none !important;
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
            .invoice-view-container .invoice-body {
                padding: 30px !important;
            }
            .invoice-view-container .print-btn {
                display: none !important;
            }
            .invoice-view-container * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <!-- الترويسة المرفقة -->
    <img class="invoice-header-img" src="{{ asset('images/11.jpg') }}" alt="True Media Advertising Header">

    <!-- جسم الفاتورة الداخلي -->
    <div class="invoice-body">
        @php
            $currencyModel = $record->currency ?? $record->contract?->currency ?? \App\Models\Currency::getBase();
            $currencyName = $currencyModel?->currency_name ?? 'ريال سعودي';
            $currencyCode = $currencyModel?->currency ?? 'SAR';
            $currencySymbol = $currencyModel?->symbol ?? $currencyCode;
            $decimals = 2;
            $isUSD = $currencyCode === 'USD';

            $paymentMethod = match ($record->status) {
                'paid' => 'نقدي',
                'posted' => 'آجل',
                default => 'آجل',
            };

            $subtotal = $record->items->sum('total');
            $grandTotal = $record->total_amount;
            $discount = $subtotal - $grandTotal;
        @endphp

        <!-- جدول تفاصيل الفاتورة العلوي المحدث بناءً على طلبكم -->
        <table class="top-meta-table">
            <tr>
                <!-- اليمين: العملة -->
                <td class="meta-td-right border-bottom-cell">
                    <span class="meta-field-label">العملة:</span>
                    <span class="meta-field-value">{{ $currencyName }} ({{ $currencyCode }})</span>
                </td>

                <!-- الوسط: ممتد لصفين وعريض عمودياً -->
                <td rowspan="2" class="meta-td-center">
                    <h1 class="invoice-main-title">فاتورة مبيعات</h1>
                </td>

                <!-- اليسار: رقم الفاتورة -->
                <td class="meta-td-left border-bottom-cell">
                    <span class="meta-field-label">رقم الفاتورة:</span>
                    <span class="meta-field-value">#{{ $record->invoice_number }}</span>
                </td>
            </tr>
            <tr>
                <!-- اليمين: طريقة الدفع -->
                <td class="meta-td-right">
                    <span class="meta-field-label">طريقة الدفع:</span>
                    <span class="meta-field-value">{{ $paymentMethod }}</span>
                </td>

                <!-- اليسار: التاريخ -->
                <td class="meta-td-left">
                    <span class="meta-field-label">التاريخ:</span>
                    <span class="meta-field-value">{{ $record->issue_date->format('Y/m/d') }}</span>
                </td>
            </tr>
        </table>

        <!-- الاسم تحتهن مباشرة ممتد بشكل أفقي أنيق -->
        <div class="client-name-banner">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="client-label">موجّه إلى السادة:</span>
                <span class="client-value" style="font-size: 15px; font-weight: 800; color: var(--primary-color);">{{ $record->client->company }}</span>
            </div>
            <span class="client-value" style="font-size: 15px; font-weight: 800; color: var(--primary-color);">المحترمين</span>
        </div>

        <!-- جدول المواد والخدمات الرئيسي -->
        <table class="official-table">
            <thead>
                <tr>
                    <th class="td-hash">#</th>
                    <th style="width: 52%;">البيان / وصف الخدمة</th>
                    <th style="width: 12%; text-align: center;">الكمية</th>
                    <th style="width: 14%; text-align: center;">سعر الوحدة</th>
                    <th style="width: 14%; text-align: left;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($record->items as $index => $item)
                    <tr>
                        <td class="td-hash"><span class="hash-badge">{{ $index + 1 }}</span></td>
                        <td>{{ $item->description }}</td>
                        <td style="text-align: center;">{{ number_format($item->quantity, 0) }}</td>
                        <td style="text-align: center;">
                            {!! $isUSD ? '$' : '' !!}{{ number_format($item->unit_amount, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                        </td>
                        <td>
                            {!! $isUSD ? '$' : '' !!}{{ number_format($item->total, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- الجزء السفلي: الشروط والإجماليات -->
        <div class="invoice-bottom-grid">
            <!-- الملاحظات الرسمية -->
            <div class="terms-block">
                <div class="terms-block-title">ملاحظات هامة:</div>
                @if($record->notes)
                    <p style="margin-bottom: 4px;">• {!! nl2br(e($record->notes)) !!}</p>
                @endif
                <p>• يُرجى مراجعة وتدقيق الفاتورة فور الاستلام والتحصيل المالي.</p>
                <p>• نشكركم لثقتكم واختياركم خدمات ترو ميديا للدعاية والإعلان.</p>
            </div>

            <!-- المبالغ والخصومات مع تمييز الإجمالي النهائي -->
            <div class="totals-block">
                <div class="totals-row">
                    <span style="color: var(--text-muted);">المجموع الفرعي:</span>
                    <span class="meta-value">
                        {!! $isUSD ? '$' : '' !!}{{ number_format($subtotal, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                    </span>
                </div>
                @if($discount > 0)
                    <div class="totals-row">
                        <span style="color: var(--text-muted);">خصم خاص:</span>
                        <span class="meta-value" style="color: var(--secondary-color);">
                            -{!! $isUSD ? '$' : '' !!}{{ number_format($discount, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                        </span>
                    </div>
                @endif
                <!-- الإجمالي الكلي المتميز والمحاط بإطار متقطع -->
                <div class="totals-row grand-total-row">
                    <span>الإجمالي الكلي:</span>
                    <span>
                        {!! $isUSD ? '$' : '' !!}{{ number_format($grandTotal, $decimals) }}{!! !$isUSD ? ' ' . $currencySymbol : '' !!}
                    </span>
                </div>
            </div>
        </div>

        <!-- قسم التواقيع والاعتماد للجهات الرسمية -->
        <div class="signatures-section">
            <div class="signature-col">
                <div class="signature-placeholder"></div>
                <span class="signature-text">المحاسب / عبدالغني العامري</span>
            </div>
            <div class="signature-col">
                <div class="signature-placeholder"></div>
                <span class="signature-text">المدير العام / ناصر غراب</span>
            </div>
        </div>

        <!-- زر الطباعة -->
        <button class="print-btn" onclick="
            var content = document.querySelector('.invoice-view-container').outerHTML;
            var win = window.open('', '_blank', 'width=900,height=700');
            win.document.write('<!DOCTYPE html><html lang=\'ar\' dir=\'rtl\'><head><meta charset=\'UTF-8\'><title>فاتورة</title><link rel=\'stylesheet\' href=\'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap\'><style>body{margin:0;padding:20px;background:#fff;font-family:Cairo,sans-serif;}.print-btn{display:none!important;}</style></head><body>' + content + '<script>window.onload=function(){window.print();window.close();}<\/script></body></html>');
            win.document.close();
        ">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
            طباعة الفاتورة
        </button>

    </div>

    <!-- شريط الزينة السفلي المكمل للهوية -->
    <div class="invoice-bottom-accent"></div>
</div>
