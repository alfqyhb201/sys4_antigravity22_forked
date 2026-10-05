<x-filament-panels::page>
    <div class="space-y-6" x-data="{ copied: false }">

        {{-- ──────────────────────────────────────────────
             1. بطاقات إحصائيات السجلات ومستويات الأخطاء
             ────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- إجمالي السجلات --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي سجلات الملف</p>
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-1">
                        {{ number_format($logsData['stats']['total'] ?? 0) }}
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5">سجل مسجل بالملف</p>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-xl">
                    <x-heroicon-o-document-text class="w-6 h-6" />
                </div>
            </div>

            {{-- الأخطاء الحرجة --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">الأخطاء والاستثناءات</p>
                    <p class="text-xl font-extrabold text-red-600 dark:text-red-400 mt-1">
                        {{ number_format($logsData['stats']['error_count'] ?? 0) }}
                    </p>
                    <p class="text-[11px] text-red-400 mt-0.5">Critical / Error / Alert</p>
                </div>
                <div class="p-3 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-xl">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                </div>
            </div>

            {{-- التحذيرات --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">التحذيرات المسجلة</p>
                    <p class="text-xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">
                        {{ number_format($logsData['stats']['warning_count'] ?? 0) }}
                    </p>
                    <p class="text-[11px] text-amber-500 mt-0.5">Warnings</p>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-xl">
                    <x-heroicon-o-shield-exclamation class="w-6 h-6" />
                </div>
            </div>

            {{-- حجم الملف وتوقيت التعديل --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">حجم الملف الحالي</p>
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-1">
                        {{ $logsData['stats']['file_size'] ?? '0 B' }}
                    </p>
                    <p class="text-[11px] text-gray-400 font-mono mt-0.5" title="آخر تعديل">
                        {{ $logsData['stats']['last_modified'] ?? '—' }}
                    </p>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 rounded-xl">
                    <x-heroicon-o-circle-stack class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────
             2. شريط أدوات التحكم والفلترة
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm space-y-4">
            {{-- الصف العلوي: اختيار الملف + الإجراءات --}}
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                {{-- أزرار ملفات اللوج المتوفرة --}}
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 ml-1">الملف:</span>
                    @foreach($availableFiles as $file)
                        <button
                            type="button"
                            wire:click="changeFile('{{ $file['name'] }}')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $selectedFile === $file['name'] ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                            <x-heroicon-m-document-text class="w-3.5 h-3.5 opacity-70" />
                            <span>{{ $file['name'] }}</span>
                            <span class="text-[10px] opacity-75 font-mono">({{ $file['size_formatted'] }})</span>
                        </button>
                    @endforeach
                </div>

                {{-- أزرار الإجراءات على الملف --}}
                <div class="flex items-center gap-2 self-end md:self-auto">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-path"
                        wire:click="loadLogs"
                        wire:loading.attr="disabled">
                        تحديث
                    </x-filament::button>

                    <x-filament::button
                        color="primary"
                        size="sm"
                        icon="heroicon-m-arrow-down-tray"
                        wire:click="downloadLog">
                        تحميل الملف
                    </x-filament::button>

                    <x-filament::button
                        color="danger"
                        outlined
                        size="sm"
                        icon="heroicon-m-trash"
                        wire:click="clearLog"
                        wire:loading.attr="disabled"
                        wire:confirm="هل أنت متأكد تماماً من تفريغ كافة سجلات هذا الملف؟ لن تتمكن من استعادتها بعد ذلك.">
                        تفريغ السجل
                    </x-filament::button>
                </div>
            </div>

            {{-- الصف السفلي: البحث النصي + فلاتر المستويات --}}
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                {{-- فلاتر المستويات --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        wire:click="changeLevel('all')"
                        class="px-3 py-1 rounded-lg text-xs font-semibold transition-all {{ $selectedLevel === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200' }}">
                        الكل ({{ $logsData['stats']['total'] ?? 0 }})
                    </button>

                    <button
                        type="button"
                        wire:click="changeLevel('errors')"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold transition-all {{ $selectedLevel === 'errors' ? 'bg-red-600 text-white' : 'bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 hover:bg-red-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                        الأخطاء الحرجة ({{ $logsData['stats']['error_count'] ?? 0 }})
                    </button>

                    <button
                        type="button"
                        wire:click="changeLevel('warning')"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold transition-all {{ $selectedLevel === 'warning' ? 'bg-amber-600 text-white' : 'bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 hover:bg-amber-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        التحذيرات ({{ $logsData['stats']['warning_count'] ?? 0 }})
                    </button>

                    <button
                        type="button"
                        wire:click="changeLevel('info')"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold transition-all {{ $selectedLevel === 'info' ? 'bg-blue-600 text-white' : 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 hover:bg-blue-100' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                        المعلومات ({{ $logsData['stats']['info_count'] ?? 0 }})
                    </button>

                    <button
                        type="button"
                        wire:click="changeLevel('debug')"
                        class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold transition-all {{ $selectedLevel === 'debug' ? 'bg-gray-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-200' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                        التصحيح ({{ $logsData['stats']['debug_count'] ?? 0 }})
                    </button>
                </div>

                {{-- حقل البحث الفوري --}}
                <div class="relative min-w-[260px] max-w-md">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="بحث في نص الخطأ أو التتبع..."
                        class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/50 text-gray-900 dark:text-white px-3.5 py-2 pl-9 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-all">
                    <div class="absolute left-3 top-2.5 text-gray-400 pointer-events-none">
                        <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                    </div>
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────
             3. قائمة سجلات الأخطاء المعروضة
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-list-bullet class="w-4 h-4 text-gray-500" />
                    <span>السجلات المعروضة ({{ number_format($logsData['total_matching'] ?? 0) }} من إجمالي {{ number_format($logsData['total_in_file'] ?? 0) }})</span>
                </h3>

                {{-- مؤشر رقم الصفحة --}}
                @if(($logsData['last_page'] ?? 1) > 1)
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                        صفحة {{ $logsData['current_page'] ?? 1 }} من {{ $logsData['last_page'] ?? 1 }}
                    </span>
                @endif
            </div>

            @if(empty($logsData['entries']))
                <div class="p-12 text-center">
                    <div class="inline-flex p-4 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mb-3">
                        <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-500" />
                    </div>
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">لا توجد سجلات مطابقة</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        @if($search || $selectedLevel !== 'all')
                            لم يتم العثور على أي أخطاء تطابق معايير الفلترة الحالية. جرب تغيير الفلتر أو مسح البحث.
                        @else
                            ملف السجل فارغ ونظيف تماماً! لا توجد أخطاء مسجلة حالياً.
                        @endif
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($logsData['entries'] as $entry)
                        <div class="p-4 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors flex flex-col md:flex-row md:items-start justify-between gap-4">
                            <div class="space-y-1.5 flex-1 min-w-0">
                                {{-- الترويسة: الشارة + التوقيت + البيئة --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    {{-- شارة المستوى --}}
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold
                                        {{ $entry['badge_color'] === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : '' }}
                                        {{ $entry['badge_color'] === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : '' }}
                                        {{ $entry['badge_color'] === 'info' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : '' }}
                                        {{ $entry['badge_color'] === 'gray' ? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' : '' }}">
                                        {{ $entry['badge_label'] }}
                                    </span>

                                    {{-- بيئة التشغيل --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ strtoupper($entry['env']) }}
                                    </span>

                                    {{-- التوقيت --}}
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 flex items-center gap-1">
                                        <x-heroicon-m-clock class="w-3.5 h-3.5" />
                                        <span>{{ $entry['formatted_time'] }}</span>
                                        <span class="text-[11px] text-gray-400 font-sans">({{ $entry['human_time'] }})</span>
                                    </span>
                                </div>

                                {{-- نص الخطأ --}}
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100 font-mono break-words leading-relaxed">
                                    {{ $entry['message'] }}
                                </div>
                            </div>

                            {{-- زر عرض التفاصيل والـ Stack Trace --}}
                            <div class="shrink-0 flex items-center gap-2 self-start">
                                <x-filament::button
                                    size="xs"
                                    color="{{ $entry['badge_color'] === 'danger' ? 'danger' : 'gray' }}"
                                    outlined
                                    icon="heroicon-m-eye"
                                    wire:click="openStackTraceModal('{{ $entry['id'] }}')">
                                    تفاصيل الخطأ وتتبعه
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- شريط الترقيم والتنقل بين الصفحات --}}
                @if(($logsData['last_page'] ?? 1) > 1)
                    <div class="p-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            عرض الصفحة {{ $logsData['current_page'] }} من أصل {{ $logsData['last_page'] }}
                        </div>

                        <div class="flex items-center gap-2">
                            <x-filament::button
                                size="xs"
                                color="gray"
                                icon="heroicon-m-chevron-right"
                                wire:click="previousPage"
                                :disabled="($logsData['current_page'] ?? 1) <= 1">
                                السابق
                            </x-filament::button>

                            <x-filament::button
                                size="xs"
                                color="gray"
                                icon="heroicon-m-chevron-left"
                                icon-position="after"
                                wire:click="nextPage"
                                :disabled="($logsData['current_page'] ?? 1) >= ($logsData['last_page'] ?? 1)">
                                التالي
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- ──────────────────────────────────────────────
             4. مودال تفاصيل الخطأ وتتبع الـ Stack Trace
             ────────────────────────────────────────────── --}}
        @if($showModal && $activeEntry)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                {{-- خلفية معتمة --}}
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-xs transition-opacity" wire:click="closeModal"></div>

                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 text-right shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl border border-gray-200 dark:border-gray-800">
                        {{-- رأس المودال --}}
                        <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                        {{ $activeEntry['badge_color'] === 'danger' ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : '' }}
                                        {{ $activeEntry['badge_color'] === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : '' }}
                                        {{ $activeEntry['badge_color'] === 'info' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : '' }}
                                        {{ $activeEntry['badge_color'] === 'gray' ? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' : '' }}">
                                        {{ $activeEntry['badge_label'] }}
                                    </span>
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                        {{ $activeEntry['formatted_time'] }} ({{ $activeEntry['human_time'] }})
                                    </span>
                                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                        {{ strtoupper($activeEntry['env']) }}
                                    </span>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-white font-mono break-words leading-relaxed" id="modal-title">
                                    {{ $activeEntry['message'] }}
                                </h3>
                            </div>

                            <button
                                type="button"
                                wire:click="closeModal"
                                class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <x-heroicon-m-x-mark class="w-6 h-6" />
                            </button>
                        </div>

                        {{-- محتوى الـ Stack Trace --}}
                        <div class="p-6 space-y-4">
                            @if(!empty($activeEntry['stack_trace']))
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                            <x-heroicon-m-code-bracket class="w-4 h-4 text-primary-500" />
                                            <span>مسار وتتبع الخطأ (Stack Trace):</span>
                                        </label>

                                        {{-- زر النسخ للحافظة --}}
                                        <button
                                            type="button"
                                            @click="
                                                navigator.clipboard.writeText($refs.stackContent.innerText);
                                                copied = true;
                                                setTimeout(() => copied = false, 2500);
                                            "
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 transition-all">
                                            <span x-show="!copied" class="flex items-center gap-1">
                                                <x-heroicon-m-clipboard-document class="w-3.5 h-3.5" />
                                                <span>نسخ التتبع</span>
                                            </span>
                                            <span x-show="copied" class="flex items-center gap-1 text-emerald-500 font-bold" style="display: none;">
                                                <x-heroicon-m-check class="w-3.5 h-3.5" />
                                                <span>تم النسخ بنجاح!</span>
                                            </span>
                                        </button>
                                    </div>

                                    <div class="p-4 rounded-xl bg-gray-950 text-gray-200 font-mono text-xs border border-gray-800 max-h-[480px] overflow-y-auto leading-relaxed dir-ltr text-left selection:bg-primary-600 selection:text-white">
                                        <pre x-ref="stackContent" class="whitespace-pre-wrap">{{ $activeEntry['stack_trace'] }}</pre>
                                    </div>
                                </div>
                            @else
                                <div class="p-8 text-center bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">لا يوجد تتبع Stack Trace إضافي لهذا السجل.</p>
                                </div>
                            @endif
                        </div>

                        {{-- أزرار أسفل المودال --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                            <x-filament::button
                                color="gray"
                                wire:click="closeModal">
                                إغلاق
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
