<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>كشف حساب عميل - {{ $client->company_name ?? $client->client_name }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 12mm 12mm 12mm 12mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        /* ── ترويسة التقرير ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #50277c;
            margin-bottom: 12px;
            padding-bottom: 6px;
        }

        .header-table td {
            vertical-align: middle;
            border: none;
            padding: 4px;
        }

        .report-title {
            font-size: 15px;
            font-weight: bold;
            color: #50277c;
            margin: 0 0 4px 0;
        }

        .report-subtitle {
            font-size: 9.5px;
            color: #64748b;
            margin: 0;
        }

        .meta-text {
            font-size: 8.5px;
            color: #475569;
            line-height: 1.4;
        }

        /* ── بطاقة معلومات العميل ── */
        .client-box {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            margin-bottom: 10px;
        }

        .client-box td {
            padding: 4px 8px;
            border: none;
            font-size: 9px;
            vertical-align: middle;
        }

        .client-box .label {
            font-weight: bold;
            color: #475569;
        }

        /* ── بطاقات الإجماليات ── */
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 10px;
        }

        .summary-cell {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-right: 3px solid #50277c;
            padding: 4px 6px;
            text-align: right;
        }

        .summary-label {
            font-size: 8.5px;
            color: #64748b;
        }

        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }

        /* ── جدول الحركات ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table th {
            background-color: #50277c;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 5px 4px;
            border: 1px solid #3b1a5d;
            text-align: center;
        }

        .data-table td {
            padding: 4px 4px;
            border: 1px solid #cbd5e1;
            font-size: 8.5px;
            vertical-align: middle;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .badge-type {
            font-weight: bold;
            font-size: 8px;
        }

        .data-table tfoot th, .data-table tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
            border: 1px solid #94a3b8;
            padding: 5px 6px;
            font-size: 9.5px;
        }

        /* ── التوقيعات ── */
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            border-collapse: collapse;
        }

        .signatures-table td {
            text-align: center;
            border: none;
            width: 33.33%;
            padding: 6px;
        }

        .signature-title {
            font-weight: bold;
            color: #50277c;
            margin-bottom: 30px;
            font-size: 9.5px;
        }

        .signature-dots {
            border-bottom: 1px dotted #64748b;
            width: 110px;
            margin: 0 auto;
        }

        .footer-note {
            margin-top: 15px;
            font-size: 8px;
            color: #94a3b8;
            text-align: left;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    @php
        $logoPath = public_path('images/true-logo.png');
        $logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    {{-- ترويسة التقرير --}}
    <table class="header-table">
        <tr>
            <td style="width: 25%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" style="max-height: 40px;" alt="Logo">
                @else
                    <strong style="color: #50277c; font-size: 15px;">TrueERP</strong>
                @endif
            </td>
            <td style="width: 50%; text-align: center;">
                <div class="report-title">كشف حساب مالي تفصيلي</div>
                <div class="report-subtitle">سجل الحركات المالية المعتمدة (فواتير مرحلة/مدفوعة وسندات قبض)</div>
            </td>
            <td style="width: 25%; text-align: left;" class="meta-text">
                <div><strong>تاريخ الاستخراج:</strong> {{ $generatedAt }}</div>
                <div><strong>المُعد:</strong> {{ $generatedBy }}</div>
                <div>
                    <strong>الفترة:</strong>
                    @if($fromDate && $toDate)
                        {{ $fromDate }} → {{ $toDate }}
                    @else
                        كامل المدة
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- معلومات العميل --}}
    <table class="client-box">
        <tr>
            <td style="width: 100%; padding: 6px 10px;">
                <span class="label">اسم العميل:</span>
                <strong style="color: #50277c; font-size: 11px;">{{ $client->company ?: $client->client_name }}</strong>
            </td>
        </tr>
    </table>

    {{-- بطاقات الملخص المالي --}}
    <table class="summary-table">
        <tr>
            <td class="summary-cell" style="border-right-color: #d97706;">
                <div class="summary-label">إجمالي المسحوبات (مدين +)</div>
                <div class="summary-value" style="color: #d97706;">
                    {{ number_format($totalDebit, 2) }} {{ $baseCurrencySymbol }}
                </div>
            </td>
            <td class="summary-cell" style="border-right-color: #16a34a;">
                <div class="summary-label">إجمالي المقبوضات (دائن -)</div>
                <div class="summary-value" style="color: #16a34a;">
                    {{ number_format($totalCredit, 2) }} {{ $baseCurrencySymbol }}
                </div>
            </td>
            <td class="summary-cell" style="border-right-color: {{ $finalBalance > 0 ? '#dc2626' : '#16a34a' }};">
                <div class="summary-label">صافي الرصيد المستحق</div>
                <div class="summary-value" style="color: {{ $finalBalance > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ number_format($finalBalance, 2) }} {{ $baseCurrencySymbol }}
                </div>
            </td>
            @if($unallocatedAdvanceBalance > 0)
                <td class="summary-cell" style="border-right-color: #7c3aed;">
                    <div class="summary-label">فائض دفعات مقدمة</div>
                    <div class="summary-value" style="color: #7c3aed;">
                        {{ number_format($unallocatedAdvanceBalance, 2) }} {{ $baseCurrencySymbol }}
                    </div>
                </td>
            @endif
        </tr>
    </table>

    {{-- جدول الحركات التفصيلي --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">م</th>
                <th style="width: 14%;">التاريخ</th>
                <th style="width: 13%;">نوع الحركة</th>
                <th style="width: 15%;">رقم المرجع</th>
                <th style="width: 25%; text-align: right;">البيان / التفاصيل</th>
                <th style="width: 9%;">مدين (+)</th>
                <th style="width: 9%;">دائن (-)</th>
                <th style="width: 10%;">الرصيد</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">
                        {{ $item->transaction_date ? \Illuminate\Support\Carbon::parse($item->transaction_date)->format('Y-m-d') : '—' }}
                    </td>
                    <td class="text-center badge-type" style="color: {{ $item->type === 'invoice' ? '#92400e' : '#166534' }};">
                        {{ $item->type === 'invoice' ? 'فاتورة مبيعات' : 'سند قبض' }}
                    </td>
                    <td class="text-center">{{ $item->reference_number }}</td>
                    <td class="text-right">{{ $item->details }}</td>
                    <td class="text-center" style="color: {{ $item->debit > 0 ? '#d97706' : '#94a3b8' }};">
                        {{ $item->debit > 0 ? number_format($item->debit_base, 2) : '—' }}
                    </td>
                    <td class="text-center" style="color: {{ $item->credit > 0 ? '#16a34a' : '#94a3b8' }};">
                        {{ $item->credit > 0 ? number_format($item->credit_base, 2) : '—' }}
                    </td>
                    <td class="text-center" style="font-weight: bold; color: {{ $item->running_balance > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ number_format($item->running_balance, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 15px; color: #64748b;">
                        لا توجد حركات مالية مرحلة أو مسددة مسجلة لهذا العميل.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-center">المجموع الإجمالي ({{ $baseCurrencySymbol }})</td>
                <td class="text-center" style="color: #d97706;">{{ number_format($totalDebit, 2) }}</td>
                <td class="text-center" style="color: #16a34a;">{{ number_format($totalCredit, 2) }}</td>
                <td class="text-center" style="color: {{ $finalBalance > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ number_format($finalBalance, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- التوقيعات --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">المحاسب المسؤول</div>
                <div class="signature-dots"></div>
                <div style="font-size: 8px; color: #64748b; margin-top: 4px;">{{ $generatedBy }}</div>
            </td>
            <td>
                <div class="signature-title">الإدارة المالية / التدقيق</div>
                <div class="signature-dots"></div>
                <div style="font-size: 8px; color: #64748b; margin-top: 4px;">................................</div>
            </td>
            <td>
                <div class="signature-title">توقيع واستلام العميل</div>
                <div class="signature-dots"></div>
                <div style="font-size: 8px; color: #64748b; margin-top: 4px;">{{ $client->client_name }}</div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        True Media Advertising — نظام TrueERP المحاسبي المتكامل | تم الاستخراج آلياً في {{ $generatedAt }}
    </div>

</body>
</html>
