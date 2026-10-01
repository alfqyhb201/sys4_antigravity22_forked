@php
    $ideas = $tag->ideas()->latest()->get();
    $totalIdeas = $ideas->count();
    $activeIdeasCount = $ideas->where('is_visible_in_generator', true)->count();
    $scheduledIdeasCount = $ideas->whereNotNull('scheduled_at')->count();
@endphp

<div
    class="space-y-6 text-sm font-sans"
    dir="rtl"
    x-data="{
        search: '',
        filterType: 'all',
        showAddForm: {{ $totalIdeas === 0 ? 'true' : 'false' }},
        errorMessage: '',
        newIdea: {
            name: '',
            content: '',
            description: '',
            scheduled_at: '',
            is_visible: true
        },
        submitting: false,
        expandedIdeas: {},
        toggleExpand(id) {
            this.expandedIdeas[id] = !this.expandedIdeas[id];
        },
        submitIdea() {
            this.errorMessage = '';
            const name = (this.newIdea.name || '').trim();
            const content = (this.newIdea.content || '').trim();

            if (!name) {
                this.errorMessage = '⚠️ يرجى كتابة اسم أو عنوان الفكرة أولاً.';
                return;
            }
            if (!content) {
                this.errorMessage = '⚠️ يرجى كتابة محتوى أو سيناريو الفكرة بالتفصيل.';
                return;
            }

            this.submitting = true;
            $wire.quickAddIdeaToTag(
                {{ $tag->id }},
                name,
                content,
                this.newIdea.description || null,
                this.newIdea.scheduled_at || null,
                this.newIdea.is_visible
            ).then(() => {
                this.newIdea.name = '';
                this.newIdea.content = '';
                this.newIdea.description = '';
                this.newIdea.scheduled_at = '';
                this.errorMessage = '';
                this.submitting = false;
            }).catch((err) => {
                this.errorMessage = 'حدث خطأ أثناء حفظ الفكرة. يرجى إعادة المحاولة.';
                this.submitting = false;
            });
        },
        matches(name, content, description, isVisible, isScheduled) {
            if (this.filterType === 'active' && !isVisible) return false;
            if (this.filterType === 'scheduled' && !isScheduled) return false;
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase().trim();
            return (name && name.toLowerCase().includes(q)) ||
                   (content && content.toLowerCase().includes(q)) ||
                   (description && description.toLowerCase().includes(q));
        }
    }"
>
    {{-- Header Banner & Tag Context --}}
    <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-white via-gray-50/50 to-primary-50/20 p-5 shadow-sm dark:border-gray-800 dark:from-gray-900 dark:via-gray-900/80 dark:to-primary-950/20">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-100 text-primary-700 shadow-sm dark:bg-primary-900/60 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-tag" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ $tag->name }}</span>
                            @if($tag->is_active)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                    نشط
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    غير نشط
                                </span>
                            @endif
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            @if($tag->tagGroup)
                                <span class="flex items-center gap-1 font-medium text-gray-700 dark:text-gray-300">
                                    <x-filament::icon icon="heroicon-m-rectangle-group" class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ $tag->tagGroup->name }}</span>
                                </span>
                                <span>•</span>
                            @endif
                            <span class="font-medium">
                                الأهمية: 
                                <span class="font-bold text-gray-800 dark:text-gray-200">
                                    @switch($tag->importance)
                                        @case('veryhigh') عالية جداً @break
                                        @case('high') عالية @break
                                        @case('medium') متوسطة @break
                                        @case('low') منخفضة @break
                                        @default {{ $tag->importance ?? '—' }}
                                    @endswitch
                                </span>
                            </span>
                            @if($tag->is_there_date_for_sending)
                                <span>•</span>
                                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold">
                                    <x-filament::icon icon="heroicon-m-clock" class="h-3.5 w-3.5" />
                                    {{ $tag->date_for_sending_yearly ? 'سنوي' : 'أسبوعي' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary Badges --}}
            <div class="flex items-center gap-2">
                <div class="rounded-xl border border-gray-200/80 bg-white px-3.5 py-2 text-center shadow-xs dark:border-gray-800 dark:bg-gray-800/80">
                    <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400">إجمالي الأفكار</div>
                    <div class="text-base font-black {{ $totalIdeas > 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }}">
                        {{ $totalIdeas }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200/80 bg-white px-3.5 py-2 text-center shadow-xs dark:border-gray-800 dark:bg-gray-800/80">
                    <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400">مؤهلة للتوزيع</div>
                    <div class="text-base font-black text-emerald-600 dark:text-emerald-400">
                        {{ $activeIdeasCount }}
                    </div>
                </div>
                <div class="rounded-xl border border-gray-200/80 bg-white px-3.5 py-2 text-center shadow-xs dark:border-gray-800 dark:bg-gray-800/80">
                    <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400">مجدولة</div>
                    <div class="text-base font-black text-teal-600 dark:text-teal-400">
                        {{ $scheduledIdeasCount }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Zero ideas critical alert --}}
        @if($totalIdeas === 0)
            <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50/90 p-3.5 text-xs text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200 flex items-start gap-2.5">
                <div class="flex-shrink-0 mt-0.5">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400 animate-pulse" />
                </div>
                <div>
                    <strong class="font-bold">تنبيه تشغيلي حرج:</strong>
                    هذا الوسم لا يحتوي على أي أفكار بعد! في خوارزمية التوزيع الأسبوعي والسنوي، الوسوم الخالية من الأفكار تُعد غير مكتملة ولن تدخل في توليد مهام التصميم. يُرجى إضافة فكرة سريعة عبر النموذج أدناه.
                </div>
            </div>
        @endif
    </div>

    {{-- Inline Quick Idea Creator Form --}}
    <div class="rounded-2xl border-2 border-dashed border-primary-300/80 bg-gradient-to-br from-primary-50/30 via-white to-white p-5 shadow-xs dark:border-primary-700/60 dark:from-primary-950/30 dark:via-gray-900 dark:to-gray-900">
        <div class="flex items-center justify-between mb-3.5">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white shadow-xs">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                </span>
                <div>
                    <h3 class="text-sm font-black text-gray-900 dark:text-white">
                        إضافة فكرة سريعة لهذا الوسم
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        تُحفظ الفكرة وتُرتبط فوراً بهذا الوسم دون مغادرة الشاشة
                    </p>
                </div>
            </div>

            @if($totalIdeas > 0)
                <button
                    type="button"
                    @click="showAddForm = !showAddForm"
                    class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1"
                >
                    <span x-text="showAddForm ? 'إخفاء النموذج ▲' : 'إظهار النموذج ▼'"></span>
                </button>
            @endif
        </div>

        <div
            x-show="showAddForm"
            x-collapse
            class="space-y-3.5"
        >
            <div
                x-show="errorMessage"
                x-transition
                class="rounded-xl border border-danger-200 bg-danger-50 p-3 text-xs font-bold text-danger-700 dark:border-danger-800 dark:bg-danger-950/60 dark:text-danger-300 flex items-center gap-2"
            >
                <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-4 w-4 text-danger-500" />
                <span x-text="errorMessage"></span>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                    اسم / عنوان الفكرة <span class="text-danger-500">*</span>
                </label>
                <input
                    type="text"
                    x-model="newIdea.name"
                    @keydown.enter.prevent="submitIdea"
                    placeholder="مثال: خصم اليوم الوطني 20%، أو نصيحة احترافية أسبوعية..."
                    class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                />
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                    محتوى الفكرة / سيناريو التصميم <span class="text-danger-500">*</span>
                </label>
                <textarea
                    x-model="newIdea.content"
                    rows="3"
                    placeholder="اكتب المحتوى أو السيناريو أو التعليمات الإبداعية للمصمم بالتفصيل هنا..."
                    class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                ></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                        تاريخ الجدولة (اختياري)
                    </label>
                    <input
                        type="datetime-local"
                        x-model="newIdea.scheduled_at"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                </div>

                <div class="flex items-center justify-start pt-5">
                    <label class="relative flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            x-model="newIdea.is_visible"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800"
                        />
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            مؤهلة للتوزيع التلقائي في المولد
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1 border-t border-gray-200/60 dark:border-gray-800/60">
                <button
                    type="button"
                    @click="newIdea = { name: '', content: '', description: '', scheduled_at: '', is_visible: true }; errorMessage = ''"
                    class="rounded-xl px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 transition"
                >
                    تفريغ الحقول
                </button>
                <button
                    type="button"
                    @click="submitIdea"
                    :disabled="submitting"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-primary-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer"
                >
                    <span x-show="!submitting" class="flex items-center gap-1">
                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        حفظ وربط الفكرة فوراً ⚡
                    </span>
                    <span x-show="submitting" class="flex items-center gap-1">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        جاري الحفظ...
                    </span>
                </button>
            </div>
        </div>
    </div>

    {{-- Ideas List Section --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="h-4 w-4 text-primary-500" />
                    <span>قائمة الأفكار المرتبطة ({{ $totalIdeas }})</span>
                </h3>
            </div>

            @if($totalIdeas > 0)
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <input
                            type="text"
                            x-model="search"
                            placeholder="بحث في أفكار هذا الوسم..."
                            class="w-48 sm:w-56 rounded-xl border border-gray-300 bg-white px-3 py-1.5 pl-8 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500"
                        />
                        <span class="absolute left-2.5 top-2 text-gray-400">
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-3.5 w-3.5" />
                        </span>
                    </div>

                    <select
                        x-model="filterType"
                        class="rounded-xl border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-900 focus:border-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                        <option value="all">كل الأفكار</option>
                        <option value="active">المؤهلة للتوزيع</option>
                        <option value="scheduled">المجدولة فقط</option>
                    </select>
                </div>
            @endif
        </div>

        @if($totalIdeas === 0)
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-800">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-500 dark:bg-amber-950/40 dark:text-amber-400">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="h-6 w-6" />
                </div>
                <h4 class="mt-3 text-sm font-bold text-gray-900 dark:text-white">لا توجد أفكار مرتبطة بهذا الوسم حتى الآن</h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    استخدم النموذج السريع بالأعلى لإضافة أول فكرة للوسم وتفعيله في نظام التوزيع.
                </p>
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($ideas as $idea)
                    @php
                        $ideaName = addslashes($idea->name ?? '');
                        $ideaContent = addslashes($idea->content ?? '');
                        $ideaDesc = addslashes($idea->description ?? '');
                        $isVisible = $idea->is_visible_in_generator ? 'true' : 'false';
                        $isScheduled = $idea->scheduled_at ? 'true' : 'false';
                    @endphp

                    <div
                        x-show="matches('{{ $ideaName }}', '{{ $ideaContent }}', '{{ $ideaDesc }}', {{ $isVisible }}, {{ $isScheduled }})"
                        x-transition
                        class="group rounded-xl border border-gray-200/90 bg-white p-4 transition-all duration-200 hover:border-primary-400 hover:shadow-md dark:border-gray-800 dark:bg-gray-900/90 dark:hover:border-primary-600"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-extrabold text-sm text-gray-900 dark:text-white">
                                        {{ $idea->name }}
                                    </span>
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-mono text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        #{{ $idea->id }}
                                    </span>
                                    @if($idea->is_visible_in_generator)
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            مؤهلة للتوزيع
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            معطلة
                                        </span>
                                    @endif

                                    @if($idea->scheduled_at)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold text-teal-700 dark:bg-teal-950/50 dark:text-teal-300">
                                            <x-filament::icon icon="heroicon-m-calendar-days" class="h-3 w-3" />
                                            {{ \Carbon\Carbon::parse($idea->scheduled_at)->format('Y-m-d H:i') }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Content snippet with expand / collapse --}}
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed">
                                    <div :class="expandedIdeas[{{ $idea->id }}] ? '' : 'line-clamp-2'">
                                        {{ $idea->content }}
                                    </div>
                                    @if(mb_strlen($idea->content ?? '') > 120)
                                        <button
                                            type="button"
                                            @click="toggleExpand({{ $idea->id }})"
                                            class="text-[11px] font-bold text-primary-600 dark:text-primary-400 hover:underline mt-0.5"
                                        >
                                            <span x-text="expandedIdeas[{{ $idea->id }}] ? 'عرض أقل ▲' : 'قراءة المزيد ▼'"></span>
                                        </button>
                                    @endif
                                </div>

                                @if($idea->description)
                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 italic mt-0.5">
                                        {{ $idea->description }}
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    wire:click="toggleIdeaVisibility({{ $idea->id }})"
                                    title="{{ $idea->is_visible_in_generator ? 'تعطيل من التوزيع' : 'تفعيل في التوزيع' }}"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition"
                                >
                                    @if($idea->is_visible_in_generator)
                                        <x-filament::icon icon="heroicon-m-eye" class="h-4 w-4 text-emerald-600" />
                                    @else
                                        <x-filament::icon icon="heroicon-m-eye-slash" class="h-4 w-4 text-gray-400" />
                                    @endif
                                </button>

                                <a
                                    href="{{ \App\Filament\Resources\IdeaResource::getUrl('index', ['tableSearch' => $idea->name]) }}"
                                    target="_blank"
                                    title="عرض في جدول الأفكار"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-primary-50 hover:text-primary-600 dark:hover:bg-primary-950/40 dark:hover:text-primary-400 transition"
                                >
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-4 w-4" />
                                </a>

                                <button
                                    type="button"
                                    wire:click="detachIdeaFromTag({{ $tag->id }}, {{ $idea->id }})"
                                    wire:confirm="هل أنت متأكد من فك ارتباط هذه الفكرة عن الوسم؟"
                                    title="فك الارتباط عن الوسم"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400 transition"
                                >
                                    <x-filament::icon icon="heroicon-m-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-2.5 flex items-center justify-between border-t border-gray-100 pt-2 text-[11px] text-gray-400 dark:border-gray-800/80">
                            <span>
                                أُضيفت: {{ $idea->created_at?->diffForHumans() ?? '—' }}
                                @if($idea->addedBy)
                                    بواسطة {{ $idea->addedBy->name }}
                                @endif
                            </span>
                            @if($idea->repeat_for_clients)
                                <span class="text-teal-600 dark:text-teal-400 font-medium">
                                    تكرار للعملاء مفعل
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
