<x-filament-panels::page>
    @php
        $selectedWeekDate = $selectedWeek ? \Carbon\Carbon::parse($selectedWeek) : null;
        $isCurrentWeek = $selectedWeekDate
            ? $selectedWeekDate->copy()->startOfWeek()->isSameWeek(\Carbon\Carbon::now()->startOfWeek())
            : false;
        $weekStartDate = $selectedWeekDate?->copy()->startOfWeek();
        $weekEndDate = $selectedWeekDate?->copy()->endOfWeek();
    @endphp

    <div class="mx-auto w-full max-w-7xl space-y-7">
        <x-filament::section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-900/50">
            <x-slot name="heading">
                توزيع المصممين الأسبوعي
            </x-slot>

            <x-slot name="description">
                تحكم سريع بالأسبوع المستهدف ومتابعة حالة التوزيع بشكل أوضح
            </x-slot>

            <div class="space-y-5">
                <div class="flex flex-wrap items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50/90 p-2 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
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
                        color="primary"
                        size="md">
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
                </div>

                @if($selectedWeekDate)
                    <div class="space-y-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-4 shadow-sm dark:border-primary-800 dark:bg-primary-900/20">
                        <div class="flex flex-wrap items-center justify-center gap-2 text-sm font-medium text-primary-700 dark:text-primary-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-11 9h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v11a2 2 0 002 2z" />
                            </svg>

                            <span>الأسبوع المحدد:</span>
                            <span class="text-base font-bold">{{ $selectedWeekDate->format('Y-m-d') }}</span>

                            @if($isCurrentWeek)
                                <span class="rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-semibold text-success-700 dark:bg-success-900/30 dark:text-success-300">
                                    الأسبوع الحالي
                                </span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($selectedWeek)->startOfWeek()->lt(\Carbon\Carbon::now()->startOfWeek()) ? 'أسبوع سابق' : 'أسبوع قادم' }}
                                </span>
                            @endif
                        </div>

                        @if($weekStartDate && $weekEndDate)
                            <div class="flex flex-wrap items-center justify-center gap-2 text-xs text-primary-700/90 dark:text-primary-300/90">
                                <span class="rounded-md bg-white/70 px-2 py-1 font-medium dark:bg-gray-800/50">
                                    من {{ $weekStartDate->format('Y-m-d') }}
                                </span>
                                <span class="rounded-md bg-white/70 px-2 py-1 font-medium dark:bg-gray-800/50">
                                    إلى {{ $weekEndDate->format('Y-m-d') }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section class="rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm dark:border-gray-700 dark:bg-gray-900/80">
            <x-slot name="heading">
                التوزيع الحالي
            </x-slot>

            <x-slot name="description">
                عرض وإدارة توزيع العملاء على المصممين للأسبوع المحدد
            </x-slot>

            <x-slot name="headerEnd">
                <div class="w-full overflow-x-auto md:w-auto">
                    <x-filament::tabs label="التبويبات" class="min-w-max border-b-0">
                        @foreach($this->getTabs() as $key => $label)
                            <x-filament::tabs.item
                                :active="$activeTab == (string) $key"
                                wire:click="$set('activeTab', '{{ $key }}')"
                            >
                                {{ $label }}
                            </x-filament::tabs.item>
                        @endforeach
                    </x-filament::tabs>
                </div>
            </x-slot>

            <div class="rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                {{ $this->table }}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>