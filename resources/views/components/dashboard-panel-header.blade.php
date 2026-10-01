@props([
    'title' => '',
    'description' => '',
    'badgeText' => null,
    'badgeColor' => 'purple',
    'metricValue' => null,
    'metricLabel' => null,
])

@php
$badgeClasses = match ($badgeColor) {
    'orange' => 'text-orange-700 bg-orange-50 border-orange-200/70 dark:text-orange-400 dark:bg-orange-950/30 dark:border-orange-800/40',
    'emerald' => 'text-emerald-700 bg-emerald-50 border-emerald-200/70 dark:text-emerald-400 dark:bg-emerald-950/30 dark:border-emerald-800/40',
    'blue' => 'text-blue-700 bg-blue-50 border-blue-200/70 dark:text-blue-400 dark:bg-blue-950/30 dark:border-blue-800/40',
    default => 'text-purple-700 bg-purple-50 border-purple-200/70 dark:text-purple-300 dark:bg-purple-950/30 dark:border-purple-800/40',
};

$dotClasses = match ($badgeColor) {
    'orange' => 'bg-orange-500',
    'emerald' => 'bg-emerald-500',
    'blue' => 'bg-blue-500',
    default => 'bg-purple-500',
};

$showMetric = !is_null($metricValue);
@endphp

<section class="rounded-2xl border border-gray-200/80 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/90 sm:p-6">
    <div class="flex flex-col items-center justify-between gap-5 sm:gap-6 md:flex-row">
        <div class="space-y-2 text-center md:text-right">
            @if($badgeText)
                <div class="flex justify-center md:justify-start">
                    <div class="inline-flex items-center gap-2 rounded-xl border px-3 py-1 text-xs font-bold {{ $badgeClasses }}">
                        <span class="h-2 w-2 rounded-full {{ $dotClasses }}"></span>
                        {{ $badgeText }}
                    </div>
                </div>
            @endif
            <h1 class="text-xl font-black tracking-tight text-gray-900 dark:text-white sm:text-2xl">{{ $title }}</h1>
            <p class="max-w-2xl text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ $description }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3 md:justify-end">
            @if(trim($slot))
                {{ $slot }}
            @endif

            @if($showMetric)
                <div class="flex min-w-[140px] flex-col items-center justify-center gap-1 rounded-xl border border-gray-100 bg-gray-50/80 p-4 text-center dark:border-gray-800 dark:bg-gray-800/50">
                    <div class="w-full text-center text-3xl font-black tracking-tight text-gray-900 dark:text-white sm:text-4xl">{{ $metricValue }}</div>
                    <div class="w-full text-center text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $metricLabel }}</div>
                </div>
            @endif
        </div>
    </div>
</section>
