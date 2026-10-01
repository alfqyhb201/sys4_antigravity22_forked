@php
    $totalDesigns = $designs->count();
    $tags = $designs->pluck('tag.name')->filter()->unique()->values();
    $designers = $designs->pluck('clientDesigner.designer.user.name')->filter()->unique()->values();
    $lastDesignDate = $designs->first()?->completed_at?->diffForHumans() ?? $designs->first()?->updated_at?->diffForHumans() ?? '—';
@endphp

<div
    class="space-y-5 text-sm font-sans"
    dir="rtl"
    x-data="{
        search: '',
        selectedTag: 'all',
        selectedDesigner: 'all',
        lightboxOpen: false,
        lightboxImage: '',
        lightboxTitle: '',
        lightboxDesigner: '',
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
        openLightbox(imgUrl, title, designer, date, downloadUrl) {
            this.lightboxImage = imgUrl;
            this.lightboxTitle = title;
            this.lightboxDesigner = designer;
            this.lightboxDate = date;
            this.lightboxDownloadUrl = downloadUrl;
            this.lightboxOpen = true;
        },
        matches(tag, designer, title, date) {
            if (this.selectedTag !== 'all' && tag !== this.selectedTag) {
                return false;
            }
            if (this.selectedDesigner !== 'all' && designer !== this.selectedDesigner) {
                return false;
            }
            if (!this.search.trim()) {
                return true;
            }
            const q = this.search.toLowerCase().trim();
            return (tag && tag.toLowerCase().includes(q)) ||
                   (designer && designer.toLowerCase().includes(q)) ||
                   (title && title.toLowerCase().includes(q)) ||
                   (date && date.toLowerCase().includes(q));
        }
    }"
    @keydown.escape.window="if (lightboxOpen) lightboxOpen = false; if (revisionOpen) revisionOpen = false;"
>
    {{-- بطاقة الترويسة الرئيسية والإحصائيات السريعة --}}
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-white via-gray-50/50 to-primary-50/20 p-5 shadow-sm dark:border-gray-800 dark:from-gray-900 dark:via-gray-900/80 dark:to-primary-950/20">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            {{-- معلومات العميل --}}
            <div class="space-y-1.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-700 shadow-sm dark:bg-primary-900/60 dark:text-primary-300">
                        <x-heroicon-m-building-office class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">
                            {{ $client->company }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            @if($client->client_name)
                                <span class="flex items-center gap-1 font-medium text-gray-700 dark:text-gray-300">
                                    <x-heroicon-m-user class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ $client->client_name }}</span>
                                </span>
                                <span>•</span>
                            @endif
                            @if($client->category)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ $client->category->name }}
                                </span>
                            @endif
                            @if($client->location)
                                <span class="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                    <x-heroicon-m-map-pin class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ $client->location->name }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- إحصائيات سريعة وأزرار الإجراءات --}}
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-1.5 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                    <x-heroicon-m-photo class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    <div class="text-xs">
                        <span class="text-gray-500 dark:text-gray-400">التصاميم:</span>
                        <span class="font-extrabold text-gray-900 dark:text-white mr-1">{{ $totalDesigns }}</span>
                    </div>
                </div>

                @if($totalDesigns > 0)
                    <button
                        type="button"
                        wire:click="downloadZip({{ $client->id }})"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 active:bg-primary-700 disabled:opacity-50"
                        title="تحميل جميع تصاميم العميل كملف مضغوط"
                    >
                        <span wire:loading.remove wire:target="downloadZip({{ $client->id }})">
                            <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                        </span>
                        <span wire:loading wire:target="downloadZip({{ $client->id }})" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                        <span>تحميل الكل (ZIP)</span>
                    </button>
                @endif

                <a
                    href="{{ \App\Filament\Pages\Archive\ArchivedClientDesigns::getUrl(['client_id' => $client->id]) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    title="فتح صفحة الأرشيف الكاملة في نافذة جديدة"
                >
                    <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4 text-gray-400" />
                    <span>الصفحة الكاملة</span>
                </a>
            </div>
        </div>
    </div>

    {{-- شريط البحث والتصفية المباشرة (Alpine.js) --}}
    @if($totalDesigns > 0)
        <div class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                {{-- حقل البحث الفوري --}}
                <div class="relative flex-1">
                    <x-heroicon-m-magnifying-glass class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="text"
                        x-model="search"
                        placeholder="ابحث في التاقات، الأفكار، المصممين، التاريخ..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 pr-9 pl-3 text-xs text-gray-900 transition focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100 dark:focus:border-primary-500"
                    />
                    <button
                        type="button"
                        x-show="search.length > 0"
                        @click="search = ''"
                        class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    >
                        <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                    </button>
                </div>

                {{-- تصفية بالمصمم --}}
                @if($designers->count() > 1)
                    <div class="w-full sm:w-48">
                        <select
                            x-model="selectedDesigner"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2 px-3 text-xs text-gray-900 transition focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                        >
                            <option value="all">كل المصممين ({{ $designers->count() }})</option>
                            @foreach($designers as $designerName)
                                <option value="{{ $designerName }}">{{ $designerName }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            {{-- أزرار وسوم التاقات السريعة --}}
            @if($tags->count() > 0)
                <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-gray-100 dark:border-gray-800/80">
                    <span class="text-[11px] font-bold text-gray-400 ml-1">الوسوم:</span>
                    <button
                        type="button"
                        @click="selectedTag = 'all'"
                        :class="selectedTag === 'all' 
                            ? 'bg-primary-600 text-white shadow-sm' 
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                        class="rounded-lg px-2.5 py-1 text-[11px] font-bold transition"
                    >
                        الكل ({{ $totalDesigns }})
                    </button>

                    @foreach($tags as $tagItem)
                        @php
                            $tagCount = $designs->where('tag.name', $tagItem)->count();
                        @endphp
                        <button
                            type="button"
                            @click="selectedTag = '{{ addslashes($tagItem) }}'"
                            :class="selectedTag === '{{ addslashes($tagItem) }}' 
                                ? 'bg-primary-600 text-white shadow-sm' 
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                            class="rounded-lg px-2.5 py-1 text-[11px] font-bold transition"
                        >
                            # {{ $tagItem }} ({{ $tagCount }})
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- الشبكة البصرية للتصاميم (Visual Grid) --}}
    @if($totalDesigns > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($designs as $design)
                @php
                    $hasAttachment = !empty($design->attachment_path);
                    $imgUrl = $hasAttachment ? asset('storage/' . $design->attachment_path) : null;
                    $tagName = $design->tag?->name ?? 'بدون وسم';
                    $ideaName = $design->idea?->name ?? $design->custom_idea ?? 'محتوى مخصص';
                    $designerName = $design->clientDesigner?->designer?->user?->name ?? 'مصمم غير محدد';
                    $senderName = $design->sender?->name ?? '—';
                    $dateFormatted = $design->completed_at ? $design->completed_at->format('Y-m-d h:i A') : $design->updated_at->format('Y-m-d');
                    $dateDiff = $design->completed_at ? $design->completed_at->diffForHumans() : $design->updated_at->diffForHumans();
                    $safeDownloadName = \Illuminate\Support\Str::slug($client->company . '-' . $ideaName . '-' . $design->id, '_');
                @endphp

                <div
                    x-show="matches('{{ addslashes($tagName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($ideaName) }}', '{{ addslashes($dateFormatted) }}')"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all duration-200 hover:border-primary-400 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-600"
                >
                    {{-- ترويسة البطاقة --}}
                    <div class="flex items-center justify-between border-b border-gray-100 p-3 dark:border-gray-800">
                        <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">
                            <span>#</span>
                            <span class="truncate max-w-[120px]">{{ $tagName }}</span>
                        </span>

                        {{-- شارة الحالة --}}
                        <template x-if="modifiedDesignIds.includes({{ $design->id }})">
                            <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300 animate-pulse">
                                <x-heroicon-m-arrow-path class="h-3 w-3" />
                                <span>مطلوب تعديل</span>
                            </span>
                        </template>
                        <template x-if="!modifiedDesignIds.includes({{ $design->id }})">
                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span>مكتمل</span>
                            </span>
                        </template>
                    </div>

                    {{-- منطقة المعاينة البصرية للصورة --}}
                    <div class="relative flex min-h-[190px] items-center justify-center p-3">
                        @if($hasAttachment)
                            <div class="relative flex h-44 w-full items-center justify-center overflow-hidden rounded-xl bg-gray-950/5 shadow-inner dark:bg-gray-950/40">
                                <img
                                    src="{{ $imgUrl }}"
                                    alt="{{ $ideaName }}"
                                    class="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                                    loading="lazy"
                                    @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')"
                                />

                                {{-- طبقة المعاينة السريعة عند التمرير --}}
                                <div class="absolute inset-0 flex items-center justify-center bg-gray-950/40 opacity-0 backdrop-blur-xs transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/90 px-3 py-1.5 text-xs font-extrabold text-gray-900 shadow-lg dark:bg-gray-900/90 dark:text-white pointer-events-auto cursor-pointer"
                                          @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ addslashes($dateFormatted) }}', '{{ $imgUrl }}')">
                                        <x-heroicon-m-eye class="h-4 w-4 text-primary-600" />
                                        <span>معاينة مكبرة</span>
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center py-8 text-center text-gray-400 dark:text-gray-600">
                                <x-heroicon-o-photo class="h-12 w-12 stroke-1" />
                                <p class="mt-1.5 text-xs">لا يوجد ملف مرفق</p>
                            </div>
                        @endif
                    </div>

                    {{-- تفاصيل التصميم --}}
                    <div class="space-y-2 p-3 pt-0">
                        <h3 class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm line-clamp-1" title="{{ $ideaName }}">
                            {{ $ideaName }}
                        </h3>

                        <div class="space-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1 font-medium text-gray-700 dark:text-gray-300">
                                    <x-heroicon-m-pencil-square class="h-3.5 w-3.5 text-primary-500" />
                                    <span>المصمم: {{ $designerName }}</span>
                                </span>
                                <span class="flex items-center gap-1 text-gray-400">
                                    <x-heroicon-m-paper-airplane class="h-3 w-3" />
                                    <span>{{ $senderName }}</span>
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-[10px] text-gray-400 dark:text-gray-500 pt-0.5">
                                <span class="flex items-center gap-1">
                                    <x-heroicon-m-calendar class="h-3 w-3" />
                                    <span>{{ $dateFormatted }}</span>
                                </span>
                                <span>{{ $dateDiff }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- شريط أزرار الإجراءات المباشرة (تحميل / طلب تعديل) --}}
                    <div class="flex items-center gap-1.5 border-t border-gray-100 bg-gray-50/70 p-2.5 dark:border-gray-800 dark:bg-gray-900/70">
                        @if($hasAttachment)
                            <a
                                href="{{ $imgUrl }}"
                                download="{{ $safeDownloadName }}"
                                class="flex-1 inline-flex items-center justify-center gap-1 rounded-xl border border-gray-200 bg-white py-1.5 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-100 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                title="تحميل ملف التصميم"
                            >
                                <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" />
                                <span>تحميل</span>
                            </a>
                        @endif

                        <button
                            type="button"
                            @click="openRevision({{ $design->id }}, '{{ addslashes($ideaName) }}', '{{ addslashes($designerName) }}', '{{ $imgUrl }}')"
                            class="flex-1 inline-flex items-center justify-center gap-1 rounded-xl bg-amber-500/10 border border-amber-500/20 py-1.5 text-xs font-bold text-amber-700 transition hover:bg-amber-500/20 dark:bg-amber-950/40 dark:border-amber-800/50 dark:text-amber-300 dark:hover:bg-amber-900/50"
                            title="إعادة التصميم للمصمم لإجراء تعديلات"
                        >
                            <x-heroicon-m-arrow-path class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                            <span>طلب تعديل</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- حالة عدم وجود تصاميم --}}
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 p-12 text-center dark:border-gray-800 dark:bg-gray-900/30">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                <x-heroicon-o-photo class="h-7 w-7" />
            </span>
            <h3 class="mt-4 text-base font-bold text-gray-900 dark:text-white">لا توجد تصاميم مؤرشفة</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-sm">
                لم يتم أرشفة أي تصاميم مكتملة لهذا العميل حتى الآن.
            </p>
        </div>
    @endif

    {{-- مودال طلب التعديل التفاعلي داخل الـ Drawer --}}
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
            {{-- ترويسة مودال طلب التعديل --}}
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
                            سيتم إعادة التصميم إلى لوحة المصمم
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

            {{-- محتوى المودال --}}
            <div class="p-4 space-y-4">
                {{-- ملخص التصميم والمصمم --}}
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

                {{-- مقترحات التعديل السريعة (Tags) --}}
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

                {{-- حقل كتابة الملاحظات --}}
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

            {{-- أزرار حفظ وإلغاء المودال --}}
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
            {{-- زر إغلاق المعاينة --}}
            <button
                type="button"
                @click="lightboxOpen = false"
                class="absolute -top-11 left-0 flex items-center gap-1 rounded-xl bg-white/10 px-3 py-1 text-xs font-bold text-white backdrop-blur-md transition hover:bg-white/20"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
                <span>إغلاق (Esc)</span>
            </button>

            {{-- الصورة بالحجم الكامل --}}
            <div class="overflow-hidden rounded-2xl bg-black/40 p-2 shadow-2xl ring-1 ring-white/10">
                <img
                    :src="lightboxImage"
                    :alt="lightboxTitle"
                    class="max-h-[75vh] max-w-full rounded-xl object-contain"
                />
            </div>

            {{-- شريط معلومات الصورة وزر التحميل --}}
            <div class="mt-3 flex items-center justify-between w-full rounded-2xl bg-gray-900/90 border border-gray-800 px-4 py-2.5 backdrop-blur-md text-white text-xs">
                <div class="space-y-0.5">
                    <div class="font-bold text-sm" x-text="lightboxTitle"></div>
                    <div class="flex items-center gap-3 text-[11px] text-gray-400">
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
