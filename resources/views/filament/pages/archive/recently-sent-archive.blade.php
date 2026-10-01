<x-filament-panels::page>
    <div class="mx-auto w-full max-w-7xl space-y-6 font-sans" dir="rtl">
        <x-dashboard-panel-header
            title="المرسلة حديثاً"
            description="التصاميم التي تم تأكيد إرسالها خلال آخر 7 أيام. يمكنك التراجع عن أي إرسال تم بالخطأ."
            badgeText="أرشيف سريع"
            badgeColor="amber"
            :metricValue="$last_24h"
            metricLabel="أُرسلت خلال 24 ساعة"
        >
            <x-filament::button
                :href="\App\Filament\Pages\SendingFollowUp::getUrl()"
                tag="a"
                color="gray"
                icon="heroicon-o-arrow-right"
            >
                العودة لواجهة الإرسال
            </x-filament::button>
        </x-dashboard-panel-header>

        {{-- Quick Stats --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-4 dark:bg-emerald-950/30 ring-1 ring-emerald-200 dark:ring-emerald-800">
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20">
                    <x-heroicon-m-check-circle class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400">آخر 24 ساعة</p>
                    <p class="text-xl font-bold text-emerald-700 dark:text-emerald-300">{{ $last_24h }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30 ring-1 ring-blue-200 dark:ring-blue-800">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-500/10 dark:bg-blue-500/20">
                    <x-heroicon-m-calendar-days class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-xs text-blue-600 dark:text-blue-400">آخر 7 أيام</p>
                    <p class="text-xl font-bold text-blue-700 dark:text-blue-300">{{ $last_7d }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-[var(--surface)] p-2 shadow-sm">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
