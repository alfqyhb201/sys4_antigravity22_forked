<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $fullHeaderTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', sans-serif;
            letter-spacing: normal !important;
        }

        body {
            background-color: #f8fafc;
            color: #0f172a;
            direction: rtl;
            text-align: right;
            line-height: 1.5;
            font-size: 14px;
            padding: 24px;
        }

        /* ── شريط الأدوات العلوي للشاشة (يختفي بالطباعة) ── */
        .toolbar {
            max-width: 850px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            user-select: none;
        }

        .btn-primary {
            background-color: #50277c;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #3b1c5c;
        }

        .btn-success {
            background-color: #059669;
            color: #ffffff;
        }
        .btn-success:hover {
            background-color: #047857;
        }

        .btn-secondary {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }
        .btn-secondary:hover {
            background-color: #f1f5f9;
        }

        /* ── غلاف التقرير الخارجي للتوسيط ── */
        .report-wrapper {
            max-width: 850px;
            margin: 0 auto;
            width: 100%;
        }

        /* ── بطاقة التقرير الفعلية (التي يتم تصويرها) ── */
        .report-card {
            width: 100%;
            margin: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            direction: rtl;
            text-align: right;
            box-sizing: border-box;
        }

        /* ── ترويسة العنوان المطابقة للشكل المطلوب ── */
        .report-banner {
            background-color: #f6e899;
            border: 1px solid #e2cb64;
            padding: 12px 16px;
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 14px;
            border-radius: 4px;
            direction: rtl;
        }

        /* ── جدول التقرير ── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 10px 14px;
            border: 1px solid #94a3b8;
            text-align: right;
        }

        .report-table th {
            background-color: #374785;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            border-color: #24305e;
        }

        .report-table th.center,
        .report-table td.center {
            text-align: center;
        }

        .report-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .report-table tbody td {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }

        .designer-name {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .task-count {
            font-weight: 800;
            color: #b91c1c;
            font-size: 15px;
        }

        .note-cell {
            color: #64748b;
            font-size: 13px;
        }

        .empty-notice {
            text-align: center;
            padding: 30px 20px;
            color: #16a34a;
            font-size: 15px;
            font-weight: 700;
            background-color: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 6px;
            margin-top: 10px;
        }

        /* ── رسالة التنبيه السريع (Toast) ── */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #1e293b;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, opacity 0.3s ease;
            opacity: 0;
            z-index: 1000;
            pointer-events: none;
        }
        .toast-notification.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* ── قواعد الطباعة ── */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }

            .toolbar,
            .toast-notification {
                display: none !important;
            }

            .report-wrapper,
            .report-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .report-banner {
                background-color: #f6e899 !important;
                border: 1px solid #d8be4f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: #000000 !important;
            }

            .report-table th {
                background-color: #374785 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #000000 !important;
            }

            .report-table td {
                border: 1px solid #000000 !important;
                color: #000000 !important;
            }
        }
    </style>
</head>
<body>

    {{-- شريط أدوات للتحكم بالشاشة، الطباعة، ومشاركة الصورة --}}
    <div class="toolbar">
        <div>
            <span style="font-weight: 700; color: #475569; font-size: 13px;">خيارات التصدير والمشاركة</span>
        </div>
        <div class="toolbar-actions">
            <button type="button" onclick="copyImageToClipboard()" class="btn btn-success" id="copyImgBtn" title="نسخ الصورة مباشرة للصقها في الواتساب">
                🖼️ نسخ كصورة (للواتساب)
            </button>
            <button type="button" onclick="downloadReportImage()" class="btn btn-secondary" id="downloadImgBtn" title="حفظ التقرير كصورة PNG على جهازك">
                📸 حفظ كصورة
            </button>
            <button type="button" onclick="copyReportText()" class="btn btn-secondary" id="copyTextBtn" title="نسخ التقرير كنص">
                📋 نسخ النص
            </button>
            <button type="button" onclick="window.print()" class="btn btn-primary" title="طباعة أو تصدير PDF">
                🖨️ طباعة
            </button>
        </div>
    </div>

    {{-- غلاف التقرير --}}
    <div class="report-wrapper">
        <div class="report-card" id="reportCard">
            {{-- شريط العنوان المطابق للصورة --}}
            <div class="report-banner">
                كشف جرد التصاميم بتاريخ {{ $day }} / {{ $month }} / {{ $year }} م الموافق يوم {{ $dayName }}
            </div>

            {{-- جدول بسيط باسم المصمم وعدد المهام غير المنجزة وعمود ملاحظة --}}
            @if(count($reportRows) > 0)
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="center" style="width: 50px;">#</th>
                            <th style="width: 200px;">اسم المصمم</th>
                            <th class="center" style="width: 170px;">عدد المهام غير المنجزة</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reportRows as $index => $row)
                            <tr>
                                <td class="center">{{ $index + 1 }}</td>
                                <td class="designer-name">{{ $row['designer_name'] }}</td>
                                <td class="center task-count">{{ $row['today_uncompleted'] }}</td>
                                <td class="note-cell"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty-notice">
                    ✓ جميع المصممين أنجزوا كامل مهامهم لهذا اليوم، لا توجد مهام معلقة.
                </div>
            @endif
        </div>
    </div>

    {{-- نافذة الإشعار السريع --}}
    <div id="toast" class="toast-notification"></div>

    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.innerText = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        async function getReportBlob() {
            await document.fonts.ready;
            const element = document.getElementById('reportCard');
            const width = element.offsetWidth;
            const height = element.offsetHeight;

            if (window.htmlToImage) {
                return await window.htmlToImage.toBlob(element, {
                    width: width,
                    height: height,
                    style: {
                        margin: '0',
                        left: '0',
                        top: '0',
                        position: 'static',
                    },
                    pixelRatio: 2.5,
                    backgroundColor: '#ffffff',
                });
            }

            const canvas = await html2canvas(element, {
                scale: 2.5,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                width: width,
                height: height,
                scrollX: 0,
                scrollY: 0,
            });
            return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        }

        async function getReportPngDataUrl() {
            await document.fonts.ready;
            const element = document.getElementById('reportCard');
            const width = element.offsetWidth;
            const height = element.offsetHeight;

            if (window.htmlToImage) {
                return await window.htmlToImage.toPng(element, {
                    width: width,
                    height: height,
                    style: {
                        margin: '0',
                        left: '0',
                        top: '0',
                        position: 'static',
                    },
                    pixelRatio: 2.5,
                    backgroundColor: '#ffffff',
                });
            }

            const canvas = await html2canvas(element, {
                scale: 2.5,
                backgroundColor: '#ffffff',
                useCORS: true,
                logging: false,
                width: width,
                height: height,
                scrollX: 0,
                scrollY: 0,
            });
            return canvas.toDataURL('image/png');
        }

        // 1. نسخ الصورة مباشرة إلى الحافظة للصقها فوراً في الواتساب
        async function copyImageToClipboard() {
            const btn = document.getElementById('copyImgBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ جاري المعالجة...';
            btn.disabled = true;

            try {
                const blob = await getReportBlob();
                if (!blob) {
                    showToast('❌ تعذر إنشاء الصورة');
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    return;
                }

                try {
                    if (navigator.clipboard && navigator.clipboard.write) {
                        const item = new ClipboardItem({ 'image/png': blob });
                        await navigator.clipboard.write([item]);
                        showToast('✓ تم نسخ الصورة للحافظة! يمكنك الآن لصقها في الواتساب (Ctrl + V)');
                    } else {
                        downloadBlob(blob, 'كشف_جرد_التصاميم_{{ $targetDate }}.png');
                        showToast('✓ تم حفظ الصورة على جهازك');
                    }
                } catch (err) {
                    downloadBlob(blob, 'كشف_جرد_التصاميم_{{ $targetDate }}.png');
                    showToast('✓ تم حفظ الصورة (يمكنك إرسالها الآن)');
                }

                btn.innerHTML = '✓ تم بنجاح!';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            } catch (error) {
                console.error(error);
                showToast('❌ حدث خطأ أثناء نسخ الصورة');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // 2. تنزيل الصورة كملف PNG
        async function downloadReportImage() {
            const btn = document.getElementById('downloadImgBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ جاري التحميل...';
            btn.disabled = true;

            try {
                const imageUri = await getReportPngDataUrl();
                const link = document.createElement('a');
                link.download = 'كشف_جرد_التصاميم_{{ $targetDate }}.png';
                link.href = imageUri;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showToast('✓ تم حفظ صورة التقرير بنجاح!');
                btn.innerHTML = '✓ تم الحفظ!';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            } catch (error) {
                console.error(error);
                showToast('❌ تعذر حفظ الصورة');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        function downloadBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // 3. نسخ التقرير كنص
        function copyReportText() {
            let text = "📊 *{{ $fullHeaderTitle }}*\n";
            text += "━━━━━━━━━━━━━━━━━━━━\n";
            @if(count($reportRows) > 0)
                @foreach($reportRows as $index => $row)
                    text += "{{ $index + 1 }}. {{ $row['designer_name'] }}: {{ $row['today_uncompleted'] }} مهام غير منجزة\n";
                @endforeach
            @else
                text += "✓ جميع المصممين أنجزوا مهامهم بنجاح.\n";
            @endif

            navigator.clipboard.writeText(text).then(function() {
                showToast('✓ تم نسخ نص التقرير للحافظة');
            });
        }

        @if($autoPrint)
            window.addEventListener('load', () => window.print());
        @endif
    </script>
</body>
</html>
