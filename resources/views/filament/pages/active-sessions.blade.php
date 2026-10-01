<x-filament-panels::page>
    @php
        $stats = $this->getSessionStats();
        $filterOptions = $this->getTimeFilterOptions();
    @endphp

    {{-- كروت الإحصائيات العلوية --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-2">
        {{-- كرت المتصلين الآن --}}
        <div class="relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                    <x-heroicon-o-signal class="h-6 w-6 animate-pulse" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">متصل الآن</p>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['online_now'] }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                            مستخدم
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- كرت غير المتصلين --}}
        <div class="relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-500/10 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400">
                    <x-heroicon-o-moon class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">غير متصلين</p>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['offline_count'] }}</span>
                        <span class="text-xs text-gray-500">مستخدم</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- كرت إجمالي المستخدمين --}}
        <div class="relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                    <x-heroicon-o-users class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">إجمالي المستخدمين</p>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_users'] }}</span>
                        <span class="text-xs text-gray-500">حساب</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- كرت IP المتصل الحالي --}}
        <div class="relative overflow-hidden rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400">
                    <x-heroicon-o-shield-check class="h-6 w-6" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">عنوان IP الخاص بك</p>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-base font-bold font-mono text-gray-900 dark:text-white">{{ $stats['current_ip'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- شريط التبويبات (المتصلون الآن / جميع المستخدمين) --}}
    <div class="flex flex-wrap items-center gap-2 p-1.5 bg-gray-100 dark:bg-gray-800/60 rounded-xl w-fit">
        @foreach ($filterOptions as $key => $option)
            <button
                wire:click="setTimeFilter('{{ $key }}')"
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-150 {{ $this->timeFilter === $key ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}"
            >
                <span>{{ $option['label'] }}</span>
                <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full {{ $this->timeFilter === $key ? 'bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                    {{ $option['count'] }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- جدول المستخدمين والنشاط --}}
    <div class="mt-2">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
