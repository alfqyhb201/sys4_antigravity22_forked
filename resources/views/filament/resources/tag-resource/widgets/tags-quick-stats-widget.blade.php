@php
    $stats = $this->getStatsData();
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3">
        <!-- Main Stats Grid -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            
            <!-- 1. Active Tags Card -->
            <button
                type="button"
                wire:click="filterBy('active')"
                class="group relative text-start w-full overflow-hidden rounded-xl border p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none {{ $activeFilter === 'active' ? 'ring-2 ring-primary-500 border-primary-500 bg-primary-50/80 dark:bg-primary-950/30 dark:border-primary-500 shadow-md' : 'border-gray-200/80 bg-white/80 hover:border-primary-400 dark:border-gray-800 dark:bg-gray-900/80 dark:hover:border-primary-600' }}"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-950/60 dark:text-primary-400 group-hover:scale-110 transition-transform duration-200">
                            <x-filament::icon
                                icon="heroicon-o-tag"
                                class="h-5 w-5"
                            />
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">إجمالي الوسوم النشطة</span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['active_tags']) }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">/ {{ number_format($stats['total_tags']) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    @if($stats['this_month_tags'] > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-950/50 dark:text-primary-300">
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" class="h-3 w-3" />
                            +{{ $stats['this_month_tags'] }} هذا الشهر
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $stats['active_percentage'] }}% نشط
                        </span>
                    @endif
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-gray-800/80 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $stats['inactive_tags'] > 0 ? $stats['inactive_tags'] . ' وسم غير نشط' : 'جميع الوسوم في حالة نشطة' }}
                    </span>
                    <span class="font-medium text-primary-600 dark:text-primary-400 group-hover:underline flex items-center gap-0.5">
                        {{ $activeFilter === 'active' ? 'إلغاء التصفية ✕' : 'فلترة النشط' }}
                    </span>
                </div>
            </button>

            <!-- 2. Tags Without Ideas Card (Critical Warning) -->
            <button
                type="button"
                wire:click="filterBy('without_ideas')"
                class="group relative text-start w-full overflow-hidden rounded-xl border p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none {{ $activeFilter === 'without_ideas' ? 'ring-2 ring-amber-500 border-amber-500 bg-amber-50/80 dark:bg-amber-950/30 dark:border-amber-500 shadow-md' : ($stats['tags_without_ideas'] > 0 ? 'border-amber-300/80 bg-gradient-to-br from-amber-50/40 via-white to-white dark:from-amber-950/20 dark:via-gray-900 dark:to-gray-900 dark:border-amber-800/60 hover:border-amber-400' : 'border-gray-200/80 bg-white/80 dark:border-gray-800 dark:bg-gray-900/80 hover:border-emerald-400') }}"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <div class="relative flex h-10 w-10 items-center justify-center rounded-lg {{ $stats['tags_without_ideas'] > 0 ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400' : 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400' }} group-hover:scale-110 transition-transform duration-200">
                            @if($stats['tags_without_ideas'] > 0)
                                <x-filament::icon
                                    icon="heroicon-o-exclamation-triangle"
                                    class="h-5 w-5"
                                />
                                <span class="absolute -top-1 -right-1 flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                </span>
                            @else
                                <x-filament::icon
                                    icon="heroicon-o-check-circle"
                                    class="h-5 w-5"
                                />
                            @endif
                        </div>
                        <div>
                            <span class="text-xs font-semibold {{ $stats['tags_without_ideas'] > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                وسوم بدون أفكار ⚠️
                            </span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-2xl font-black tracking-tight {{ $stats['tags_without_ideas'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ number_format($stats['tags_without_ideas']) }}
                                </span>
                                @if($stats['tags_without_ideas'] > 0)
                                    <span class="text-xs text-amber-600/80 dark:text-amber-500/80">بحاجة لأفكار</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($stats['tags_without_ideas'] > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-950/70 dark:text-amber-300">
                            إجراء حرج
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            مكتمل 100%
                        </span>
                    @endif
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-gray-800/80 text-xs">
                    <span class="{{ $stats['tags_without_ideas'] > 0 ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-500 dark:text-gray-400' }}">
                        {{ $stats['tags_without_ideas'] > 0 ? $stats['active_tags_without_ideas'] . ' وسم نشط يحتاج أفكار' : 'جميع الوسوم مرتبطة بأفكار' }}
                    </span>
                    <span class="font-bold {{ $stats['tags_without_ideas'] > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-400' }} group-hover:underline flex items-center gap-0.5">
                        {{ $activeFilter === 'without_ideas' ? 'إلغاء التصفية ✕' : 'معالجة فورية ⚡' }}
                    </span>
                </div>
            </button>

            <!-- 3. Scheduled Tags Card -->
            <button
                type="button"
                wire:click="filterBy('scheduled')"
                class="group relative text-start w-full overflow-hidden rounded-xl border p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none {{ $activeFilter === 'scheduled' ? 'ring-2 ring-emerald-500 border-emerald-500 bg-emerald-50/80 dark:bg-emerald-950/30 dark:border-emerald-500 shadow-md' : 'border-gray-200/80 bg-white/80 hover:border-emerald-400 dark:border-gray-800 dark:bg-gray-900/80 dark:hover:border-emerald-600' }}"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 group-hover:scale-110 transition-transform duration-200">
                            <x-filament::icon
                                icon="heroicon-o-calendar-days"
                                class="h-5 w-5"
                            />
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">وسوم مجدولة (أسبوعي/سنوي)</span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['scheduled_tags']) }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">مجدول</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col items-end gap-1">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            {{ $stats['weekly_scheduled'] }} أسبوعي
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold text-teal-700 dark:bg-teal-950/50 dark:text-teal-300">
                            {{ $stats['yearly_scheduled'] }} سنوي
                        </span>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-gray-800/80 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">
                        جاهزة للإرسال والتوزيع التلقائي
                    </span>
                    <span class="font-medium text-emerald-600 dark:text-emerald-400 group-hover:underline flex items-center gap-0.5">
                        {{ $activeFilter === 'scheduled' ? 'إلغاء التصفية ✕' : 'فلترة المجدولة' }}
                    </span>
                </div>
            </button>

            <!-- 4. Client-Specific Tags Card -->
            <button
                type="button"
                wire:click="filterBy('custom_clients')"
                class="group relative text-start w-full overflow-hidden rounded-xl border p-4 transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none {{ $activeFilter === 'custom_clients' ? 'ring-2 ring-purple-500 border-purple-500 bg-purple-50/80 dark:bg-purple-950/30 dark:border-purple-500 shadow-md' : 'border-gray-200/80 bg-white/80 hover:border-purple-400 dark:border-gray-800 dark:bg-gray-900/80 dark:hover:border-purple-600' }}"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-950/60 dark:text-purple-400 group-hover:scale-110 transition-transform duration-200">
                            <x-filament::icon
                                icon="heroicon-o-user-group"
                                class="h-5 w-5"
                            />
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">وسوم مخصصة لعملاء</span>
                            <div class="flex items-baseline gap-1.5 mt-0.5">
                                <span class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ number_format($stats['custom_client_tags']) }}</span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">وسم مخصص</span>
                            </div>
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2 py-0.5 text-[11px] font-semibold text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">
                        لـ {{ $stats['unique_clients_count'] }} عميل
                    </span>
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 dark:border-gray-800/80 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">
                        حصرية وغير معينة تلقائياً
                    </span>
                    <span class="font-medium text-purple-600 dark:text-purple-400 group-hover:underline flex items-center gap-0.5">
                        {{ $activeFilter === 'custom_clients' ? 'إلغاء التصفية ✕' : 'فلترة المخصصة' }}
                    </span>
                </div>
            </button>

        </div>

        <!-- Active Filter Bar (when any quick filter is active) -->
        @if($activeFilter)
            <div class="flex items-center justify-between rounded-lg bg-gray-100/90 px-3.5 py-2 text-xs text-gray-700 dark:bg-gray-800/90 dark:text-gray-300 border border-gray-200/60 dark:border-gray-700/60 transition-all duration-200">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2 w-2 rounded-full bg-primary-500 animate-pulse"></span>
                    <span>
                        <strong>تصفية مفعلة:</strong>
                        @switch($activeFilter)
                            @case('active')
                                عرض الوسوم النشطة فقط
                                @break
                            @case('without_ideas')
                                <span class="text-amber-600 dark:text-amber-400 font-bold">⚠️ عرض الوسوم التي لا تحتوي على أفكار</span>
                                @break
                            @case('scheduled')
                                عرض الوسوم المجدولة (أسبوعياً وسنوياً)
                                @break
                            @case('custom_clients')
                                عرض الوسوم المخصصة لعملاء محددين
                                @break
                        @endswitch
                    </span>
                </div>
                <button
                    type="button"
                    wire:click="resetFilter"
                    class="inline-flex items-center gap-1 rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 transition-colors"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                    إلغاء التصفية وعرض كل الوسوم
                </button>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
