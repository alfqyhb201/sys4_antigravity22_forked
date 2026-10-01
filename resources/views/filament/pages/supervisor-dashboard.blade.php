<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6 font-sans" dir="rtl">
        <x-dashboard-panel-header
            title="واجهة الإشراف"
            :description="$isCumulative ? 'نظرة شاملة على تدفق العمل — إحصائيات تراكمية شاملة لجميع الفترات.' : 'نظرة شاملة على تدفق العمل — إحصائيات يوم ' . \Carbon\Carbon::parse($currentFilterDate)->translatedFormat('l، d F Y') . '.'"
            :badgeText="$isCumulative ? 'تراكمي شامل' : ($isToday ? 'اليوم' : \Carbon\Carbon::parse($currentFilterDate)->translatedFormat('d F Y'))"
            :badgeColor="$isCumulative ? 'blue' : 'purple'"
            :metricValue="$completedCount + $sendingCount + $reviewingCount + $pendingCount"
            :metricLabel="$isCumulative ? 'إجمالي التصاميم (تراكمي)' : 'إجمالي تصاميم التاريخ'"
        >
            {{-- زر تبديل نطاق الإحصائيات (تاريخ التقرير / التراكمي الشامل) --}}
            <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50/90 p-1 shadow-2xs dark:border-gray-700 dark:bg-gray-800/90">
                <button
                    type="button"
                    wire:click="setStatsScope('date')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ !$isCumulative ? 'bg-primary-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900 hover:bg-white dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white' }}"
                    title="عرض إحصائيات البطاقات العلوية لتاريخ التقرير المحدد فقط"
                >
                    <x-heroicon-m-calendar-days class="h-3.5 w-3.5" />
                    <span>تاريخ التقرير</span>
                </button>

                <button
                    type="button"
                    wire:click="setStatsScope('cumulative')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $isCumulative ? 'bg-primary-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900 hover:bg-white dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white' }}"
                    title="عرض إحصائيات البطاقات العلوية التراكمية الشاملة"
                >
                    <x-heroicon-m-chart-bar class="h-3.5 w-3.5" />
                    <span>التراكمي الشامل</span>
                </button>
            </div>
        </x-dashboard-panel-header>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card
                icon="heroicon-o-clock"
                label="قيد التنفيذ"
                :count="$pendingCount"
                color="purple"
                :footnote="$isCumulative ? 'إجمالي قيد الإنجاز في النظام' : 'مهام قيد التنفيذ لتاريخ التقرير'" />

            <x-stat-card
                icon="heroicon-o-magnifying-glass-circle"
                label="قيد المراجعة"
                :count="$reviewingCount"
                color="blue"
                badge="تدقيق"
                :footnote="$isCumulative ? 'إجمالي بانتظار قرار المشرف' : 'بانتظار المراجعة لتاريخ التقرير'" />

            <x-stat-card
                icon="heroicon-o-paper-airplane"
                label="جاهزة للإرسال"
                :count="$sendingCount"
                color="orange"
                dot="true"
                arrow="true"
                :footnote="$isCumulative ? 'اضغط لعرض قائمة الإرسال' : 'جاهز للإرسال لتاريخ التقرير'"
                :href="\App\Filament\Pages\SendingFollowUp::getUrl()" />

            <x-stat-card
                icon="heroicon-o-check-badge"
                label="مكتملة"
                :count="$completedCount"
                color="emerald"
                badge="تم التسليم"
                :footnote="$isCumulative ? 'إجمالي المنجز تاريخياً' : 'أنجزت في تاريخ التقرير'" />
        </div>

        {{-- ============================================= --}}
        {{-- التقرير اليومي للمصممين --}}
        {{-- ============================================= --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/90 sm:p-6">
            {{-- Header with Quick Date Navigation --}}
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b pb-4 border-gray-100 dark:border-gray-800">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📊 التقرير اليومي للمصممين</span>
                        <span class="inline-flex items-center rounded-lg bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                            {{ \Carbon\Carbon::parse($currentFilterDate)->translatedFormat('l, d F Y') }}
                        </span>
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">ملخص إنجاز المصممين — مهام اليوم، المتأخرات، والإنجاز.</p>
                </div>

                {{-- أزرار التنقل السريع بين الأيام والطباعة --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-50/80 p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800/80">
                        <button
                            type="button"
                            wire:click="setPreviousDay"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-white hover:text-gray-900 hover:shadow-xs dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            title="اليوم السابق"
                        >
                            <span>◀</span>
                            <span>الأمس</span>
                        </button>

                        <button
                            type="button"
                            wire:click="setToday"
                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $isToday ? 'bg-primary-600 text-white shadow-xs' : 'text-gray-700 hover:bg-white hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700' }}"
                            title="اليوم الحالي"
                        >
                            <x-heroicon-m-calendar-days class="h-3.5 w-3.5" />
                            <span>اليوم</span>
                        </button>

                        <button
                            type="button"
                            wire:click="setNextDay"
                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-white hover:text-gray-900 hover:shadow-xs dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                            title="اليوم التالي"
                        >
                            <span>الغد</span>
                            <span>▶</span>
                        </button>
                    </div>

                    <a
                        href="{{ route('reports.uncompleted-tasks', ['date' => $currentFilterDate]) }}"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        title="عرض التقرير اليومي للمهام غير المنجزة (جاهز للطباعة والتصوير)"
                    >
                        <x-heroicon-m-printer class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                        <span>طباعة تقرير غير المنجز</span>
                    </a>
                </div>
            </div>

            {{-- Filament Table --}}
            <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white dark:border-gray-800 dark:bg-gray-900">
                {{ $this->table }}
            </div>

            {{-- Summary - Clean Mini Stat Pills --}}
            @if($totalDesigners > 0)
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {{-- مصممون نشطون --}}
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-purple-200/50 bg-purple-50 text-purple-600 dark:border-purple-800/40 dark:bg-purple-950/40 dark:text-purple-400">
                        <x-heroicon-o-users class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">المصممون النشطون</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $totalDesigners }}</p>
                    </div>
                </div>

                {{-- مهام اليوم --}}
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-orange-200/50 bg-orange-50 text-orange-600 dark:border-orange-800/40 dark:bg-orange-950/40 dark:text-orange-400">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">مهام اليوم</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $totalToday }}</p>
                    </div>
                </div>

                {{-- متأخرات --}}
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rose-200/50 bg-rose-50 text-rose-600 dark:border-rose-800/40 dark:bg-rose-950/40 dark:text-rose-400">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">متأخرات</p>
                        <p class="text-base font-bold {{ $totalOverdue > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">{{ $totalOverdue }}</p>
                    </div>
                </div>

                {{-- منجز اليوم --}}
                <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-emerald-200/50 bg-emerald-50 text-emerald-600 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-400">
                        <x-heroicon-o-check-badge class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">منجز اليوم</p>
                        <p class="text-base font-bold text-gray-900 dark:text-white">{{ $totalCompleted }}</p>
                    </div>
                </div>
            </div>
            @endif
        </section>

    </div>
</x-filament-panels::page>