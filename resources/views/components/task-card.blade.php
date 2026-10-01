@props([
'wireKey' => '',
'status' => 'pending',
'taskType' => null,
'title' => '',
'subtitle' => '',
'description' => null,
'descriptionLabel' => 'وصف المهمة',
'actionWireClick' => null,
'actionLabel' => '',
'actionIcon' => 'heroicon-m-arrow-up-tray',
'actionTheme' => 'primary',
'actionDisabled' => false,
'priority' => null,
'isExtra' => false,
'extraAmount' => null,
'referenceFiles' => [],
'designFiles' => [],
'revisionNotes' => null,
'revisionAttachments' => [],
'avatarLetter' => null,
'avatarUrl' => null,
'avatarTheme' => 'purple',
'templateTypeLabel' => null,
])

@php
$attachmentUrls = collect($revisionAttachments ?? [])->filter()->map(fn ($path) => \Illuminate\Support\Facades\Storage::url($path))->values()->all();
$avatarBg = match ($avatarTheme) {
'purple' => 'from-purple-500 to-indigo-600 shadow-purple-500/10 dark:from-purple-600 dark:to-indigo-700',
'teal' => 'from-teal-500 to-emerald-600 shadow-teal-500/10 dark:from-teal-600 dark:to-emerald-700',
'orange' => 'from-orange-500 to-amber-600 shadow-orange-500/10 dark:from-orange-600 dark:to-amber-700',
'blue' => 'from-blue-500 to-indigo-600 shadow-blue-500/10 dark:from-blue-600 dark:to-indigo-700',
default => 'from-gray-500 to-slate-600 shadow-gray-500/10 dark:from-gray-600 dark:to-slate-700',
};

$statusConfig = match ($status) {
'pending' => [
'line' => 'from-amber-400 via-amber-500 to-amber-400',
'label' => 'قيد الانتظار',
'text' => 'text-amber-700 dark:text-amber-400',
'bg' => 'bg-amber-50/80 dark:bg-amber-950/20',
],
'needs_revision' => [
'line' => 'from-red-400 via-red-500 to-red-400',
'label' => 'يحتاج تعديل',
'text' => 'text-red-700 dark:text-red-400',
'bg' => 'bg-red-50/80 dark:bg-red-950/20',
],
'changes_requested' => [
'line' => 'from-orange-400 via-orange-500 to-orange-400',
'label' => 'طلب تعديل',
'text' => 'text-orange-700 dark:text-orange-400',
'bg' => 'bg-orange-50/80 dark:bg-orange-950/20',
],
'in_review' => [
'line' => 'from-blue-400 via-blue-500 to-blue-400',
'label' => 'قيد المراجعة',
'text' => 'text-blue-700 dark:text-blue-400',
'bg' => 'bg-blue-50/80 dark:bg-blue-950/20',
],
default => [
'line' => 'from-gray-400 via-gray-500 to-gray-400',
'label' => $status,
'text' => 'text-gray-700 dark:text-gray-400',
'bg' => 'bg-gray-50/80 dark:bg-gray-950/20',
],
};

$priorityConfig = match ($priority) {
'high' => ['label' => 'عالية', 'badge' => 'bg-red-50 text-red-700 ring-red-600/10 dark:bg-red-950/20 dark:text-red-400 dark:ring-red-800/30', 'icon' => 'heroicon-m-arrow-trending-up'],
'medium' => ['label' => 'متوسطة', 'badge' => 'bg-amber-50 text-amber-700 ring-amber-600/10 dark:bg-amber-950/20 dark:text-amber-400 dark:ring-amber-800/30', 'icon' => 'heroicon-m-minus'],
'low' => ['label' => 'منخفضة', 'badge' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800', 'icon' => 'heroicon-m-arrow-trending-down'],
default => null,
};

$taskTypeConfig = $taskType ? match ($taskType) {
'daily' => ['label' => 'مهمة يومية', 'badge' => 'bg-purple-50 text-purple-700 ring-purple-600/10 dark:bg-purple-950/20 dark:text-purple-400 dark:ring-purple-800/30'],
'design' => ['label' => 'مهمة تصميم', 'badge' => 'bg-orange-50 text-orange-700 ring-orange-600/10 dark:bg-orange-950/20 dark:text-orange-300 dark:ring-orange-800/30'],
'template' => ['label' => $templateTypeLabel ?? 'تحديث كليشة', 'badge' => 'bg-teal-50 text-teal-700 ring-teal-600/10 dark:bg-teal-950/20 dark:text-teal-400 dark:ring-teal-800/30'],
default => null,
} : null;

$actionClasses = match ($actionTheme) {
'teal' => 'bg-teal-600 hover:bg-teal-700 shadow-teal-600/20 focus-visible:ring-teal-400/40',
'red' => 'bg-red-600 hover:bg-red-700 shadow-red-600/20 focus-visible:ring-red-400/40',
default => 'bg-brand-purple hover:bg-brand-purple-dark shadow-brand-purple/20 focus-visible:ring-brand-purple/40',
};
@endphp

<div wire:key="{{ $wireKey }}"
    class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 dark:ring-white/5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:bg-[var(--surface)]">

    {{-- Top accent gradient line --}}
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $statusConfig['line'] }}"></div>

    {{-- Header Section --}}
    <div class="flex flex-col gap-2 p-4 pb-3">
        <div class="flex items-center gap-3">
            @if($avatarUrl)
            <div class="h-9 w-9 shrink-0 overflow-hidden rounded-xl shadow-sm">
                <img src="{{ $avatarUrl }}" alt="{{ $title }}" class="h-full w-full object-cover">
            </div>
            @elseif($avatarLetter)
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-white font-extrabold text-sm shadow-sm select-none {{ $avatarBg }}">
                {{ $avatarLetter }}
            </div>
            @endif
            <div class="min-w-0 flex-1">
                <h3 class="line-clamp-2 text-sm font-bold text-gray-900 dark:text-white group-hover:text-brand-purple dark:group-hover:text-brand-purple-light transition-colors duration-200 leading-snug">
                    {{ $title }}
                </h3>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            @if($taskTypeConfig)
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $taskTypeConfig['badge'] }}">
                {{ $taskTypeConfig['label'] }}
            </span>
            @endif

            @if($priorityConfig)
            <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-bold ring-1 ring-inset {{ $priorityConfig['badge'] }}">
                <x-dynamic-component :component="$priorityConfig['icon']" class="h-3 w-3" />
                {{ $priorityConfig['label'] }}
            </span>
            @endif

            @if($isExtra)
            <span class="inline-flex items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-950/20 dark:text-emerald-400 dark:ring-emerald-800/30">
                إضافي@if($extraAmount) ({{ number_format($extraAmount, 0) }})@endif
            </span>
            @endif

            @if($subtitle)
            <span class="text-[11px] text-gray-400 dark:text-gray-550 font-medium">
                {{ $subtitle }}
            </span>
            @endif
        </div>
    </div>

    {{-- Description Section --}}
    @if($description)
    @php $isLongDesc = mb_strlen($description) > 120; @endphp
    <div class="px-4 pb-3 flex-1">
        <p class="text-xs text-gray-500 dark:text-gray-450 leading-relaxed line-clamp-2 @if($isLongDesc) cursor-pointer hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-200 @endif"
            @if($isLongDesc) @click="activeDescription = { title: {{ \Illuminate\Support\Js::from($title) }}, label: {{ \Illuminate\Support\Js::from($descriptionLabel) }}, content: {{ \Illuminate\Support\Js::from($description) }} }" @endif>
            {{ $description }}
        </p>
        @if($isLongDesc)
        <button @click="activeDescription = { title: {{ \Illuminate\Support\Js::from($title) }}, label: {{ \Illuminate\Support\Js::from($descriptionLabel) }}, content: {{ \Illuminate\Support\Js::from($description) }} }"
            class="mt-1 inline-flex items-center text-[10px] font-bold text-brand-purple hover:text-brand-purple-dark transition-colors dark:text-brand-purple-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40 rounded-md px-1">
            المزيد...
        </button>
        @endif
    </div>
    @endif

    {{-- Reference Files Section --}}
    @if(count($referenceFiles) > 0)
    <div class="px-4 pb-3">
        <label class="block text-[10px] font-extrabold text-gray-400 dark:text-gray-550 mb-1.5 uppercase tracking-wider">الملفات المرجعية</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach($referenceFiles as $index => $refFile)
            <a href="{{ Storage::url($refFile) }}" target="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-100 bg-blue-50/50 px-2.5 py-1 text-xs font-bold text-blue-600 hover:bg-blue-100 hover:text-blue-700 transition-all dark:border-blue-900/20 dark:bg-blue-950/20 dark:text-blue-400 dark:hover:bg-blue-950/40">
                <x-heroicon-m-paper-clip class="h-3.5 w-3.5" />
                <span>ملف {{ $index + 1 }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Revision Notes Section --}}
    @if(in_array($status, ['needs_revision', 'changes_requested']) && ($revisionNotes || count($attachmentUrls) > 0))
    <div class="mx-4 pb-3">
        <div class="rounded-xl border-r-4 border-red-500 bg-red-50/75 p-3 dark:bg-red-950/20 border border-red-200/60 dark:border-red-900/30 transition-all">
            <div class="flex items-center justify-between gap-2 mb-1.5">
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-red-800 dark:text-red-300">
                    <x-heroicon-m-exclamation-triangle class="w-3.5 h-3.5 text-red-600 dark:text-red-400 shrink-0" />
                    <span>ملاحظات التعديل:</span>
                </div>
                <button type="button"
                    @click="activeDescription = { title: 'ملاحظات التعديل: ' + {{ \Illuminate\Support\Js::from($title) }}, label: 'التعديلات المطلوبة للمهمة', content: {{ \Illuminate\Support\Js::from($revisionNotes ?? 'يرجى مراجعة الصور المرفقة أدناه.') }}, attachments: {{ \Illuminate\Support\Js::from($attachmentUrls) }} }"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-red-700 hover:text-red-900 dark:text-red-300 dark:hover:text-red-100 hover:underline transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-red-400 rounded px-1 py-0.5"
                    title="عرض ملاحظات التعديل في نافذة كاملة">
                    <x-heroicon-m-arrows-pointing-out class="w-3 h-3" />
                    <span>عرض المزيد</span>
                </button>
            </div>
            @if($revisionNotes)
            <p class="text-xs text-red-700 dark:text-red-400 leading-relaxed font-medium line-clamp-2 cursor-pointer hover:text-red-900 dark:hover:text-red-200 transition-colors"
                @click="activeDescription = { title: 'ملاحظات التعديل: ' + {{ \Illuminate\Support\Js::from($title) }}, label: 'التعديلات المطلوبة للمهمة', content: {{ \Illuminate\Support\Js::from($revisionNotes) }}, attachments: {{ \Illuminate\Support\Js::from($attachmentUrls) }} }"
                title="اضغط لتكبير الملاحظات في نافذة">
                {{ $revisionNotes }}
            </p>
            @endif

            @if(count($attachmentUrls) > 0)
            <div class="mt-2.5 flex flex-wrap items-center gap-2 pt-2 border-t border-red-200/50 dark:border-red-900/30">
                <span class="text-[10px] font-bold text-red-800 dark:text-red-300">مرفقات ({{ count($attachmentUrls) }}):</span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($attachmentUrls as $index => $url)
                    <a href="{{ $url }}" target="_blank"
                        class="group/rev relative h-9 w-9 overflow-hidden rounded-lg border border-red-200 bg-white shadow-xs dark:border-red-800 dark:bg-gray-900 transition-all hover:scale-110 hover:shadow"
                        title="معاينة المرفق {{ $index + 1 }}">
                        <img src="{{ $url }}" alt="مرفق {{ $index + 1 }}" class="h-full w-full object-cover" />
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover/rev:opacity-100 transition-opacity">
                            <x-heroicon-o-eye class="w-3.5 h-3.5 text-white" />
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Slot for Custom UI Elements --}}
    {{ $bodyExtra ?? '' }}

    {{-- Uploaded Designs Section --}}
    @if(count($designFiles) > 0)
    <div class="px-4 pb-3">
        <label class="block text-[10px] font-extrabold text-gray-400 dark:text-gray-550 mb-1.5 uppercase tracking-wider">التصاميم المرفوعة</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach(array_slice($designFiles, 0, 4) as $file)
            @php
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            @endphp
            <a href="{{ Storage::url($file) }}" target="_blank"
                class="group/img relative h-12 w-12 overflow-hidden rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-black/50 transition-all duration-200 hover:scale-105 hover:shadow-sm">
                @if($isImg)
                <img src="{{ Storage::url($file) }}" alt="تصميم" class="h-full w-full object-cover transition-transform duration-300 group-hover/img:scale-[1.05]" />
                <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/img:opacity-100">
                    <x-heroicon-o-eye class="w-4 h-4 text-white" />
                </div>
                @else
                <div class="flex h-full w-full flex-col items-center justify-center bg-gray-100 dark:bg-gray-900 text-gray-400">
                    <x-heroicon-o-document class="w-4 h-4" />
                    <span class="text-[8px] font-bold uppercase mt-0.5">{{ $ext }}</span>
                </div>
                @endif
            </a>
            @endforeach
            @if(count($designFiles) > 4)
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-gray-100 bg-gray-50 text-xs font-bold text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                +{{ count($designFiles) - 4 }}
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Action Button Footer Section --}}
    <div class="mt-auto px-4 pb-4 pt-1">
        @if($actionDisabled)
        <div class="flex w-full cursor-default items-center justify-center gap-1.5 rounded-lg border border-blue-100/60 bg-blue-50/50 min-h-[44px] py-3 text-xs font-bold text-blue-600 dark:border-blue-900/30 dark:bg-blue-950/20 dark:text-blue-400">
            <x-heroicon-m-eye class="w-3.5 h-3.5" />
            <span>قيد المراجعة</span>
        </div>
        @else
        <button @if($actionWireClick) wire:click="{!! $actionWireClick !!}" @endif
            class="flex w-full items-center justify-center gap-1.5 rounded-lg min-h-[44px] py-3 text-sm font-bold text-white shadow-sm transition-all hover:shadow focus-visible:outline-none focus-visible:ring-2 active:scale-[0.98] {{ $actionClasses }}">
            <x-dynamic-component :component="$actionIcon" class="w-3.5 h-3.5" />
            <span>{{ $actionLabel }}</span>
        </button>
        @endif
    </div>
</div>