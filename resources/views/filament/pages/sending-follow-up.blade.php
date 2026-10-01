<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6 font-sans" dir="rtl">
        <x-dashboard-panel-header
            title="واجهة الإرسال والمتابعة"
            description="إدارة نهائية لتسليم التصاميم المعتمدة وضمان وصولها للعملاء في مواعيدها المحددة."
            badgeText="مركز تسليم المحتوى"
            badgeColor="orange"
            :metricValue="$todayCount"
            metricLabel="مواعيد اليوم المستحقة"
        >
            <x-filament::button
                :href="\App\Filament\Pages\Archive\RecentlySentArchive::getUrl()"
                tag="a"
                color="gray"
                icon="heroicon-o-archive-box-arrow-down"
            >
                المرسلة حديثاً (آخر 7 أيام)
            </x-filament::button>
        </x-dashboard-panel-header>

        <!-- Tabs Section -->
        <div class="flex items-center justify-start overflow-x-auto pb-1">
            <x-filament::tabs label="تبويبات مواعيد الإرسال">
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

        <div class="rounded-xl bg-[var(--surface)] p-2 shadow-sm">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
