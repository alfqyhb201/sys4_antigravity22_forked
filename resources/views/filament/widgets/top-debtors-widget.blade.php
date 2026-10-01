<x-filament-widgets::widget>
    <x-filament::section>
        {{-- شريط التبويبات الأفقية الذكية مع العدادات الحية وزر الطباعة --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3" dir="rtl">
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'all'"
                    wire:click="$set('activeTab', 'all')"
                    :badge="$tabCounts['all'] ?? 0"
                    badge-color="gray"
                    icon="heroicon-m-queue-list"
                >
                    جميع العملاء
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'critical_overdue'"
                    wire:click="$set('activeTab', 'critical_overdue')"
                    :badge="$tabCounts['critical_overdue'] ?? 0"
                    badge-color="danger"
                    icon="heroicon-m-exclamation-triangle"
                >
                    متأخرات حرجة
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'expiring_soon'"
                    wire:click="$set('activeTab', 'expiring_soon')"
                    :badge="$tabCounts['expiring_soon'] ?? 0"
                    badge-color="warning"
                    icon="heroicon-m-clock"
                >
                    تجديد وشيك (7 أيام)
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'expired_suspended'"
                    wire:click="$set('activeTab', 'expired_suspended')"
                    :badge="$tabCounts['expired_suspended'] ?? 0"
                    badge-color="danger"
                    icon="heroicon-m-no-symbol"
                >
                    موقوف / منتهي
                </x-filament::tabs.item>
            </x-filament::tabs>

            <a
                href="{{ route('reports.client-debtors') }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-white bg-danger-600 hover:bg-danger-500 rounded-lg shadow transition"
                title="فتح وطباعة جدول مديونيات العملاء المالي بصيغة PDF"
            >
                <x-filament::icon icon="heroicon-m-printer" class="w-4 h-4" />
                <span>طباعة تقرير المديونيات الشامل (PDF)</span>
            </a>
        </div>

        {{-- جدول العملاء المالي --}}
        {{ $this->table }}
    </x-filament::section>
</x-filament-widgets::widget>
