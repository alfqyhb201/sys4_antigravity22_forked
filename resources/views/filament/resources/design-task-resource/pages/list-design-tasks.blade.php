<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        @can('create', \App\Models\DesignTask::class)
            <x-filament::section
                icon="heroicon-o-sparkles"
                class="shadow-xs border border-gray-200/80 dark:border-gray-800"
            >
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-base text-gray-900 dark:text-white">إضافة مهمة سريعة</span>
                            <span class="hidden sm:inline-flex text-xs px-2.5 py-0.5 rounded-full bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800 font-medium">
                                تكليف مباشر للمصممين
                            </span>
                        </div>
                    </div>
                </x-slot>

                <x-filament-panels::form wire:submit="createTask">
                    {{ $this->createTaskForm }}

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-3 mt-1 border-t border-gray-100 dark:border-gray-800/80">
                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                            <x-heroicon-m-bolt class="w-4 h-4 text-amber-500 shrink-0" />
                            <span>يتم إسناد المهمة للمصمم المحدد فوراً وإشعاره بالطلب في لوحته الخاصة.</span>
                        </p>
                        <x-filament::button
                            type="submit"
                            icon="heroicon-o-paper-airplane"
                            wire:loading.attr="disabled"
                            wire:target="createTask"
                            class="w-full sm:w-auto"
                        >
                            <span wire:loading.remove wire:target="createTask">إسناد المهمة</span>
                            <span wire:loading wire:target="createTask">جاري الإسناد...</span>
                        </x-filament::button>
                    </div>
                </x-filament-panels::form>
            </x-filament::section>
        @endcan

        <x-filament-panels::resources.tabs />

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

        {{ $this->table }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
    </div>
</x-filament-panels::page>
