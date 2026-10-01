<x-filament-panels::page>
    <div class="mx-auto w-full max-w-5xl space-y-6 pb-12 font-sans" dir="rtl" wire:poll.30s.keep-alive
        x-data="{
            lightboxOpen: false,
            lightboxImg: '',
            lightboxTitle: '',
            lightboxClient: null,
            lightboxZoom: 1,
            clientDrawerOpen: false,
            clientDrawerData: null,
            drawerActiveTab: 'cliche',
            copiedPath: false,
            zoomIn() { this.lightboxZoom = Math.min(+(this.lightboxZoom + 0.25).toFixed(2), 3.5) },
            zoomOut() { this.lightboxZoom = Math.max(+(this.lightboxZoom - 0.25).toFixed(2), 0.5) },
            resetZoom() { this.lightboxZoom = 1 },
            openLightbox(img, title, client = null) {
                this.lightboxImg = img;
                this.lightboxTitle = title;
                this.lightboxClient = client;
                this.lightboxZoom = 1;
                this.lightboxOpen = true;
            },
            openClientDrawer(data) {
                this.clientDrawerData = data;
                this.drawerActiveTab = data?.cliche?.has_cliche ? 'cliche' : (data?.has_logo ? 'logo' : 'cliche');
                this.clientDrawerOpen = true;
                this.copiedPath = false;
            },
            closeClientDrawer() {
                this.clientDrawerOpen = false;
            }
        }"
        @keydown.escape.window="if (lightboxOpen) { lightboxOpen = false; } else if (clientDrawerOpen) { clientDrawerOpen = false; }">

        {{-- Header Section --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white/95 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/80 sm:p-6">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-1">
                        <h1 class="text-xl font-black tracking-tight text-gray-900 dark:text-white sm:text-2xl">لوحة مراجعة التصاميم</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">اعتمد التصميم مباشرة أو اطلب تعديلًا مع الحفاظ على تدفق مراجعة واضح وسريع.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 p-5 text-white shadow-md">
                        <div class="absolute -left-3 -top-4 opacity-20">
                            <x-heroicon-o-calendar-days class="h-24 w-24" />
                        </div>
                        <div class="relative z-10 space-y-2">
                            <p class="text-sm font-bold text-indigo-100">تصاميم اليوم والمقبلة</p>
                            <p class="text-4xl font-black sm:text-5xl">{{ $todayCount }}</p>
                            <p class="text-xs text-indigo-100/90">جاهزة للمراجعة والاعتماد</p>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-amber-200/70 bg-white p-5 text-gray-900 shadow-sm dark:border-amber-800/50 dark:bg-gray-900 dark:text-white">
                        <div class="absolute -left-3 -top-4 opacity-10 dark:opacity-20">
                            <x-heroicon-o-clock class="h-24 w-24" />
                        </div>
                        <div class="relative z-10 space-y-2">
                            <p class="text-sm font-bold text-amber-700 dark:text-amber-300">تصاميم فائتة</p>
                            <p class="text-4xl font-black text-amber-500 sm:text-5xl">{{ $previousCount }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">مرسلة من أيام سابقة وتحتاج قرارًا</p>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-orange-200/70 bg-white p-5 text-gray-900 shadow-sm dark:border-orange-800/50 dark:bg-gray-900 dark:text-white">
                        <div class="absolute -left-3 -top-4 opacity-10 dark:opacity-20">
                            <x-heroicon-o-arrow-path class="h-24 w-24" />
                        </div>
                        <div class="relative z-10 space-y-2">
                            <p class="text-sm font-bold text-orange-700 dark:text-orange-300">قيد التعديل</p>
                            <p class="text-4xl font-black text-orange-500 sm:text-5xl">{{ $revisionCount }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">بانتظار إعادة تسليم المصمم</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tab Navigation --}}
        <div class="flex gap-1 rounded-2xl border border-gray-200 bg-gray-100 p-1.5 dark:border-gray-800 dark:bg-gray-900">
            <button type="button"
                wire:click="$set('activeTab', 'reviewing')"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition-all duration-200
                    {{ $activeTab === 'reviewing'
                        ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:text-indigo-300 dark:ring-gray-700'
                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' }}">
                <x-heroicon-o-eye class="h-4 w-4" />
                <span>قيد المراجعة</span>
                @if($todayCount + $previousCount > 0)
                <span class="rounded-full {{ $activeTab === 'reviewing' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }} px-2 py-0.5 text-xs font-black">
                    {{ $todayCount + $previousCount }}
                </span>
                @endif
            </button>

            <button type="button"
                wire:click="$set('activeTab', 'revision')"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition-all duration-200
                    {{ $activeTab === 'revision'
                        ? 'bg-white text-orange-700 shadow-sm ring-1 ring-inset ring-gray-200 dark:bg-gray-800 dark:text-orange-300 dark:ring-gray-700'
                        : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' }}">
                <x-heroicon-o-arrow-path class="h-4 w-4" />
                <span>قيد التعديل</span>
                @if($revisionCount > 0)
                <span class="rounded-full {{ $activeTab === 'revision' ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300' : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }} px-2 py-0.5 text-xs font-black">
                    {{ $revisionCount }}
                </span>
                @endif
            </button>
        </div>

        {{-- شريط البحث والفلترة السريعة --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900/80 sm:p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                {{-- حقل البحث السريع --}}
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400">
                        <x-heroicon-m-magnifying-glass class="h-5 w-5" />
                    </div>
                    <input type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="ابحث باسم العميل، التاق، أو المصمم..."
                        class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2.5 pr-10 pl-10 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800/60 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-indigo-500 dark:focus:bg-gray-800" />
                    @if($search)
                    <button type="button" wire:click="$set('search', '')" class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <x-heroicon-m-x-mark class="h-4 w-4" />
                    </button>
                    @endif
                </div>

                {{-- فلتر اختيار العميل --}}
                <div class="flex items-center gap-2">
                    <div class="relative min-w-[200px] sm:min-w-[240px]">
                        <select wire:model.live="selectedClient"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/50 py-2.5 pr-3 pl-8 text-sm font-medium text-gray-700 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-200 dark:focus:border-indigo-500 dark:focus:bg-gray-800">
                            <option value="">جميع العملاء ({{ count($availableClients) }})</option>
                            @foreach($availableClients as $client)
                            <option value="{{ $client->id }}">{{ $client->company }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($hasActiveFilters)
                    <button type="button"
                        wire:click="resetFilters"
                        class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-100 px-3 py-2.5 text-xs font-bold text-gray-600 transition hover:bg-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        <x-heroicon-m-arrow-path class="h-4 w-4" />
                        <span>إلغاء الفلترة</span>
                    </button>
                    @endif
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- TAB 1: قيد المراجعة --}}
        {{-- ================================================================= --}}
        @if($activeTab === 'reviewing')
        @php
        $sections = [
            'today' => [
                'title' => 'تصاميم اليوم والمقبلة',
                'subtitle' => 'الأولوية الأعلى للمراجعة والاعتماد',
                'items' => $todayItems,
                'headerClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800/50',
                'accentClass' => 'bg-emerald-500',
                'stateLabel' => 'جديد / مقبل',
                'stateClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800/40',
            ],
            'previous' => [
                'title' => 'تصاميم فائتة',
                'subtitle' => 'تحتاج متابعة قبل تراكمها',
                'items' => $previousItems,
                'headerClass' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800/50',
                'accentClass' => 'bg-amber-500',
                'stateLabel' => 'متأخر',
                'stateClass' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800/40',
            ],
        ];
        @endphp

        <div class="space-y-8">
            @foreach($sections as $key => $section)
            @php
            $items = $section['items'];
            @endphp

            @if($items->isNotEmpty())
            <section class="space-y-4">
                <header class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between {{ $section['headerClass'] }}">
                    <div>
                        <h2 class="text-base font-black sm:text-lg">{{ $section['title'] }}</h2>
                        <p class="text-xs font-medium opacity-80 sm:text-sm">{{ $section['subtitle'] }}</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-lg bg-white/80 px-3 py-1.5 text-xs font-extrabold text-gray-700 shadow-sm dark:bg-gray-900/70 dark:text-gray-200">
                        <x-heroicon-o-queue-list class="h-4 w-4" />
                        {{ $items->total() }} عنصر
                    </span>
                </header>

                <div class="space-y-5">
                    @foreach($items as $item)
                    @php
                        $client = $item->clientDesigner?->client;
                        $clicheTemplate = $client?->templates?->firstWhere('type', \App\Filament\Enums\ClientTemplateType::Cliche->value);
                        $clicheUrl = $clicheTemplate?->file ? Storage::url($clicheTemplate->file) : null;
                        $clicheThumbUrl = $clicheTemplate?->thumbnail_url ?: $clicheUrl;
                        $clientLogoUrl = $client?->logo_path ? Storage::url($client->logo_path) : null;
                        $clientName = $client?->company ?: ($client?->client_name ?: 'عميل');
                        $designerName = $item->clientDesigner?->designer?->user?->name ?? 'غير محدد';
                        $tagName = $item->tag?->name ?? 'عام';

                        $clientDrawerPayload = [
                            'client_id' => $client?->id,
                            'client_name' => $clientName,
                            'logo_url' => $clientLogoUrl,
                            'has_logo' => !empty($clientLogoUrl),
                            'cliche' => [
                                'file_url' => $clicheUrl,
                                'thumbnail_url' => $clicheThumbUrl,
                                'local_path' => $clicheTemplate?->local_path,
                                'updated_at' => $clicheTemplate?->updated_at ? $clicheTemplate->updated_at->format('Y-m-d h:i A') : null,
                                'has_cliche' => !empty($clicheUrl),
                            ],
                            'category' => $client?->category?->name,
                            'rating' => $client?->customer_rating_value,
                            'notes' => $client?->notes,
                            'designer_name' => $designerName,
                            'tag_name' => $tagName,
                            'distribution_id' => $item->id,
                        ];
                    @endphp
                    <article wire:key="review-item-{{ $key }}-{{ $item->id }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:ring-white/10">
                        <div class="h-1.5 w-full {{ $section['accentClass'] }}"></div>

                        <div class="space-y-4 p-4 sm:p-5">
                            <div class="flex items-start gap-3">
                                <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                                    @if($item->clientDesigner->designer->user->profile_image)
                                    <img src="{{ asset('storage/' . $item->clientDesigner->designer->user->profile_image) }}" alt="صورة المستخدم: {{ $item->clientDesigner->designer->user->name ?? 'مصمم' }}" class="h-full w-full object-cover">
                                    @else
                                    <div class="flex h-full w-full items-center justify-center text-xs font-black text-gray-600 dark:text-gray-300">
                                        {{ Str::substr($item->clientDesigner->designer->user->name ?? '?', 0, 2) }}
                                    </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1 space-y-1">
                                    <h3 class="truncate text-sm font-black text-gray-900 dark:text-white sm:text-base">
                                        {{ $item->clientDesigner->designer->user->name ?? 'مُصمم' }}
                                        <span class="mx-1 font-normal text-gray-400">لـ</span>
                                        <button type="button"
                                            @click="openClientDrawer(@js($clientDrawerPayload))"
                                            class="font-black text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded underline decoration-indigo-300 underline-offset-4 hover:decoration-indigo-600 dark:decoration-indigo-700 dark:hover:decoration-indigo-400 cursor-pointer"
                                            title="انقر لعرض الكليشة الرسمية والشعار">
                                            {{ $clientName }}
                                        </button>
                                    </h3>

                                    <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span dir="ltr" class="font-semibold">{{ $item->updated_at->diffForHumans() }}</span>
                                        <span class="text-gray-300 dark:text-gray-600">•</span>
                                        <span class="rounded-md border border-gray-200 bg-gray-100 px-2 py-1 font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $item->tag->name ?? 'عام' }}</span>
                                        <span class="rounded-md border px-2 py-1 font-bold {{ $section['stateClass'] }}">{{ $section['stateLabel'] }}</span>
                                        @if($item->reviewer_feedback || ($item->reviewer_attachments && count($item->reviewer_attachments) > 0))
                                        <span class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-1 font-black text-amber-700 shadow-sm dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-300">
                                            <x-heroicon-m-arrow-path-rounded-square class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                            <span>مُعاد تسليمه بعد تعديل</span>
                                        </span>
                                        @endif
                                        @if($item->distribution_date)
                                        <span class="rounded-md border border-gray-100 bg-gray-50 px-2 py-1 font-medium text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">تاريخ التوزيع: {{ $item->distribution_date }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- إبراز طلب التعديل السابق المطلوب التحقق منه --}}
                            @if($item->reviewer_feedback || ($item->reviewer_attachments && count($item->reviewer_attachments) > 0))
                            <div class="rounded-xl border-2 border-amber-300/90 bg-gradient-to-br from-amber-50/95 via-amber-50/70 to-orange-50/60 p-3.5 shadow-sm dark:border-amber-700/60 dark:from-amber-950/40 dark:via-amber-950/25 dark:to-orange-950/20" dir="rtl">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-500 text-white shadow-sm">
                                            <x-heroicon-m-clipboard-document-check class="h-4 w-4" />
                                        </div>
                                        <span class="text-xs font-black text-amber-900 dark:text-amber-200">
                                            ملاحظات التعديل السابقة المطلوب التحقق منها:
                                        </span>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-200/80 px-2.5 py-0.5 text-[10px] font-black text-amber-900 dark:bg-amber-900/60 dark:text-amber-200">
                                        <x-heroicon-m-check-badge class="h-3.5 w-3.5 text-amber-700 dark:text-amber-300" />
                                        <span>تأكد من تنفيذ المصمم لهذه التعديلات</span>
                                    </span>
                                </div>

                                @if($item->reviewer_feedback)
                                <div class="rounded-lg border border-amber-200/80 bg-white/95 p-3 shadow-inner dark:border-amber-800/50 dark:bg-gray-900/90">
                                    <p class="whitespace-pre-line text-xs font-bold leading-relaxed text-gray-800 dark:text-gray-100">{{ $item->reviewer_feedback }}</p>
                                </div>
                                @endif

                                @if($item->reviewer_attachments && count($item->reviewer_attachments) > 0)
                                <div class="mt-2.5 flex flex-wrap items-center gap-2 border-t border-amber-200/70 pt-2.5 dark:border-amber-800/40">
                                    <span class="text-[11px] font-black text-amber-800 dark:text-amber-300">مرفقاتك التوضيحية السابقة ({{ count($item->reviewer_attachments) }}):</span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($item->reviewer_attachments as $att)
                                        @php
                                            $attUrl = Storage::url($att);
                                        @endphp
                                        <a href="{{ $attUrl }}" target="_blank"
                                            class="group/att relative h-10 w-10 overflow-hidden rounded-lg border-2 border-amber-300 bg-white shadow-sm transition hover:scale-110 hover:shadow-md dark:border-amber-700 dark:bg-gray-800"
                                            title="عرض المرفق التوضيحي">
                                            <img src="{{ $attUrl }}" alt="مرفق تعديل توضيحي" class="h-full w-full object-cover" />
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/att:opacity-100">
                                                <x-heroicon-o-eye class="h-4 w-4 text-white" />
                                            </div>
                                        </a>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endif

                            @if($item->idea || $item->designer_notes || $item->custom_idea)
                            <div class="space-y-3" dir="rtl">
                                @if($item->idea)
                                <div class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-3 dark:border-indigo-800/40 dark:bg-indigo-900/20">
                                    <div class="mb-2 flex items-center gap-2">
                                        <x-heroicon-m-light-bulb class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                        <span class="text-sm font-black text-indigo-800 dark:text-indigo-300">الفكرة: {{ $item->idea->name }}</span>
                                    </div>

                                    @if($item->idea->description)
                                    <p class="mb-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item->idea->description }}</p>
                                    @endif

                                    @if($item->idea->content)
                                    <div class="rounded-lg border border-indigo-100 bg-white p-2.5 dark:border-indigo-800/40 dark:bg-gray-900/60">
                                        <span class="mb-1.5 block text-[10px] font-black uppercase tracking-wider text-indigo-500">محتوى الفكرة المطلوب</span>
                                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $item->idea->content }}</p>
                                    </div>
                                    @endif
                                </div>
                                @elseif($item->custom_idea)
                                <div class="rounded-xl border border-orange-100 bg-orange-50 p-3 dark:border-orange-800/40 dark:bg-orange-900/20">
                                    <div class="mb-1 flex items-center gap-2">
                                        <x-heroicon-m-sparkles class="h-4 w-4 text-orange-500" />
                                        <span class="text-sm font-black text-orange-800 dark:text-orange-300">فكرة مخصصة</span>
                                    </div>
                                    <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item->custom_idea }}</p>
                                </div>
                                @endif

                                @if($item->designer_notes)
                                <div class="flex items-start gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
                                    <div class="mt-0.5 shrink-0">
                                        <x-heroicon-m-pencil-square class="h-4 w-4 text-gray-400" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="mb-1 block text-xs font-black text-gray-500">ملاحظات المصمم</span>
                                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item->designer_notes }}</p>
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>

                        {{-- قسم معاينة التصميم --}}
                        <div class="group relative overflow-hidden border-y border-gray-100 bg-gray-100 dark:border-gray-800 dark:bg-black/50" x-data="{ loaded: false }">
                            @if($item->attachment_path)
                            @php
                                $imgUrl = Storage::url($item->attachment_path);
                                $imgTitle = ($item->clientDesigner->designer->user->name ?? 'مصمم') . ' - ' . ($item->clientDesigner->client->company ?? 'عميل') . ' (' . ($item->tag->name ?? 'عام') . ')';
                            @endphp
                            <div @click="openLightbox('{{ $imgUrl }}', '{{ addslashes($imgTitle) }}', @js($clientDrawerPayload))"
                                class="block w-full relative min-h-[200px] sm:min-h-[300px] cursor-zoom-in">
                                {{-- Skeleton loader while image loads --}}
                                <div x-show="!loaded"
                                    class="absolute inset-0 animate-pulse bg-gray-200 dark:bg-gray-800">
                                    <div class="flex h-full items-center justify-center">
                                        <x-heroicon-o-photo class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                                    </div>
                                </div>

                                <img src="{{ $imgUrl }}"
                                    @load.window="loaded = true"
                                    x-on:load="loaded = true"
                                    class="mx-auto h-auto max-h-[620px] w-full object-contain bg-gray-50 transition-all duration-300 group-hover:scale-[1.01] dark:bg-gray-900"
                                    alt="معاينة التصميم المُسلم للمراجعة"
                                    loading="lazy" />

                                <div x-show="loaded" style="display: none;"
                                    class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-black/70 px-3.5 py-2 text-xs font-bold text-white shadow-lg backdrop-blur-sm transition-transform duration-200 group-hover:scale-105">
                                        <x-heroicon-o-magnifying-glass-plus class="h-4 w-4" />
                                        <span>تكبير ومعاينة تفاعلية</span>
                                    </span>
                                </div>
                            </div>
                            @else
                            <div class="flex h-64 flex-col items-center justify-center gap-2 text-gray-400">
                                <x-heroicon-o-photo class="h-12 w-12 opacity-50" />
                                <span class="text-sm font-semibold">لا يوجد صورة مرفقة</span>
                            </div>
                            @endif
                        </div>

                        {{-- أزرار الإجراءات --}}
                        <div class="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2">
                            <button
                                wire:click="mountAction('approve', { id: {{ $item->id }} })"
                                wire:loading.attr="disabled"
                                wire:target="mountAction('approve', { id: {{ $item->id }} })"
                                class="group/btn inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-black text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/35">
                                <span wire:loading.remove wire:target="mountAction('approve', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                                    <x-heroicon-o-hand-thumb-up class="h-5 w-5 transition-transform group-hover/btn:scale-110" />
                                    <span>اعتماد</span>
                                </span>
                                <span wire:loading wire:target="mountAction('approve', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                                    <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin" />
                                    <span>جاري...</span>
                                </span>
                            </button>

                            <button
                                wire:click="mountAction('requestChanges', { id: {{ $item->id }} })"
                                wire:loading.attr="disabled"
                                wire:target="mountAction('requestChanges', { id: {{ $item->id }} })"
                                class="group/btn inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 py-2.5 text-sm font-black text-rose-700 transition hover:bg-rose-100 hover:text-rose-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-rose-800/50 dark:bg-rose-900/20 dark:text-rose-300 dark:hover:bg-rose-900/35">
                                <span wire:loading.remove wire:target="mountAction('requestChanges', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                                    <x-heroicon-o-chat-bubble-left-ellipsis class="h-5 w-5 transition-transform group-hover/btn:scale-110" />
                                    <span>طلب تعديل</span>
                                </span>
                                <span wire:loading wire:target="mountAction('requestChanges', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                                    <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin" />
                                    <span>جاري...</span>
                                </span>
                            </button>
                        </div>
                    </article>
                    @endforeach
                </div>

                @if($items->hasPages())
                <div class="mt-6">
                    {{ $items->links() }}
                </div>
                @endif
            </section>
            @endif
            @endforeach

            @if($todayItems->isEmpty() && $previousItems->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <x-heroicon-o-check-badge class="mx-auto mb-4 h-16 w-16 text-emerald-500/70" />
                <h2 class="text-lg font-black text-gray-900 dark:text-white">
                    @if($hasActiveFilters)
                    لا توجد تصاميم مطابقة للبحث أو الفلتر المحدد
                    @else
                    لا توجد تصاميم للمراجعة حالياً
                    @endif
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($hasActiveFilters)
                    جرب البحث بكلمات أخرى أو قم بإلغاء الفلتر لعرض جميع التصاميم.
                    @else
                    جميع العناصر تمت مراجعتها أو لم يتم تسليمها بعد.
                    @endif
                </p>
                @if($hasActiveFilters)
                <div class="mt-4">
                    <button type="button" wire:click="resetFilters" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-600 transition hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300">
                        <x-heroicon-m-arrow-path class="h-4 w-4" />
                        <span>إلغاء الفلترة والبحث</span>
                    </button>
                </div>
                @else
                <div class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    <x-heroicon-m-arrow-path class="h-4 w-4 animate-spin-slow" />
                    <span>يتم التحديث تلقائياً كل 30 ثانية</span>
                </div>
                @endif
            </div>
            @endif
        </div>
        @endif

        {{-- ================================================================= --}}
        {{-- TAB 2: قيد التعديل --}}
        {{-- ================================================================= --}}
        @if($activeTab === 'revision')
        <div class="space-y-5">
            @forelse($revisionItems as $item)
            @php
                $client = $item->clientDesigner?->client;
                $clicheTemplate = $client?->templates?->firstWhere('type', \App\Filament\Enums\ClientTemplateType::Cliche->value);
                $clicheUrl = $clicheTemplate?->file ? Storage::url($clicheTemplate->file) : null;
                $clicheThumbUrl = $clicheTemplate?->thumbnail_url ?: $clicheUrl;
                $clientLogoUrl = $client?->logo_path ? Storage::url($client->logo_path) : null;
                $clientName = $client?->company ?: ($client?->client_name ?: 'عميل');
                $designerName = $item->clientDesigner?->designer?->user?->name ?? 'غير محدد';
                $tagName = $item->tag?->name ?? 'عام';

                $clientDrawerPayload = [
                    'client_id' => $client?->id,
                    'client_name' => $clientName,
                    'logo_url' => $clientLogoUrl,
                    'has_logo' => !empty($clientLogoUrl),
                    'cliche' => [
                        'file_url' => $clicheUrl,
                        'thumbnail_url' => $clicheThumbUrl,
                        'local_path' => $clicheTemplate?->local_path,
                        'updated_at' => $clicheTemplate?->updated_at ? $clicheTemplate->updated_at->format('Y-m-d h:i A') : null,
                        'has_cliche' => !empty($clicheUrl),
                    ],
                    'category' => $client?->category?->name,
                    'rating' => $client?->customer_rating_value,
                    'notes' => $client?->notes,
                    'designer_name' => $designerName,
                    'tag_name' => $tagName,
                    'distribution_id' => $item->id,
                ];
            @endphp
            <article wire:key="revision-item-{{ $item->id }}" class="overflow-hidden rounded-2xl border border-orange-200 bg-white shadow-sm ring-1 ring-orange-950/5 transition hover:shadow-md dark:border-orange-800/50 dark:bg-gray-900 dark:ring-white/10">
                <div class="h-1.5 w-full bg-gradient-to-r from-orange-400 via-orange-500 to-amber-400"></div>

                <div class="space-y-4 p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                            @if($item->clientDesigner->designer->user->profile_image)
                            <img src="{{ asset('storage/' . $item->clientDesigner->designer->user->profile_image) }}" alt="صورة المصمم" class="h-full w-full object-cover">
                            @else
                            <div class="flex h-full w-full items-center justify-center text-xs font-black text-gray-600 dark:text-gray-300">
                                {{ Str::substr($item->clientDesigner->designer->user->name ?? '?', 0, 2) }}
                            </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 space-y-1">
                            <h3 class="truncate text-sm font-black text-gray-900 dark:text-white sm:text-base">
                                {{ $item->clientDesigner->designer->user->name ?? 'مُصمم' }}
                                <span class="mx-1 font-normal text-gray-400">لـ</span>
                                <button type="button"
                                    @click="openClientDrawer(@js($clientDrawerPayload))"
                                    class="font-black text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 rounded underline decoration-indigo-300 underline-offset-4 hover:decoration-indigo-600 dark:decoration-indigo-700 dark:hover:decoration-indigo-400 cursor-pointer"
                                    title="انقر لعرض الكليشة الرسمية والشعار">
                                    {{ $clientName }}
                                </button>
                            </h3>

                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                <span dir="ltr" class="font-semibold">{{ $item->updated_at->diffForHumans() }}</span>
                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                <span class="rounded-md border border-gray-200 bg-gray-100 px-2 py-1 font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $item->tag->name ?? 'عام' }}</span>
                                <span class="rounded-md border border-orange-200 bg-orange-50 px-2 py-1 font-bold text-orange-700 dark:border-orange-800/40 dark:bg-orange-900/30 dark:text-orange-300">بانتظار تعديل المصمم</span>
                                @if($item->distribution_date)
                                <span class="rounded-md border border-gray-100 bg-gray-50 px-2 py-1 font-medium text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">تاريخ التوزيع: {{ $item->distribution_date }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ملاحظات التعديل المرسلة --}}
                    @if($item->reviewer_feedback || ($item->reviewer_attachments && count($item->reviewer_attachments) > 0))
                    <div class="rounded-xl border border-orange-200 bg-orange-50/80 p-3 dark:border-orange-800/40 dark:bg-orange-900/10">
                        <div class="mb-2 flex items-center gap-2">
                            <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                            <span class="text-xs font-black uppercase tracking-wide text-orange-700 dark:text-orange-300">ملاحظات التعديل المرسلة للمصمم</span>
                        </div>
                        @if($item->reviewer_feedback)
                        <p class="whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ $item->reviewer_feedback }}</p>
                        @endif
                        @if($item->reviewer_attachments && count($item->reviewer_attachments) > 0)
                        <div class="mt-2.5 flex flex-wrap gap-2 border-t border-orange-200/60 pt-2.5 dark:border-orange-800/30">
                            <span class="text-[10px] font-bold text-orange-700 dark:text-orange-300">مرفقات ({{ count($item->reviewer_attachments) }}):</span>
                            @foreach($item->reviewer_attachments as $att)
                            <a href="{{ Storage::url($att) }}" target="_blank"
                                class="group/att relative h-10 w-10 overflow-hidden rounded-lg border border-orange-300 dark:border-orange-700 hover:scale-110 transition-all">
                                <img src="{{ Storage::url($att) }}" alt="مرفق تعديل" class="h-full w-full object-cover" />
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover/att:opacity-100 transition-opacity">
                                    <x-heroicon-o-eye class="h-3.5 w-3.5 text-white" />
                                </div>
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- التصميم الأصلي المُسلَّم --}}
                    @if($item->attachment_path)
                    @php
                        $revImgUrl = Storage::url($item->attachment_path);
                        $revImgTitle = ($item->clientDesigner->designer->user->name ?? 'مصمم') . ' - ' . ($item->clientDesigner->client->company ?? 'عميل');
                    @endphp
                    <div>
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">التصميم المُسلَّم</label>
                        <div class="group relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900/50 cursor-zoom-in"
                            @click="openLightbox('{{ $revImgUrl }}', '{{ addslashes($revImgTitle) }}', @js($clientDrawerPayload))">
                            <img src="{{ $revImgUrl }}" alt="التصميم المُسلَّم"
                                class="mx-auto max-h-60 w-full object-contain transition-transform duration-300 group-hover:scale-[1.01]"
                                loading="lazy" />
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition-opacity group-hover:opacity-100">
                                <span class="rounded-xl bg-black/70 px-3 py-1.5 text-xs font-bold text-white">
                                    <x-heroicon-o-magnifying-glass-plus class="inline h-3.5 w-3.5" /> تكبير
                                </span>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- أزرار إجراءات التعديل --}}
                <div class="grid grid-cols-1 gap-2 border-t border-orange-100 p-3 dark:border-orange-900/30 sm:grid-cols-2">
                    {{-- تعديل الملاحظات --}}
                    <button
                        wire:click="mountAction('updateChanges', { id: {{ $item->id }} })"
                        wire:loading.attr="disabled"
                        wire:target="mountAction('updateChanges', { id: {{ $item->id }} })"
                        class="group/btn inline-flex items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 py-2.5 text-sm font-black text-amber-700 transition hover:bg-amber-100 hover:text-amber-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-300 dark:hover:bg-amber-900/35">
                        <span wire:loading.remove wire:target="mountAction('updateChanges', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                            <x-heroicon-o-pencil-square class="h-5 w-5 transition-transform group-hover/btn:scale-110" />
                            <span>تعديل الملاحظات</span>
                        </span>
                        <span wire:loading wire:target="mountAction('updateChanges', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                            <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin" />
                            <span>جاري...</span>
                        </span>
                    </button>

                    {{-- إعادة إلى المراجعة (إلغاء طلب التعديل) --}}
                    <button
                        wire:click="mountAction('approve', { id: {{ $item->id }} })"
                        wire:loading.attr="disabled"
                        wire:target="mountAction('approve', { id: {{ $item->id }} })"
                        class="group/btn inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 py-2.5 text-sm font-black text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-emerald-800/50 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/35">
                        <span wire:loading.remove wire:target="mountAction('approve', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                            <x-heroicon-o-hand-thumb-up class="h-5 w-5 transition-transform group-hover/btn:scale-110" />
                            <span>اعتماد التصميم</span>
                        </span>
                        <span wire:loading wire:target="mountAction('approve', { id: {{ $item->id }} })" class="inline-flex items-center gap-2">
                            <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin" />
                            <span>جاري...</span>
                        </span>
                    </button>
                </div>
            </article>
            @empty
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white py-16 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <x-heroicon-o-check-badge class="mx-auto mb-4 h-16 w-16 text-emerald-500/70" />
                <h2 class="text-lg font-black text-gray-900 dark:text-white">
                    @if($hasActiveFilters)
                    لا توجد مهام قيد التعديل مطابقة للفلتر
                    @else
                    لا توجد مهام قيد التعديل حالياً
                    @endif
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($hasActiveFilters)
                    جرب إلغاء الفلتر لعرض جميع المهام.
                    @else
                    جميع المصممين أنجزوا التعديلات المطلوبة.
                    @endif
                </p>
            </div>
            @endforelse

            @if($revisionItems->hasPages())
            <div class="mt-6">
                {{ $revisionItems->links() }}
            </div>
            @endif
        </div>
        @endif

        {{-- ============================================= --}}
        {{-- نافذة معاينة الصور التفاعلية (Image Lightbox / Zoom) --}}
        {{-- ============================================= --}}
        <div x-show="lightboxOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-cloak
            class="fixed inset-0 z-[100] flex flex-col bg-black/95 backdrop-blur-md"
            style="display: none;">

            {{-- شريط التحكم العلوي --}}
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 text-white" dir="rtl">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white">
                        <x-heroicon-o-photo class="h-5 w-5" />
                    </span>
                    <h3 class="truncate text-sm font-bold sm:text-base" x-text="lightboxTitle"></h3>

                    {{-- زر فتح سحاب الكليشة والشعار في وضع التدقيق / المعاينة --}}
                    <template x-if="lightboxClient">
                        <button type="button"
                            @click="openClientDrawer(lightboxClient)"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 text-xs font-bold shadow-lg transition-all active:scale-95 border border-indigo-400/30 cursor-pointer"
                            title="عرض الكليشة الرسمية وشعار العميل">
                            <x-heroicon-m-swatch class="h-4 w-4" />
                            <span>كليشة وشعار العميل</span>
                        </button>
                    </template>
                </div>

                {{-- أزرار التكبير والتحكم --}}
                <div class="flex items-center gap-2">
                    {{-- Zoom Out --}}
                    <button type="button" @click="zoomOut()"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 transition hover:bg-white/20 active:scale-95 text-white"
                        title="تصغير (-)">
                        <x-heroicon-o-magnifying-glass-minus class="h-5 w-5" />
                    </button>

                    {{-- Zoom Percentage / Reset --}}
                    <button type="button" @click="resetZoom()"
                        class="px-2.5 py-1.5 rounded-xl bg-white/10 text-xs font-bold transition hover:bg-white/20 active:scale-95 text-white"
                        title="إعادة ضبط 100%">
                        <span x-text="Math.round(lightboxZoom * 100) + '%'"></span>
                    </button>

                    {{-- Zoom In --}}
                    <button type="button" @click="zoomIn()"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 transition hover:bg-white/20 active:scale-95 text-white"
                        title="تكبير (+)">
                        <x-heroicon-o-magnifying-glass-plus class="h-5 w-5" />
                    </button>

                    {{-- Open in new tab --}}
                    <a :href="lightboxImg" target="_blank"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 transition hover:bg-white/20 active:scale-95 text-white"
                        title="فتح في تبويب جديد">
                        <x-heroicon-o-arrow-top-right-on-square class="h-5 w-5" />
                    </a>

                    <div class="h-6 w-px bg-white/20 mx-1"></div>

                    {{-- Close button --}}
                    <button type="button" @click="lightboxOpen = false"
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-500/80 text-white transition hover:bg-red-600 active:scale-95"
                        title="إغلاق (Esc)">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
            </div>

            {{-- مساحة عرض الصورة مع Scroll والتكبير --}}
            <div class="relative flex-1 overflow-auto p-4 flex items-center justify-center"
                @click.self="lightboxOpen = false">
                <div class="transition-transform duration-200 ease-out origin-center"
                    :style="`transform: scale(${lightboxZoom});`">
                    <img :src="lightboxImg"
                        alt="معاينة بالحجم الكامل"
                        class="max-h-[85vh] max-w-[90vw] object-contain rounded-lg shadow-2xl select-none" />
                </div>
            </div>

            {{-- شريط سفلي تلميحات --}}
            <div class="border-t border-white/10 px-4 py-2 text-center text-xs text-white/50" dir="rtl">
                <span>اضغط <kbd class="rounded bg-white/15 px-1.5 py-0.5 text-[10px] text-white">Esc</kbd> أو انقر خارج الصورة للإغلاق</span>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- سحّاب جانبي من اليسار لعرض كليشة وشعار العميل (Slide-over Drawer from Left) --}}
        {{-- ========================================================================= --}}
        {{-- Backdrop --}}
        <div x-show="clientDrawerOpen"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="closeClientDrawer()"
            class="fixed inset-0 z-[70] bg-gray-950/60 backdrop-blur-sm"
            style="display: none;"
            x-cloak>
        </div>

        {{-- Slide-over Drawer Panel (Fixed to Left Side of Viewport) --}}
        <div x-show="clientDrawerOpen"
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-[75] flex w-full max-w-lg flex-col bg-white shadow-2xl border-r border-gray-200 dark:border-gray-800 dark:bg-gray-900"
            style="display: none;"
            x-cloak
            dir="rtl">

            {{-- رأس السحاب (Drawer Header) --}}
            <div class="relative overflow-hidden border-b border-gray-100 bg-gradient-to-r from-indigo-50/70 via-purple-50/40 to-white px-5 py-4 dark:border-gray-800 dark:from-indigo-950/30 dark:via-purple-950/20 dark:to-gray-900">
                <div class="pointer-events-none absolute -left-6 -top-6 h-24 w-24 rounded-full bg-indigo-500/10 blur-xl"></div>

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        {{-- أيقونة أو صورة الشعار المصغرة --}}
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-indigo-200/80 bg-white shadow-sm dark:border-indigo-800/80 dark:bg-gray-800">
                            <template x-if="clientDrawerData?.logo_url">
                                <img :src="clientDrawerData?.logo_url" :alt="clientDrawerData?.client_name" class="h-full w-full object-contain p-1" />
                            </template>
                            <template x-if="!clientDrawerData?.logo_url">
                                <x-heroicon-o-building-office-2 class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                            </template>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate text-base font-black text-gray-900 dark:text-white" x-text="clientDrawerData?.client_name || 'تفاصيل العميل'"></h3>
                                <template x-if="clientDrawerData?.category">
                                    <span class="shrink-0 rounded-md bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300" x-text="clientDrawerData?.category"></span>
                                </template>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">الكليشة المعتمدة والهوية البصرية للعميل</p>
                        </div>
                    </div>

                    {{-- زر إغلاق --}}
                    <button type="button"
                        @click="closeClientDrawer()"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:bg-gray-100 hover:text-gray-800 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white cursor-pointer"
                        title="إغلاق (Esc)">
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- تبويبات التنقل السريع داخل السحاب --}}
                <div class="mt-4 flex gap-1 rounded-xl bg-gray-100/80 p-1 dark:bg-gray-800/80">
                    {{-- تبويب الكليشة --}}
                    <button type="button"
                        @click="drawerActiveTab = 'cliche'"
                        :class="drawerActiveTab === 'cliche'
                            ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-gray-700 dark:text-indigo-300 dark:ring-gray-600'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        <x-heroicon-m-swatch class="h-4 w-4" />
                        <span>الكليشة الرسمية</span>
                        <template x-if="clientDrawerData?.cliche?.has_cliche">
                            <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </template>
                        <template x-if="!clientDrawerData?.cliche?.has_cliche">
                            <span class="flex h-2 w-2 rounded-full bg-amber-400"></span>
                        </template>
                    </button>

                    {{-- تبويب الشعار --}}
                    <button type="button"
                        @click="drawerActiveTab = 'logo'"
                        :class="drawerActiveTab === 'logo'
                            ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-gray-700 dark:text-indigo-300 dark:ring-gray-600'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        <x-heroicon-m-photo class="h-4 w-4" />
                        <span>شعار العميل</span>
                        <template x-if="clientDrawerData?.has_logo">
                            <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </template>
                        <template x-if="!clientDrawerData?.has_logo">
                            <span class="flex h-2 w-2 rounded-full bg-amber-400"></span>
                        </template>
                    </button>

                    {{-- تبويب كلاهما معاً --}}
                    <button type="button"
                        @click="drawerActiveTab = 'both'"
                        :class="drawerActiveTab === 'both'
                            ? 'bg-white text-indigo-700 shadow-sm ring-1 ring-gray-200/80 dark:bg-gray-700 dark:text-indigo-300 dark:ring-gray-600'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition-all cursor-pointer">
                        <x-heroicon-m-rectangle-group class="h-4 w-4" />
                        <span>عرض الكل</span>
                    </button>
                </div>
            </div>

            {{-- محتوى السحاب القابل للتمرير (Drawer Scrollable Body) --}}
            <div class="flex-1 overflow-y-auto p-5 space-y-6">

                {{-- ================= TAB: الكليشة الرسمية ================= --}}
                <div x-show="drawerActiveTab === 'cliche' || drawerActiveTab === 'both'" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                                <x-heroicon-m-swatch class="h-4 w-4" />
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-gray-900 dark:text-white">الكليشة الرسمية المعتمدة</h4>
                                <template x-if="clientDrawerData?.cliche?.updated_at">
                                    <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                        آخر تحديث: <span x-text="clientDrawerData?.cliche?.updated_at"></span>
                                    </p>
                                </template>
                            </div>
                        </div>

                        <template x-if="clientDrawerData?.cliche?.has_cliche">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800/50">
                                <x-heroicon-m-check-circle class="h-3.5 w-3.5 text-emerald-500" />
                                <span>معتمدة</span>
                            </span>
                        </template>
                    </div>

                    {{-- حالة وجود الكليشة --}}
                    <template x-if="clientDrawerData?.cliche?.has_cliche">
                        <div class="space-y-3">
                            {{-- صندوق عرض صورة الكليشة --}}
                            <div class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-950/5 p-2 shadow-inner transition hover:border-teal-300 dark:border-gray-800 dark:bg-gray-950/40 dark:hover:border-teal-700">
                                <div class="flex min-h-[200px] max-h-[380px] w-full items-center justify-center overflow-hidden rounded-xl bg-white/50 dark:bg-gray-900/50">
                                    <img :src="clientDrawerData?.cliche?.file_url"
                                        :alt="'كليشة ' + clientDrawerData?.client_name"
                                        class="max-h-[360px] max-w-full rounded-lg object-contain transition-transform duration-300 group-hover:scale-[1.02] cursor-zoom-in"
                                        @click="openLightbox(clientDrawerData?.cliche?.file_url, 'الكليشة الرسمية - ' + clientDrawerData?.client_name, clientDrawerData)"
                                        loading="lazy" />
                                </div>

                                {{-- تلميح النقر للتكبير --}}
                                <div class="absolute bottom-4 left-1/2 -translate-x-1/2 opacity-0 transition-opacity group-hover:opacity-100 pointer-events-none">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-black/75 px-3 py-1 text-xs font-bold text-white shadow-md backdrop-blur-sm">
                                        <x-heroicon-m-magnifying-glass-plus class="h-3.5 w-3.5" />
                                        <span>انقر للمعاينة والتكبير</span>
                                    </span>
                                </div>
                            </div>

                            {{-- مسار الملف المحلي (إن وجد) مع ميزة النسخ السريع --}}
                            <template x-if="clientDrawerData?.cliche?.local_path">
                                <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50/80 p-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                                    <div class="flex items-center gap-2 overflow-hidden text-xs text-gray-700 dark:text-gray-300">
                                        <x-heroicon-m-folder class="h-4 w-4 shrink-0 text-amber-500" />
                                        <span class="shrink-0 font-bold">المسار المحلي:</span>
                                        <code class="truncate rounded bg-white px-2 py-0.5 font-mono text-[11px] text-gray-800 shadow-sm dark:bg-gray-900 dark:text-gray-200"
                                            x-text="clientDrawerData?.cliche?.local_path"></code>
                                    </div>
                                    <button
                                        type="button"
                                        @click="
                                            navigator.clipboard.writeText(clientDrawerData?.cliche?.local_path || '');
                                            copiedPath = true;
                                            setTimeout(() => copiedPath = false, 2000);
                                        "
                                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                                        <template x-if="!copiedPath">
                                            <span class="inline-flex items-center gap-1">
                                                <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" />
                                                <span>نسخ</span>
                                            </span>
                                        </template>
                                        <template x-if="copiedPath">
                                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                <x-heroicon-m-check class="h-3.5 w-3.5" />
                                                <span>تم النسخ!</span>
                                            </span>
                                        </template>
                                    </button>
                                </div>
                            </template>

                            {{-- زر تكبير الكليشة --}}
                            <div class="pt-1">
                                <button type="button"
                                    @click="openLightbox(clientDrawerData?.cliche?.file_url, 'الكليشة الرسمية - ' + clientDrawerData?.client_name, clientDrawerData)"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                                    <x-heroicon-m-magnifying-glass-plus class="h-4 w-4" />
                                    <span>تكبير الكليشة</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- حالة عدم وجود الكليشة --}}
                    <template x-if="!clientDrawerData?.cliche?.has_cliche">
                        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-amber-300 bg-amber-50/50 p-8 text-center dark:border-amber-800/60 dark:bg-amber-950/20">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                <x-heroicon-o-swatch class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-black text-amber-900 dark:text-amber-200">لا توجد كليشة رسمية مسجلة</p>
                                <p class="mt-1 text-xs text-amber-700/80 dark:text-amber-400/80">لم يتم رفع نموذج كليشة رسمي معتمد لهذا العميل حتى الآن.</p>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- فاصل بين الأقسام عند اختيار عرض الكل --}}
                <template x-if="drawerActiveTab === 'both'">
                    <hr class="border-gray-200 dark:border-gray-800" />
                </template>

                {{-- ================= TAB: شعار العميل ================= --}}
                <div x-show="drawerActiveTab === 'logo' || drawerActiveTab === 'both'" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <x-heroicon-m-photo class="h-4 w-4" />
                            </div>
                            <h4 class="text-sm font-black text-gray-900 dark:text-white">شعار العميل المعتمد</h4>
                        </div>

                        <template x-if="clientDrawerData?.has_logo">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800/50">
                                <x-heroicon-m-check-circle class="h-3.5 w-3.5 text-emerald-500" />
                                <span>متوفر</span>
                            </span>
                        </template>
                    </div>

                    {{-- حالة وجود الشعار --}}
                    <template x-if="clientDrawerData?.has_logo">
                        <div class="space-y-3">
                            {{-- صندوق عرض الشعار مع خلفية شبكية لدعم الصور الشفافة --}}
                            <div class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 dark:border-gray-800 dark:bg-gray-800/80 dark:hover:border-indigo-700">
                                <div class="flex min-h-[160px] max-h-[260px] w-full items-center justify-center rounded-xl p-4"
                                     style="background-image: radial-gradient(rgba(100, 116, 139, 0.2) 1px, transparent 1px); background-size: 16px 16px;">
                                    <img :src="clientDrawerData?.logo_url"
                                        :alt="'شعار ' + clientDrawerData?.client_name"
                                        class="max-h-[220px] max-w-full object-contain transition-transform duration-300 group-hover:scale-105 cursor-zoom-in"
                                        @click="openLightbox(clientDrawerData?.logo_url, 'شعار العميل - ' + clientDrawerData?.client_name, clientDrawerData)"
                                        loading="lazy" />
                                </div>

                                {{-- تلميح التكبير --}}
                                <div class="absolute bottom-3 left-1/2 -translate-x-1/2 opacity-0 transition-opacity group-hover:opacity-100 pointer-events-none">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-black/75 px-3 py-1 text-xs font-bold text-white shadow-md backdrop-blur-sm">
                                        <x-heroicon-m-magnifying-glass-plus class="h-3.5 w-3.5" />
                                        <span>تكبير الشعار</span>
                                    </span>
                                </div>
                            </div>

                            {{-- زر تكبير الشعار --}}
                            <div class="pt-1">
                                <button type="button"
                                    @click="openLightbox(clientDrawerData?.logo_url, 'شعار العميل - ' + clientDrawerData?.client_name, clientDrawerData)"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-gray-300 bg-white py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                                    <x-heroicon-m-magnifying-glass-plus class="h-4 w-4" />
                                    <span>تكبير الشعار</span>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- حالة عدم وجود الشعار --}}
                    <template x-if="!clientDrawerData?.has_logo">
                        <div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-gray-50/50 p-8 text-center dark:border-gray-700 dark:bg-gray-800/40">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-200/70 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                <x-heroicon-o-photo class="h-6 w-6" />
                            </div>
                            <div>
                                <p class="text-sm font-black text-gray-800 dark:text-gray-200">لا يوجد شعار مرفوع</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">لم يتم إرفاق ملف شعار في بطاقة هذا العميل.</p>
                            </div>
                        </div>
                    </template>
                </div>

            </div>

            {{-- أسفل السحاب (Drawer Footer) --}}
            <div class="border-t border-gray-100 bg-gray-50/60 p-4 text-center text-xs text-gray-400 dark:border-gray-800 dark:bg-gray-900">
                <span>اضغط <kbd class="rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-mono text-gray-700 dark:bg-gray-800 dark:text-gray-300">Esc</kbd> أو انقر في أي مكان خارج اللوحة للإغلاق</span>
            </div>
        </div>
    </div>

</x-filament-panels::page>