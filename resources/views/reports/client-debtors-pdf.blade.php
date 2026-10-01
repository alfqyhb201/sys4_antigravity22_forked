<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تقرير مديونيات العملاء المالي</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 15mm 15mm 15mm 15mm;
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
            font-size: 16px;
            font-weight: bold;
            color: #50277c;
            margin: 0 0 4px 0;
        }

        .report-subtitle {
            font-size: 10px;
            color: #64748b;
            margin: 0;
        }

        .meta-text {
            font-size: 9px;
            color: #475569;
            line-height: 1.4;
        }

        /* ── بطاقات الإجماليات ── */
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 10px;
        }

        .summary-cell {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-right: 3px solid #50277c;
            padding: 5px 8px;
            text-align: right;
        }

        .summary-label {
            font-size: 9px;
            color: #64748b;
        }

        .summary-value {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }

        /* ── جدول المديونيات ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .data-table th {
            background-color: #50277c;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: bold;
            padding: 6px 4px;
            border: 1px solid #3b1a5d;
            text-align: center;
        }

        .data-table td {
            padding: 5px 4px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
            vertical-align: middle;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .client-name {
            font-weight: bold;
            color: #1e1b4b;
        }

        .client-notes {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }

        .client-total {
            font-weight: bold;
            color: #dc7530;
            text-align: center;
        }

        .invoice-debt {
            font-weight: bold;
            color: #b91c1c;
            text-align: center;
        }

        .overdue-badge {
            font-size: 8px;
            color: #dc2626;
            display: block;
        }

        .data-table tfoot th, .data-table tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            font-size: 10px;
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
            padding: 10px;
        }

        .signature-title {
            font-weight: bold;
            color: #50277c;
            margin-bottom: 35px;
        }

        .signature-dots {
            border-bottom: 1px dotted #64748b;
            width: 120px;
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

    {{-- مخفي مؤقتاً بناءً على طلب الإدارة: الترويسة وبطاقات الملخص المالي
    <table class="header-table">
        <tr>
            <td style="width: 25%;">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" style="max-height: 45px;" alt="Logo">
                @else
                    <strong style="color: #50277c; font-size: 16px;">TrueERP</strong>
                @endif
            </td>
            <td style="width: 50%; text-align: center;">
                <div class="report-title">تقرير مديونيات العملاء المالي</div>
                <div class="report-subtitle">كشف تفصيلي بالعملاء المستحقة عليهم مديونيات مع الفواتير وتواريخها وملاحظاتها</div>
            </td>
            <td style="width: 25%; text-align: left;" class="meta-text">
                <div><strong>تاريخ الاستخراج:</strong> {{ $generated_at }}</div>
                <div><strong>المُعد:</strong> {{ $generated_by }}</div>
                <div><strong>الصفحة:</strong> A4 Landscape</div>
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="summary-cell">
                <div class="summary-label">إجمالي العملاء المدينين</div>
                <div class="summary-value">{{ number_format($total_debtor_clients) }} عميل</div>
            </td>
            <td class="summary-cell">
                <div class="summary-label">إجمالي الفواتير المستحقة</div>
                <div class="summary-value">{{ number_format($total_unpaid_invoices) }} فاتورة</div>
            </td>
            @foreach($grand_totals_by_currency as $code => $tot)
                <td class="summary-cell" style="border-right-color: #dc7530;">
                    <div class="summary-label">إجمالي المديونية ({{ $code }})</div>
                    <div class="summary-value" style="color: #dc7530;">
                        {{ number_format($tot['amount'], 2) }} {{ $tot['symbol'] }}
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
    --}}

    {{-- جدول البيانات الرئيسي --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 6%;">م</th>
                <th style="width: 38%; text-align: right;">اسم العميل / الشركة</th>
                <th style="width: 20%;">إجمالي مديونية العميل</th>
                <th style="width: 18%;">مديونية الفاتورة</th>
                <th style="width: 18%;">تاريخ المديونية</th>
            </tr>
        </thead>
        <tbody>
            @forelse($debtors as $index => $debtor)
                @php
                    $invoices = $debtor['invoices'];
                    $invoicesCount = count($invoices);
                @endphp

                @foreach($invoices as $invIndex => $inv)
                    <tr>
                        @if($invIndex === 0)
                            <td rowspan="{{ $invoicesCount }}" class="text-center" style="font-weight: bold;">
                                {{ $index + 1 }}
                            </td>
                            <td rowspan="{{ $invoicesCount }}" class="text-right">
                                <div class="client-name">{{ $debtor['company_name'] }}</div>
                            </td>
                            <td rowspan="{{ $invoicesCount }}" class="client-total">
                                {{ $debtor['total_debt_formatted'] }}
                            </td>
                        @endif

                        <td class="invoice-debt">
                            {{ $inv['debt_amount_formatted'] }}
                        </td>

                        <td class="text-center">
                            {{ $inv['debt_date'] }}
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px;">لا توجد مديونيات معلقة.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" class="text-right" style="color: #50277c;">الإجمالي العام لمديونيات العملاء:</th>
                <th class="text-center" style="color: #dc7530;">
                    @php
                        $totStrs = [];
                        foreach($grand_totals_by_currency as $currData) {
                            $totStrs[] = number_format($currData['amount'], 2) . ' ' . $currData['symbol'];
                        }
                    @endphp
                    {{ implode(' + ', $totStrs) }}
                </th>
                <th colspan="2" class="text-right" style="font-size: 8.5px; font-weight: normal; color: #64748b;">
                    (مجموع كافة المديونيات المعلقة بجميع العملات في النظام)
                </th>
            </tr>
        </tfoot>
    </table>

    {{-- قسم الاعتماد والتوقيع --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-title">إعداد المحاسب</div>
                <div class="signature-dots"></div>
            </td>
            <td>
                <div class="signature-title">مراجعة المدير المالي</div>
                <div class="signature-dots"></div>
            </td>
            <td>
                <div class="signature-title">اعتماد الإدارة العامة</div>
                <div class="signature-dots"></div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        نظام TrueERP المالي — طُبع بواسطة {{ $generated_by }} بتاريخ {{ $generated_at }}
    </div>

</body>
</html>
