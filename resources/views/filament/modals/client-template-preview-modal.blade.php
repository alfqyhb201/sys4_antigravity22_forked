@php
    $fileUrl = $template->file ? \Illuminate\Support\Facades\Storage::url($template->file) : null;
    $updatedAtFormatted = $template->updated_at ? $template->updated_at->format('Y-m-d h:i A') : null;
    $updatedAtDiff = $template->updated_at ? $template->updated_at->diffForHumans() : null;
@endphp

<div class="space-y-4 text-sm font-sans" dir="rtl" x-data="{ copied: false }">
    {{-- الشريط العلوي للمعلومات --}}
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                <x-heroicon-m-swatch class="h-5 w-5" />
            </span>
            <div>
                <h4 class="font-bold text-gray-900 dark:text-white">{{ $typeLabel }}</h4>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $client->company }}</p>
            </div>
        </div>

        @if($updatedAtFormatted)
            <div class="flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <x-heroicon-m-clock class="h-4 w-4 text-gray-400" />
                <span>آخر تحديث: <strong>{{ $updatedAtFormatted }}</strong> ({{ $updatedAtDiff }})</span>
            </div>
        @endif
    </div>

    {{-- نافذة عرض الصورة --}}
    <div class="relative flex min-h-[300px] max-h-[60vh] w-full items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-950/5 p-2 shadow-inner dark:border-gray-800 dark:bg-gray-900/60">
        @if($fileUrl)
            <img 
                src="{{ $fileUrl }}" 
                alt="{{ $typeLabel }} - {{ $client->company }}" 
                class="max-h-[55vh] max-w-full rounded-lg object-contain transition-transform duration-300 hover:scale-[1.02]" 
                loading="lazy"
            />
        @else
            <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                <x-heroicon-o-photo class="h-16 w-16 stroke-1 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm">لا يتوفر ملف صورة لهذا القالب</p>
            </div>
        @endif
    </div>

    {{-- معلومات المسار المحلي والأزرار التفاعلية --}}
    <div class="space-y-3 pt-1">
        @if($template->local_path)
            <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                <div class="flex items-center gap-2 overflow-hidden text-xs text-gray-700 dark:text-gray-300">
                    <x-heroicon-m-folder class="h-4 w-4 shrink-0 text-amber-500" />
                    <span class="shrink-0 font-bold">المسار المحلي:</span>
                    <code class="truncate rounded bg-white px-2 py-0.5 font-mono text-[11px] text-gray-800 shadow-sm dark:bg-gray-900 dark:text-gray-200">
                        {{ $template->local_path }}
                    </code>
                </div>
                <button
                    type="button"
                    x-on:click="
                        navigator.clipboard.writeText('{{ addslashes($template->local_path) }}');
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <template x-if="!copied">
                        <span class="inline-flex items-center gap-1">
                            <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" />
                            <span>نسخ المسار</span>
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                            <x-heroicon-m-check class="h-3.5 w-3.5" />
                            <span>تم النسخ!</span>
                        </span>
                    </template>
                </button>
            </div>
        @endif

        {{-- أزرار الإجراءات --}}
        <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
            @if($fileUrl)
                <a
                    href="{{ $fileUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" />
                    <span>فتح بالحجم الكامل</span>
                </a>

                <a
                    href="{{ $fileUrl }}"
                    download="{{ $client->company }}-{{ $typeLabel }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none dark:bg-primary-500 dark:hover:bg-primary-400"
                >
                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                    <span>تحميل القالب</span>
                </a>
            @endif
        </div>
    </div>
</div>
