@props([
    'icon' => 'heroicon-o-clock',
    'label' => '',
    'count' => 0,
    'color' => 'default',
    'footnote' => null,
    'badge' => null,
    'href' => null,
    'dot' => false,
    'arrow' => false,
    'alpineClick' => null,
])

@php
$iconThemes = [
    'purple' => 'bg-purple-50 text-purple-600 border-purple-200/50 dark:bg-purple-500/10 dark:text-purple-400 dark:border-purple-500/20',
    'orange' => 'bg-orange-50 text-orange-600 border-orange-200/50 dark:bg-orange-500/10 dark:text-orange-400 dark:border-orange-500/20',
    'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-200/50 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
    'blue' => 'bg-blue-50 text-blue-600 border-blue-200/50 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20',
    'red' => 'bg-rose-50 text-rose-600 border-rose-200/50 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
    'default' => 'bg-gray-100 text-gray-700 border-gray-200/50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
];

$badgeThemes = [
    'purple' => 'bg-purple-50 text-purple-700 border border-purple-200/60 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800/40',
    'orange' => 'bg-orange-50 text-orange-700 border border-orange-200/60 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-800/40',
    'emerald' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    'blue' => 'bg-blue-50 text-blue-700 border border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/40',
    'red' => 'bg-rose-50 text-rose-700 border border-rose-200/60 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    'default' => 'bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
];

$dotColors = [
    'purple' => 'bg-purple-500',
    'orange' => 'bg-orange-500',
    'emerald' => 'bg-emerald-500',
    'blue' => 'bg-blue-500',
    'red' => 'bg-rose-500',
    'default' => 'bg-gray-400',
];

$iconClass = $iconThemes[$color] ?? $iconThemes['default'];
$badgeClass = $badgeThemes[$color] ?? $badgeThemes['default'];
$dotColor = $dotColors[$color] ?? $dotColors['default'];

$wrapperClasses = 'group relative flex flex-col justify-between rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-gray-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900/90 dark:hover:border-gray-700 dark:hover:shadow-black/40';

if ($href) {
    $wrapperClasses .= ' focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2';
}

if ($alpineClick) {
    $wrapperClasses .= ' cursor-pointer';
}
@endphp

@if($alpineClick)
<div @click="{{ $alpineClick }}" class="{{ $wrapperClasses }}">
@elseif($href)
<a href="{{ $href }}" class="{{ $wrapperClasses }}">
@else
<div class="{{ $wrapperClasses }}">
@endif

    <div>
        <div class="mb-4 flex items-center justify-between">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl border {{ $iconClass }}">
                <x-dynamic-component :component="$icon" class="h-6 w-6" />
            </div>

            @if($badge || $dot)
            <div class="flex items-center gap-1.5">
                @if($dot)
                <span class="h-2 w-2 rounded-full {{ $dotColor }}"></span>
                @endif
                @if($badge)
                <span class="rounded-lg px-2 py-0.5 text-[11px] font-bold {{ $badgeClass }}">{{ $badge }}</span>
                @endif
            </div>
            @endif
        </div>

        <div class="space-y-1">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $label }}</p>
            <p class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">{{ $count }}</p>
        </div>
    </div>

    @if($footnote || $arrow)
    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800/80">
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            @if($footnote)
            <span>{{ $footnote }}</span>
            @endif
            @if($arrow)
            <x-heroicon-m-arrow-left class="h-4 w-4 text-gray-400 transition-transform group-hover:-translate-x-1 dark:text-gray-500" />
            @endif
        </div>
    </div>
    @endif

@if($alpineClick)
</div>
@elseif($href)
</a>
@else
</div>
@endif