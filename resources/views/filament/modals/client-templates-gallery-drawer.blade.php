@php
    $allTypes = \App\Filament\Enums\ClientTemplateType::cases();
    $totalCount = count($allTypes);
    $presentTemplates = $record->templates->keyBy('type');
    $presentCount = $presentTemplates->filter(fn ($t) => !empty($t->file))->count();
    $percentage = $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0;

    $progressColorClass = match (true) {
        $presentCount === $totalCount => 'bg-emerald-500',
        $presentCount >= 3 => 'bg-amber-500',
        default => 'bg-rose-500',
    };

    $badgeColorClass = match (true) {
        $presentCount === $totalCount => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        $presentCount >= 3 => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        default => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
    };
@endphp

<div class="space-y-6 text-sm font-sans" dir="rtl">
    {{-- بطاقة رأس المعرض وإحصائية الاكتمال --}}
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-white to-gray-50/50 p-4 shadow-sm dark:border-gray-800 dark:from-gray-900 dark:to-gray-900/50">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                        <x-heroicon-m-building-office class="h-4 w-4" />
                    </span>
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">
                        {{ $record->company }}
                    </h3>
                </div>
                @if($record->client_name)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mr-9">
                        المسؤول: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->client_name }}</span>
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1 text-xs font-bold shadow-sm {{ $badgeColorClass }}">
                    @if($presentCount === $totalCount)
                        <x-heroicon-m-check-badge class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        <span>مكتمل كلياً ({{ $presentCount }}/{{ $totalCount }})</span>
                    @else
                        <x-heroicon-m-exclamation-circle class="h-4 w-4" />
                        <span>توفر {{ $presentCount }} من أصل {{ $totalCount }}</span>
                    @endif
                </span>
            </div>
        </div>

        {{-- شريط نسبة التقدم --}}
        <div class="mt-4 space-y-1.5">
            <div class="flex justify-between text-xs font-medium text-gray-600 dark:text-gray-400">
                <span>نسبة جاهزية القوالب</span>
                <span class="font-bold text-gray-900 dark:text-white">{{ $percentage }}%</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                <div 
                    class="h-full rounded-full transition-all duration-500 {{ $progressColorClass }}" 
                    style="width: {{ $percentage }}%"
                ></div>
            </div>
        </div>
    </div>

    {{-- شبكة بطاقات القوالب الـ 6 --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($allTypes as $type)
            @php
                $template = $presentTemplates->get($type->value);
                $hasFile = $template && !empty($template->file);
                $fileUrl = $hasFile ? \Illuminate\Support\Facades\Storage::url($template->file) : null;
                $updatedAtFormatted = ($template && $template->updated_at) ? $template->updated_at->format('Y-m-d') : null;
                $updatedAtDiff = ($template && $template->updated_at) ? $template->updated_at->diffForHumans() : null;
            @endphp

            <div 
                x-data="{ copied: false }"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border transition-all duration-200 
                {{ $hasFile 
                    ? 'border-gray-200 bg-white shadow-sm hover:shadow-md hover:border-primary-300 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700' 
                    : 'border-dashed border-gray-300 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/30' }}"
            >
                {{-- ترويسة البطاقة --}}
                <div class="flex items-center justify-between border-b border-gray-100 p-3.5 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md {{ $hasFile ? 'bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400' : 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500' }}">
                            <x-heroicon-m-swatch class="h-3.5 w-3.5" />
                        </span>
                        <span class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm">
                            {{ $type->getLabel() }}
                        </span>
                    </div>

                    @if($hasFile)
                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            <span>متوفر</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            <span>غير متوفر</span>
                        </span>
                    @endif
                </div>

                {{-- منطقة المعاينة البصرية للصورة --}}
                <div class="relative flex min-h-[160px] items-center justify-center p-3">
                    @if($hasFile)
                        <div class="relative flex h-36 w-full items-center justify-center overflow-hidden rounded-xl bg-gray-950/5 shadow-inner dark:bg-gray-950/40">
                            <img 
                                src="{{ $fileUrl }}" 
                                alt="{{ $type->getLabel() }}"
                                class="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center py-6 text-center text-gray-400 dark:text-gray-600">
                            <x-heroicon-o-photo class="h-10 w-10 stroke-1" />
                            <p class="mt-1.5 text-xs">لا يوجد قالب مرفوع</p>
                        </div>
                    @endif
                </div>

                {{-- تفاصيل وأزرار أسفل البطاقة --}}
                <div class="border-t border-gray-100 bg-gray-50/50 p-3 dark:border-gray-800 dark:bg-gray-900/70">
                    @if($hasFile)
                        <div class="space-y-2 text-xs">
                            <div class="flex items-center justify-between text-gray-500 dark:text-gray-400 text-[11px]">
                                <span class="flex items-center gap-1">
                                    <x-heroicon-m-calendar class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ $updatedAtFormatted }}</span>
                                </span>
                                <span>{{ $updatedAtDiff }}</span>
                            </div>

                            @if($template->local_path)
                                <div class="flex items-center justify-between gap-1 rounded bg-white px-2 py-1 text-[11px] border border-gray-200 dark:border-gray-800 dark:bg-gray-950">
                                    <span class="truncate font-mono text-gray-600 dark:text-gray-400 max-w-[140px]" title="{{ $template->local_path }}">
                                        {{ $template->local_path }}
                                    </span>
                                    <button 
                                        type="button" 
                                        x-on:click="
                                            navigator.clipboard.writeText('{{ addslashes($template->local_path) }}');
                                            copied = true;
                                            setTimeout(() => copied = false, 2000);
                                        "
                                        class="text-gray-400 hover:text-primary-600 transition"
                                        title="نسخ المسار"
                                    >
                                        <template x-if="!copied">
                                            <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" />
                                        </template>
                                        <template x-if="copied">
                                            <x-heroicon-m-check class="h-3.5 w-3.5 text-emerald-500" />
                                        </template>
                                    </button>
                                </div>
                            @endif

                            <div class="flex items-center gap-1.5 pt-1">
                                <a 
                                    href="{{ $fileUrl }}" 
                                    target="_blank" 
                                    class="flex-1 inline-flex items-center justify-center gap-1 rounded-lg border border-gray-200 bg-white py-1.5 text-[11px] font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                >
                                    <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" />
                                    <span>معاينة كاملة</span>
                                </a>
                                <a 
                                    href="{{ $fileUrl }}" 
                                    download="{{ $record->company }}-{{ $type->getLabel() }}" 
                                    class="inline-flex items-center justify-center rounded-lg bg-primary-50 p-1.5 text-primary-700 transition hover:bg-primary-100 dark:bg-primary-900/40 dark:text-primary-300 dark:hover:bg-primary-900/70"
                                    title="تحميل الملف"
                                >
                                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="py-1 text-center text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1 text-[11px]">
                                <x-heroicon-m-plus-circle class="h-3.5 w-3.5" />
                                <span>يمكن إسناد مهمة للمصمم لإنشائه</span>
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
