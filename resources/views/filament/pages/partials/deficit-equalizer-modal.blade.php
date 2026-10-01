@if($analysis)
    <div class="space-y-4 text-right" dir="rtl">
        <!-- بطاقات المؤشرات السريعة (KPIs) -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                <div class="text-xs font-medium text-amber-700 dark:text-amber-300">عجز التصاميم المطلوب</div>
                <div class="mt-1 text-xl font-extrabold text-amber-600 dark:text-amber-400">
                    {{ $analysis['total_designs_needed'] }} <span class="text-xs font-normal">تصميم</span>
                </div>
            </div>

            <div class="rounded-xl border border-primary-200 bg-primary-50/70 p-3 text-center dark:border-primary-900/40 dark:bg-primary-950/20">
                <div class="text-xs font-medium text-primary-700 dark:text-primary-300">عقود معلقة للتوزيع</div>
                <div class="mt-1 text-xl font-extrabold text-primary-600 dark:text-primary-400">
                    {{ $analysis['total_contracts_count'] }} <span class="text-xs font-normal">عقد</span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 text-center dark:border-gray-700 dark:bg-gray-800/50">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">المصممون المتاحون</div>
                <div class="mt-1 text-xl font-extrabold text-gray-800 dark:text-gray-200">
                    {{ $analysis['designers_count'] }} <span class="text-xs font-normal">مصمم</span>
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <div class="text-xs font-medium text-emerald-700 dark:text-emerald-300">متوسط الزيادة/مصمم</div>
                <div class="mt-1 text-xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    +{{ $analysis['average_extra_per_designer'] }} <span class="text-xs font-normal">تصميم</span>
                </div>
            </div>
        </div>

        <!-- ملاحظة الزيادة المؤقتة -->
        <div class="flex items-center gap-2 rounded-lg bg-gray-100/80 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800/60 dark:text-gray-400">
            <svg class="h-4 w-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>هذه الزيادة استثنائية <strong>لهذا الأسبوع فقط</strong> ولا تُعدّل السعة الأساسية للمصممين في قاعدة البيانات.</span>
        </div>

        <!-- جدول خطة التوزيع المؤقت -->
        <div class="rounded-xl border border-gray-200 shadow-xs dark:border-gray-700">
            <div class="border-b border-gray-200 bg-gray-50/90 px-4 py-2.5 text-xs font-bold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                توزيع الحصص الإضافية على المصممين (خطة مؤقتة)
            </div>

            <div class="max-h-72 overflow-y-auto">
                <table class="w-full text-right text-xs">
                    <thead class="sticky top-0 bg-gray-100/95 text-gray-600 backdrop-blur-xs dark:bg-gray-800/95 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-2.5 font-semibold">المصمم</th>
                            <th class="px-3 py-2.5 text-center font-semibold">الموزع حالياً</th>
                            <th class="px-3 py-2.5 text-center font-semibold">السعة الأساسية</th>
                            <th class="px-3 py-2.5 text-center font-semibold">الزيادة المؤقتة</th>
                            <th class="px-4 py-2.5 text-center font-semibold">السعة المؤقتة الجديدة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 dark:bg-gray-900/30">
                        @foreach($analysis['designer_projections'] as $proj)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $proj['designer_name'] }}
                                </td>
                                <td class="px-3 py-2.5 text-center text-gray-600 dark:text-gray-400">
                                    {{ $proj['current_designs'] }} تصميم
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500">({{ $proj['current_clients'] }} عميل)</span>
                                </td>
                                <td class="px-3 py-2.5 text-center font-medium text-gray-500 dark:text-gray-400">
                                    {{ $proj['current_max'] }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        +{{ $proj['extra_capacity'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center font-bold text-primary-600 dark:text-primary-400">
                                    {{ $proj['new_temp_capacity'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@else
    <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        لا يوجد عجز في السعة حالياً أو تم توزيع جميع الاشتراكات بنجاح.
    </div>
@endif
