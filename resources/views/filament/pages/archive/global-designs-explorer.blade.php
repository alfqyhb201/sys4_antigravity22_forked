@php
    $designs = $this->globalDesigns;
    $tags = $this->explorerTags;
    $designers = $this->explorerDesigners;
    $clients = $this->explorerClients;
    $pageDesignIds = $designs->pluck('id')->toArray();
    $hasActiveFilters = !empty($this->explorerSearch) || $this->explorerTag !== 'all' || $this->explorerDesigner !== 'all' || $this->explorerClient !== 'all' || $this->explorerDateRange !== 'all';
    $selectedCount = count($this->selectedDesignIds);
    $allOnPageSelected = count($pageDesignIds) > 0 && count(array_intersect($this->selectedDesignIds, $pageDesignIds)) === count($pageDesignIds);
@endphp

<div
    class="space-y-5"
    x-data="{
        lightboxOpen: false,
        lightboxImage: '',
        lightboxTitle: '',
        lightboxDesigner: '',
        lightboxClient: '',
        lightboxDate: '',
        lightboxDownloadUrl: '',
        revisionOpen: false,
        revisionDesignId: null,
        revisionDesignTitle: '',
        revisionDesignerName: '',
        revisionFeedback: '',
        revisionDesignThumb: '',
        modifiedDesignIds: [],
        appendPreset(preset) {
            if (this.revisionFeedback.trim().length > 0) {
                this.revisionFeedback += ' - ' + preset;
            } else {
                this.revisionFeedback = preset;
            }
        },
        openRevision(id, title, designer, thumb) {
            this.revisionDesignId = id;
            this.revisionDesignTitle = title;
            this.revisionDesignerName = designer;
            this.revisionDesignThumb = thumb;
            this.revisionFeedback = '';
            this.revisionOpen = true;
        },
        submitRevisionModal() {
            if (!this.revisionFeedback.trim()) {
                return;
            }
            $wire.submitRevision(this.revisionDesignId, this.revisionFeedback);
            this.modifiedDesignIds.push(this.revisionDesignId);
            this.revisionOpen = false;
        },
        openLightbox(imgUrl, title, designer, client, date, downloadUrl) {
            this.lightboxImage = imgUrl;
            this.lightboxTitle = title;
            this.lightboxDesigner = designer;
            this.lightboxClient = client;
            this.lightboxDate = date;
            this.lightboxDownloadUrl = downloadUrl;
            this.lightboxOpen = true;
        }
    }"
    @keydown.escape.window="if (lightboxOpen) lightboxOpen = false; if (revisionOpen) revisionOpen = false;"
>
    {{-- شريط الفلاتر والتحكم المتقدم --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4">
        {{-- الصف الأول: البحث وأزرار التبديل --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            {{-- حقل البحث الفوري --}}
            <div class="relative flex-1">
                <x-heroicon-m-magnifying-glass class="absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="explorerSearch"
                    placeholder="ابحث في محتوى الفكرة، التاق، اسم الشركة، المصمم، المُرسِل..."
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2.5 pr-10 pl-9 text-xs text-gray-900 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100 dark:focus:border-primary-500"
                />
                @if(!empty($this->explorerSearch))
                    <button
                        type="button"
                        wire:click="$set('explorerSearch', '')"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        title="مسح البحث"
                    >
                        <x-heroicon-m-x-mark class="h-4 w-4" />
                    </button>
                @endif
            </div>

            {{-- أزرار نمط العرض وإعادة التعيين --}}
            <div class="flex items-center gap-2">
                @if($hasActiveFilters)
                    <button
                        type="button"
                        wire:click="resetExplorerFilters"
                        class="inline-flex items-center gap-1 rounded-xl border border-rose-200 bg-rose-50/50 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300 dark:hover:bg-rose-900/50"
                        title="إعادة تعيين جميع الفلاتر"
                    >
                        <x-heroicon-m-arrow-path class="h-3.5 w-3.5" />
                        <span>تصفير الفلاتر</span>
                    </button>
                @endif

                {{-- مبدل نمط العرض (شبكة / جدول) --}}
                <div class="flex items-center rounded-xl border border-gray-200 bg-gray-50/70 p-1 dark:border-gray-800 dark:bg-gray-950">
                    <button
                        type="button"
                        wire:click="$set('explorerViewMode', 'grid')"
                        class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $this->explorerViewMode === 'grid' ? 'bg-white text-primary-700 shadow-xs dark:bg-gray-800 dark:text-primary-300' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                        title="عرض شبكة بصرية"
                    >
                        <x-heroicon-m-squares-2x2 class="h-4 w-4" />
                        <span>شبكة</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('explorerViewMode', 'table')"
                        class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $this->explorerViewMode === 'table' ? 'bg-white text-primary-700 shadow-xs dark:bg-gray-800 dark:text-primary-300' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
                        title="عرض جدول بيانات"
                    >
                        <x-heroicon-m-table-cells class="h-4 w-4" />
                        <span>جدول</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- الصف الثاني: القوائم المنسدلة للفلاتر --}}
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4">
            {{-- فلتر الوسم --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1">الوسم (Tag):</label>
                <select
                    wire:model.live="explorerTag"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 px-3 text-xs text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                >
                    <option value="all">جميع الوسوم (الكل)</option>
                    @foreach($tags as $tagItem)
                        <option value="{{ $tagItem->id }}">{{ $tagItem->name }} ({{ $tagItem->client_tag_distributions_count }})</option>
                    @endforeach
                </select>
            </div>

            {{-- فلتر المصمم --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1">المصمم:</label>
                <select
                    wire:model.live="explorerDesigner"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 px-3 text-xs text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                >
                    <option value="all">جميع المصممين</option>
                    @foreach($designers as $designerItem)
                        <option value="{{ $designerItem->id }}">{{ $designerItem->user?->name ?? 'مصمم #'.$designerItem->id }}</option>
                    @endforeach
                </select>
            </div>

            {{-- فلتر العميل --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1">العميل:</label>
                <select
                    wire:model.live="explorerClient"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 px-3 text-xs text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                >
                    <option value="all">جميع العملاء</option>
                    @foreach($clients as $clientItem)
                        <option value="{{ $clientItem->id }}">{{ $clientItem->company }}</option>
                    @endforeach
                </select>
            </div>

            {{-- فلتر التاريخ --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 dark:text-gray-400 mb-1">تاريخ الإرسال:</label>
                <select
                    wire:model.live="explorerDateRange"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 px-3 text-xs text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                >
                    <option value="all">كل التواريخ</option>
                    <option value="today">اليوم فقط</option>
                    <option value="last_7_days">آخر 7 أيام</option>
                    <option value="last_30_days">آخر 30 يوماً</option>
                    <option value="this_month">هذا الشهر</option>
                    <option value="last_month">الشهر السابق</option>
                </select>
            </div>
        </div>

        {{-- الصف الثالث: شريط وسوم التاقات السريعة --}}
        @if($tags->count() > 0)
            <div class="flex flex-wrap items-center gap-1.5 pt-2 border-t border-gray-100 dark:border-gray-800/80">
                <span class="text-[11px] font-bold text-gray-400 ml-1">الوسوم الشائعة:</span>
                <button
                    type="button"
                    wire:click="$set('explorerTag', 'all')"
                    class="rounded-lg px-2.5 py-1 text-[11px] font-bold transition {{ $this->explorerTag === 'all' ? 'bg-primary-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    الكل
                </button>
                @foreach($tags->take(12) as $tagItem)
                    <button
                        type="button"
                        wire:click="$set('explorerTag', '{{ $tagItem->id }}')"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-bold transition {{ (string) $this->explorerTag === (string) $tagItem->id ? 'bg-primary-600 text-white shadow-xs' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    >
                        # {{ $tagItem->name }} ({{ $tagItem->client_tag_distributions_count }})
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- شريط الإجراءات الجماعية (يظهر عند تحديد عناصر) --}}
    @if($selectedCount > 0)
        <div class="flex items-center justify-between rounded-2xl bg-primary-50 border border-primary-200 p-3.5 dark:bg-primary-950/40 dark:border-primary-900/50 shadow-sm animate-fadeIn">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-white text-xs font-extrabold">
                    {{ $selectedCount }}
                </span>
                <span class="text-xs font-bold text-primary-900 dark:text-primary-200">
                    تم تحديد {{ $selectedCount }} تصميم
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="downloadSelectedExplorerZip"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="downloadSelectedExplorerZip">
                        <x-heroicon-m-archive-box-arrow-down class="h-4 w-4" />
                    </span>
                    <span wire:loading wire:target="downloadSelectedExplorerZip" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    <span>تحميل المحدد كـ ZIP</span>
                </button>

                <button
                    type="button"
                    wire:click="$set('selectedDesignIds', [])"
                    class="rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
                    إلغاء التحديد
                </button>
            </div>
        </div>
    @endif

    {{-- شريط ملخص النتائج وأزرار تحديد الصفحة --}}
    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-1">
        <div class="flex items-center gap-2">
            <span>إجمالي النتائج: <strong class="text-gray-900 dark:text-white">{{ $designs->total() }}</strong> تصميم</span>
            @if($hasActiveFilters)
                <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                    (تصفية مفعّلة)
                </span>
            @endif
        </div>

        @if($designs->count() > 0)
            <button
                type="button"
                wire:click="toggleSelectAllOnPage(@js($pageDesignIds))"
                class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
            >
                {{ $allOnPageSelected ? 'إلغاء تحديد عناصر الصفحة' : 'تحديد كل عناصر الصفحة ('.$designs->count().')' }}
            </button>
        @endif
    </div>

    {{-- عرض الشبكة البصرية (Visual Cards Grid) --}}
    @if($this->explorerViewMode === 'grid')
        @if($designs->count() > 0)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach($designs as $design)
                    @php
                        $hasAttachment = !empty($design->attachment_path);
                        $imgUrl = $hasAttachment ? asset('storage/' . $design->attachment_path) : null;
                        $tagName = $design->tag?->name ?? 'بدون وسم';
                        $ideaName = $design->idea?->name ?? $design->custom_idea ?? 'محتوى مخصص';
                        $clientCompany = $design->clientDesigner?->client?->company ?? 'عميل غير محدد';
                        $designerName = $design->clientDesigner?->designer?->user?->name ?? 'مصمم غير محدد';
                        $senderName = $design->sender?->name ?? '—';
                        $dateFormatted = $design->completed_at ? $design->completed_at->format('Y-m-d') : $design->updated_at->format('Y-m-d');
                        $safeDownloadName = \Illuminate\Support\Str::slug($clientCompany . '-' . $ideaName . '-' . $design->id, '_');
                        $isSelected = in_array($design->id, $this->selectedDesignIds);
                    @endphp

                    <div
                        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border transition-all duration-200 {{ $isSelected ? 'border-primary-500 ring-2 ring-primary-500/20 bg-primary-50/10 dark:bg-primary-950/20' : 'border-gray-200 bg-white hover:border-primary-400 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-600' }}"
                    >
                        {{-- ترويسة البطاقة وصندوق التحديد والوسم --}}
                        <div class="flex items-center justify-between border-b border-gray-100 p-2.5 dark:border-gray-800">
                            <label class="inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedDesignIds"
                                    value="{{ $design->id }}"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                />
                            </label>

                            <span class="inline-flex items-center gap-0.5 rounded-md bg-primary-50 px-1.5 py-0.5 text-[10px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 truncate max-w-[110px]" title="{{ $tagName }}">
                                <span>#</span>
                                <span>{{ $tagName }}</span>
                            </span>
                        </div>

                        {{-- منطقة المعاينة البصرية --}}
                        <div class="relative flex min-h-[140px] items-center justify-center p-2">
                            @if($hasAttachment)
                                <div class="relative flex h-36 w-full items-center justify-center overflow-hidden rounded-xl bg-gray-950/5 shadow-inner dark:bg-gray-950/40">
                                    <img
                                        src="{{ $imgUrl }}"
                                        alt="{{ $ideaName }}"
                                        class="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                                        loading="lazy"
                                        @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($clientCompany) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')"
                                    />

                                    {{-- تراكب المعاينة السريعة --}}
                                    <div class="absolute inset-0 flex items-center justify-center bg-gray-950/40 opacity-0 backdrop-blur-xs transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg bg-white/90 px-2.5 py-1 text-[11px] font-extrabold text-gray-900 shadow-md dark:bg-gray-900/90 dark:text-white pointer-events-auto cursor-pointer hover:bg-white"
                                            @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($clientCompany) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')"
                                        >
                                            <x-heroicon-m-eye class="h-3.5 w-3.5 text-primary-600" />
                                            <span>تكبير</span>
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center py-6 text-center text-gray-400 dark:text-gray-600">
                                    <x-heroicon-o-photo class="h-8 w-8 stroke-1" />
                                    <p class="mt-1 text-[10px]">بدون ملف</p>
                                </div>
                            @endif
                        </div>

                        {{-- تفاصيل التصميم --}}
                        <div class="space-y-1.5 p-2.5 pt-0">
                            {{-- اسم الشركة --}}
                            <div class="flex items-center gap-1 text-[11px] font-extrabold text-gray-900 dark:text-white truncate" title="{{ $clientCompany }}">
                                <x-heroicon-m-building-office class="h-3.5 w-3.5 text-gray-400 shrink-0" />
                                <span class="truncate">{{ $clientCompany }}</span>
                            </div>

                            {{-- اسم الفكرة/المحتوى --}}
                            <div class="text-[10px] text-gray-600 dark:text-gray-300 font-medium line-clamp-1" title="{{ $ideaName }}">
                                💡 {{ $ideaName }}
                            </div>

                            {{-- المصمم والتاريخ --}}
                            <div class="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500 pt-0.5">
                                <span class="truncate max-w-[80px]" title="{{ $designerName }}">{{ $designerName }}</span>
                                <span>{{ $dateFormatted }}</span>
                            </div>
                        </div>

                        {{-- أزرار الإجراءات السريعة أسفل البطاقة --}}
                        <div class="flex items-center gap-1 border-t border-gray-100 bg-gray-50/70 p-1.5 dark:border-gray-800 dark:bg-gray-900/70">
                            @if($hasAttachment)
                                <a
                                    href="{{ $imgUrl }}"
                                    download="{{ $safeDownloadName }}"
                                    class="flex-1 inline-flex items-center justify-center gap-0.5 rounded-lg border border-gray-200 bg-white py-1 text-[10px] font-bold text-gray-700 shadow-xs transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    title="تحميل التصميم"
                                >
                                    <x-heroicon-m-arrow-down-tray class="h-3 w-3" />
                                    <span>تحميل</span>
                                </a>
                            @endif

                            <button
                                type="button"
                                @click="openRevision({{ $design->id }}, '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ $imgUrl }}')"
                                class="inline-flex items-center justify-center rounded-lg bg-amber-500/10 border border-amber-500/20 p-1 text-amber-700 transition hover:bg-amber-500/20 dark:bg-amber-950/40 dark:border-amber-800/50 dark:text-amber-300"
                                title="طلب تعديل على التصميم"
                            >
                                <x-heroicon-m-arrow-path class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- حالة عدم وجود نتائج --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                    <x-heroicon-o-squares-2x2 class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-bold text-gray-900 dark:text-white">لم يتم العثور على أي تصاميم مطابقة</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-sm">
                    جرب تغيير خيارات البحث أو إعادة ضبط الفلاتر المحددة.
                </p>
                @if($hasActiveFilters)
                    <button
                        type="button"
                        wire:click="resetExplorerFilters"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-primary-500"
                    >
                        <x-heroicon-m-arrow-path class="h-4 w-4" />
                        <span>إعادة ضبط جميع الفلاتر</span>
                    </button>
                @endif
            </div>
        @endif
    @else
        {{-- عرض الجدول المنظم (Table View) --}}
        @if($designs->count() > 0)
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-400 font-bold">
                            <tr>
                                <th class="p-3 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        wire:click="toggleSelectAllOnPage(@js($pageDesignIds))"
                                        @checked($allOnPageSelected)
                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                    />
                                </th>
                                <th class="p-3 w-16 text-center">التصميم</th>
                                <th class="p-3">الشركة / العميل</th>
                                <th class="p-3">الوسم</th>
                                <th class="p-3">الفكرة / المحتوى</th>
                                <th class="p-3">المصمم</th>
                                <th class="p-3">تاريخ الإرسال</th>
                                <th class="p-3">المُرسِل</th>
                                <th class="p-3 text-center w-28">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($designs as $design)
                                @php
                                    $hasAttachment = !empty($design->attachment_path);
                                    $imgUrl = $hasAttachment ? asset('storage/' . $design->attachment_path) : null;
                                    $tagName = $design->tag?->name ?? 'بدون وسم';
                                    $ideaName = $design->idea?->name ?? $design->custom_idea ?? 'محتوى مخصص';
                                    $clientCompany = $design->clientDesigner?->client?->company ?? 'عميل غير محدد';
                                    $designerName = $design->clientDesigner?->designer?->user?->name ?? 'مصمم غير محدد';
                                    $senderName = $design->sender?->name ?? '—';
                                    $dateFormatted = $design->completed_at ? $design->completed_at->format('Y-m-d h:i A') : $design->updated_at->format('Y-m-d');
                                    $safeDownloadName = \Illuminate\Support\Str::slug($clientCompany . '-' . $ideaName . '-' . $design->id, '_');
                                    $isSelected = in_array($design->id, $this->selectedDesignIds);
                                @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/50 transition {{ $isSelected ? 'bg-primary-50/20 dark:bg-primary-950/20' : '' }}">
                                    <td class="p-3 text-center">
                                        <input
                                            type="checkbox"
                                            wire:model.live="selectedDesignIds"
                                            value="{{ $design->id }}"
                                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                                        />
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($hasAttachment)
                                            <img
                                                src="{{ $imgUrl }}"
                                                alt="{{ $ideaName }}"
                                                class="h-10 w-10 mx-auto rounded-lg object-cover cursor-pointer border border-gray-200 dark:border-gray-700 shadow-xs hover:scale-110 transition"
                                                @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($clientCompany) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')"
                                            />
                                        @else
                                            <span class="inline-block h-8 w-8 rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-800 p-1 text-center">
                                                <x-heroicon-o-photo class="h-6 w-6" />
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3 font-extrabold text-gray-900 dark:text-white">
                                        {{ $clientCompany }}
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                                            # {{ $tagName }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-gray-700 dark:text-gray-300 font-medium">
                                        {{ $ideaName }}
                                    </td>
                                    <td class="p-3 text-gray-600 dark:text-gray-400">
                                        {{ $designerName }}
                                    </td>
                                    <td class="p-3 text-gray-500 dark:text-gray-400 text-[11px]">
                                        {{ $dateFormatted }}
                                    </td>
                                    <td class="p-3 text-gray-500 dark:text-gray-400">
                                        {{ $senderName }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            @if($hasAttachment)
                                                <button
                                                    type="button"
                                                    @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($clientCompany) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')"
                                                    class="p-1 rounded-lg text-gray-400 hover:text-primary-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                                    title="معاينة مكبرة"
                                                >
                                                    <x-heroicon-m-eye class="h-4 w-4" />
                                                </button>
                                                <a
                                                    href="{{ $imgUrl }}"
                                                    download="{{ $safeDownloadName }}"
                                                    class="p-1 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                                    title="تحميل التصميم"
                                                >
                                                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                                                </a>
                                            @endif
                                            <button
                                                type="button"
                                                @click="openRevision({{ $design->id }}, '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ $imgUrl }}')"
                                                class="p-1 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                                title="طلب تعديل"
                                            >
                                                <x-heroicon-m-arrow-path class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-800 dark:bg-gray-900">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                    <x-heroicon-o-table-cells class="h-7 w-7" />
                </span>
                <h3 class="mt-4 text-base font-bold text-gray-900 dark:text-white">لا توجد نتائج مطابقة</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    جرب تصفير الفلاتر للوصول لجميع التصاميم.
                </p>
            </div>
        @endif
    @endif

    {{-- روابط الترقيم والتنقل (Pagination) --}}
    @if($designs->hasPages())
        <div class="pt-2">
            {{ $designs->links() }}
        </div>
    @endif

    {{-- مودال طلب التعديل التفاعلي --}}
    <div
        x-show="revisionOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            @click.outside="revisionOpen = false"
            class="w-full max-w-lg overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
        >
            <div class="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <x-heroicon-m-arrow-path class="h-4 w-4" />
                    </span>
                    <div>
                        <h3 class="font-extrabold text-gray-900 dark:text-white text-sm">
                            طلب تعديل على التصميم
                        </h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            سيتم إشعار المصمم وإعادة التصميم للوحته
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="revisionOpen = false"
                    class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <x-heroicon-m-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="p-4 space-y-4">
                <div class="flex items-center gap-3 rounded-2xl bg-gray-50 p-3 border border-gray-100 dark:bg-gray-950 dark:border-gray-800">
                    <template x-if="revisionDesignThumb">
                        <img :src="revisionDesignThumb" class="h-12 w-12 rounded-xl object-contain bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800" />
                    </template>
                    <div class="space-y-0.5">
                        <div class="font-bold text-gray-900 dark:text-white text-xs line-clamp-1" x-text="revisionDesignTitle"></div>
                        <div class="text-[11px] text-gray-500 dark:text-gray-400">
                            المصمم المُكلّف: <span class="font-bold text-primary-600 dark:text-primary-400" x-text="revisionDesignerName"></span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-600 dark:text-gray-300">
                        مقترحات سريعة (انقر للإضافة):
                    </label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(['تعديل النصوص والأخطاء الإملائية', 'تعديل الألوان ودرجاتها', 'تغيير موضع أو حجم الشعار', 'تحديث التاريخ أو أرقام التواصل', 'تعديل المقاس بما يناسب المنصة'] as $preset)
                            <button
                                type="button"
                                @click="appendPreset('{{ $preset }}')"
                                class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold text-gray-600 transition hover:border-primary-400 hover:bg-primary-50/50 hover:text-primary-700 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-primary-500"
                            >
                                + {{ $preset }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-gray-800 dark:text-gray-200">
                        ملاحظات التعديل المطلوبة <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        x-model="revisionFeedback"
                        rows="3"
                        placeholder="اكتب ملاحظات التعديل بوضوح ودقة للمصمم..."
                        class="w-full rounded-2xl border border-gray-200 bg-gray-50/50 p-3 text-xs text-gray-900 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100 dark:focus:border-primary-500"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
                <button
                    type="button"
                    @click="revisionOpen = false"
                    class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    إلغاء
                </button>
                <button
                    type="button"
                    @click="submitRevisionModal()"
                    :disabled="!revisionFeedback.trim()"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-amber-500 active:bg-amber-700 disabled:opacity-50"
                >
                    <x-heroicon-m-paper-airplane class="h-3.5 w-3.5" />
                    <span>إرسال طلب التعديل</span>
                </button>
            </div>
        </div>
    </div>

    {{-- نافذة المعاينة المكبرة (Lightbox) --}}
    <div
        x-show="lightboxOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/85 backdrop-blur-md"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            @click.outside="lightboxOpen = false"
            class="relative flex flex-col items-center max-w-4xl max-h-[90vh] w-full"
        >
            <button
                type="button"
                @click="lightboxOpen = false"
                class="absolute -top-11 left-0 flex items-center gap-1 rounded-xl bg-white/10 px-3 py-1 text-xs font-bold text-white backdrop-blur-md transition hover:bg-white/20"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
                <span>إغلاق (Esc)</span>
            </button>

            <div class="overflow-hidden rounded-2xl bg-black/40 p-2 shadow-2xl ring-1 ring-white/10">
                <img
                    :src="lightboxImage"
                    :alt="lightboxTitle"
                    class="max-h-[75vh] max-w-full rounded-xl object-contain"
                />
            </div>

            <div class="mt-3 flex items-center justify-between w-full rounded-2xl bg-gray-900/90 border border-gray-800 px-4 py-2.5 backdrop-blur-md text-white text-xs">
                <div class="space-y-0.5">
                    <div class="font-bold text-sm" x-text="lightboxTitle"></div>
                    <div class="flex items-center gap-3 text-[11px] text-gray-400">
                        <span x-text="'الشركة: ' + lightboxClient"></span>
                        <span>•</span>
                        <span x-text="'المصمم: ' + lightboxDesigner"></span>
                        <span>•</span>
                        <span x-text="lightboxDate"></span>
                    </div>
                </div>

                <a
                    :href="lightboxDownloadUrl"
                    download
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-3.5 py-1.5 font-bold text-white shadow-sm transition hover:bg-primary-500"
                >
                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                    <span>تحميل الصورة</span>
                </a>
            </div>
        </div>
    </div>
</div>
