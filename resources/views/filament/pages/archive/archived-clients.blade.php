<x-filament-panels::page>
    <div class="space-y-6 font-sans" dir="rtl">
        {{-- ترويسة الصفحة مع إحصائيات سريعة --}}
        <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-white via-gray-50/50 to-primary-50/20 p-6 shadow-sm dark:border-gray-800 dark:from-gray-900 dark:via-gray-900/80 dark:to-primary-950/20">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-100 text-primary-700 shadow-sm dark:bg-primary-900/60 dark:text-primary-300">
                            <x-heroicon-o-archive-box class="h-6 w-6" />
                        </span>
                        <div>
                            <h1 class="text-xl font-extrabold text-gray-900 dark:text-white">
                                أرشيف التصاميم والعملاء المكتملة
                            </h1>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                استعراض واستلهام كافة التصاميم المنجزة، تصفية دقيقة بحسب الوسوم والمصممين، مع معاينة فورية وتنزيل الأصول.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- شارات الإحصائيات السريعة --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2.5 rounded-2xl border border-gray-200 bg-white px-4 py-2 shadow-xs dark:border-gray-800 dark:bg-gray-800">
                        <x-heroicon-m-building-office class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        <div class="text-xs">
                            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">العملاء في الأرشيف</span>
                            <span class="font-extrabold text-sm text-gray-900 dark:text-white">{{ $this->archiveStats['clients_count'] }} عميل</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 rounded-2xl border border-gray-200 bg-white px-4 py-2 shadow-xs dark:border-gray-800 dark:bg-gray-800">
                        <x-heroicon-m-photo class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                        <div class="text-xs">
                            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">إجمالي التصاميم المؤرشفة</span>
                            <span class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400">{{ $this->archiveStats['total_designs_count'] }} تصميم</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- شريط التبويبات الرئيسي (Filament Tabs) --}}
        <div class="border-b border-gray-200 dark:border-gray-800">
            <x-filament::tabs label="تبويبات الأرشيف">
                <x-filament::tabs.item
                    :active="$activeTab === 'clients'"
                    wire:click="$set('activeTab', 'clients')"
                    :badge="$this->archiveStats['clients_count']"
                    badge-color="primary"
                    icon="heroicon-o-building-office"
                >
                    حسب العملاء (By Clients)
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'all_designs'"
                    wire:click="$set('activeTab', 'all_designs')"
                    :badge="$this->archiveStats['total_designs_count']"
                    badge-color="success"
                    icon="heroicon-o-squares-2x2"
                >
                    مستكشف التصاميم العام (All Designs Gallery)
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- محتوى التبويب النشط --}}
        @if($activeTab === 'clients')
            <div class="space-y-4">
                <style>
                    /* توسيع وتوسيط حقل البحث الافتراضي الخاص بالجدول */
                    .fi-ta-search-field {
                        max-width: 100% !important;
                        width: 450px !important;
                    }
                </style>
                {{ $this->table }}
            </div>
        @else
            @include('filament.pages.archive.global-designs-explorer')
        @endif
    </div>
</x-filament-panels::page>