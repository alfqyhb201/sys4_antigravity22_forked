<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button
                type="submit"
                wire:target="save"
                wire:loading.attr="disabled"
                wire:loading.class="opacity-75 cursor-wait"
                :loading-indicator="false"
            >
                <div class="flex items-center gap-x-2">
                    <x-filament::loading-indicator
                        wire:loading
                        wire:target="save"
                        class="h-5 w-5 animate-spin"
                    />
                    <x-filament::icon
                        icon="heroicon-m-check-circle"
                        wire:loading.remove
                        wire:target="save"
                        class="h-5 w-5"
                    />
                    <span>حفظ الإعدادات</span>
                </div>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
