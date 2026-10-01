@php
    $properties = $activity->properties ?? collect();
    $rawOld = is_array($properties) ? ($properties['old'] ?? []) : ($properties['old'] ?? []);
    $rawAttributes = is_array($properties) ? ($properties['attributes'] ?? []) : ($properties['attributes'] ?? []);

    $excludeFields = \App\Filament\Pages\ActivityLogPage::$excludedFields ?? [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'email_verified_at',
    ];

    // استبعاد الحقول النظامية والتقنية لتنقية عرض المقارنة
    $old = is_array($rawOld) ? array_diff_key($rawOld, array_flip($excludeFields)) : [];
    $attributes = is_array($rawAttributes) ? array_diff_key($rawAttributes, array_flip($excludeFields)) : [];

    $event = $activity->description;
    $eventColor = \App\Filament\Pages\ActivityLogPage::eventColor($event);


    $subjectName = \App\Filament\Pages\ActivityLogPage::getSubjectName($activity);
    $subjectType = \App\Filament\Pages\ActivityLogPage::translateModel($activity->subject_type ?? '');
    $subjectUrl = \App\Filament\Pages\ActivityLogPage::getSubjectUrl($activity);

    // قاموس ترجمة الحقول العامة إلى العربية
    $fieldLabels = [
        'id' => 'المعرف',
        'name' => 'الاسم',
        'company' => 'اسم الشركة / المؤسسة',
        'client_name' => 'اسم العميل / الشخص المتواصل',
        'title' => 'العنوان',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'tag_groups' => 'مجموعات الوسوم',
        'tag_group_filter' => 'مجموعات الوسوم',
        'tags' => 'الوسوم',
        'tag_group_id' => 'معرف مجموعة الوسوم',
        'client_needs' => 'احتياجات العميل',
        'social_media' => 'وسائل التواصل',
        'location_id' => 'الموقع',
        'category_id' => 'الفئة / التصنيف',
        'fixed_designer_id' => 'المصمم المثبت',
        'address' => 'العنوان التفصيلي',
        'contact_number' => 'رقم التواصل',
        'contact_job' => 'وظيفة جهة الاتصال',
        'tax_number' => 'الرقم الضريبي',
        'is_subscribed_client' => 'عميل مشترك',
        'is_credit_allowed' => 'السماح بالآجل',
        'customer_rating_value' => 'تقييم العميل',
        'wallet_balance' => 'رصيد المحفظة',
        'has_generate_feature' => 'ميزة التوليد (Generate)',
        'generate_types' => 'أنواع التوليد المتاحة',
        'enable_very_high' => 'تفعيل أولوية عالية جداً',
        'importance_weights' => 'أوزان الأهمية',
        'status' => 'الحالة',
        'total_amount' => 'المبلغ الإجمالي',
        'base_currency_amount' => 'المبلغ بالعملة الأساسية',
        'amount' => 'المبلغ',
        'original_amount' => 'المبلغ الأصلي',
        'exchange_rate' => 'سعر الصرف',
        'issue_date' => 'تاريخ الإصدار',
        'due_date' => 'تاريخ الاستحقاق',
        'receipt_date' => 'تاريخ السند',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'notification_date' => 'تاريخ الإشعار',
        'billing_cycle' => 'دورة الفوترة',
        'payment_type' => 'نوع الدفع',
        'payment_method' => 'طريقة الدفع',
        'reference_number' => 'رقم المرجع',
        'notes' => 'ملاحظات',
        'legal_notes' => 'ملاحظات قانونية',
        'description' => 'الوصف',
        'weekly_designs_count' => 'عدد التصاميم الأسبوعية',
        'monthly_designs_count' => 'عدد التصاميم الشهرية',
        'additional_designs_enabled' => 'تفعيل التصاميم الإضافية',
        'additional_design_price' => 'سعر التصميم الإضافي',
        'additional_designs_count' => 'عدد التصاميم الإضافية',
        'additional_designs_balance' => 'رصيد التصاميم الإضافية',
        'simple_requests_enabled' => 'تفعيل الطلبات البسيطة',
        'simple_request_price' => 'سعر الطلب البسيط',
        'simple_requests_count' => 'عدد الطلبات البسيطة',
        'auto_suspension_enabled' => 'تفعيل التوقيف التلقائي',
        'suspension_period_days' => 'مدة التوقيف (أيام)',
        'suspension_days' => 'أيام التوقيف',
        'auto_renewal' => 'تجديد تلقائي',
        'is_under_lawsuit' => 'تحت الملاحقة القانونية',
        'grace_period_days' => 'فترة المهلة (أيام)',
        'marketing_amount' => 'مبلغ التسويق',
        'credit_limit' => 'السقف الائتماني',
        'client_id' => 'معرف العميل',
        'contract_id' => 'معرف الاشتراك',
        'invoice_id' => 'معرف الفاتورة',
        'currency_id' => 'معرف العملة',
        'paid_currency_id' => 'معرف عملة الدفع',
        'designer_id' => 'معرف المصمم',
        'client_designer_id' => 'ربط العميل بالمصمم',
        'tag_id' => 'الوسم / نوع التصميم',
        'idea_id' => 'الفكرة',
        'custom_idea' => 'فكرة مخصصة',
        'distribution_date' => 'تاريخ التوزيع',
        'scheduled_sending_at' => 'موعد الإرسال المجدول',
        'attachment_path' => 'مسار التصميم المرفق',
        'reviewer_feedback' => 'ملاحظات المراجع',
        'reviewer_attachments' => 'مرفقات المراجع',
        'reviewer_id' => 'معرف المراجع',
        'sender_id' => 'معرف المرسل',
        'completed_at' => 'تاريخ الاكتمال',
        'designer_notes' => 'ملاحظات المصمم',
        'created_by_user' => 'أنشئ بواسطة (ID)',
        'updated_by_user' => 'عدّل بواسطة (ID)',
        'effective_date' => 'تاريخ النفاذ',
        'from_currency_id' => 'من عملة',
        'to_currency_id' => 'إلى عملة',
        'rate' => 'المعدل/السعر',
        'key' => 'المفتاح',
        'value' => 'القيمة',
        'is_active' => 'نشط',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'deleted_at' => 'تاريخ الحذف',
    ];

    $formatValue = function ($val, bool $isNew = false, ?string $key = null) {
        if (is_null($val) || $val === '') {
            return '<span class="text-gray-400 dark:text-gray-500 italic">فارغ (لا يوجد)</span>';
        }
        if (is_bool($val)) {
            return $val
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">نعم</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300">لا</span>';
        }
        if (is_string($val) && ($key === 'attachment_path' || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $val))) {
            $imgUrl = asset('storage/' . ltrim($val, '/'));
            return '<div class="flex items-center gap-2">
                <a href="' . e($imgUrl) . '" target="_blank" rel="noopener noreferrer" class="group relative inline-block border border-gray-200 dark:border-gray-700 rounded overflow-hidden shadow-xs hover:border-primary-500">
                    <img src="' . e($imgUrl) . '" class="w-12 h-12 object-cover transition transform group-hover:scale-110" alt="معاينة" />
                </a>
                <span class="text-[11px] font-mono text-gray-500 dark:text-gray-400 break-all">' . e($val) . '</span>
            </div>';
        }
        if (is_array($val)) {
            if (empty($val)) {
                return '<span class="text-gray-400 dark:text-gray-500 italic">فارغ (لا يوجد)</span>';
            }

            $isList = array_is_list($val);
            if ($isList) {
                $badgeBg = $isNew
                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800'
                    : 'bg-rose-50 text-rose-800 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800';

                $badges = array_map(function ($item) use ($badgeBg) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ' . $badgeBg . '">' . e((string) $item) . '</span>';
                }, $val);

                return '<div class="flex flex-wrap gap-1.5">' . implode('', $badges) . '</div>';
            }

            return '<code class="text-xs bg-gray-100 dark:bg-gray-800 p-1.5 rounded font-mono block overflow-x-auto whitespace-pre leading-relaxed">' . e(json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</code>';
        }

        $statusLabels = [
            'pending' => 'قيد الانتظار',
            'in_progress' => 'قيد العمل',
            'reviewing' => 'قيد المراجعة',
            'rejected' => 'مرفوض',
            'sending' => 'جاهز للإرسال',
            'completed' => 'مكتمل',
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'posted' => 'مرحلة',
            'paid' => 'مدفوعة',
            'partially_paid' => 'مدفوعة جزئياً',
            'unpaid' => 'غير مدفوعة',
            'draft' => 'مسودة',
            'cancelled' => 'ملغي',
            'resolved' => 'تم الحل',
            'new' => 'جديد',
        ];

        if ($key === 'status' && isset($statusLabels[$val])) {
            return '<span class="font-semibold">' . e($statusLabels[$val]) . '</span> <span class="text-[10px] text-gray-400 font-mono">(' . e($val) . ')</span>';
        }

        return e((string) $val);
    };

    $getLabel = function ($key) use ($fieldLabels) {
        return $fieldLabels[$key] ?? str_replace('_', ' ', $key);
    };
@endphp

<div class="space-y-4 text-sm dir-rtl">
    <!-- ملخص النشاط -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800">
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">المستخدم المسؤول:</span>
            <span class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-1.5">
                <span class="inline-block w-2 h-2 rounded-full bg-primary-500"></span>
                {{ $activity->causer?->name ?? 'النظام / تلقائي' }}
            </span>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">نوع الحدث:</span>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold
                    @if(in_array($eventColor, ['success', 'emerald'])) bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300
                    @elseif($eventColor === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300
                    @elseif($eventColor === 'danger') bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300
                    @elseif($eventColor === 'info') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300
                    @else bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-300 @endif">
                    {{ \App\Filament\Pages\ActivityLogPage::translateEvent($event) }}
                </span>
            </div>
            @if(!in_array($event, ['created', 'updated', 'deleted', 'restored']))
                <span class="text-[11px] text-gray-600 dark:text-gray-300 block mt-1 leading-snug font-medium">{{ $event }}</span>
            @endif
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">نوع العنصر:</span>
            <span class="font-medium text-gray-900 dark:text-gray-100">
                {{ $subjectType }} <span class="text-xs text-gray-400 font-mono">#{{ $activity->subject_id }}</span>
            </span>
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">
                {{ $activity->subject_type === 'App\Models\Client' ? 'اسم العميل:' : 'اسم العنصر / العميل:' }}
            </span>
            @if ($subjectUrl)
                <a href="{{ $subjectUrl }}" target="_blank" rel="noopener noreferrer"
                   class="font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 underline decoration-dotted inline-flex items-center gap-1 transition-colors"
                   title="الانتقال إلى العنصر الأصلي في تبويب جديد">
                    <span>{{ $subjectName ?: '—' }}</span>
                    <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5 inline text-primary-500" />
                </a>
            @else
                <span class="font-bold text-primary-600 dark:text-primary-400">
                    {{ $subjectName ?: '—' }}
                </span>
            @endif
        </div>
        <div>
            <span class="text-xs text-gray-500 dark:text-gray-400 block mb-0.5">التاريخ والوقت:</span>
            <span class="font-medium text-gray-900 dark:text-gray-100">
                {{ $activity->created_at?->format('Y-m-d H:i:s') }}
                <span class="text-[11px] text-gray-500">({{ $activity->created_at?->diffForHumans() }})</span>
            </span>
        </div>
    </div>

    <!-- شريط الانتقال المباشر للعنصر الأصلي -->
    @if ($subjectUrl)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 p-3 bg-primary-50/70 dark:bg-primary-950/40 rounded-lg border border-primary-200/80 dark:border-primary-800/60 shadow-xs">
            <div class="flex items-center gap-2.5">
                <div class="p-1.5 bg-primary-100 dark:bg-primary-900/60 rounded-md text-primary-600 dark:text-primary-400">
                    <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                </div>
                <div>
                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100 block">
                        الانتقال إلى {{ $subjectType }}: <span class="text-primary-700 dark:text-primary-300">{{ $subjectName ?: ('#' . $activity->subject_id) }}</span>
                    </span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                        فتح صفحة المورد الأصلي في تبويب جديد لإجراء التعديلات أو الاستعراض
                    </span>
                </div>
            </div>
            <a href="{{ $subjectUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-bold text-white bg-primary-600 hover:bg-primary-700 active:bg-primary-800 transition shadow-xs">
                <span>فتح العنصر الأصلي</span>
                <x-heroicon-m-arrow-up-right class="w-3.5 h-3.5" />
            </a>
        </div>
    @endif

    @if ($event === 'updated')
        <!-- حظر التعديل: مقارنة البيانات القديمة بالجديدة -->
        <div class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
            <div class="bg-amber-50 dark:bg-amber-950/40 px-4 py-2.5 border-b border-amber-200 dark:border-amber-900/50 flex items-center justify-between">
                <span class="font-bold text-amber-900 dark:text-amber-300 text-xs flex items-center gap-1.5">
                    📝 تفاصيل التغييرات (الحقول المعدّلة فقط)
                </span>
                <span class="text-xs text-amber-700 dark:text-amber-400 font-mono bg-amber-100/70 dark:bg-amber-900/60 px-2 py-0.5 rounded">
                    {{ count($attributes) }} حقل/حقول
                </span>
            </div>

            @if (count($attributes) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="p-2.5 w-1/3">الحقل</th>
                                <th class="p-2.5 w-1/3 text-rose-700 dark:text-rose-400 bg-rose-50/50 dark:bg-rose-950/20">القيمة السابقة</th>
                                <th class="p-2.5 w-1/3 text-emerald-700 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20">القيمة الجديدة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($attributes as $key => $newValue)
                                @php $oldValue = $old[$key] ?? null; @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="p-2.5 font-medium text-gray-900 dark:text-gray-200 align-top">
                                        {{ $getLabel($key) }}
                                        <span class="block text-[10px] text-gray-400 font-mono mt-0.5">{{ $key }}</span>
                                    </td>
                                    <td class="p-2.5 bg-rose-50/30 dark:bg-rose-950/10 text-rose-900 dark:text-rose-200 text-xs align-top">
                                        {!! $formatValue($oldValue, false, $key) !!}
                                    </td>
                                    <td class="p-2.5 bg-emerald-50/30 dark:bg-emerald-950/10 text-emerald-900 dark:text-green-200 text-xs align-top">
                                        {!! $formatValue($newValue, true, $key) !!}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-xs">
                    لا توجد تفاصيل حقول مسجلة في هذا التعديل.
                </div>
            @endif
        </div>

    @elseif ($event === 'deleted')
        <!-- حظر الحذف: عرض البيانات التي كانت محذوفة -->
        <div class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
            <div class="bg-rose-50 dark:bg-rose-950/40 px-4 py-2.5 border-b border-rose-200 dark:border-rose-900/50 flex items-center justify-between">
                <span class="font-bold text-rose-900 dark:text-rose-300 text-xs flex items-center gap-1.5">
                    🗑️ البيانات التي تم حذفها
                </span>
                <span class="text-xs text-rose-700 dark:text-rose-400 font-mono bg-rose-100/70 dark:bg-rose-900/60 px-2 py-0.5 rounded">
                    {{ count($old) }} حقل/حقول
                </span>
            </div>

            @if (count($old) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="p-2.5 w-1/3">الحقل</th>
                                <th class="p-2.5 w-2/3 text-rose-700 dark:text-rose-400">القيمة المحذوفة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($old as $key => $value)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="p-2.5 font-medium text-gray-900 dark:text-gray-200 align-top">
                                        {{ $getLabel($key) }}
                                        <span class="block text-[10px] text-gray-400 font-mono mt-0.5">{{ $key }}</span>
                                    </td>
                                    <td class="p-2.5 bg-rose-50/20 dark:bg-rose-950/10 text-rose-900 dark:text-rose-200 text-xs align-top">
                                        {!! $formatValue($value, false, $key) !!}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-xs">
                    لا توجد بيانات محفوظة لهذا العنصر المحذوف.
                </div>
            @endif
        </div>

    @else
        <!-- حظر الإنشاء / أحداث أخرى -->
        <div class="border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
            <div class="bg-emerald-50 dark:bg-emerald-950/40 px-4 py-2.5 border-b border-emerald-200 dark:border-emerald-900/50 flex items-center justify-between">
                <span class="font-bold text-emerald-900 dark:text-emerald-300 text-xs flex items-center gap-1.5">
                    ✨ البيانات الأولية المضافة
                </span>
                <span class="text-xs text-emerald-700 dark:text-emerald-400 font-mono bg-emerald-100/70 dark:bg-emerald-900/60 px-2 py-0.5 rounded">
                    {{ count($attributes) }} حقل/حقول
                </span>
            </div>

            @if (count($attributes) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-semibold border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="p-2.5 w-1/3">الحقل</th>
                                <th class="p-2.5 w-2/3 text-emerald-700 dark:text-emerald-400">القيمة المضافة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($attributes as $key => $value)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                    <td class="p-2.5 font-medium text-gray-900 dark:text-gray-200 align-top">
                                        {{ $getLabel($key) }}
                                        <span class="block text-[10px] text-gray-400 font-mono mt-0.5">{{ $key }}</span>
                                    </td>
                                    <td class="p-2.5 bg-emerald-50/20 dark:bg-emerald-950/10 text-emerald-900 dark:text-emerald-200 text-xs align-top">
                                        {!! $formatValue($value, true, $key) !!}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-gray-500 dark:text-gray-400 text-xs">
                    لا توجد بيانات مسجلة في هذا الحدث.
                </div>
            @endif
        </div>
    @endif
</div>
