<x-filament-panels::page>
    @php
        $stats = $this->getStorageStats();
        $filesData = $this->getFilesData();
        $files = $filesData['items'];
        $selectedCount = count($selectedFiles);
    @endphp

    <div class="space-y-6" dir="rtl">
        {{-- 1. بطاقات الإحصائيات العلوية --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- إجمالي التخزين --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي التخزين العام</p>
                        <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $stats['total_size_formatted'] }}</p>
                        <span class="text-xs text-gray-400 dark:text-gray-500 font-medium mt-1 inline-block">{{ $stats['total_files_count'] }} ملف في النظام</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40 dark:text-purple-400">
                        <x-heroicon-o-server class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- الملفات المرتبطة بالسجلات --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">الملفات المرتبطة والنشطة</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $stats['linked_size_formatted'] }}</p>
                        <span class="text-xs text-emerald-600/80 dark:text-emerald-400/80 font-medium mt-1 inline-block">{{ $stats['linked_files_count'] }} ملف مرتبط بسجلات</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                        <x-heroicon-o-check-circle class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- الملفات اليتيمة غير المرتبطة --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-amber-500/20 bg-amber-50/20 dark:bg-gray-900 dark:ring-amber-500/30 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            <span>الملفات اليتيمة (غير المرتبطة)</span>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">مستهدفة للتنظيف</span>
                        </p>
                        <p class="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $stats['orphaned_size_formatted'] }}</p>
                        <span class="text-xs text-amber-700/80 dark:text-amber-400/80 font-medium mt-1 inline-block">{{ $stats['orphaned_files_count'] }} ملف غير مرتبط بأي سجل</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100/80 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">
                        <x-heroicon-o-trash class="h-6 w-6" />
                    </div>
                </div>
            </div>

            {{-- الملفات المؤقتة livewire-tmp --}}
            <div class="relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition hover:shadow-md">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">الملفات المؤقتة (Livewire)</p>
                        <p class="mt-2 text-2xl font-bold text-gray-700 dark:text-gray-300">{{ $stats['temp_size_formatted'] }}</p>
                        <span class="text-xs text-gray-400 dark:text-gray-500 font-medium mt-1 inline-block">{{ $stats['temp_files_count'] }} ملف مؤقت</span>
                    </div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                        <x-heroicon-o-clock class="h-6 w-6" />
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. شريط الإجراءات والتحكم السريع --}}
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 bg-white p-4 rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center gap-2">
                {{-- زر تنظيف كافة الملفات اليتيمة --}}
                <x-filament::button
                    color="danger"
                    icon="heroicon-o-trash"
                    wire:click="cleanAllOrphans"
                    wire:confirm="هل أنت متأكد من رغبتك في حذف كافة الملفات غير المرتبطة بقاعدة البيانات نهائياً؟ (سيتم استثناء الملفات المرفوعة خلال آخر 60 دقيقة لحمايتها)"
                    wire:loading.attr="disabled"
                >
                    تنظيف كافة الملفات اليتيمة ({{ $stats['orphaned_files_count'] }})
                </x-filament::button>

                {{-- زر تنظيف الملفات المؤقتة --}}
                <x-filament::button
                    color="gray"
                    icon="heroicon-o-sparkles"
                    wire:click="cleanTempFiles"
                    wire:confirm="هل تريد تنظيف ملفات الرفع المؤقتة الأقدم من 24 ساعة؟"
                    wire:loading.attr="disabled"
                >
                    مسح الملفات المؤقتة
                </x-filament::button>
            </div>

            <div class="flex items-center gap-2 self-end md:self-auto">
                {{-- مفتاح التبديل: الملفات اليتيمة فقط أو الكل --}}
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300 select-none">
                    <input type="checkbox" wire:model.live="onlyOrphans" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800">
                    <span>عرض الملفات اليتيمة فقط</span>
                </label>

                <div class="h-4 w-px bg-gray-200 dark:bg-gray-700 mx-1"></div>

                {{-- تبديل طريقة العرض (شبكة / جدول) --}}
                <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-800">
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'grid')"
                        class="p-1.5 rounded-md transition {{ $viewMode === 'grid' ? 'bg-white shadow-xs text-primary-600 dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                        title="عرض شبكة"
                    >
                        <x-heroicon-m-squares-2x2 class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        wire:click="$set('viewMode', 'table')"
                        class="p-1.5 rounded-md transition {{ $viewMode === 'table' ? 'bg-white shadow-xs text-primary-600 dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                        title="عرض جدول"
                    >
                        <x-heroicon-m-list-bullet class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        {{-- 3. أدوات الفلترة والبحث السريع --}}
        <div class="bg-white p-4 rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 space-y-4">
            <div class="flex flex-col md:flex-row gap-3">
                {{-- البحث بالاسم أو المسار --}}
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <x-heroicon-m-magnifying-glass class="h-4 w-4 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="searchQuery"
                        placeholder="ابحث باسم الملف أو المسار (مثال: banner.jpg أو clients/492)..."
                        class="block w-full rounded-xl border-0 py-2.5 pr-10 pl-4 text-sm text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-gray-800 dark:text-white dark:ring-gray-700 dark:focus:ring-primary-500"
                    >
                    @if(!empty($searchQuery))
                        <button
                            type="button"
                            wire:click="$set('searchQuery', '')"
                            class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            <x-heroicon-m-x-mark class="h-4 w-4" />
                        </button>
                    @endif
                </div>

                {{-- فلتر نوع الملف --}}
                <div class="w-full md:w-56">
                    <select
                        wire:model.live="selectedFileType"
                        class="block w-full rounded-xl border-0 py-2.5 px-3 text-sm text-gray-900 shadow-xs ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-gray-800 dark:text-white dark:ring-gray-700 dark:focus:ring-primary-500"
                    >
                        <option value="all">جميع أنواع الملفات</option>
                        <option value="image">صور فقط (JPG, PNG, WebP...)</option>
                        <option value="design">ملفات تصميم (AI, PSD, CDR...)</option>
                        <option value="document">مستندات (PDF, Word...)</option>
                        <option value="archive">ملفات مضغوطة (ZIP, RAR...)</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
            </div>

            {{-- تبويبات التصنيفات والمجلدات --}}
            <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 pt-3 dark:border-gray-800">
                <button
                    type="button"
                    wire:click="$set('selectedCategory', 'all')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $selectedCategory === 'all' ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-950/40 dark:text-primary-300 dark:ring-primary-500/30' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                >
                    <span>كافة المجلدات</span>
                    <span class="rounded-full bg-gray-200/80 px-1.5 py-0.5 text-[10px] dark:bg-gray-700">{{ $stats['total_files_count'] }}</span>
                </button>

                @foreach($stats['categories'] as $cat)
                    <button
                        type="button"
                        wire:click="$set('selectedCategory', '{{ $cat['key'] }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition {{ $selectedCategory === $cat['key'] ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-950/40 dark:text-primary-300 dark:ring-primary-500/30' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                    >
                        <span>{{ $cat['label'] }}</span>
                        <span class="rounded-full {{ $onlyOrphans ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-gray-200/80 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }} px-1.5 py-0.5 text-[10px]">
                            {{ $onlyOrphans ? $cat['orphaned_count'] : $cat['total_count'] }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- 4. شريط الإجراءات الجماعية عند تحديد ملفات --}}
        @if($selectedCount > 0)
            <div class="flex items-center justify-between bg-primary-900 text-white px-5 py-3 rounded-2xl shadow-lg animate-in fade-in slide-in-from-top-2">
                <div class="flex items-center gap-3 text-sm font-medium">
                    <x-heroicon-s-check-circle class="w-5 h-5 text-primary-300" />
                    <span>تم تحديد <strong>{{ $selectedCount }}</strong> ملف</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="deleteSelected"
                        wire:confirm="هل أنت متأكد من حذف {{ $selectedCount }} ملف محدد نهائياً من التخزين؟"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-red-500 transition shadow-xs"
                    >
                        <x-heroicon-m-trash class="w-4 h-4" />
                        <span>حذف المحدد</span>
                    </button>
                    <button
                        type="button"
                        wire:click="deselectAll"
                        class="inline-flex items-center rounded-lg bg-white/10 px-3 py-1.5 text-xs font-medium text-white hover:bg-white/20 transition"
                    >
                        إلغاء التحديد
                    </button>
                </div>
            </div>
        @endif

        {{-- 5. شريط التحديد السريع للصفحة الحالية --}}
        @if(count($files) > 0)
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-2">
                <div>
                    عرض <strong>{{ $filesData['from'] }}</strong> إلى <strong>{{ $filesData['to'] }}</strong> من إجمالي <strong>{{ $filesData['total'] }}</strong> ملف
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="selectAllInPage" class="text-primary-600 dark:text-primary-400 hover:underline">
                        تحديد جميع عناصر الصفحة ({{ count($files) }})
                    </button>
                    @if($selectedCount > 0)
                        <span>•</span>
                        <button type="button" wire:click="deselectAll" class="text-gray-500 hover:underline">
                            إلغاء الكل
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- 6. محتوى استعراض الملفات --}}
        @if(empty($files))
            <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center dark:border-gray-800 bg-white dark:bg-gray-900">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 dark:bg-gray-800 text-gray-400">
                    <x-heroicon-o-check-badge class="h-8 w-8 text-emerald-500" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">التخزين نظيف ومثالي!</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $onlyOrphans ? 'لا توجد أي ملفات يتيمة تطابق معايير الفلترة المحددة.' : 'لا توجد ملفات تطابق الفلترة الحالية.' }}
                </p>
                @if($onlyOrphans)
                    <button
                        type="button"
                        wire:click="$set('onlyOrphans', false)"
                        class="mt-4 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        عرض جميع الملفات المسجلة في التخزين
                    </button>
                @endif
            </div>
        @elseif($viewMode === 'grid')
            {{-- عرض الشبكة (Grid Cards) --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                @foreach($files as $file)
                    @php
                        $isSelected = in_array($file['path'], $selectedFiles);
                    @endphp
                    <div class="group relative flex flex-col justify-between rounded-2xl bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition hover:shadow-md hover:ring-primary-500/50 {{ $isSelected ? 'ring-2 ring-primary-600 dark:ring-primary-500 bg-primary-50/10' : '' }}">
                        {{-- رأس الكرت مع خيار التحديد وشارة الحالة --}}
                        <div class="flex items-center justify-between mb-2">
                            <label class="cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:click="toggleSelectFile('{{ $file['path'] }}')"
                                    {{ $isSelected ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                >
                            </label>

                            @if($file['is_orphan'])
                                <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-300">
                                    يتيم
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                                    مرتبط
                                </span>
                            @endif
                        </div>

                        {{-- منطقة المعاينة --}}
                        <div
                            class="relative flex h-32 w-full items-center justify-center rounded-xl bg-gray-50 dark:bg-gray-800/60 overflow-hidden cursor-pointer group-hover:opacity-95"
                            wire:click="openPreviewModal('{{ $file['path'] }}', '{{ $file['url'] }}', '{{ $file['filename'] }}', '{{ $file['size_formatted'] }}', '{{ $file['extension'] }}')"
                        >
                            @if($file['is_image'] && $file['url'])
                                <img
                                    src="{{ $file['url'] }}"
                                    alt="{{ $file['filename'] }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition group-hover:scale-105"
                                >
                            @else
                                <div class="flex flex-col items-center justify-center p-2 text-center">
                                    @if($file['type'] === 'design')
                                        <x-heroicon-o-paint-brush class="h-10 w-10 text-indigo-500 mb-1" />
                                    @elseif($file['type'] === 'document')
                                        <x-heroicon-o-document-text class="h-10 w-10 text-blue-500 mb-1" />
                                    @elseif($file['type'] === 'archive')
                                        <x-heroicon-o-archive-box class="h-10 w-10 text-amber-500 mb-1" />
                                    @else
                                        <x-heroicon-o-paper-clip class="h-10 w-10 text-gray-400 mb-1" />
                                    @endif
                                    <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400">{{ $file['extension'] ?: 'ملف' }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- تفاصيل الملف --}}
                        <div class="mt-2.5 space-y-1">
                            <p class="text-xs font-semibold text-gray-900 dark:text-white truncate" title="{{ $file['filename'] }}">
                                {{ $file['filename'] }}
                            </p>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 truncate" title="{{ $file['path'] }}">
                                {{ $file['category_label'] }}
                            </p>
                            <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400 pt-1 border-t border-gray-100 dark:border-gray-800/80">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $file['size_formatted'] }}</span>
                                <span>{{ $file['last_modified_formatted'] }}</span>
                            </div>
                        </div>

                        {{-- أزرار الإجراءات السريعة --}}
                        <div class="mt-2.5 flex items-center justify-end gap-1 border-t border-gray-100 pt-2 dark:border-gray-800">
                            {{-- تحميل --}}
                            <button
                                type="button"
                                wire:click="downloadFile('{{ $file['path'] }}', '{{ $file['disk'] }}')"
                                class="p-1 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 dark:hover:text-gray-200 dark:hover:bg-gray-800"
                                title="تحميل الملف"
                            >
                                <x-heroicon-o-arrow-down-tray class="w-3.5 h-3.5" />
                            </button>

                            {{-- حذف --}}
                            <button
                                type="button"
                                wire:click="deleteSingleFile('{{ $file['path'] }}', '{{ $file['disk'] }}')"
                                wire:confirm="هل أنت متأكد من حذف هذا الملف نهائياً؟"
                                class="p-1 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"
                                title="حذف الملف"
                            >
                                <x-heroicon-o-trash class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- عرض الجدول (Table View) --}}
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-right">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="py-3.5 pr-4 pl-3 text-right">
                                <span class="sr-only">تحديد</span>
                            </th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">المعاينة</th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">اسم الملف والمسار</th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">التصنيف</th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">الحجم</th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">الحالة</th>
                            <th scope="col" class="py-3.5 px-3 text-xs font-semibold text-gray-900 dark:text-white">تاريخ الرفع</th>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-900 dark:text-white">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach($files as $file)
                            @php
                                $isSelected = in_array($file['path'], $selectedFiles);
                            @endphp
                            <tr class="transition hover:bg-gray-50/50 dark:hover:bg-gray-800/30 {{ $isSelected ? 'bg-primary-50/20 dark:bg-primary-950/20' : '' }}">
                                <td class="py-3 pr-4 pl-3">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleSelectFile('{{ $file['path'] }}')"
                                        {{ $isSelected ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                    >
                                </td>
                                <td class="py-3 px-3">
                                    <div
                                        class="h-10 w-10 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center cursor-pointer"
                                        wire:click="openPreviewModal('{{ $file['path'] }}', '{{ $file['url'] }}', '{{ $file['filename'] }}', '{{ $file['size_formatted'] }}', '{{ $file['extension'] }}')"
                                    >
                                        @if($file['is_image'] && $file['url'])
                                            <img src="{{ $file['url'] }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-[10px] font-bold text-gray-500">{{ strtoupper($file['extension'] ?: 'FILE') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-3 max-w-xs">
                                    <div class="text-xs font-semibold text-gray-900 dark:text-white truncate" title="{{ $file['filename'] }}">
                                        {{ $file['filename'] }}
                                    </div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-mono truncate" dir="ltr" title="{{ $file['path'] }}">
                                        {{ $file['path'] }}
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                        {{ $file['category_label'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ $file['size_formatted'] }}
                                </td>
                                <td class="py-3 px-3">
                                    @if($file['is_orphan'])
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/50 dark:text-amber-300">
                                            يتيم
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                                            مرتبط
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $file['last_modified_formatted'] }}
                                </td>
                                <td class="py-3 pl-4 pr-3 text-left">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="downloadFile('{{ $file['path'] }}', '{{ $file['disk'] }}')"
                                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                            title="تحميل"
                                        >
                                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="deleteSingleFile('{{ $file['path'] }}', '{{ $file['disk'] }}')"
                                            wire:confirm="هل أنت متأكد من حذف هذا الملف نهائياً؟"
                                            class="text-red-400 hover:text-red-600"
                                            title="حذف"
                                        >
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- 7. الترقيم والتنقل بين الصفحات (Pagination) --}}
        @if($filesData['last_page'] > 1)
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 rounded-2xl dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-1 justify-between sm:hidden">
                    <button
                        type="button"
                        wire:click="previousPage"
                        @disabled($filesData['current_page'] <= 1)
                        class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-300"
                    >
                        السابق
                    </button>
                    <button
                        type="button"
                        wire:click="nextPage"
                        @disabled($filesData['current_page'] >= $filesData['last_page'])
                        class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-300"
                    >
                        التالي
                    </button>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs text-gray-700 dark:text-gray-400">
                            الصفحة <strong>{{ $filesData['current_page'] }}</strong> من إجمالي <strong>{{ $filesData['last_page'] }}</strong>
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-xl shadow-xs gap-1" aria-label="Pagination">
                            <button
                                type="button"
                                wire:click="previousPage"
                                @disabled($filesData['current_page'] <= 1)
                                class="relative inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                <x-heroicon-m-chevron-right class="h-4 w-4 ml-1" />
                                السابق
                            </button>

                            @for($i = max(1, $filesData['current_page'] - 2); $i <= min($filesData['last_page'], $filesData['current_page'] + 2); $i++)
                                <button
                                    type="button"
                                    wire:click="gotoPage({{ $i }})"
                                    class="relative inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold {{ $filesData['current_page'] === $i ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
                                >
                                    {{ $i }}
                                </button>
                            @endfor

                            <button
                                type="button"
                                wire:click="nextPage"
                                @disabled($filesData['current_page'] >= $filesData['last_page'])
                                class="relative inline-flex items-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                التالي
                                <x-heroicon-m-chevron-left class="h-4 w-4 mr-1" />
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- 8. مودال المعاينة السريعة (Preview Modal) --}}
    <x-filament::modal id="media-preview-modal" width="3xl">
        <x-slot name="heading">
            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $previewName ?: 'معاينة الملف' }}</span>
        </x-slot>

        <div class="space-y-4" dir="rtl">
            <div class="flex items-center justify-center rounded-2xl bg-gray-900/5 dark:bg-black/30 p-2 overflow-hidden max-h-[60vh]">
                @if($previewUrl && in_array(strtolower($previewType ?? ''), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']))
                    <img src="{{ $previewUrl }}" alt="{{ $previewName }}" class="max-h-[55vh] max-w-full rounded-xl object-contain shadow-md">
                @else
                    <div class="py-12 flex flex-col items-center justify-center text-center">
                        <x-heroicon-o-document-magnifying-glass class="h-16 w-16 text-gray-400 mb-2" />
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">المعاينة المباشرة غير مدعومة لصيغة هذا الملف ({{ $previewType }}).</p>
                        <p class="text-xs text-gray-400 mt-1">يمكنك تحميل الملف لعرضه على جهازك.</p>
                    </div>
                @endif
            </div>

            <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300 space-y-1">
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">المسار:</span>
                    <span class="font-mono text-[11px] text-gray-900 dark:text-white select-all" dir="ltr">{{ $previewPath }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-medium text-gray-500">الحجم:</span>
                    <span class="font-semibold">{{ $previewSize }}</span>
                </div>
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex items-center justify-between w-full">
                @if($previewPath)
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            color="primary"
                            icon="heroicon-o-arrow-down-tray"
                            wire:click="downloadFile('{{ $previewPath }}', 'public')"
                        >
                            تحميل الملف
                        </x-filament::button>

                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-trash"
                            wire:click="deleteSingleFile('{{ $previewPath }}', 'public')"
                            wire:confirm="هل أنت متأكد من حذف هذا الملف نهائياً؟"
                        >
                            حذف نهائي
                        </x-filament::button>
                    </div>
                @endif

                <x-filament::button color="gray" wire:click="closePreviewModal">
                    إغلاق
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
