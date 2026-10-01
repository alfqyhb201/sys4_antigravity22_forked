@props([
'wireKey' => '',
'clientName' => '',
'assignerName' => '',
'description' => null,
'status' => null,
'actionWireClick' => null,
'actionDisabled' => false,
])

<div wire:key="{{ $wireKey }}"
    class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 dark:ring-white/5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:bg-[var(--surface)]">

    <div class="flex items-start justify-between gap-3 p-4">
        <div class="min-w-0 flex-1">
            <h3 class="line-clamp-1 text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-purple dark:group-hover:text-brand-purple-light transition-colors duration-200">
                {{ $clientName }}
            </h3>
            @if($assignerName)
            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                بواسطة: {{ $assignerName }}
            </span>
            @endif
        </div>

        @if(!$actionDisabled)
        <button wire:click="{!! $actionWireClick !!}"
            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-brand-purple p-2.5 text-white shadow-sm shadow-brand-purple/20 transition-all min-h-[44px] min-w-[44px] hover:bg-brand-purple-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40 active:scale-[0.98]"
            aria-label="إرسال للمراجعة">
            <x-heroicon-m-paper-airplane class="w-5 h-5" />
        </button>
        @else
        <div class="inline-flex shrink-0 cursor-not-allowed items-center justify-center rounded-lg border border-blue-100 bg-blue-50 p-2.5 text-blue-600 min-h-[44px] min-w-[44px] dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300">
            <x-heroicon-m-eye class="w-5 h-5 animate-pulse" />
        </div>
        @endif
    </div>

    @if($description)
    @php $isLongDesc = mb_strlen($description) > 120; @endphp
    <div class="border-t border-dashed border-gray-100 px-4 py-3 dark:border-gray-800/80"
        x-data="{ expanded: false }">
        <p class="text-xs text-gray-500 dark:text-gray-450 leading-relaxed"
            :class="!expanded ? 'line-clamp-2' : ''">
            {{ $description }}
        </p>
        @if($isLongDesc)
        <button @click="expanded = !expanded"
            class="mt-1.5 text-xs font-bold text-brand-purple hover:text-brand-purple-dark transition-colors dark:text-brand-purple-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40 rounded-md px-1">
            <span x-show="!expanded">عرض المزيد</span>
            <span x-show="expanded">عرض أقل</span>
        </button>
        @endif
    </div>
    @endif
</div>