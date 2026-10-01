<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف حساب عميل - {{ $client->company_name ?? $client->client_name }} - TrueERP</title>
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
            --success-light: #f0fdf4;
            --warning-color: #d97706;
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
            max-width: 1000px;
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

        .filter-input {
            padding: 6px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 12px;
            background: #ffffff;
            color: var(--text-dark);
            outline: none;
        }
        .filter-input:focus {
            border-color: var(--primary-color);
        }

        /* ── حاوية الكشف القابلة للطباعة ── */
        .report-page-container {
            max-width: 1000px;
            margin: 25px auto 45px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--border-color);
        }

        .header-banner-img {
            width: 100%;
            display: block;
            border-bottom: 3px solid var(--secondary-color);
            margin-bottom: 20px;
            border-radius: 4px;
        }

        /* ── ترويسة كشف الحساب ── */
        .statement-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .statement-title-bar h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-color);
        }

        .statement-title-bar .meta-dates {
            font-size: 12px;
            color: var(--text-muted);
            text-align: left;
        }

        /* ── بطاقة اسم العميل ── */
        .client-info-box {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-right: 4px solid var(--primary-color);
            border-radius: 6px;
            padding: 10px 18px;
            margin-bottom: 20px;
        }

        .client-info-box .info-label {
            font-weight: 700;
            color: var(--text-muted);
            font-size: 13px;
        }

        .client-info-box .info-val {
            font-weight: 800;
            color: var(--primary-color);
            font-size: 15px;
        }

        /* ── بطاقات الإجماليات ── */
        .summary-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
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

        .summary-card.danger {
            border-right-color: var(--danger-color);
            background: var(--danger-light);
        }

        .summary-card.success {
            border-right-color: var(--success-color);
            background: var(--success-light);
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

        /* ── جدول الحركات التفصيلي ── */
        .report-table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 25px;
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
            padding: 9px 10px;
            font-weight: 700;
            font-size: 11.5px;
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
        }

        table.report-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        table.report-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        table.report-table tfoot {
            background-color: #e2e8f0;
            font-weight: 800;
            border-top: 2px solid var(--primary-color);
        }

        table.report-table tfoot td {
            padding: 10px 10px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-mono { font-family: monospace, sans-serif; }

        .badge-type {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-invoice {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-receipt {
            background-color: #dcfce7;
            color: #166534;
        }

        /* ── قسم التوقيعات والاعتمادات ── */
        .signatures-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px dashed var(--border-color);
            page-break-inside: avoid;
        }

        .signature-box {
            text-align: center;
        }

        .signature-box .sig-title {
            font-weight: 700;
            font-size: 12px;
            color: var(--primary-color);
            margin-bottom: 45px;
        }

        .signature-box .sig-line {
            border-bottom: 1px dotted var(--text-muted);
            width: 140px;
            margin: 0 auto 5px auto;
        }

        .signature-box .sig-name {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* ── تذييل التقرير ── */
        .report-footer {
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--text-muted);
        }

        /* ── تنسيقات خاصة بالطباعة الورقية وتصدير PDF ── */
        @media print {
            body {
                background: #ffffff !important;
                font-size: 11px !important;
            }
            .toolbar-wrapper {
                display: none !important;
            }
            .report-page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
                border: none !important;
                box-shadow: none !important;
            }
            table.report-table th, table.report-table td {
                padding: 5px 6px !important;
                font-size: 10.5px !important;
            }
            .summary-cards-container {
                gap: 8px !important;
                margin-bottom: 14px !important;
            }
            .summary-card {
                padding: 6px 10px !important;
            }
            .summary-card .card-value {
                font-size: 13px !important;
            }
            .signatures-section {
                margin-top: 25px !important;
                padding-top: 15px !important;
            }
            .signature-box .sig-title {
                margin-bottom: 35px !important;
            }
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    {{-- شريط الأدوات العلوي للشاشة --}}
    <div class="toolbar-wrapper">
        <div class="toolbar-container">
            <div class="toolbar-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                    <span>طباعة الكشف / حفظ PDF</span>
                </button>
                <a href="{{ \App\Filament\Pages\ClientFinancialDetail::getUrl(['client' => $client->id]) }}" class="btn btn-secondary">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    <span>الملف المالي للعميل</span>
                </a>
            </div>

            <form method="GET" action="{{ url()->current() }}" class="filter-form">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);">من تاريخ:</label>
                <input type="date" name="from_date" value="{{ $fromDate ?? '' }}" class="filter-input">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-muted);">إلى تاريخ:</label>
                <input type="date" name="to_date" value="{{ $toDate ?? '' }}" class="filter-input">
                <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">تصفية</button>
                @if($fromDate || $toDate)
                    <a href="{{ url()->current() }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">إلغاء الفلتر</a>
                @endif
            </form>
        </div>
    </div>

    {{-- الحاوية الرئيسية للكشف --}}
    <div class="report-page-container">
        
        {{-- الترويسة المرفقة لـ True Media --}}
        @if(file_exists(public_path('images/11.jpg')))
            <img class="header-banner-img" src="{{ asset('images/11.jpg') }}" alt="True Media Advertising Header">
        @endif

        {{-- عنوان الكشف وتاريخه --}}
        <div class="statement-title-bar">
            <div>
                <h1>كشف حساب مالي تفصيلي</h1>
                <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">سجل الحركات المالية المعتمدة (فواتير مرحلة ومدفوعة + سندات قبض)</p>
            </div>
            <div class="meta-dates">
                <div><strong>تاريخ الاستخراج:</strong> {{ $generatedAt }}</div>
                <div><strong>المُعد:</strong> {{ $generatedBy }}</div>
                <div>
                    <strong>فترة الكشف:</strong>
                    @if($fromDate && $toDate)
                        من {{ $fromDate }} إلى {{ $toDate }}
                    @elseif($fromDate)
                        من {{ $fromDate }} حتى الآن
                    @elseif($toDate)
                        حتى {{ $toDate }}
                    @else
                        كامل المدة
                    @endif
                </div>
            </div>
        </div>

        {{-- بيانات العميل --}}
        <div class="client-info-box">
            <span class="info-label">اسم العميل:</span>
            <span class="info-val">{{ $client->company ?: $client->client_name }}</span>
        </div>

        {{-- بطاقات الملخص المالي --}}
        <div class="summary-cards-container">
            <div class="summary-card">
                <div class="card-label">إجمالي المسحوبات (مدين +)</div>
                <div class="card-value" style="color: var(--warning-color);">
                    {{ number_format($totalDebit, 2) }} {{ $baseCurrencySymbol }}
                </div>
            </div>
            <div class="summary-card accent">
                <div class="card-label">إجمالي المقبوضات (دائن -)</div>
                <div class="card-value" style="color: var(--success-color);">
                    {{ number_format($totalCredit, 2) }} {{ $baseCurrencySymbol }}
                </div>
            </div>
            <div class="summary-card {{ $finalBalance > 0 ? 'danger' : ($finalBalance < 0 ? 'accent' : 'success') }}">
                <div class="card-label">صافي الرصيد المستحق</div>
                <div class="card-value">
                    {{ number_format($finalBalance, 2) }} {{ $baseCurrencySymbol }}
                    <span style="font-size: 11px; font-weight: 600;">
                        @if($finalBalance > 0)
                            (مستحق على العميل)
                        @elseif($finalBalance < 0)
                            (رصيد دائن للعميل)
                        @else
                            (خالص / مسدد)
                        @endif
                    </span>
                </div>
            </div>
            @if($unallocatedAdvanceBalance > 0)
                <div class="summary-card" style="border-right-color: #8b5cf6; background: #faf5ff;">
                    <div class="card-label">فائض دفعات مقدمة غير مخصصة</div>
                    <div class="card-value" style="color: #7c3aed;">
                        {{ number_format($unallocatedAdvanceBalance, 2) }} {{ $baseCurrencySymbol }}
                    </div>
                </div>
            @endif
        </div>

        {{-- جدول الحركات المالية --}}
        <div class="report-table-wrapper">
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 4%;">م</th>
                        <th style="width: 12%;">التاريخ</th>
                        <th style="width: 12%;">نوع الحركة</th>
                        <th style="width: 14%;">رقم المرجع</th>
                        <th style="width: 26%;" class="align-right">البيان / التفاصيل</th>
                        <th style="width: 11%;">مدين (+)</th>
                        <th style="width: 11%;">دائن (-)</th>
                        <th style="width: 10%;">الرصيد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        <tr>
                            <td class="text-center font-mono">{{ $index + 1 }}</td>
                            <td class="text-center font-mono">
                                {{ $item->transaction_date ? \Illuminate\Support\Carbon::parse($item->transaction_date)->format('Y-m-d') : '—' }}
                            </td>
                            <td class="text-center">
                                @if($item->type === 'invoice')
                                    <span class="badge-type badge-invoice">فاتورة مبيعات</span>
                                @else
                                    <span class="badge-type badge-receipt">سند قبض</span>
                                @endif
                            </td>
                            <td class="text-center font-mono font-bold">{{ $item->reference_number }}</td>
                            <td class="text-right">{{ $item->details }}</td>
                            <td class="text-center font-mono font-bold" style="color: {{ $item->debit > 0 ? 'var(--warning-color)' : '#94a3b8' }};">
                                @if($item->debit > 0)
                                    {{ number_format($item->debit, 2) }}
                                    @if($item->is_foreign_currency)
                                        <div style="font-size: 10px; color: var(--text-muted);">({{ number_format($item->debit_base, 2) }} {{ $item->base_currency_symbol }})</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center font-mono font-bold" style="color: {{ $item->credit > 0 ? 'var(--success-color)' : '#94a3b8' }};">
                                @if($item->credit > 0)
                                    {{ number_format($item->credit, 2) }}
                                    @if($item->is_foreign_currency)
                                        <div style="font-size: 10px; color: var(--text-muted);">({{ number_format($item->credit_base, 2) }} {{ $item->base_currency_symbol }})</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-center font-mono font-bold" style="color: {{ $item->running_balance > 0 ? 'var(--danger-color)' : 'var(--success-color)' }};">
                                {{ number_format($item->running_balance, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 24px; color: var(--text-muted);">
                                لا توجد حركات مالية مرحلة أو مسددة مسجلة لهذا العميل في هذه الفترة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-center">المجموع الإجمالي ({{ $baseCurrencySymbol }})</td>
                        <td class="text-center font-mono" style="color: var(--warning-color);">{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-center font-mono" style="color: var(--success-color);">{{ number_format($totalCredit, 2) }}</td>
                        <td class="text-center font-mono" style="color: {{ $finalBalance > 0 ? 'var(--danger-color)' : 'var(--success-color)' }};">
                            {{ number_format($finalBalance, 2) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- قسم التوقيعات والاعتمادات الرسمية --}}
        <div class="signatures-section">
            <div class="signature-box">
                <div class="sig-title">المحاسب المسؤول</div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $generatedBy }}</div>
            </div>
            <div class="signature-box">
                <div class="sig-title">الإدارة المالية / التدقيق</div>
                <div class="sig-line"></div>
                <div class="sig-name">................................</div>
            </div>
            <div class="signature-box">
                <div class="sig-title">توقيع واستلام العميل</div>
                <div class="sig-line"></div>
                <div class="sig-name">{{ $client->client_name }}</div>
            </div>
        </div>

        {{-- تذييل الصفحة --}}
        <div class="report-footer">
            <div>True Media Advertising — نظام TrueERP المحاسبي المتكامل</div>
            <div>تم استخراج هذا الكشف آلياً بتاريخ {{ $generatedAt }}</div>
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
