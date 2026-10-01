<x-filament-panels::page>
    @php
        $stats = $this->getQuickStats();
    @endphp

    <div class="space-y-4" dir="rtl">
        {{-- ============================================= --}}
        {{-- شريط الإحصائيات السريع (Quick Stats Bar) --}}
        {{-- ============================================= --}}
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 sm:p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900/90">
            {{-- رأس شريط الإحصائيات مع محدد الفترة الزمنية --}}
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-3.5 border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                        <x-heroicon-m-chart-bar class="h-5 w-5" />
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>نبض النشاط والتغييرات</span>
                            <span class="inline-flex items-center rounded-lg bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                                {{ $stats['periodLabel'] }}
                            </span>
                        </h2>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">نظرة فورية على العمليات الحساسة ونشاط النظام مع إمكانية التحقيق السريع.</p>
                    </div>
                </div>

                {{-- تبويبات فلترة الفترة الزمنية للإحصائيات --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50/90 p-1 shadow-2xs dark:border-gray-700 dark:bg-gray-800/90">
                        @php
                            $periods = [
                                'today' => 'اليوم',
                                '7days' => 'آخر 7 أيام',
                                'this_month' => 'هذا الشهر',
                                'all' => 'الكل',
                            ];
                        @endphp
                        @foreach ($periods as $pKey => $pLabel)
                            <button
                                type="button"
                                wire:click="setStatsPeriod('{{ $pKey }}')"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $this->statsPeriod === $pKey ? 'bg-white text-primary-700 shadow-xs dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                            >
                                <span>{{ $pLabel }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- زر تطبيق الفترة على الجدول --}}
                    <button
                        type="button"
                        wire:click="applyStatsPeriodToTable"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-primary-200 bg-primary-50/70 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-300 dark:hover:bg-primary-900/60 transition shadow-2xs"
                        title="تطبيق نطاق هذه الفترة على فلتر التاريخ في الجدول"
                    >
                        <x-heroicon-m-calendar-days class="h-3.5 w-3.5" />
                        <span>تطبيق على الجدول</span>
                    </button>
                </div>
            </div>

            {{-- شبكة بطاقات الإحصائيات الأربع --}}
            <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                {{-- البطاقة 1: حجم الأنشطة --}}
                <div
                    wire:click="applyStatsPeriodToTable"
                    class="group relative flex flex-col justify-between rounded-xl border border-blue-200/70 bg-gradient-to-br from-blue-50/40 to-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-xs cursor-pointer dark:border-blue-900/50 dark:from-blue-950/20 dark:to-gray-900"
                    title="اضغط لتصفية الجدول حسب الفترة المحددة"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400 border border-blue-200/50 dark:border-blue-800/50">
                            <x-heroicon-o-bolt class="h-5 w-5" />
                        </div>
                        <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200/60 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800/40">
                            {{ $stats['periodLabel'] }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">إجمالي الأنشطة</p>
                        <p class="text-2xl font-black tracking-tight text-gray-900 dark:text-white mt-0.5">
                            {{ number_format($stats['total']) }}
                        </p>
                    </div>
                    <div class="mt-3 border-t border-blue-100/80 pt-2 text-[11px] text-blue-600 dark:border-blue-900/40 dark:text-blue-400 flex items-center justify-between">
                        <span>حجم العمليات المسجلة</span>
                        <x-heroicon-m-arrow-left class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1" />
                    </div>
                </div>

                {{-- البطاقة 2: عمليات الحذف (تنبيه أمني) --}}
                <div
                    wire:click="filterByAction('deleted')"
                    class="group relative flex flex-col justify-between rounded-xl border {{ $stats['deleted'] > 0 ? 'border-rose-300/80 bg-gradient-to-br from-rose-50/60 to-white dark:border-rose-900/60 dark:from-rose-950/30' : 'border-gray-200/70 bg-gradient-to-br from-gray-50/40 to-white dark:border-gray-800 dark:from-gray-800/20' }} p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-rose-400 hover:shadow-xs cursor-pointer dark:to-gray-900"
                    title="اضغط لتصفية الجدول وعرض عمليات الحذف فقط للتحقيق"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $stats['deleted'] > 0 ? 'bg-rose-500/10 text-rose-600 border border-rose-200/60 dark:bg-rose-500/20 dark:text-rose-400 dark:border-rose-800/60' : 'bg-emerald-500/10 text-emerald-600 border border-emerald-200/60 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-800/60' }}">
                            @if ($stats['deleted'] > 0)
                                <x-heroicon-o-trash class="h-5 w-5" />
                            @else
                                <x-heroicon-o-shield-check class="h-5 w-5" />
                            @endif
                        </div>
                        @if ($stats['deleted'] > 0)
                            <span class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                تنبيه أمني
                            </span>
                        @else
                            <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                                سليم (0)
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">عمليات الحذف</p>
                        <p class="text-2xl font-black tracking-tight {{ $stats['deleted'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }} mt-0.5">
                            {{ number_format($stats['deleted']) }}
                        </p>
                    </div>
                    <div class="mt-3 border-t border-rose-100/80 pt-2 text-[11px] text-rose-600 dark:border-rose-900/40 dark:text-rose-400 flex items-center justify-between">
                        <span>{{ $stats['deleted'] > 0 ? 'اضغط للتحقيق وتصفية السجلات' : 'لا توجد عمليات حذف' }}</span>
                        <x-heroicon-m-arrow-left class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1" />
                    </div>
                </div>

                {{-- البطاقة 3: أكثر مستخدم نشاطاً --}}
                <div
                    @if(!empty($stats['topUser']['id']))
                        wire:click="filterByUser({{ $stats['topUser']['id'] }}, '{{ addslashes($stats['topUser']['name']) }}')"
                    @endif
                    class="group relative flex flex-col justify-between rounded-xl border border-purple-200/70 bg-gradient-to-br from-purple-50/40 to-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-purple-300 hover:shadow-xs {{ !empty($stats['topUser']['id']) ? 'cursor-pointer' : '' }} dark:border-purple-900/50 dark:from-purple-950/20 dark:to-gray-900"
                    title="{{ !empty($stats['topUser']['id']) ? 'اضغط لتصفية الجدول لعرض نشاط ' . $stats['topUser']['name'] : '' }}"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-500/10 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400 border border-purple-200/50 dark:border-purple-800/50">
                            <x-heroicon-o-user class="h-5 w-5" />
                        </div>
                        @if (!empty($stats['topUser']))
                            <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200/60 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800/40 font-mono">
                                {{ number_format($stats['topUser']['count']) }} عملية
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">أكثر مستخدم نشاطاً</p>
                        <p class="text-base font-bold tracking-tight text-gray-900 dark:text-white mt-1 truncate" title="{{ $stats['topUser']['name'] ?? '—' }}">
                            {{ $stats['topUser']['name'] ?? '—' }}
                        </p>
                    </div>
                    <div class="mt-3 border-t border-purple-100/80 pt-2 text-[11px] text-purple-600 dark:border-purple-900/40 dark:text-purple-400 flex items-center justify-between">
                        <span>{{ !empty($stats['topUser']['id']) ? 'اضغط لتصفية نشاط المستخدم' : 'لا يوجد نشاط مسجل' }}</span>
                        @if (!empty($stats['topUser']['id']))
                            <x-heroicon-m-arrow-left class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1" />
                        @endif
                    </div>
                </div>

                {{-- البطاقة 4: أكثر نوع عنصر تعديلاً / نشاطاً --}}
                <div
                    @if(!empty($stats['topSubject']['type']))
                        wire:click="filterBySubjectType('{{ addslashes($stats['topSubject']['type']) }}', '{{ addslashes($stats['topSubject']['name']) }}')"
                    @endif
                    class="group relative flex flex-col justify-between rounded-xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/40 to-white p-4 shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-xs {{ !empty($stats['topSubject']['type']) ? 'cursor-pointer' : '' }} dark:border-emerald-900/50 dark:from-emerald-950/20 dark:to-gray-900"
                    title="{{ !empty($stats['topSubject']['type']) ? 'اضغط لتصفية الجدول لعرض حركة ' . $stats['topSubject']['name'] : '' }}"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-800/50">
                            <x-heroicon-o-cube class="h-5 w-5" />
                        </div>
                        @if (!empty($stats['topSubject']))
                            <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/40 font-mono">
                                {{ number_format($stats['topSubject']['count']) }} حركة
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-bold text-gray-500 dark:text-gray-400">أكثر نوع عنصر نشاطاً</p>
                        <p class="text-base font-bold tracking-tight text-gray-900 dark:text-white mt-1 truncate" title="{{ $stats['topSubject']['name'] ?? '—' }}">
                            {{ $stats['topSubject']['name'] ?? '—' }}
                        </p>
                    </div>
                    <div class="mt-3 border-t border-emerald-100/80 pt-2 text-[11px] text-emerald-600 dark:border-emerald-900/40 dark:text-emerald-400 flex items-center justify-between">
                        <span>{{ !empty($stats['topSubject']['type']) ? 'اضغط لتصفية نشاط هذا النموذج' : 'لا يوجد نشاط مسجل' }}</span>
                        @if (!empty($stats['topSubject']['type']))
                            <x-heroicon-m-arrow-left class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1" />
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- شريط إشعار التصفية السريعة النشطة (إن وجدت) --}}
        @if ($activeQuickFilter)
            <div class="flex items-center justify-between p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800/60 text-xs text-amber-900 dark:text-amber-200 shadow-2xs">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-funnel class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    <span>تصفية سريعة مطبقة على الجدول:</span>
                    <span class="font-bold bg-amber-100 dark:bg-amber-900/70 px-2 py-0.5 rounded text-amber-800 dark:text-amber-300">
                        {{ $activeQuickFilter }}
                    </span>
                </div>
                <button
                    type="button"
                    wire:click="resetQuickFilter"
                    class="inline-flex items-center gap-1 font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 underline decoration-dotted"
                >
                    <span>إلغاء التصفية وعرض الكل</span>
                    <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                </button>
            </div>
        @endif

        {{-- ============================================= --}}
        {{-- جدول سجل النشاطات --}}
        {{-- ============================================= --}}
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
