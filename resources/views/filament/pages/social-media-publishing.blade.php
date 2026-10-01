<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-3">
        <!-- Ready to Publish Card -->
        <div class="p-5 border shadow-sm rounded-xl bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-megaphone class="w-7 h-7" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $readyToPublishCount }}
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        مستحق النشر الآن
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Card -->
        <div class="p-5 border shadow-sm rounded-xl bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                    <x-heroicon-o-exclamation-triangle class="w-7 h-7" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $overdueCount }}
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        مواعيد نشر متأخرة
                    </div>
                </div>
            </div>
        </div>

        <!-- Published Today Card -->
        <div class="p-5 border shadow-sm rounded-xl bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-4">
                <div class="p-3 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-check-badge class="w-7 h-7" />
                </div>
                <div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $publishedTodayCount }}
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        منشورات اليوم 
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs & Table Section -->
    <div class="space-y-4">
        <div class="flex items-center justify-start overflow-x-auto pb-1">
            <x-filament::tabs label="تبويبات مواعيد النشر">
                @foreach($this->getTabs() as $key => $tab)
                    <x-filament::tabs.item
                        :active="$activeTab === (string) $key"
                        wire:click="$set('activeTab', '{{ $key }}')"
                        :badge="$tab['badge']"
                        :badge-color="$tab['badgeColor'] ?? 'primary'"
                        :icon="$tab['icon'] ?? null"
                    >
                        {{ $tab['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
