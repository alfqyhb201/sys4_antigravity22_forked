<x-filament-panels::page>
    @php
        $isPastWeek = $this->isPastWeek();
        $selectedWeekDate = $selectedWeek ? \Carbon\Carbon::parse($selectedWeek) : null;
        $isCurrentWeek = $selectedWeekDate
            ? $selectedWeekDate->copy()->startOfWeek()->isSameWeek(\Carbon\Carbon::now()->startOfWeek())
            : false;
    @endphp

    {{-- ==================== شريط التنقل بين الأسابيع ==================== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button
                wire:click="goToPreviousWeek"
                icon="heroicon-o-chevron-right"
                icon-position="before"
                color="gray"
                size="sm">
                الأسبوع السابق
            </x-filament::button>

            <x-filament::button
                wire:click="goToCurrentWeek"
                icon="heroicon-o-calendar"
                :color="$isCurrentWeek ? 'primary' : 'gray'"
                size="sm">
                الأسبوع الحالي
            </x-filament::button>

            <x-filament::button
                wire:click="goToNextWeek"
                icon="heroicon-o-chevron-left"
                icon-position="after"
                color="gray"
                size="sm">
                الأسبوع التالي
            </x-filament::button>

            <div class="mx-1 hidden h-6 w-px bg-gray-300 dark:bg-gray-700 sm:block"></div>

            <x-filament::button
                wire:click="exportExcel"
                icon="heroicon-o-arrow-down-tray"
                color="success"
                size="sm"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                title="تصدير جدول التوزيع إلى ملف إكسل احترافي مقسم حسب المصممين">
                <span wire:loading.remove wire:target="exportExcel">تصدير Excel</span>
                <span wire:loading wire:target="exportExcel">جاري التصدير...</span>
            </x-filament::button>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-4 py-1.5 shadow-sm ring-1 ring-gray-300/60 dark:bg-gray-900 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
            <span class="text-xs font-bold text-gray-700 dark:text-gray-200 sm:text-sm">
                {{ $this->getWeekDateRange() }}
            </span>
            @if($isCurrentWeek)
                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                    الأسبوع الحالي
                </span>
            @endif
        </div>
    </div>

    {{-- ==================== تنبيه وضع القراءة فقط للأسابيع السابقة ==================== --}}
    @if($isPastWeek)
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-amber-800 dark:border-amber-500/20 dark:bg-amber-950/40 dark:text-amber-300">
            <div class="flex items-center gap-2">
                <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <span class="text-sm font-bold">هذا الأسبوع مؤرشف (وضع القراءة فقط)</span>
            </div>
            <p class="mt-1 text-xs text-amber-700/90 dark:text-amber-400/80">
                أنت تستعرض سجلاً لأسبوع سابق. التعديل والإضافة والحذف معطلة للحفاظ على سلامة البيانات المؤرشفة.
            </p>
        </div>
    @endif

    @if($assignments->isEmpty())
        <div class="mx-auto flex max-w-xl flex-col items-center justify-center rounded-xl bg-gray-50 p-8 text-center shadow-sm ring-1 ring-gray-300/60 dark:bg-gray-900 dark:ring-white/10">
            <x-heroicon-o-inbox class="h-10 w-10 text-gray-400" />
            @if($isPastWeek)
                <p class="mt-3 text-lg font-semibold text-gray-700 dark:text-gray-200">لا توجد توزيعات في هذا الأسبوع</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">لم يتم توزيع التاقات في هذا الأسبوع.</p>
            @else
                <p class="mt-3 text-lg font-semibold text-gray-700 dark:text-gray-200">لا توجد توزيعات لهذا الأسبوع</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">يمكنك اختيار أسبوع آخر أو البدء بعملية التوزيع.</p>
            @endif
        </div>
    @else
        @php
            $groupedAssignments = $assignments->groupBy('designer.user.name');
            $weekStart = \Carbon\Carbon::parse($selectedWeek);
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $weekStart->copy()->addDays($i);
                if ($day->dayOfWeek !== \Carbon\Carbon::FRIDAY) {
                    $days[] = $day;
                }
            }
        @endphp

        <div x-data="{ activeTab: $wire.entangle('activeTab'), compactMode: false, scrollPositions: {} }" class="space-y-6">
            {{-- ==================== شريط التبويبات والبحث ==================== --}}
            <div class="overflow-x-auto pb-1">
                <div class="inline-flex min-w-full flex-wrap items-center justify-between gap-3 rounded-xl bg-gray-50 p-2 shadow-sm ring-1 ring-gray-300/60 dark:bg-gray-900 dark:ring-white/10">
                    <nav class="inline-flex flex-wrap items-center gap-1.5" aria-label="Tabs">
                        @foreach($groupedAssignments as $designerName => $designerAssignments)
                            @php
                                $tabTagCount = $designerAssignments->sum(fn ($a) => ($a->distributions ?? collect())->count());
                            @endphp
                            <button
                                type="button"
                                @click="
                                    const currentScroller = $el.querySelector('[data-scroll-tab=\'' + activeTab + '\']');
                                    if (currentScroller) {
                                        scrollPositions[activeTab] = currentScroller.scrollLeft;
                                    }
                                    activeTab = @js($designerName);
                                    $nextTick(() => {
                                        const nextScroller = $el.querySelector('[data-scroll-tab=\'' + activeTab + '\']');
                                        if (nextScroller) {
                                            nextScroller.scrollLeft = scrollPositions[activeTab] ?? 0;
                                        }
                                    });
                                "
                                :class="activeTab === @js($designerName)
                                    ? 'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-400/30 font-bold'
                                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-200/70 dark:text-gray-300 dark:hover:text-white dark:hover:bg-white/10 font-medium'"
                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3.5 py-1.5 text-xs transition">
                                <span>{{ $designerName }}</span>
                                <span class="rounded-md bg-white/70 px-1.5 py-0.5 text-[10px] font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    {{ $tabTagCount }}
                                </span>
                            </button>
                        @endforeach
                    </nav>

                    <div class="flex items-center gap-2">
                        {{-- حقل تصفية وبحث العملاء --}}
                        <div class="relative w-44 sm:w-56">
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="searchClient"
                                placeholder="تصفية عميل..."
                                class="w-full rounded-lg border-gray-300 py-1 pe-7 ps-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                            @if(!empty($searchClient))
                                <button
                                    type="button"
                                    wire:click="$set('searchClient', '')"
                                    class="absolute end-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    title="مسح البحث">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                                </button>
                            @endif
                        </div>

                        {{-- تبديل الوضع المضغوط / المريح --}}
                        <button
                            type="button"
                            @click="compactMode = !compactMode"
                            class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-200/70 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/10 dark:hover:text-white"
                            :class="compactMode ? 'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300' : 'ring-1 ring-gray-200 dark:ring-white/10'">
                            <span x-show="!compactMode">وضع مضغوط</span>
                            <span x-show="compactMode">وضع مريح</span>
                        </button>
                    </div>
                </div>
            </div>

            @foreach($groupedAssignments as $designerName => $designerAssignments)
                @php
                    $filteredAssignments = $designerAssignments;
                    if (!empty($searchClient)) {
                        $term = mb_strtolower(trim($searchClient));
                        $filteredAssignments = $designerAssignments->filter(function ($assignment) use ($term) {
                            $company = mb_strtolower($assignment->client?->company ?? '');
                            $name = mb_strtolower($assignment->client?->client_name ?? '');
                            $category = mb_strtolower($assignment->client?->category?->name ?? '');

                            return str_contains($company, $term) || str_contains($name, $term) || str_contains($category, $term);
                        });
                    }

                    $assignmentDistributionsByDate = [];
                    foreach ($designerAssignments as $assignment) {
                        $assignmentDistributionsByDate[$assignment->id] = ($assignment->distributions ?? collect())->groupBy(function ($distribution) {
                            if ($distribution->distribution_date instanceof \Carbon\CarbonInterface) {
                                return $distribution->distribution_date->format('Y-m-d');
                            }

                            return \Carbon\Carbon::parse($distribution->distribution_date)->format('Y-m-d');
                        });
                    }

                    $designerClientCount = $designerAssignments->count();
                    $designerTagCount = $designerAssignments->sum(function ($assignment) {
                        return ($assignment->distributions ?? collect())->count();
                    });

                    $designerEmptyCells = $designerAssignments->sum(function ($assignment) use ($assignmentDistributionsByDate, $days) {
                        $distributionsByDate = $assignmentDistributionsByDate[$assignment->id] ?? collect();

                        return collect($days)->filter(function ($day) use ($distributionsByDate) {
                            return $distributionsByDate->get($day->format('Y-m-d'), collect())->isEmpty();
                        })->count();
                    });

                    $dayTagCounts = collect($days)->mapWithKeys(function ($day) use ($designerAssignments, $assignmentDistributionsByDate) {
                        $dateKey = $day->format('Y-m-d');

                        $count = $designerAssignments->sum(function ($assignment) use ($assignmentDistributionsByDate, $dateKey) {
                            return ($assignmentDistributionsByDate[$assignment->id] ?? collect())->get($dateKey, collect())->count();
                        });

                        return [$dateKey => $count];
                    });
                @endphp

                <div
                    x-show="activeTab === @js($designerName)"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    class="overflow-hidden rounded-xl bg-gray-50 shadow-sm ring-1 ring-gray-300/60 dark:bg-gray-900 dark:ring-white/10">

                    {{-- ترويسة بطاقة المصمم وإحصائياته --}}
                    <div class="flex flex-col gap-3 border-b border-gray-200/80 bg-gray-100/80 px-4 py-4 dark:border-white/10 dark:bg-white/5 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                                <h3 class="text-base font-bold text-gray-950 dark:text-white sm:text-lg">{{ $designerName }}</h3>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-md bg-gray-100 px-2 py-1 font-semibold text-gray-700 ring-1 ring-gray-300/70 dark:bg-gray-900/60 dark:text-gray-200 dark:ring-white/10">
                                    العملاء: {{ $designerClientCount }}
                                </span>
                                <span class="rounded-md bg-primary-100/80 px-2 py-1 font-semibold text-primary-800 ring-1 ring-primary-300 dark:bg-primary-500/15 dark:text-primary-300 dark:ring-primary-500/20">
                                    التاقات: {{ $designerTagCount }}
                                </span>
                                <span class="rounded-md bg-amber-100/80 px-2 py-1 font-semibold text-amber-800 ring-1 ring-amber-300 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/20">
                                    خلايا فارغة: {{ $designerEmptyCells }}
                                </span>
                            </div>
                        </div>

                        @php
                            $firstAssignment = $designerAssignments->first();
                            $designerId = $firstAssignment ? $firstAssignment->designer_id : null;
                        @endphp

                        @if($designerId && !$isPastWeek)
                            <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white/70 p-2 ring-1 ring-gray-300/70 dark:bg-white/5 dark:ring-white/10">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="primary"
                                        icon="heroicon-o-cpu-chip"
                                        class="!rounded-lg !px-3 !font-semibold !shadow-sm transition hover:-translate-y-0.5"
                                        wire:click="distributeSmartForDesigner({{ $designerId }})"
                                        wire:loading.attr="disabled"
                                        wire:target="distributeSmartForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من تنفيذ التوزيع الذكي المتدرج (العالية جداً ثم العالية ثم البقية ثم الأفكار) لهذا المصمم؟"
                                        title="تنفيذ التوزيع الكامل للمصمم بخطوة واحدة">
                                        <span wire:loading.remove wire:target="distributeSmartForDesigner({{ $designerId }})">توزيع ذكي متدرج</span>
                                        <span wire:loading wire:target="distributeSmartForDesigner({{ $designerId }})">جاري التوزيع...</span>
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        icon="heroicon-o-fire"
                                        class="!rounded-lg !px-3 !font-semibold !shadow-sm transition hover:-translate-y-0.5"
                                        wire:click="distributeVeryHighTagsForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من توزيع التاقات ذات الأهمية العالية جداً لهذا المصمم؟"
                                        title="ابدأ بالتاقات الأعلى أهمية">
                                        توزيع العالية جداً
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-o-bolt"
                                        class="!rounded-lg !px-3 !font-semibold !shadow-sm transition hover:-translate-y-0.5"
                                        wire:click="distributeHighTagsForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من توزيع التاقات العالية (High) لهذا المصمم؟"
                                        :disabled="!$this->hasDistributedVeryHigh($designerId)"
                                        title="متاح بعد توزيع العالية جداً">
                                        توزيع العالية
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="success"
                                        icon="heroicon-o-check-badge"
                                        class="!rounded-lg !px-3 !font-semibold !shadow-sm transition hover:-translate-y-0.5"
                                        wire:click="distributeMediumLowTagsForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من توزيع التاقات المتوسطة والمنخفضة لهذا المصمم؟"
                                        :disabled="!$this->hasDistributedHigh($designerId)"
                                        title="أكمل توزيع بقية التاقات">
                                        توزيع البقية
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="info"
                                        icon="heroicon-o-sparkles"
                                        class="!rounded-lg !px-3 !font-semibold !shadow-sm transition hover:-translate-y-0.5"
                                        wire:click="distributeIdeasForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من توزيع الأفكار لهذا المصمم؟"
                                        title="توزيع الأفكار المرتبطة بالتاقات">
                                        توزيع الأفكار
                                    </x-filament::button>
                                </div>

                                <div class="mx-1 hidden h-7 w-px bg-gray-300/80 dark:bg-gray-500 md:block"></div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::button
                                        size="sm"
                                        color="danger"
                                        icon="heroicon-o-trash"
                                        outlined
                                        class="!rounded-lg !px-3 !font-semibold transition hover:-translate-y-0.5"
                                        wire:click="clearTagsForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من حذف جميع تاقات هذا المصمم؟"
                                        title="مسح جميع التاقات للمصمم">
                                        حذف التاقات
                                    </x-filament::button>

                                    <x-filament::button
                                        size="sm"
                                        color="warning"
                                        icon="heroicon-o-light-bulb"
                                        outlined
                                        class="!rounded-lg !px-3 !font-semibold transition hover:-translate-y-0.5"
                                        wire:click="clearIdeasForDesigner({{ $designerId }})"
                                        wire:confirm="هل أنت متأكد من حذف جميع أفكار هذا المصمم؟"
                                        title="مسح جميع الأفكار للمصمم">
                                        حذف الأفكار
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- جدول مصفوفة التوزيع --}}
                    <div class="overflow-x-auto rounded-xl bg-white/70 p-4 ring-1 ring-gray-300/70 dark:bg-gray-950/30 dark:ring-white/10 sm:p-6" dir="rtl" data-scroll-tab="{{ $designerName }}" x-on:scroll.throttle.100ms="if (activeTab === @js($designerName)) { scrollPositions[activeTab] = $event.target.scrollLeft }">
                        <table class="w-full min-w-[1160px] border-separate border-spacing-0 text-right text-sm">
                            <thead>
                                <tr>
                                    <th class="sticky right-0 top-0 z-10 w-[260px] border-b border-gray-300/70 bg-white/95 px-4 py-3 text-sm font-bold text-gray-800 backdrop-blur-sm dark:border-white/10 dark:bg-gray-900/95 dark:text-white">
                                        العميل
                                    </th>
                                    @foreach($days as $day)
                                        <th class="sticky top-0 z-10 w-[150px] border-b border-gray-300/70 bg-white/95 px-3 py-3 text-center backdrop-blur-sm dark:border-white/10 dark:bg-gray-900/95">
                                            <div class="text-sm font-bold text-gray-800 dark:text-white">{{ $day->translatedFormat('l') }}</div>
                                            <div class="mt-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400">{{ $day->format('Y-m-d') }}</div>
                                            <div class="mt-1 inline-flex items-center rounded-full bg-primary-50/80 px-2 py-0.5 text-[10px] font-semibold text-primary-700 ring-1 ring-primary-200/80 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10">
                                                {{ $dayTagCounts->get($day->format('Y-m-d'), 0) }} تاق
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200/80 dark:divide-white/10">
                                @forelse($filteredAssignments as $assignment)
                                    @php $distributionsByDate = $assignmentDistributionsByDate[$assignment->id] ?? collect(); @endphp

                                    <tr class="align-top hover:bg-primary-50/40 dark:hover:bg-white/5">
                                        <td class="sticky right-0 z-30 bg-white/95 px-4 dark:bg-gray-900" :class="compactMode ? 'py-2' : 'py-3'">
                                            <div class="flex flex-col gap-1">
                                                <span class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $assignment->client->company ?? $assignment->client->client_name }}</span>
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $assignment->client->category->name ?? 'بدون تصنيف' }}</span>
                                                    <span class="inline-flex items-center gap-0.5 rounded-md bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold text-blue-700 ring-1 ring-blue-200/80 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20" title="عدد التصاميم الأسبوعية">
                                                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.464 12.657a4.5 4.5 0 00-.964 2.843c0 .884.348 1.733.964 2.35.617.616 1.466.964 2.35.964.954 0 1.874-.383 2.546-1.054l7.121-7.122a1.5 1.5 0 000-2.122l-4.586-4.586a1.5 1.5 0 00-2.122 0L3.464 9.236a4.502 4.502 0 000 3.421z" clip-rule="evenodd" /></svg>
                                                        {{ $assignment->contract->weekly_designs_count ?? 0 }}
                                                    </span>
                                                    <span class="inline-flex items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200/80 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20" title="عدد التاقات الموزعة">
                                                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.5 3A2.5 2.5 0 003 5.5v2.879a2.5 2.5 0 00.732 1.767l6.5 6.5a2.5 2.5 0 003.536 0l2.878-2.878a2.5 2.5 0 000-3.536l-6.5-6.5A2.5 2.5 0 008.38 3H5.5zM6 7a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                                                        {{ ($assignment->distributions ?? collect())->count() }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        @foreach($days as $day)
                                            @php
                                                $dayDistributions = $distributionsByDate->get($day->format('Y-m-d'), collect());
                                            @endphp

                                            <td class="px-2 align-top" :class="compactMode ? 'py-1.5' : 'py-2'">
                                                <div class="flex flex-col gap-1.5" :class="compactMode ? 'min-h-[56px]' : 'min-h-[72px]'">
                                                    @forelse($dayDistributions as $dist)
                                                        @php
                                                            $subtitle = null;
                                                            $subtitleType = 'tag';

                                                            if (!empty($dist->custom_idea)) {
                                                                $subtitle = $dist->custom_idea;
                                                                $subtitleType = 'custom';
                                                            } elseif ($dist->idea) {
                                                                $subtitle = $dist->idea->name;
                                                                $subtitleType = 'idea';
                                                            }

                                                            $cardTone = 'bg-primary-50 dark:bg-primary-500/10';
                                                            $subtitleTone = 'text-primary-700/80 dark:text-primary-300/80';

                                                            if ($subtitleType === 'custom') {
                                                                $cardTone = 'bg-teal-50 dark:bg-teal-500/10';
                                                                $subtitleTone = 'text-teal-700 dark:text-teal-300';
                                                            } elseif ($subtitleType === 'idea') {
                                                                $cardTone = 'bg-amber-50 dark:bg-amber-500/10';
                                                                $subtitleTone = 'text-amber-700 dark:text-amber-300';
                                                            }

                                                            $sendingTime = $dist->scheduled_sending_at
                                                                ? \Carbon\Carbon::parse($dist->scheduled_sending_at)
                                                                : null;

                                                            $sendingTimeFormatted = $sendingTime
                                                                ? $sendingTime->format('H:i | Y-m-d')
                                                                : null;

                                                            $isTimeOverdue = $sendingTime && $sendingTime->isPast();

                                                            $timeBorderClass = !$sendingTime
                                                                ? 'border-s-2 border-s-gray-200 dark:border-s-gray-600'
                                                                : ($isTimeOverdue
                                                                    ? 'border-s-2 border-s-red-400 dark:border-s-red-500'
                                                                    : 'border-s-2 border-s-green-400 dark:border-s-green-500');

                                                            $timeBadgeClass = !$sendingTime
                                                                ? 'text-gray-400 dark:text-gray-500'
                                                                : ($isTimeOverdue
                                                                    ? 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20'
                                                                    : 'bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-500/10 dark:text-green-300 dark:ring-green-500/20');

                                                            $timeIconClass = !$sendingTime
                                                                ? 'text-gray-400 dark:text-gray-500'
                                                                : ($isTimeOverdue
                                                                    ? 'text-red-500 dark:text-red-400'
                                                                    : 'text-green-500 dark:text-green-400');

                                                            $tooltipHtml = '<div class="erp-tt-content">';
                                                            $tooltipHtml .= '<div class="erp-tt-row">';
                                                            $tooltipHtml .= '<span class="erp-tt-label">التاق</span>';
                                                            $tooltipHtml .= '<span class="erp-tt-value">' . e($dist->tag->name) . '</span>';
                                                            $tooltipHtml .= '</div>';

                                                            if ($dist->custom_idea) {
                                                                $tooltipHtml .= '<div class="erp-tt-divider"></div>';
                                                                $tooltipHtml .= '<div class="erp-tt-row">';
                                                                $tooltipHtml .= '<span class="erp-tt-label">نص مخصص</span>';
                                                                $tooltipHtml .= '<span class="erp-tt-value">' . e($dist->custom_idea) . '</span>';
                                                                $tooltipHtml .= '</div>';
                                                            } elseif ($dist->idea) {
                                                                $tooltipHtml .= '<div class="erp-tt-divider"></div>';
                                                                $tooltipHtml .= '<div class="erp-tt-row">';
                                                                $tooltipHtml .= '<span class="erp-tt-label">فكرة</span>';
                                                                $tooltipHtml .= '<span class="erp-tt-value">' . e($dist->idea->name) . '</span>';
                                                                $tooltipHtml .= '</div>';
                                                            }

                                                            if ($sendingTimeFormatted) {
                                                                $tooltipHtml .= '<div class="erp-tt-divider"></div>';
                                                                $tooltipHtml .= '<div class="erp-tt-row">';
                                                                $tooltipHtml .= '<span class="erp-tt-label">توقيت الإرسال</span>';
                                                                $tooltipHtml .= '<span class="erp-tt-value ' . ($isTimeOverdue ? 'erp-tt-value--overdue' : 'erp-tt-value--scheduled') . '">' . e($sendingTimeFormatted) . '</span>';
                                                                $tooltipHtml .= '</div>';
                                                            }

                                                            $tooltipHtml .= '</div>';
                                                        @endphp

                                                        <div class="group flex items-start gap-1 rounded-lg bg-white/80 p-1.5 ring-1 ring-gray-200/70 transition hover:bg-white dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10 {{ $timeBorderClass }}" :class="compactMode ? 'min-h-[44px]' : 'min-h-[50px]'">
                                                            <div class="min-w-0 flex-1 rounded-md px-2 py-1 ring-1 ring-inset ring-gray-200/70 dark:ring-white/10 {{ $cardTone }}" x-tooltip.html="{ content: @js($tooltipHtml), theme: $store.theme, animation: 'erp-scale' }">
                                                                <div class="flex items-center justify-between gap-1.5">
                                                                    <span class="truncate text-xs font-semibold text-gray-900 dark:text-gray-100">{{ $dist->tag->name }}</span>

                                                                    {{-- شارة حالة التاق --}}
                                                                    @if($dist->status === 'reviewing')
                                                                        <span class="shrink-0 rounded bg-purple-100 px-1 py-0.2 text-[8px] font-bold text-purple-700 dark:bg-purple-900/50 dark:text-purple-300">مراجعة</span>
                                                                    @elseif($dist->status === 'sending')
                                                                        <span class="shrink-0 rounded bg-blue-100 px-1 py-0.2 text-[8px] font-bold text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">إرسال</span>
                                                                    @elseif($dist->status === 'completed')
                                                                        <span class="shrink-0 rounded bg-emerald-100 px-1 py-0.2 text-[8px] font-bold text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300">مكتمل</span>
                                                                    @endif

                                                                    @if($sendingTime)
                                                                        <span class="inline-flex shrink-0 items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[9px] font-semibold leading-tight {{ $timeBadgeClass }}" dir="ltr">
                                                                            <svg class="h-2.5 w-2.5 shrink-0 {{ $timeIconClass }}" viewBox="0 0 20 20" fill="currentColor">
                                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd" />
                                                                            </svg>
                                                                            {{ $sendingTime->format('h:i A') }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                @if($subtitle)
                                                                    <div class="mt-0.5 truncate text-[10px] font-medium {{ $subtitleTone }}">
                                                                        {{ $subtitle }}
                                                                    </div>
                                                                @endif

                                                                @if($sendingTime && !$isTimeOverdue)
                                                                    <div class="mt-0.5 text-[9px] font-medium text-green-600 dark:text-green-400">
                                                                        @php
                                                                            $diff = now()->startOfDay()->diffInDays($sendingTime->startOfDay(), false);
                                                                        @endphp
                                                                        @if($diff === 0)
                                                                            اليوم
                                                                        @elseif($diff === 1)
                                                                            غداً
                                                                        @elseif($diff > 1)
                                                                            بعد {{ $diff }} أيام
                                                                        @endif
                                                                    </div>
                                                                @elseif($sendingTime && $isTimeOverdue)
                                                                    <div class="mt-0.5 text-[9px] font-medium text-red-600 dark:text-red-400">
                                                                        @php
                                                                            $diff = $sendingTime->startOfDay()->diffInDays(now()->startOfDay(), false);
                                                                        @endphp
                                                                        @if($diff === 0)
                                                                            متأخر (اليوم)
                                                                        @elseif($diff === 1)
                                                                            متأخر يوم
                                                                        @else
                                                                            متأخر {{ $diff }} أيام
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            {{-- أزرار إجراءات البطاقة --}}
                                                            @php
                                                                $isAdminUser = auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('super_admin');
                                                                $canModifyDist = !$isPastWeek && (!in_array($dist->status, ['sending', 'reviewing', 'completed']) || $isAdminUser);
                                                            @endphp
                                                            @if($canModifyDist)
                                                                <div class="flex shrink-0 flex-col gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                                                    {{-- زر رفع التصميم المنجز --}}
                                                                    <button
                                                                        wire:click="openUploadDesignModal({{ $dist->id }})"
                                                                        class="rounded p-0.5 text-emerald-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950/40"
                                                                        title="رفع التصميم المنجز مباشرة">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    </button>

                                                                    {{-- زر التعديل --}}
                                                                    <button
                                                                        wire:click="openEditDetailsModal({{ $dist->id }})"
                                                                        class="rounded p-0.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-primary-700 dark:hover:bg-white/10"
                                                                        title="تعديل التاق والفكرة">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" /></svg>
                                                                    </button>

                                                                    {{-- زر النقل لمصمم آخر --}}
                                                                    <button
                                                                        wire:click="openTransferDesignerModal({{ $dist->id }})"
                                                                        class="rounded p-0.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-orange-600 dark:hover:bg-white/10"
                                                                        title="نقل إلى مصمم آخر">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M13.207 2.293a1 1 0 01.086 1.32l-.086.094L11.414 5.5H16.5a1.5 1.5 0 011.493 1.356L18 7a1.5 1.5 0 01-1.356 1.493L16.5 8.5h-5.086l1.793 1.793a1 1 0 01.086 1.32l-.086.094a1 1 0 01-1.32.086l-.094-.086-3.5-3.5a1 1 0 01-.086-1.32l.086-.094 3.5-3.5a1 1 0 011.414 0zm-6.414 7a1 1 0 01.086 1.32l-.086.094L5.086 12.5H10.5a1.5 1.5 0 011.493 1.356L12 14a1.5 1.5 0 01-1.356 1.493L10.5 15.5H5.086l1.793 1.793a1 1 0 01.086 1.32l-.086.094a1 1 0 01-1.32.086l-.094-.086-3.5-3.5a1 1 0 01-.086-1.32l.086-.094 3.5-3.5a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                                                    </button>

                                                                    {{-- زر تغيير اليوم ووقت الإرسال --}}
                                                                    <button
                                                                        wire:click="openChangeDateModal({{ $dist->id }})"
                                                                        class="rounded p-0.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-success-600 dark:hover:bg-white/10"
                                                                        title="تغيير يوم التوزيع والإرسال">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" /></svg>
                                                                    </button>

                                                                    {{-- زر حذف التاق الفردي (الميزة رقم 1) --}}
                                                                    <button
                                                                        wire:click="deleteDistribution({{ $dist->id }})"
                                                                        wire:confirm="هل أنت متأكد من حذف هذا التاق؟"
                                                                        class="rounded p-0.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                                                        title="حذف التاق">
                                                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 01.75.75v7a.75.75 0 01-1.5 0v-7a.75.75 0 01.75-.75zm3.59 0a.75.75 0 01.75.75v7a.75.75 0 01-1.5 0v-7a.75.75 0 01.75-.75z" clip-rule="evenodd" /></svg>
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        {{-- الخلية الفارغة مع إمكانية الإضافة السريعة (الميزة رقم 4) --}}
                                                        <div class="group/cell relative flex items-center justify-center rounded-lg border border-dashed border-gray-300/80 bg-gray-50/90 text-xs text-gray-400 transition hover:border-primary-400 hover:bg-primary-50/50 dark:border-white/10 dark:bg-white/5 dark:text-gray-600 dark:hover:border-primary-500/50 dark:hover:bg-primary-950/20" :class="compactMode ? 'min-h-[40px]' : 'min-h-[56px]'">
                                                            @if(!$isPastWeek)
                                                                <button
                                                                    type="button"
                                                                    wire:click="openQuickAddModal({{ $assignment->id }}, '{{ $day->format('Y-m-d') }}')"
                                                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-semibold text-gray-400 opacity-0 transition group-hover/cell:opacity-100 hover:bg-primary-100 hover:text-primary-700 dark:text-gray-500 dark:hover:bg-primary-500/20 dark:hover:text-primary-300"
                                                                    title="إضافة تاق في هذا اليوم">
                                                                    <x-heroicon-o-plus class="h-3.5 w-3.5" />
                                                                    <span>إضافة</span>
                                                                </button>
                                                                <span class="group-hover/cell:hidden">—</span>
                                                            @else
                                                                <span>—</span>
                                                            @endif
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($days) + 1 }}" class="p-8 text-center text-xs text-gray-500 dark:text-gray-400">
                                            @if(!empty($searchClient))
                                                لم يتم العثور على عملاء يطابقون كلمة البحث "<strong>{{ $searchClient }}</strong>" لهذا المصمم.
                                            @else
                                                لا يوجد عملاء معينون لهذا المصمم.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ==================== نافذة الإسناد السريع في الخلية (Quick Add Modal) ==================== --}}
        <x-filament::modal id="quick-add-modal" width="md">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-plus-circle class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    <span>إضافة تاق سريع</span>
                </div>
            </x-slot>

            <x-slot name="description">
                <div class="flex flex-wrap items-center gap-2 pt-1 text-xs text-gray-600 dark:text-gray-300">
                    <span>العميل: <strong class="text-gray-900 dark:text-white">{{ $quickAddClientName }}</strong></span>
                    <span>|</span>
                    <span>المصمم: <strong class="text-gray-900 dark:text-white">{{ $quickAddDesignerName }}</strong></span>
                    <span>|</span>
                    <span>التاريخ: <strong class="text-primary-600 dark:text-primary-400">{{ $quickAddDate }}</strong></span>
                </div>
            </x-slot>

            <div class="space-y-4 py-3 text-right" dir="rtl">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">
                        اختر التاق <span class="text-rose-500">*</span>
                    </label>
                    <select wire:model.live="quickAddTagId" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">-- اختر التاق المناسب --</option>
                        @foreach($quickAddAvailableTags as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                @if(!empty($quickAddAvailableIdeas))
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">
                            الفكرة المقترحة (اختياري)
                        </label>
                        <select wire:model="quickAddIdeaId" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">-- بدون فكرة محددة --</option>
                            @foreach($quickAddAvailableIdeas as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">
                        توجيه أو نص مخصص (اختياري)
                    </label>
                    <textarea
                        wire:model="quickAddCustomIdea"
                        rows="2"
                        placeholder="أضف تفاصيل أو ملاحظات للمصمم..."
                        class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                </div>

                @can('edit_sending_time')
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">
                            وقت الإرسال المجدول (اختياري)
                        </label>
                        <input
                            type="datetime-local"
                            wire:model="quickAddScheduledSendingAt"
                            class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                @endcan
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="saveQuickAdd" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveQuickAdd">إضافة التاق</span>
                        <span wire:loading wire:target="saveQuickAdd">جاري الإضافة...</span>
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'quick-add-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- ==================== نافذة نقل المصمم (Transfer Modal) ==================== --}}
        <x-filament::modal id="transfer-designer-modal" width="sm">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrows-right-left class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                    <span>نقل التاق إلى مصمم آخر</span>
                </div>
            </x-slot>

            <div class="space-y-4 py-4 text-right" dir="rtl">
                <div>
                    <label class="mb-2 block text-xs font-bold text-gray-700 dark:text-gray-200">اختر المصمم البديل</label>
                    <select wire:model="newDesignerId" wire:key="designer-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">-- اختر المصمم --</option>
                        @foreach($designers as $id => $name)
                            <option value="{{ $id }}" wire:key="designer-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="transferDesigner" color="warning" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="transferDesigner">نقل التاق</span>
                        <span wire:loading wire:target="transferDesigner">جاري النقل...</span>
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'transfer-designer-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- ==================== نافذة تعديل التاق (Edit Details Modal) ==================== --}}
        <x-filament::modal id="edit-details-modal" width="md">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-pencil-square class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    <span>تعديل تفاصيل التاق</span>
                </div>
            </x-slot>

            <div class="space-y-4 py-3 text-right" dir="rtl">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">التاق <span class="text-rose-500">*</span></label>
                    <select wire:model.live="newTagId" wire:key="tag-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        @foreach($availableTags as $id => $name)
                            <option value="{{ $id }}" wire:key="tag-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">الفكرة (اختياري)</label>
                    <select wire:model.live="newIdeaId" wire:key="idea-select-{{ $editingDistributionId }}" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">بدون فكرة محددة</option>
                        @foreach($availableIdeas as $id => $name)
                            <option value="{{ $id }}" wire:key="idea-option-{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-200">توجيه مخصص (اختياري)</label>
                    <textarea
                        wire:model="newCustomIdea"
                        rows="2"
                        placeholder="اكتب ملاحظات أو فكرة مخصصة..."
                        class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></textarea>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="updateDetails" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="updateDetails">حفظ التعديلات</span>
                        <span wire:loading wire:target="updateDetails">جاري الحفظ...</span>
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'edit-details-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- ==================== نافذة تغيير تاريخ التاق (Change Date Modal) ==================== --}}
        <x-filament::modal id="change-date-modal" width="sm">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="h-5 w-5 text-success-600 dark:text-success-400" />
                    <span>تغيير يوم التوزيع والإرسال</span>
                </div>
            </x-slot>

            <div class="space-y-4 py-4 text-right" dir="rtl">
                <div>
                    <label class="mb-2 block text-xs font-bold text-gray-700 dark:text-gray-200">اختر اليوم الجديد</label>
                    <select wire:model="newDate" class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        @foreach($this->getWeekDays() as $dayDate)
                            @php $day = \Carbon\Carbon::parse($dayDate); @endphp
                            <option value="{{ $dayDate }}">
                                {{ $day->translatedFormat('l') }} ({{ $day->format('Y-m-d') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(auth()->user()?->can('edit_sending_time') || auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('super_admin'))
                    <div>
                        <label class="mb-2 block text-xs font-bold text-gray-700 dark:text-gray-200">وقت الإرسال (اختياري)</label>
                        <input
                            type="datetime-local"
                            wire:model="newScheduledSendingAt"
                            class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button wire:click="changeDate" color="success" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="changeDate">حفظ الموعد</span>
                        <span wire:loading wire:target="changeDate">جاري الحفظ...</span>
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'change-date-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- ==================== نافذة رفع التصميم المنجز (Upload Design Modal) ==================== --}}
        <x-filament::modal id="upload-design-modal" width="md">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-arrow-up-tray class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <span>رفع التصميم المنجز</span>
                </div>
            </x-slot>

            <x-slot name="description">
                <div class="flex flex-wrap items-center gap-2 pt-1 text-xs text-gray-600 dark:text-gray-300">
                    <span>العميل: <strong class="text-gray-900 dark:text-white">{{ $uploadClientName }}</strong></span>
                    <span>|</span>
                    <span>التاق: <strong class="text-emerald-700 dark:text-emerald-300">{{ $uploadTagName }}</strong></span>
                    <span>|</span>
                    <span>التاريخ: <strong class="text-primary-600 dark:text-primary-400">{{ $uploadDate }}</strong></span>
                </div>
            </x-slot>

            <div class="space-y-4 py-3 text-right" dir="rtl">
                {{-- منطقة رفع الملف --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                        صورة التصميم <span class="text-rose-500">*</span>
                    </label>

                    <div
                        x-data="{
                            previewUrl: null,
                            fileName: '',
                            handleFileChange(e) {
                                const file = e.target.files[0];
                                if (file) {
                                    this.previewUrl = URL.createObjectURL(file);
                                    this.fileName = file.name;
                                } else {
                                    this.previewUrl = null;
                                    this.fileName = '';
                                }
                            },
                            handlePaste(e) {
                                const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
                                if (!items) return;
                                for (let item of items) {
                                    if (item.type.indexOf('image') !== -1) {
                                        const blob = item.getAsFile();
                                        this.previewUrl = URL.createObjectURL(blob);
                                        this.fileName = 'clipboard_image.png';
                                        const container = new DataTransfer();
                                        container.items.add(blob);
                                        const fileInput = $el.querySelector('input[type=file]');
                                        if (fileInput) {
                                            fileInput.files = container.files;
                                            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                        break;
                                    }
                                }
                            }
                        }"
                        @paste.window="if ($wire.uploadDistributionId) handlePaste($event)"
                        class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-emerald-300 bg-white p-4 text-center transition hover:border-emerald-500 dark:border-emerald-800 dark:bg-gray-900 shadow-2xs"
                    >
                        <input
                            type="file"
                            wire:model="uploadFile"
                            @change="handleFileChange($event)"
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0 z-10"
                        />

                        <template x-if="previewUrl">
                            <div class="flex flex-col items-center gap-1.5">
                                <img :src="previewUrl" class="h-24 w-24 rounded-lg object-cover ring-2 ring-emerald-500 shadow-sm" alt="معاينة" />
                                <span x-text="fileName" class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 truncate max-w-[220px]"></span>
                                <span class="text-[10px] text-gray-400">انقر أو اسحب صورة أخرى للاستبدال</span>
                            </div>
                        </template>

                        <template x-if="!previewUrl">
                            <div class="flex flex-col items-center gap-1.5 text-gray-500 dark:text-gray-400">
                                <x-heroicon-o-photo class="h-8 w-8 text-emerald-500" />
                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">انقر لاختيار الصورة أو اسحبها وأفلتها هنا</p>
                                <p class="text-[10px] text-gray-400">PNG, JPG, WEBP حتى 10 ميجابايت (يدعم اللصق Ctrl+V)</p>
                            </div>
                        </template>

                        <div wire:loading wire:target="uploadFile" class="absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-white/90 backdrop-blur-xs dark:bg-gray-900/90 z-20">
                            <x-filament::loading-indicator class="h-6 w-6 text-emerald-600" />
                            <span class="mt-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">جاري معالجة الصورة...</span>
                        </div>
                    </div>

                    @error('uploadFile')
                        <p class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- خيارات الحالة والاعتماد --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1.5">
                        مسار الاعتماد / الحالة الناتجة:
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 rounded-lg border p-2.5 cursor-pointer transition text-xs font-semibold"
                            :class="$wire.uploadTargetStatus === 'sending' ? 'border-emerald-500 bg-emerald-50 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200 ring-1 ring-emerald-500' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'">
                            <input type="radio" wire:model.live="uploadTargetStatus" value="sending" class="sr-only" />
                            <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <span>جاهز للإرسال (معتمد) 🚀</span>
                        </label>

                        <label class="flex items-center gap-2 rounded-lg border p-2.5 cursor-pointer transition text-xs font-semibold"
                            :class="$wire.uploadTargetStatus === 'reviewing' ? 'border-blue-500 bg-blue-50 text-blue-900 dark:bg-blue-900/40 dark:text-blue-200 ring-1 ring-blue-500' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'">
                            <input type="radio" wire:model.live="uploadTargetStatus" value="reviewing" class="sr-only" />
                            <span class="flex h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                            <span>إرسال للمراجعة 🔍</span>
                        </label>
                    </div>
                </div>

                {{-- موعد الإرسال (إذا كانت الحالة sending) --}}
                <div x-show="$wire.uploadTargetStatus === 'sending'" x-transition>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1">
                        موعد الإرسال المجدول (اختياري - افتراضياً تلقائي):
                    </label>
                    <input
                        type="datetime-local"
                        wire:model="uploadScheduledSendingAt"
                        class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                </div>

                {{-- ملاحظات --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1">
                        ملاحظات على التصميم (اختياري):
                    </label>
                    <textarea
                        wire:model="uploadNotes"
                        rows="2"
                        placeholder="أضف تفاصيل أو تعليمات..."
                        class="block w-full rounded-lg border-gray-300 py-2 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    ></textarea>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-filament::button
                        wire:click="saveUploadedDesign"
                        color="success"
                        wire:loading.attr="disabled"
                        wire:target="saveUploadedDesign, uploadFile"
                    >
                        <span wire:loading.remove wire:target="saveUploadedDesign">حفظ واعتماد التصميم</span>
                        <span wire:loading wire:target="saveUploadedDesign">جاري الحفظ والاعتماد...</span>
                    </x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'upload-design-modal' })">
                        إلغاء
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament-panels::page>