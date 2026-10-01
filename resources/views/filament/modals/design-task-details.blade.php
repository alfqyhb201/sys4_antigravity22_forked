@php
    $statusValue = $task->status instanceof \App\Filament\Enums\DesignTaskStatus ? $task->status->value : (string)$task->status;
    $priorityValue = $task->priority instanceof \App\Filament\Enums\DesignTaskPriority ? $task->priority->value : (string)$task->priority;
    
    $priorityConfig = match ($priorityValue) {
        'high' => ['label' => 'أهمية قصوى', 'color' => 'danger', 'class' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/50'],
        'medium' => ['label' => 'أهمية متوسطة', 'color' => 'warning', 'class' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/50'],
        default => ['label' => 'أهمية عادية', 'color' => 'gray', 'class' => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800/60 dark:text-gray-300 dark:border-gray-700'],
    };

    $statusConfig = match ($statusValue) {
        'in_review' => ['label' => 'تنتظر مراجعتك', 'icon' => 'heroicon-m-bell', 'dot' => 'bg-amber-500 animate-pulse', 'class' => 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60'],
        'needs_revision' => ['label' => 'قيد التعديل', 'icon' => 'heroicon-m-arrow-path', 'dot' => 'bg-rose-500', 'class' => 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60'],
        'approved' => ['label' => 'معتمد ومكتمل', 'icon' => 'heroicon-m-check-circle', 'dot' => 'bg-emerald-500', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60'],
        default => ['label' => 'قيد الانتظار', 'icon' => 'heroicon-m-clock', 'dot' => 'bg-gray-400', 'class' => 'bg-gray-50 text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700'],
    };

    $client = $task->client;
    $designFiles = $task->design_files ?? [];
    $referenceFiles = $task->reference_files ?? [];
    $revisionFiles = $task->revision_files ?? [];
@endphp

<div
    class="relative flex flex-col text-sm font-sans min-h-[500px]"
    dir="rtl"
    x-data="{
        lightboxOpen: false,
        lightboxImage: '',
        lightboxTitle: '',
        lightboxDownloadUrl: '',
        showRevisionForm: false,
        revisionNotes: '',
        revisionFiles: [],
        isSubmitting: false,
        appendPreset(preset) {
            if (this.revisionNotes.trim().length > 0) {
                this.revisionNotes += ' - ' + preset;
            } else {
                this.revisionNotes = preset;
            }
        },
        openLightbox(imgUrl, title, downloadUrl) {
            this.lightboxImage = imgUrl;
            this.lightboxTitle = title;
            this.lightboxDownloadUrl = downloadUrl;
            this.lightboxOpen = true;
        },
        handlePaste(e) {
            const items = (e.clipboardData || e.originalEvent.clipboardData)?.items;
            if (!items) return;
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.kind === 'file' && item.type.startsWith('image/')) {
                    const blob = item.getAsFile();
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        this.revisionFiles.push(event.target.result);
                    };
                    reader.readAsDataURL(blob);
                }
            }
        },
        handleFileInput(e) {
            const files = e.target.files;
            if (!files) return;
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        this.revisionFiles.push(event.target.result);
                    };
                    reader.readAsDataURL(file);
                }
            }
        },
        removeRevisionFile(index) {
            this.revisionFiles.splice(index, 1);
        },
        submitRevision(taskId) {
            if (!this.revisionNotes.trim()) {
                alert('يرجى كتابة ملاحظات التعديل المطلوبة');
                return;
            }
            this.isSubmitting = true;
            $wire.requestTaskRevision(taskId, this.revisionNotes, this.revisionFiles);
        }
    }"
    @paste.window="if (showRevisionForm) handlePaste($event)"
    @keydown.escape.window="if (lightboxOpen) { lightboxOpen = false; } else if (showRevisionForm) { showRevisionForm = false; }"
>

    {{-- ============================== --}}
    {{-- شريط الترويسة والشارات العلوية --}}
    {{-- ============================== --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gradient-to-r from-gray-50 via-white to-gray-50/50 p-4 shadow-xs dark:border-gray-800 dark:from-gray-900 dark:via-gray-900/80 dark:to-gray-900/50">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 shadow-xs dark:bg-primary-950/60 dark:text-primary-300">
                <x-heroicon-m-clipboard-document-list class="h-6 w-6" />
            </span>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-black text-gray-900 dark:text-white">
                        {{ $task->display_client_name }}
                    </h2>
                    <span class="text-xs font-semibold text-gray-400">#{{ $task->id }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span>المصمم: <strong class="text-gray-700 dark:text-gray-200">{{ $task->designer?->user?->name ?? 'غير محدد' }}</strong></span>
                    <span>•</span>
                    <span>المُنشئ: {{ $task->assigner?->name ?? 'النظام' }}</span>
                </div>
            </div>
        </div>

        {{-- الشارات --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- شارة الحالة --}}
            <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1 text-xs font-black shadow-2xs {{ $statusConfig['class'] }}">
                <span class="h-2 w-2 rounded-full {{ $statusConfig['dot'] }}"></span>
                <span>{{ $statusConfig['label'] }}</span>
            </span>

            {{-- شارة الأهمية --}}
            <span class="inline-flex items-center gap-1 rounded-xl border px-2.5 py-1 text-xs font-bold {{ $priorityConfig['class'] }}">
                <x-heroicon-m-bolt class="h-3.5 w-3.5" />
                <span>{{ $priorityConfig['label'] }}</span>
            </span>

            {{-- شارة النوع --}}
            <span class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-2.5 py-1 text-xs font-bold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                @if($task->is_template_update)
                    <x-heroicon-m-swatch class="h-3.5 w-3.5 text-primary-500" />
                    <span>تحديث قالب</span>
                @else
                    <x-heroicon-m-clipboard class="h-3.5 w-3.5 text-blue-500" />
                    <span>مهمة جانبية</span>
                @endif
            </span>

            @if($task->is_extra)
                <span class="inline-flex items-center gap-1 rounded-xl border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-extrabold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span>💰 إضافي</span>
                    @if($task->amount)
                        <span>({{ number_format($task->amount, 0) }})</span>
                    @endif
                </span>
            @endif
        </div>
    </div>

    {{-- ============================== --}}
    {{-- شبكة المساحة الرئيسية (2 أعمدة) --}}
    {{-- ============================== --}}
    <div class="grid grid-cols-1 gap-6 pb-28 lg:grid-cols-12">

        {{-- ============================== --}}
        {{-- الجانب الأيمن: البيانات والمراجع (5 أعمدة) --}}
        {{-- ============================== --}}
        <div class="space-y-4 lg:col-span-5">

            {{-- بطاقة العميل والاشتراك --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-2.5 dark:border-gray-800">
                    <span class="flex items-center gap-1.5 text-xs font-black text-gray-800 dark:text-gray-200">
                        <x-heroicon-m-building-office class="h-4 w-4 text-primary-500" />
                        <span>بيانات العميل والاشتراك</span>
                    </span>
                    @if($task->is_subscribed_client)
                        <span class="rounded-lg bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                            عميل مشترك
                        </span>
                    @else
                        <span class="rounded-lg bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            عميل فردي
                        </span>
                    @endif
                </div>

                <div class="space-y-2.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">الشركة / الاسم:</span>
                        <span class="font-bold text-gray-900 dark:text-white">{{ $task->display_client_name }}</span>
                    </div>

                    @if($client)
                        @if($client->category)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">التصنيف:</span>
                                <span class="font-medium text-blue-600 dark:text-blue-400">{{ $client->category->name }}</span>
                            </div>
                        @endif

                        @if($client->location)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 dark:text-gray-400">المدينة / الفرع:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ $client->location->name }}</span>
                            </div>
                        @endif

                        <div class="flex items-center justify-between border-t border-gray-100 pt-2 dark:border-gray-800">
                            <span class="text-gray-500 dark:text-gray-400">رصيد الكليشيهات المنجزة:</span>
                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 font-black text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <x-heroicon-m-sparkles class="h-3 w-3" />
                                <span>{{ $client->cliche_counter ?? 0 }} تصميم</span>
                            </span>
                        </div>
                    @endif

                    @if($task->deduct_from_balance)
                        <div class="flex items-center justify-between rounded-lg bg-amber-50/60 p-2 dark:bg-amber-950/20">
                            <span class="text-amber-800 dark:text-amber-300">الخصم من الرصيد:</span>
                            <span class="font-extrabold text-amber-700 dark:text-amber-400">تخصم من رصيد العميل المقبل</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- بطاقة التوقيتات وسير العمل --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <span class="mb-3 flex items-center gap-1.5 text-xs font-black text-gray-800 dark:text-gray-200 border-b border-gray-100 pb-2.5 dark:border-gray-800">
                    <x-heroicon-m-calendar-days class="h-4 w-4 text-blue-500" />
                    <span>الجدولة والتوقيتات</span>
                </span>

                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-2 dark:border-gray-800 dark:bg-gray-800/40">
                        <span class="block text-[10px] font-bold text-gray-400 mb-0.5">تاريخ الإنشاء</span>
                        <span class="text-[11px] font-bold text-gray-700 dark:text-gray-200">{{ $task->created_at->format('Y-m-d') }}</span>
                        <span class="block text-[9px] text-gray-400">{{ $task->created_at->format('h:i A') }}</span>
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-2 dark:border-gray-800 dark:bg-gray-800/40">
                        <span class="block text-[10px] font-bold text-gray-400 mb-0.5">وقت التسليم</span>
                        @if($task->submitted_at)
                            <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">{{ $task->submitted_at->format('Y-m-d') }}</span>
                            <span class="block text-[9px] text-gray-400">{{ $task->submitted_at->diffForHumans() }}</span>
                        @else
                            <span class="text-[11px] font-medium text-gray-400">لم يسلم بعد</span>
                        @endif
                    </div>

                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-2 dark:border-gray-800 dark:bg-gray-800/40">
                        <span class="block text-[10px] font-bold text-gray-400 mb-0.5">موعد الجدولة</span>
                        @if($task->scheduled_at)
                            <span class="text-[11px] font-bold text-purple-600 dark:text-purple-400">{{ $task->scheduled_at->format('m-d h:i A') }}</span>
                        @else
                            <span class="text-[11px] font-bold text-gray-600 dark:text-gray-300">مهمة فورية</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- وصف المهمة --}}
            @if($task->description)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="mb-2 flex items-center gap-1.5 text-xs font-black text-gray-800 dark:text-gray-200">
                        <x-heroicon-m-document-text class="h-4 w-4 text-purple-500" />
                        <span>وصف المهمة وملاحظات الإنشاء</span>
                    </span>
                    <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-3 text-xs leading-relaxed text-gray-800 dark:border-gray-800 dark:bg-gray-950/60 dark:text-gray-200 whitespace-pre-wrap font-sans">
                        {{ $task->description }}
                    </div>
                </div>
            @endif

            {{-- الملفات المرجعية من المدير --}}
            @if(count($referenceFiles) > 0)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <span class="mb-2.5 flex items-center justify-between text-xs font-black text-gray-800 dark:text-gray-200">
                        <span class="flex items-center gap-1.5">
                            <x-heroicon-m-paper-clip class="h-4 w-4 text-blue-500" />
                            <span>الملفات المرجعية المرفقة</span>
                        </span>
                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                            {{ count($referenceFiles) }} ملف
                        </span>
                    </span>

                    <div class="flex flex-wrap gap-2">
                        @foreach($referenceFiles as $refFile)
                            @php
                                $ext = strtolower(pathinfo($refFile, PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                $url = \Illuminate\Support\Facades\Storage::url($refFile);
                            @endphp
                            @if($isImg)
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    class="group relative h-14 w-14 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 shadow-2xs transition hover:scale-105 hover:border-primary-400 dark:border-gray-700 dark:bg-gray-800"
                                    title="فتح المرفق المرجعي"
                                >
                                    <img src="{{ $url }}" alt="ملف مرجعي" class="h-full w-full object-cover" />
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                        <x-heroicon-o-eye class="h-4 w-4 text-white" />
                                    </div>
                                </a>
                            @else
                                <a
                                    href="{{ $url }}"
                                    download
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-100 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    <x-heroicon-m-document-arrow-down class="h-4 w-4 text-blue-500" />
                                    <span>مرفق {{ $loop->iteration }} ({{ strtoupper($ext) }})</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- سجل التعديلات السابقة --}}
            @if($task->revision_notes || count($revisionFiles) > 0)
                <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-4 shadow-xs dark:border-rose-900/60 dark:bg-rose-950/20">
                    <span class="mb-2 flex items-center gap-1.5 text-xs font-black text-rose-700 dark:text-rose-300">
                        <x-heroicon-m-exclamation-circle class="h-4 w-4" />
                        <span>ملاحظات التعديل المسجلة</span>
                    </span>

                    @if($task->revision_notes)
                        <p class="rounded-xl border border-rose-200/70 bg-white/80 p-3 text-xs text-rose-900 dark:border-rose-900/40 dark:bg-gray-900/80 dark:text-rose-200 whitespace-pre-wrap leading-relaxed">
                            {{ $task->revision_notes }}
                        </p>
                    @endif

                    @if(count($revisionFiles) > 0)
                        <div class="mt-3 pt-2.5 border-t border-rose-200/80 dark:border-rose-900/50">
                            <span class="block text-[11px] font-bold text-rose-700 dark:text-rose-300 mb-1.5">مرفقات التعديل ({{ count($revisionFiles) }}):</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach($revisionFiles as $revFile)
                                    @php $revUrl = \Illuminate\Support\Facades\Storage::url($revFile); @endphp
                                    <a
                                        href="{{ $revUrl }}"
                                        target="_blank"
                                        class="group relative h-12 w-12 overflow-hidden rounded-xl border border-rose-300 bg-white shadow-xs transition hover:scale-105 dark:border-rose-800 dark:bg-gray-900"
                                    >
                                        <img src="{{ $revUrl }}" alt="مرفق تعديل" class="h-full w-full object-cover" />
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                            <x-heroicon-o-eye class="h-4 w-4 text-white" />
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

        </div>

        {{-- ============================== --}}
        {{-- الجانب الأيسر: معرض التصاميم المرفوعة والمراجعة (7 أعمدة) --}}
        {{-- ============================== --}}
        <div class="space-y-4 lg:col-span-7">

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">
                            <x-heroicon-m-photo class="h-4 w-4" />
                        </span>
                        <div>
                            <h3 class="font-black text-gray-900 dark:text-white text-xs sm:text-sm">
                                معرض التصاميم المُسلّمة
                            </h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                انقر على أي تصميم للمعاينة المكبرة بنقرة واحدة (Full Lightbox)
                            </p>
                        </div>
                    </div>

                    <span class="rounded-full bg-purple-50 px-2.5 py-1 text-xs font-black text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">
                        {{ count($designFiles) }} تصاميم
                    </span>
                </div>

                @if(count($designFiles) > 0)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach($designFiles as $designFile)
                            @php
                                $ext = strtolower(pathinfo($designFile, PATHINFO_EXTENSION));
                                $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                $fileUrl = \Illuminate\Support\Facades\Storage::url($designFile);
                                $downloadName = \Illuminate\Support\Str::slug($task->display_client_name . '-task-' . $task->id . '-' . $loop->iteration, '_') . '.' . $ext;
                            @endphp

                            <div class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 shadow-2xs transition-all duration-200 hover:border-primary-400 hover:shadow-md dark:border-gray-800 dark:bg-gray-950">
                                
                                {{-- شارة الترقيم والنوع --}}
                                <div class="flex items-center justify-between border-b border-gray-100 bg-white/70 p-2.5 dark:border-gray-800 dark:bg-gray-900/70">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-purple-50 px-2 py-0.5 text-[10px] font-black text-purple-700 dark:bg-purple-950/40 dark:text-purple-300">
                                        <span>تصميم #{{ $loop->iteration }}</span>
                                    </span>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">
                                        {{ $ext }}
                                    </span>
                                </div>

                                {{-- معاينة التصميم --}}
                                <div class="relative flex min-h-[220px] items-center justify-center p-3">
                                    @if($isImg)
                                        <div class="relative flex h-52 w-full items-center justify-center overflow-hidden rounded-xl bg-gray-950/5 dark:bg-gray-950/40">
                                            <img
                                                src="{{ $fileUrl }}"
                                                alt="تصميم {{ $loop->iteration }}"
                                                class="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105 cursor-pointer"
                                                loading="lazy"
                                                @click="openLightbox('{{ $fileUrl }}', '{{ addslashes($task->display_client_name) }} - تصميم #{{ $loop->iteration }}', '{{ $fileUrl }}')"
                                            />

                                            {{-- زر المعاينة المكبرة السريعة عند التمرير --}}
                                            <div class="absolute inset-0 flex items-center justify-center bg-gray-950/40 opacity-0 backdrop-blur-2xs transition-opacity duration-200 group-hover:opacity-100 pointer-events-none">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-xl bg-white/95 px-3 py-1.5 text-xs font-extrabold text-gray-900 shadow-lg dark:bg-gray-900/95 dark:text-white pointer-events-auto cursor-pointer"
                                                    @click="openLightbox('{{ $fileUrl }}', '{{ addslashes($task->display_client_name) }} - تصميم #{{ $loop->iteration }}', '{{ $fileUrl }}')"
                                                >
                                                    <x-heroicon-m-eye class="h-4 w-4 text-primary-600" />
                                                    <span>معاينة مكبرة</span>
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex flex-col items-center justify-center py-10 text-purple-600 dark:text-purple-400">
                                            <x-heroicon-o-document-arrow-down class="h-14 w-14 stroke-1" />
                                            <span class="mt-2 text-xs font-bold">{{ strtoupper($ext) }} ملف تصميم</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- شريط أزرار المعاينة والتحميل المباشر --}}
                                <div class="flex items-center gap-2 border-t border-gray-100 bg-white p-2.5 dark:border-gray-800 dark:bg-gray-900">
                                    @if($isImg)
                                        <button
                                            type="button"
                                            @click="openLightbox('{{ $fileUrl }}', '{{ addslashes($task->display_client_name) }} - تصميم #{{ $loop->iteration }}', '{{ $fileUrl }}')"
                                            class="flex-1 inline-flex items-center justify-center gap-1 rounded-xl border border-gray-200 bg-gray-50 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-100 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                        >
                                            <x-heroicon-m-arrows-pointing-out class="h-3.5 w-3.5 text-gray-400" />
                                            <span>تكبير</span>
                                        </button>
                                    @endif

                                    <a
                                        href="{{ $fileUrl }}"
                                        download="{{ $downloadName }}"
                                        class="flex-1 inline-flex items-center justify-center gap-1 rounded-xl bg-primary-600 py-2 text-xs font-bold text-white shadow-2xs transition hover:bg-primary-500 active:bg-primary-700"
                                        title="تحميل الملف الأصلي عالي الدقة"
                                    >
                                        <x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" />
                                        <span>تحميل عالي الدقة</span>
                                    </a>
                                </div>

                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- حالة عدم وجود تصاميم --}}
                    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/60 p-12 text-center dark:border-gray-800 dark:bg-gray-950/40">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                            <x-heroicon-o-photo class="h-7 w-7" />
                        </span>
                        <h4 class="mt-3 text-sm font-bold text-gray-800 dark:text-gray-200">لم يقم المصمم برفع تصاميم بعد</h4>
                        <p class="mt-1 text-xs text-gray-400 max-w-xs">
                            المهمة ما زالت قيد التنفيذ من قِبل المصمم ({{ $task->designer?->user?->name ?? 'المصمم' }}).
                        </p>
                    </div>
                @endif
            </div>

        </div>

    </div>

    {{-- ============================== --}}
    {{-- الشريط السفلي الثابت (Sticky Footer) --}}
    {{-- ============================== --}}
    <div class="sticky bottom-0 -mx-6 -mb-6 border-t border-gray-200 bg-white/95 p-4 shadow-2xl backdrop-blur-md dark:border-gray-800 dark:bg-gray-900/95 z-30">
        
        @if($statusValue === 'in_review')
            {{-- الحالة الافتراضية: زران عريضان (اعتماد فوري / طلب تعديل) --}}
            <div x-show="!showRevisionForm" class="flex flex-col sm:flex-row items-center gap-3">
                {{-- زر الاعتماد الفوري بنقرة واحدة --}}
                <button
                    type="button"
                    wire:click="approveTask({{ $task->id }})"
                    wire:loading.attr="disabled"
                    class="flex flex-1 w-full items-center justify-center gap-2.5 rounded-2xl bg-emerald-600 py-3.5 text-sm font-black text-white shadow-lg shadow-emerald-600/25 transition-all hover:bg-emerald-500 active:scale-[0.98] disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="approveTask({{ $task->id }})" class="flex items-center gap-2">
                        <x-heroicon-m-check-badge class="h-5 w-5" />
                        <span>اعتماد فوري للتصميم بنقرة واحدة ✅</span>
                    </span>
                    <span wire:loading wire:target="approveTask({{ $task->id }})" class="flex items-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                        <span>جاري الاعتماد...</span>
                    </span>
                </button>

                {{-- زر طلب التعديل --}}
                <button
                    type="button"
                    @click.prevent="showRevisionForm = true"
                    class="flex flex-1 w-full items-center justify-center gap-2 rounded-2xl bg-rose-600 py-3.5 text-sm font-black text-white shadow-lg shadow-rose-600/25 transition-all hover:bg-rose-500 active:scale-[0.98]"
                >
                    <x-heroicon-m-arrow-path class="h-5 w-5" />
                    <span>طلب تعديل وملاحظات 🔄</span>
                </button>
            </div>

            {{-- نموذج طلب التعديل المدمج داخل الدراور دون إغلاق --}}
            <div x-show="showRevisionForm" x-cloak class="space-y-3.5">
                <div class="flex items-center justify-between border-b border-rose-100 pb-2 dark:border-rose-900/50">
                    <div class="flex items-center gap-2 text-rose-700 dark:text-rose-300">
                        <x-heroicon-m-pencil-square class="h-4 w-4" />
                        <span class="text-xs font-black">كتابة ملاحظات التعديل وإرفاق لقطات الشاشة</span>
                    </div>
                    <span class="text-[11px] text-gray-400">يمكنك الضغط على <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded font-mono text-[10px]">Ctrl + V</kbd> للصق الصور مباشرة</span>
                </div>

                {{-- مقترحات سريعة بنقرة واحدة --}}
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] font-bold text-gray-400 ml-1">مقترحات:</span>
                    @foreach(['تعديل النصوص والإملاء', 'تعديل الألوان ودرجاتها', 'تغيير موضع وحجم الشعار', 'تحديث التاريخ وأرقام التواصل', 'تعديل المقاسات'] as $preset)
                        <button
                            type="button"
                            @click="appendPreset('{{ $preset }}')"
                            class="rounded-lg border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold text-gray-600 transition hover:border-primary-400 hover:bg-primary-50/50 hover:text-primary-700 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-primary-500"
                        >
                            + {{ $preset }}
                        </button>
                    @endforeach
                </div>

                {{-- صندوق النص --}}
                <textarea
                    x-model="revisionNotes"
                    rows="3"
                    placeholder="اكتب التعديلات والملاحظات المطلوبة هنا بوضوح للمصمم... أو الصق لقطة الشاشة مباشرة هنا (Ctrl + V)"
                    class="w-full rounded-2xl border border-gray-300 bg-gray-50/50 p-3 text-xs text-gray-900 shadow-2xs transition focus:border-rose-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-rose-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                ></textarea>

                {{-- منطقة معاينة الصور المرفوعة أو الملصوقة --}}
                <div x-show="revisionFiles.length > 0" class="space-y-1.5">
                    <span class="text-[11px] font-bold text-rose-700 dark:text-rose-300">
                        الصور المرفقة مع التعديل (<span x-text="revisionFiles.length"></span>):
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(file, index) in revisionFiles" :key="index">
                            <div class="relative group h-14 w-14 overflow-hidden rounded-xl border-2 border-rose-300 bg-white dark:border-rose-800 dark:bg-gray-900 shadow-xs">
                                <img :src="file" class="h-full w-full object-cover" />
                                <button
                                    type="button"
                                    @click="removeRevisionFile(index)"
                                    class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-600 text-white shadow-md hover:bg-rose-700"
                                >
                                    <x-heroicon-m-x-mark class="h-3 w-3" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- أزرار التنفيذ --}}
                <div class="flex items-center justify-between gap-3 pt-1">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <x-heroicon-m-photo class="h-4 w-4 text-primary-500" />
                        <span>إرفاق صور توضيحية</span>
                        <input type="file" accept="image/*" multiple class="hidden" @change="handleFileInput($event)" />
                    </label>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showRevisionForm = false; revisionNotes = ''; revisionFiles = [];"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            إلغاء والتراجع
                        </button>
                        <button
                            type="button"
                            @click="submitRevision({{ $task->id }})"
                            :disabled="!revisionNotes.trim() || isSubmitting"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-5 py-2 text-xs font-black text-white shadow-md shadow-rose-600/25 transition hover:bg-rose-500 active:bg-rose-700 disabled:opacity-50"
                        >
                            <span x-show="!isSubmitting" class="flex items-center gap-1.5">
                                <x-heroicon-m-paper-airplane class="h-3.5 w-3.5" />
                                <span>إرسال التعديل للمصمم</span>
                            </span>
                            <span x-show="isSubmitting" class="flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                                <span>جاري الإرسال...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        @else
            {{-- إذا لم تكن قيد المراجعة --}}
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs">
                    <span class="h-2.5 w-2.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                    <span class="font-bold text-gray-700 dark:text-gray-300">حالة المهمة الحالية: {{ $statusConfig['label'] }}</span>
                </div>

                <button
                    type="button"
                    @click="$dispatch('close-modal', { id: '{{ $this->getId() }}-table-action' })"
                    class="rounded-xl border border-gray-200 bg-white px-5 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                >
                    إغلاق الدرج
                </button>
            </div>
        @endif

    </div>

    {{-- ============================== --}}
    {{-- نافذة المعاينة المكبرة (Full Lightbox) --}}
    {{-- ============================== --}}
    <div
        x-show="lightboxOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/90 backdrop-blur-md"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            @click.outside="lightboxOpen = false"
            class="relative flex flex-col items-center max-w-5xl max-h-[92vh] w-full"
        >
            {{-- زر إغلاق المعاينة --}}
            <button
                type="button"
                @click="lightboxOpen = false"
                class="absolute -top-11 left-0 flex items-center gap-1.5 rounded-xl bg-white/10 px-3.5 py-1.5 text-xs font-bold text-white backdrop-blur-md transition hover:bg-white/20"
            >
                <x-heroicon-m-x-mark class="h-4 w-4" />
                <span>إغلاق (Esc)</span>
            </button>

            {{-- الصورة بالحجم الكامل --}}
            <div class="overflow-hidden rounded-2xl bg-black/50 p-2 shadow-2xl ring-1 ring-white/10 flex items-center justify-center">
                <img
                    :src="lightboxImage"
                    :alt="lightboxTitle"
                    class="max-h-[78vh] max-w-full rounded-xl object-contain shadow-2xl"
                />
            </div>

            {{-- شريط معلومات الصورة وزر التحميل المباشر --}}
            <div class="mt-3 flex items-center justify-between w-full rounded-2xl bg-gray-900/95 border border-gray-800 px-5 py-3 backdrop-blur-md text-white text-xs shadow-xl">
                <div class="space-y-0.5">
                    <div class="font-bold text-sm text-white" x-text="lightboxTitle"></div>
                    <div class="text-[11px] text-gray-400">معاينة بدقة العرض الأصلية</div>
                </div>

                <a
                    :href="lightboxDownloadUrl"
                    download
                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 font-black text-white shadow-sm transition hover:bg-primary-500 active:scale-95"
                >
                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                    <span>تحميل الصورة عالية الدقة</span>
                </a>
            </div>
        </div>
    </div>

</div>