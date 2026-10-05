<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6 font-sans" dir="rtl">
        <div wire:key="follow-up-header">
            <x-dashboard-panel-header
                title="واجهة الإرسال والمتابعة"
                description="إدارة نهائية لتسليم التصاميم المعتمدة وضمان وصولها للعملاء في مواعيدها المحددة."
                badgeText="مركز تسليم المحتوى"
                badgeColor="orange"
                :metricValue="$todayCount"
                metricLabel="مواعيد اليوم المستحقة">
                <x-filament::button
                    :href="\App\Filament\Pages\Archive\RecentlySentArchive::getUrl()"
                    tag="a"
                    color="gray"
                    icon="heroicon-o-archive-box-arrow-down">
                    المرسلة حديثاً (آخر 7 أيام)
                </x-filament::button>
            </x-dashboard-panel-header>
        </div>

        <!-- Tabs Section -->
        <div wire:key="follow-up-tabs" class="flex items-center justify-start overflow-x-auto pb-1">
            <x-filament::tabs label="تبويبات مواعيد الإرسال">
                @foreach($this->getTabs() as $key => $tab)
                <x-filament::tabs.item
                    :active="$activeTab === (string) $key"
                    wire:click="$set('activeTab', '{{ $key }}')"
                    :badge="$tab['badge']"
                    :badge-color="$tab['badgeColor'] ?? 'primary'"
                    :icon="$tab['icon'] ?? null">
                    {{ $tab['label'] }}
                </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>

        <!-- Progress Box -->
        <div wire:key="follow-up-progress-wrapper">
            @if ($zipToken)
            <div wire:key="zip-progress-card-{{ $zipToken }}"
                class="rounded-xl border border-primary-200 bg-primary-50/50 p-4 shadow-sm dark:border-primary-500/20 dark:bg-primary-950/20">
                <div class="mb-2 flex items-center justify-between text-sm font-semibold text-gray-700 dark:text-gray-200">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>جارٍ تجهيز ملف التنزيل (ZIP)...</span>
                    </span>
                    <span class="font-bold text-primary-600 dark:text-primary-400">{{ $zipPercent }}%</span>
                </div>
                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div class="h-full rounded-full transition-all duration-300" style="width: {{ $zipPercent }}%; background-color: rgb(var(--primary-600))"></div>
                </div>
            </div>
            @endif
        </div>

        <div wire:key="follow-up-table-container" class="rounded-xl bg-[var(--surface)] p-2 shadow-sm">
            {{ $this->table }}
        </div>
    </div>

    @script
    <script>
        $wire.on('trigger-next-zip-chunk', () => {
            setTimeout(() => {
                $wire.processNextZipChunk();
            }, 30);
        });

        $wire.on('download-sending-zip', ({
            url
        }) => {
            const frame = document.createElement('iframe');
            frame.hidden = true;
            frame.src = url;
            document.body.appendChild(frame);
            setTimeout(() => frame.remove(), 60000);
        });
    </script>
    @endscript
</x-filament-panels::page>