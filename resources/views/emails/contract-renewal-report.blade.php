<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportTitle ?? 'تقرير دورة تجديد الاشتراكات وإصدار الفواتير - TrueERP' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 24px 12px;
            color: #1e293b;
            direction: rtl;
            text-align: right;
        }
        .wrapper {
            max-width: 740px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 32px 28px;
            color: #ffffff;
            text-align: center;
        }
        .brand-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .header-title {
            margin: 0 0 8px 0;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.4;
        }
        .header-meta {
            margin: 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .content {
            padding: 28px;
        }
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }
        .kpi-cell {
            display: table-cell;
            width: 25%;
            padding: 6px;
            box-sizing: border-box;
        }
        .kpi-card {
            padding: 16px 12px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .kpi-card.success {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }
        .kpi-card.warning {
            background-color: #fffbeb;
            border-color: #fde68a;
        }
        .kpi-card.info {
            background-color: #eff6ff;
            border-color: #bfdbfe;
        }
        .kpi-card.danger {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        .kpi-number {
            font-size: 26px;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
        }
        .kpi-card.success .kpi-number { color: #15803d; }
        .kpi-card.warning .kpi-number { color: #b45309; }
        .kpi-card.info .kpi-number { color: #1d4ed8; }
        .kpi-card.danger .kpi-number { color: #b91c1c; }
        .kpi-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-top: 4px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 24px 0 12px 0;
            color: #0f172a;
            display: flex;
            align-items: center;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 24px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: right;
        }
        thead th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        tbody tr:last-child td {
            border-bottom: none;
        }
        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .amount-highlight {
            font-weight: 700;
            color: #0f172a;
        }
        .client-name {
            font-weight: 600;
            color: #0f172a;
        }
        .empty-notice {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 22px 28px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }
        .footer a {
            color: #3b82f6;
            text-decoration: none;
        }
        .btn-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .btn-link:hover {
            text-decoration: underline;
        }
        @media only screen and (max-width: 600px) {
            .kpi-cell {
                display: block;
                width: 100%;
                margin-bottom: 8px;
            }
            .content {
                padding: 16px;
            }
            .header {
                padding: 24px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header -->
        <div class="header">
            <div class="brand-badge">TrueERP • إدارة الاشتراكات والفواتير</div>
            <h1 class="header-title">📊 تقرير دورة تجديد الاشتراكات وإصدار الفواتير</h1>
            <p class="header-meta">
                تاريخ ووقت التشغيل: <strong>{{ $summaryStats['executed_at'] ?? now()->format('Y-m-d H:i') }}</strong>
                &nbsp;|&nbsp;
                الحالة: <strong>{{ $summaryStats['status'] ?? 'ناجحة ✅' }}</strong>
            </p>
        </div>

        <div class="content">
            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-cell">
                    <div class="kpi-card success">
                        <div class="kpi-number">{{ $summaryStats['renewed_count'] ?? count($renewedContracts) }}</div>
                        <div class="kpi-label">إشتراكات تم تجديدها</div>
                    </div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-card info">
                        <div class="kpi-number">{{ $summaryStats['renewed_count'] ?? count($renewedContracts) }}</div>
                        <div class="kpi-label">فواتير تم إصدارها</div>
                    </div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-card warning">
                        <div class="kpi-number">{{ $summaryStats['expired_count'] ?? count($expiredContracts) }}</div>
                        <div class="kpi-label">إشتراكات منتهية وموقوفة</div>
                    </div>
                </div>
                <div class="kpi-cell">
                    <div class="kpi-card {{ ($summaryStats['failed_count'] ?? count($failedOperations)) > 0 ? 'danger' : 'info' }}">
                        <div class="kpi-number">{{ ($summaryStats['failed_count'] ?? count($failedOperations)) > 0 ? ($summaryStats['failed_count'] ?? count($failedOperations)) : ($summaryStats['notified_count'] ?? count($notifiedContracts)) }}</div>
                        <div class="kpi-label">{{ ($summaryStats['failed_count'] ?? count($failedOperations)) > 0 ? 'عمليات فشلت' : 'تنبيهات مرسلة' }}</div>
                    </div>
                </div>
            </div>

            <!-- Section 1: Renewed Contracts -->
            <div class="section-title">
                <span>🔹 الإشتراكات المجددة والفواتير الصادرة بنجاح ({{ count($renewedContracts) }})</span>
            </div>
            @if(count($renewedContracts) > 0)
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم العميل / المنشأة</th>
                                <th>الإشتراك السابق</th>
                                <th>الإشتراك الجديد</th>
                                <th>رقم الفاتورة</th>
                                <th>المبلغ</th>
                                <th>نهاية الاشتراك</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($renewedContracts as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="client-name">{{ $row['client_name'] }}</td>
                                    <td>
                                        @if(!empty($row['old_contract_url']))
                                            <a href="{{ $row['old_contract_url'] }}" class="btn-link">#{{ $row['old_contract_id'] }}</a>
                                        @else
                                            #{{ $row['old_contract_id'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($row['new_contract_url']))
                                            <a href="{{ $row['new_contract_url'] }}" class="btn-link">#{{ $row['new_contract_id'] }}</a>
                                        @else
                                            #{{ $row['new_contract_id'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($row['invoice_url']))
                                            <a href="{{ $row['invoice_url'] }}" class="btn-link">{{ $row['invoice_number'] }}</a>
                                        @else
                                            {{ $row['invoice_number'] }}
                                        @endif
                                    </td>
                                    <td class="amount-highlight">{{ $row['formatted_amount'] }}</td>
                                    <td>{{ $row['end_date'] }}</td>
                                    <td><span class="badge badge-success">مجدد ✅</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-notice">
                    ℹ️ لم تكن هناك إشتراكات مستحقة للتجديد التلقائي في هذه الدورة.
                </div>
            @endif

            <!-- Section 2: Suspended Contracts -->
            @if(count($expiredContracts) > 0)
                <div class="section-title">
                    <span>⚠️ إشتراكات انتهت وتم إيقافها (التجديد التلقائي معطل) ({{ count($expiredContracts) }})</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم العميل / المنشأة</th>
                                <th>رقم الإشتراك</th>
                                <th>تاريخ الانتهاء</th>
                                <th>الإجراء المتخذ</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($expiredContracts as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="client-name">{{ $row['client_name'] }}</td>
                                    <td>#{{ $row['contract_id'] }}</td>
                                    <td>{{ $row['end_date'] }}</td>
                                    <td>{{ $row['action_note'] }}</td>
                                    <td><span class="badge badge-warning">موقوف ⚠️</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Section 3: Near Expiry Alerts -->
            @if(count($notifiedContracts) > 0)
                <div class="section-title">
                    <span>🔔 تنبيهات قرب انتهاء الاشتراكات ({{ count($notifiedContracts) }})</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم العميل / المنشأة</th>
                                <th>رقم الإشتراك</th>
                                <th>تاريخ الانتهاء</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notifiedContracts as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="client-name">{{ $row['client_name'] }}</td>
                                    <td>#{{ $row['contract_id'] }}</td>
                                    <td>{{ $row['end_date'] }}</td>
                                    <td><span class="badge badge-info">تم إشعار النظام 🔔</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Section 4: Failed Operations -->
            @if(count($failedOperations) > 0)
                <div class="section-title" style="color: #b91c1c;">
                    <span>❌ عمليات واجهت أخطاء أثناء التنفيذ ({{ count($failedOperations) }})</span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr style="background-color: #fef2f2;">
                                <th>#</th>
                                <th>العميل / الإشتراك</th>
                                <th>نوع العملية</th>
                                <th>رسالة الخطأ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedOperations as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td class="client-name">{{ $row['client_name'] }}</td>
                                    <td>{{ $row['operation'] }}</td>
                                    <td style="color: #b91c1c;">{{ $row['error_message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 6px 0;">تم إنشاء هذا التقرير آلياً بواسطة نظام <strong>TrueERP</strong>.</p>
            <p style="margin: 0;">جميع الحقوق محفوظة &copy; {{ date('Y') }} TrueERP</p>
        </div>
    </div>
</body>
</html>
