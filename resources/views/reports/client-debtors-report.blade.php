<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير مديونيات العملاء المالي - TrueERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #50277c;
            --primary-light: #f6f2fa;
            --primary-dark: #3a1c59;
            --secondary-color: #dc7530;
            --border-color: #cbd5e1;
            --text-dark: #0f172a;
            --text-muted: #475569;
            --bg-light: #f8fafc;
            --danger-color: #dc2626;
            --danger-light: #fef2f2;
            --success-color: #16a34a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background-color: #f1f5f9;
            color: var(--text-dark);
            direction: rtl;
            text-align: right;
            line-height: 1.5;
            font-size: 13px;
        }

        /* ── شريط الأدوات العلوي للشاشة (يختفي بالطباعة) ── */
        .toolbar-wrapper {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .toolbar-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #ffffff;
            border-color: var(--border-color);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background-color: var(--bg-light);
        }

        .btn-download {
            background-color: var(--secondary-color);
            color: #ffffff;
        }
        .btn-download:hover {
            background-color: #c46424;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-input, .filter-select {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 12px;
            background: #ffffff;
            color: var(--text-dark);
            outline: none;
        }
        .filter-input:focus, .filter-select:focus {
            border-color: var(--primary-color);
        }

        /* ── حاوية التقرير القابلة للطباعة (تنسيق طولي A4 مع هوامش مريحة) ── */
        .report-page-container {
            max-width: 960px;
            margin: 25px auto 45px auto;
            background: #ffffff;
            padding: 35px 35px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        /* ── ترويسة التقرير ── */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .header-logo {
            max-height: 55px;
            width: auto;
            display: block;
        }

        .header-title-block {
            text-align: center;
            flex-grow: 1;
        }

        .header-title-block h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .header-title-block p {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .header-meta {
            text-align: left;
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .header-meta strong {
            color: var(--text-dark);
        }

        /* ── بطاقات الإجماليات العلوية ── */
        .summary-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-right: 4px solid var(--primary-color);
            border-radius: 6px;
            padding: 10px 14px;
        }

        .summary-card.accent {
            border-right-color: var(--secondary-color);
        }

        .summary-card .card-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .summary-card .card-value {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
        }

        /* ── جدول التقرير الرئيسي ── */
        .report-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: right;
            border: 1px solid var(--border-color);
        }

        table.report-table thead {
            background: var(--primary-color);
            color: #ffffff;
            display: table-header-group;
        }

        table.report-table th {
            padding: 10px 12px;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            vertical-align: middle;
            text-align: center;
        }

        table.report-table th.align-right {
            text-align: right;
        }

        table.report-table td {
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-dark);
        }

        table.report-table tbody tr.client-group-first {
            border-top: 2px solid #94a3b8;
        }

        table.report-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .client-cell {
            background: #ffffff;
            font-weight: 700;
            color: var(--primary-dark);
            font-size: 12.5px;
        }

        .client-notes-badge {
            display: block;
            margin-top: 4px;
            font-size: 11px;
            font-weight: normal;
            color: var(--text-muted);
            background: var(--bg-light);
            padding: 3px 6px;
            border-radius: 4px;
            border: 1px dashed #cbd5e1;
        }

        .client-total-debt {
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            color: var(--secondary-color);
            background: #fffcf9;
            white-space: nowrap;
        }

        .invoice-debt-cell {
            text-align: center;
            font-weight: 700;
            color: #b91c1c;
            white-space: nowrap;
        }

        .date-cell {
            text-align: center;
            white-space: nowrap;
            font-size: 11.5px;
        }

        .overdue-tag {
            display: inline-block;
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
            color: var(--danger-color);
            background: var(--danger-light);
            padding: 1px 5px;
            border-radius: 4px;
            border: 1px solid #fecaca;
        }

        .notes-cell {
            font-size: 11.5px;
            color: #334155;
            max-width: 320px;
            word-wrap: break-word;
        }

        /* ── تذييل الجدول للإجمالي العام ── */
        table.report-table tfoot {
            background: var(--bg-light);
            border-top: 2px solid var(--primary-color);
            font-weight: 800;
            display: table-footer-group;
        }

        table.report-table tfoot td, table.report-table tfoot th {
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            font-size: 13px;
        }

        .grand-total-label {
            text-align: left;
            font-weight: 800;
            color: var(--primary-color);
            padding-left: 15px;
        }

        .grand-total-values {
            font-weight: 800;
            color: var(--secondary-color);
            font-size: 13.5px;
        }

        /* ── قسم التوقيع والاعتماد ── */
        .signatures-section {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            text-align: center;
            font-size: 12px;
            color: var(--text-dark);
            page-break-inside: avoid;
        }

        .signature-box {
            padding: 10px;
        }

        .signature-title {
            font-weight: 700;
            margin-bottom: 45px;
            color: var(--primary-dark);
        }

        .signature-line {
            width: 160px;
            margin: 0 auto;
            border-bottom: 1px dashed #64748b;
        }

        .print-footer-info {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: var(--text-muted);
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }

        /* ── إعدادات الطباعة الدقيقة على ورق (A4 Portrait مع هوامش أفقية مريحة 15mm) ── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm 15mm 15mm 15mm;
            }

            body {
                background: #ffffff !important;
                font-size: 11px !important;
                color: #000000 !important;
            }

            .no-print {
                display: none !important;
            }

            .report-page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            .report-header {
                margin-bottom: 12px;
                padding-bottom: 8px;
                border-bottom: 2px solid #50277c;
            }

            .summary-cards-container {
                margin-bottom: 12px;
                gap: 8px;
            }

            .summary-card {
                padding: 6px 10px;
                border: 1px solid #cbd5e1;
            }

            .summary-card .card-value {
                font-size: 13px;
            }

            table.report-table {
                font-size: 10.5px !important;
                border: 1px solid #64748b !important;
            }

            table.report-table thead {
                display: table-header-group !important;
                background: #50277c !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            table.report-table th {
                padding: 6px 8px !important;
                border: 1px solid #475569 !important;
            }

            table.report-table td {
                padding: 5px 6px !important;
                border: 1px solid #94a3b8 !important;
            }

            table.report-table tfoot {
                display: table-footer-group !important;
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .signatures-section {
                margin-top: 25px !important;
                padding-top: 15px !important;
                page-break-inside: avoid !important;
            }

            .signature-title {
                margin-bottom: 40px !important;
            }
        }
    </style>
</head>
<body>

    {{-- شريط التحكم العلوي للشاشة (يختفي بالطباعة) --}}
    <div class="toolbar-wrapper no-print">
        <div class="toolbar-container">
            <div class="toolbar-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    طباعة الجدول / حفظ PDF
                </button>

                <a href="{{ route('reports.client-debtors', array_merge(request()->query(), ['download' => 'pdf'])) }}" class="btn btn-download">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    تحميل ملف PDF
                </a>

                <a href="{{ route('filament.admin.pages.accounting-dashboard') }}" class="btn btn-secondary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    العودة للوحة التحكم
                </a>
            </div>

            {{-- نموذج الفلترة والبحث --}}
            <form method="GET" action="{{ route('reports.client-debtors') }}" class="filter-form">
                <select name="currency_id" class="filter-select">
                    <option value="">جميع العملات</option>
                    @foreach($currencies as $curr)
                        <option value="{{ $curr->id }}" {{ ($filters['currency_id'] ?? null) == $curr->id ? 'selected' : '' }}>
                            {{ $curr->currency_name }} ({{ $curr->symbol ?? $curr->currency }})
                        </option>
                    @endforeach
                </select>

                <select name="sort_by" class="filter-select">
                    <option value="debt_desc" {{ ($filters['sort_by'] ?? '') == 'debt_desc' ? 'selected' : '' }}>الأعلى مديونية أولاً</option>
                    <option value="debt_asc" {{ ($filters['sort_by'] ?? '') == 'debt_asc' ? 'selected' : '' }}>الأقل مديونية أولاً</option>
                    <option value="date_asc" {{ ($filters['sort_by'] ?? '') == 'date_asc' ? 'selected' : '' }}>أقدم تاريخ مديونية</option>
                    <option value="date_desc" {{ ($filters['sort_by'] ?? '') == 'date_desc' ? 'selected' : '' }}>أحدث تاريخ مديونية</option>
                    <option value="name_asc" {{ ($filters['sort_by'] ?? '') == 'name_asc' ? 'selected' : '' }}>أبجدياً (اسم العميل)</option>
                </select>

                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث عن عميل أو ملاحظة..." class="filter-input">

                <button type="submit" class="btn btn-secondary" style="padding: 6px 12px;">تصفية</button>
                @if(!empty($filters['currency_id']) || !empty($filters['search']) || ($filters['sort_by'] ?? 'debt_desc') !== 'debt_desc')
                    <a href="{{ route('reports.client-debtors') }}" class="btn btn-secondary" style="padding: 6px 10px; color: var(--danger-color);" title="إعادة الضبط">✕</a>
                @endif
            </form>
        </div>
    </div>

    {{-- الحاوية الرئيسية للتقرير المطبوع --}}
    <div class="report-page-container">

        {{-- مخفي مؤقتاً بناءً على طلب الإدارة: الترويسة وبطاقات الملخص المالي
        <div class="report-header">
            <div>
                <img src="{{ asset('images/true-logo.png') }}" alt="TrueERP Logo" class="header-logo">
            </div>

            <div class="header-title-block">
                <h1>تقرير مديونيات العملاء المالي</h1>
                <p>كشف تفصيلي بالعملاء المستحقة عليهم مديونيات مع تواريخ وملاحظات الفواتير</p>
            </div>

            <div class="header-meta">
                <div><strong>تاريخ الطباعة:</strong> {{ $generated_at }}</div>
                <div><strong>المُعد:</strong> {{ $generated_by }}</div>
                <div><strong>النظام:</strong> TrueERP v3.0</div>
            </div>
        </div>

        <div class="summary-cards-container">
            <div class="summary-card">
                <div class="card-label">إجمالي عدد العملاء المدينين</div>
                <div class="card-value">{{ number_format($total_debtor_clients) }} عميل</div>
            </div>

            <div class="summary-card">
                <div class="card-label">إجمالي عدد الفواتير المستحقة</div>
                <div class="card-value">{{ number_format($total_unpaid_invoices) }} فاتورة</div>
            </div>

            @foreach($grand_totals_by_currency as $code => $tot)
                <div class="summary-card accent">
                    <div class="card-label">إجمالي المديونية ({{ $code }})</div>
                    <div class="card-value" style="color: var(--secondary-color);">
                        {{ number_format($tot['amount'], 2) }} {{ $tot['symbol'] }}
                    </div>
                </div>
            @endforeach
        </div>
        --}}

        {{-- جدول مديونيات العملاء الموحد --}}
        <div class="report-table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">م</th>
                        <th class="align-right">اسم العميل / الشركة</th>
                        <th style="width: 160px;">إجمالي مديونية العميل</th>
                        <th style="width: 140px;">مديونية الفاتورة</th>
                        <th style="width: 130px;">تاريخ المديونية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($debtors as $index => $debtor)
                        @php
                            $invoices = $debtor['invoices'];
                            $invoicesCount = count($invoices);
                        @endphp

                        @foreach($invoices as $invIndex => $inv)
                            <tr class="{{ $invIndex === 0 ? 'client-group-first' : '' }}">
                                {{-- إذا كان هذا السطر الأول للعميل، ندمج خلايا العميل بمقدار عدد فواتيره --}}
                                @if($invIndex === 0)
                                    <td rowspan="{{ $invoicesCount }}" style="text-align: center; font-weight: 700; background: #ffffff;">
                                        {{ $index + 1 }}
                                    </td>
                                    <td rowspan="{{ $invoicesCount }}" class="client-cell">
                                        {{ $debtor['company_name'] }}
                                    </td>
                                    <td rowspan="{{ $invoicesCount }}" class="client-total-debt">
                                        {{ $debtor['total_debt_formatted'] }}
                                    </td>
                                @endif

                                {{-- بيانات الفاتورة المستحقة --}}
                                <td class="invoice-debt-cell">
                                    {{ $inv['debt_amount_formatted'] }}
                                </td>

                                <td class="date-cell">
                                    {{ $inv['debt_date'] }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                لا توجد مديونيات مطابقة للمعايير المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                {{-- صف الإجمالي العام --}}
                <tfoot>
                    <tr>
                        <th colspan="2" class="grand-total-label">
                            الإجمالي العام لجميع مديونيات العملاء:
                        </th>
                        <th class="grand-total-values" style="text-align: center;">
                            @php
                                $totalStrings = [];
                                foreach($grand_totals_by_currency as $currData) {
                                    $totalStrings[] = number_format($currData['amount'], 2) . ' ' . $currData['symbol'];
                                }
                            @endphp
                            {{ implode(' + ', $totalStrings) }}
                        </th>
                        <th colspan="2" style="text-align: right; font-weight: normal; color: var(--text-muted); font-size: 11px;">
                            (مجموع المبالغ المستحقة لجميع الفواتير المعلقة في النظام)
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- خانات التوقيع والاعتماد للطباعة --}}
        <div class="signatures-section">
            <div class="signature-box">
                <div class="signature-title">إعداد المحاسب</div>
                <div class="signature-line"></div>
            </div>

            <div class="signature-box">
                <div class="signature-title">مراجعة المدير المالي</div>
                <div class="signature-line"></div>
            </div>

            <div class="signature-box">
                <div class="signature-title">اعتماد الإدارة العامة</div>
                <div class="signature-line"></div>
            </div>
        </div>

        {{-- تذييل أسفل الورقة المطبوعة --}}
        <div class="print-footer-info">
            <div>نظام TrueERP لإدارة الموارد والمحاسبة — صفحة التقرير المالي الرسمي</div>
            <div>طُبع بواسطة: {{ $generated_by }} | {{ $generated_at }}</div>
        </div>

    </div>

    @if(!empty($autoPrint))
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 400);
            });
        </script>
    @endif

</body>
</html>
