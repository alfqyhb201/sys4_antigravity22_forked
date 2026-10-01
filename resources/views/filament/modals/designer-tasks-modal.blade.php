@php
    $targetDate = $targetDate ?? \Carbon\Carbon::now()->format('Y-m-d');
    $todayTasks = $todayTasks ?? collect();
    $overdueTasks = $overdueTasks ?? collect();
    $availableDesigners = $availableDesigners ?? [];
    $uploadTaskId = $uploadTaskId ?? null;
    $uploadFile = $uploadFile ?? null;

    // دمج جميع المهام بترتيب: المتأخرات أولاً ثم مهام اليوم
    $allTasks = $overdueTasks->concat($todayTasks);

    $totalCount = $allTasks->count();
    $todayCount = $todayTasks->count();
    $overdueCount = $overdueTasks->count();
    $completedCount = $todayTasks->whereIn('status', ['sending', 'completed'])->count();
    $pendingCount = $allTasks->whereIn('status', ['pending', 'in_progress', null, ''])->count();

    $statusConfig = [
        'pending' => [
            'label' => 'قيد الانتظار',
            'badge' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
            'dot' => 'bg-gray-400',
        ],
        'in_progress' => [
            'label' => 'قيد التنفيذ',
            'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'dot' => 'bg-amber-500',
        ],
        'reviewing' => [
            'label' => 'قيد المراجعة',
            'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'dot' => 'bg-blue-500',
        ],
        'changes_requested' => [
            'label' => 'مطلوب تعديل',
            'badge' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            'dot' => 'bg-rose-500',
        ],
        'sending' => [
            'label' => 'جاهز للإرسال',
            'badge' => 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300 border-orange-200 dark:border-orange-800',
            'dot' => 'bg-orange-500',
        ],
        'completed' => [
            'label' => 'مكتمل',
            'badge' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'dot' => 'bg-emerald-500',
        ],
    ];
@endphp

<div
    class="space-y-4 font-sans text-sm"
    dir="rtl"
    x-data="{
        search: '',
        activeTab: 'all',
        activeTransferTaskId: null,
        activeUploadTaskId: null,
        matchesTask(taskName, tagName, status, isOverdue, isToday) {
            const query = this.search.toLowerCase().trim();
            const matchesSearch = !query || taskName.toLowerCase().includes(query) || tagName.toLowerCase().includes(query);
            
            if (!matchesSearch) return false;

            if (this.activeTab === 'overdue') return isOverdue;
            if (this.activeTab === 'today') return isToday;
            if (this.activeTab === 'completed') return status === 'completed' || status === 'sending';
            if (this.activeTab === 'in_progress') return status === 'in_progress' || status === 'pending' || status === 'changes_requested';
            
            return true;
        }
    }"
>

    {{-- شريط الإحصائيات السريع العلوي للدرج الجانبي (KPI Metric Cards) --}}
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {{-- إجمالي المهام --}}
        <div class="flex items-center gap-2.5 rounded-xl border border-gray-200/80 bg-gray-50/70 p-2.5 dark:border-gray-800 dark:bg-gray-800/40">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-200/60 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <x-heroicon-o-queue-list class="h-4 w-4" />
            </div>
            <div>
                <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">إجمالي المهام</p>
                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $totalCount }}</p>
            </div>
        </div>

        {{-- مهام اليوم --}}
        <div class="flex items-center gap-2.5 rounded-xl border border-amber-200/60 bg-amber-50/40 p-2.5 dark:border-amber-900/30 dark:bg-amber-950/20">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <x-heroicon-o-calendar-days class="h-4 w-4" />
            </div>
            <div>
                <p class="text-[11px] font-medium text-amber-700/80 dark:text-amber-400/80">مهام اليوم</p>
                <p class="text-sm font-bold text-amber-900 dark:text-amber-200">{{ $todayCount }}</p>
            </div>
        </div>

        {{-- المتأخرات --}}
        <div class="flex items-center gap-2.5 rounded-xl border {{ $overdueCount > 0 ? 'border-rose-200/80 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/20 ring-1 ring-rose-500/20' : 'border-gray-200/80 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/40' }} p-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $overdueCount > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' : 'bg-gray-200/60 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
            </div>
            <div>
                <p class="text-[11px] font-medium {{ $overdueCount > 0 ? 'text-rose-700/80 dark:text-rose-400/80' : 'text-gray-500 dark:text-gray-400' }}">متأخرات</p>
                <p class="text-sm font-bold {{ $overdueCount > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-gray-900 dark:text-white' }}">{{ $overdueCount }}</p>
            </div>
        </div>

        {{-- منجز اليوم --}}
        <div class="flex items-center gap-2.5 rounded-xl border border-emerald-200/60 bg-emerald-50/40 p-2.5 dark:border-emerald-900/30 dark:bg-emerald-950/20">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                <x-heroicon-o-check-badge class="h-4 w-4" />
            </div>
            <div>
                <p class="text-[11px] font-medium text-emerald-700/80 dark:text-emerald-400/80">المنجز</p>
                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-200">{{ $completedCount }}</p>
            </div>
        </div>
    </div>

    {{-- شريط التصفية والبحث السريع داخل الدرج الجانبي --}}
    @if($allTasks->isNotEmpty())
        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
            {{-- أزرار التبويب --}}
            <div class="flex items-center gap-1 overflow-x-auto rounded-lg bg-gray-100/80 p-1 dark:bg-gray-800/80">
                <button
                    type="button"
                    @click="activeTab = 'all'"
                    :class="activeTab === 'all' ? 'bg-white text-gray-900 shadow-2xs dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                >
                    الكل ({{ $totalCount }})
                </button>

                @if($overdueCount > 0)
                    <button
                        type="button"
                        @click="activeTab = 'overdue'"
                        :class="activeTab === 'overdue' ? 'bg-rose-500 text-white shadow-2xs' : 'text-rose-600 hover:bg-rose-100/50 dark:text-rose-400 dark:hover:bg-rose-950/50'"
                        class="rounded-md px-2.5 py-1 text-xs font-bold transition flex items-center gap-1"
                    >
                        <span>متأخرات</span>
                        <span class="rounded-full bg-rose-200/80 px-1 text-[10px] text-rose-800 dark:bg-rose-900 dark:text-rose-200">{{ $overdueCount }}</span>
                    </button>
                @endif

                <button
                    type="button"
                    @click="activeTab = 'today'"
                    :class="activeTab === 'today' ? 'bg-white text-gray-900 shadow-2xs dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                >
                    مهام اليوم ({{ $todayCount }})
                </button>

                <button
                    type="button"
                    @click="activeTab = 'completed'"
                    :class="activeTab === 'completed' ? 'bg-white text-gray-900 shadow-2xs dark:bg-gray-700 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                    class="rounded-md px-2.5 py-1 text-xs font-semibold transition"
                >
                    المنجز ({{ $completedCount }})
                </button>
            </div>

            {{-- حقل البحث السريع --}}
            <div class="relative w-full sm:w-56">
                <input
                    type="text"
                    x-model="search"
                    placeholder="بحث باسم العميل أو التاق..."
                    class="w-full rounded-lg border-gray-200 bg-white py-1.5 pe-8 ps-3 text-xs placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500"
                />
                <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-2.5 text-gray-400">
                    <x-heroicon-m-magnifying-glass class="h-3.5 w-3.5" />
                </div>
            </div>
        </div>
    @endif

    {{-- قائمة / جدول المهام في الدرج الجانبي --}}
    @if($allTasks->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 py-12 text-center dark:border-gray-800">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500">
                <x-heroicon-o-clipboard-document-check class="h-6 w-6" />
            </div>
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-300">لا توجد أي مهام مسندة لهذا المصمم في هذا التاريخ</p>
            <p class="mt-1 text-xs text-gray-400">لا توجد مهام حالية أو متأخرات تتطلب المتابعة.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($allTasks as $index => $task)
                @php
                    $isOverdue = $task->distribution_date < $targetDate && !in_array($task->status, ['sending', 'completed']);
                    $isToday = $task->distribution_date === $targetDate;
                    $status = $statusConfig[$task->status ?? 'pending'] ?? $statusConfig['pending'];
                    $hasAttachment = !empty($task->attachment_path);
                    $clientName = $task->clientDesigner?->client?->company ?? 'عميل غير محدد';
                    $tagName = $task->tag?->name ?? 'عام';
                    $canTransfer = !in_array($task->status, ['completed']);
                    $canUpload = !in_array($task->status, ['completed']);
                @endphp

                <div
                    x-show="matchesTask('{{ addslashes($clientName) }}', '{{ addslashes($tagName) }}', '{{ $task->status ?? 'pending' }}', {{ $isOverdue ? 'true' : 'false' }}, {{ $isToday ? 'true' : 'false' }})"
                    x-transition
                    class="rounded-xl border transition {{ $isOverdue ? 'border-rose-200/80 bg-rose-50/20 dark:border-rose-900/40 dark:bg-rose-950/10' : 'border-gray-200/80 bg-white dark:border-gray-800 dark:bg-gray-900/90' }} shadow-2xs hover:shadow-xs p-3.5 space-y-3"
                >
                    {{-- الصف العلوي: العميل، التاق، الحالة، والتاريخ --}}
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="space-y-1 min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $index + 1 }}
                                </span>

                                <span class="font-bold text-gray-900 dark:text-white text-sm">
                                    {{ $clientName }}
                                </span>

                                <span class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    🏷️ {{ $tagName }}
                                </span>

                                @if($isOverdue)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
                                        <x-heroicon-m-exclamation-triangle class="h-3 w-3" />
                                        متأخر منذ {{ $task->distribution_date }}
                                    </span>
                                @elseif($isToday)
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">
                                        اليوم
                                    </span>
                                @else
                                    <span class="text-[11px] text-gray-400">
                                        {{ $task->distribution_date }}
                                    </span>
                                @endif
                            </div>

                            {{-- الفكرة / الملاحظة إن وجدت --}}
                            @if($task->idea)
                                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1 pt-0.5" title="{{ $task->idea->name }}">
                                    <span>💡</span>
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $task->idea->name }}</span>
                                </p>
                            @endif

                            {{-- تنبيه ملاحظات المراجع --}}
                            @if($task->reviewer_feedback)
                                <div class="mt-1.5 rounded-lg bg-rose-50 p-2 text-xs text-rose-700 border border-rose-200/70 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/40">
                                    <span class="font-bold">📝 ملاحظات المراجع:</span> {{ $task->reviewer_feedback }}
                                </div>
                            @endif
                        </div>

                        {{-- الحالة ومعاينة التصميم إن وجد --}}
                        <div class="flex items-center gap-2 shrink-0">
                            {{-- شارة الحالة --}}
                            <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold {{ $status['badge'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $status['dot'] }}"></span>
                                <span>{{ $status['label'] }}</span>
                            </span>

                            {{-- مصغّر المعاينة --}}
                            @if($hasAttachment)
                                <a
                                    href="{{ asset('storage/' . $task->attachment_path) }}"
                                    target="_blank"
                                    class="group relative inline-block shrink-0"
                                    title="عرض التصميم المرفق بحجم كامل"
                                >
                                    <img
                                        src="{{ asset('storage/' . $task->attachment_path) }}"
                                        class="h-8 w-8 rounded-lg object-cover ring-1 ring-gray-200 transition group-hover:scale-110 group-hover:ring-primary-500 dark:ring-gray-700 shadow-2xs"
                                        alt="معاينة"
                                    />
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- شريط الإجراءات السريعة لكل سطر (Quick Actions Row) --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2.5 dark:border-gray-800">
                        {{-- معلومات التوقيت --}}
                        <div class="text-[11px] text-gray-400 dark:text-gray-500">
                            @if($task->scheduled_sending_at)
                                <span>موعد الإرسال: {{ \Carbon\Carbon::parse($task->scheduled_sending_at)->format('h:i A') }}</span>
                            @else
                                <span>تاريخ المهمة: {{ $task->distribution_date }}</span>
                            @endif
                        </div>

                        {{-- أزرار الإجراءات السريعة --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- زر رفع التصميم المنجز --}}
                            @if($canUpload)
                                <button
                                    type="button"
                                    @click="activeUploadTaskId = (activeUploadTaskId === {{ $task->id }} ? null : {{ $task->id }}); activeTransferTaskId = null; if(activeUploadTaskId) { $wire.prepareDirectUpload({{ $task->id }}); }"
                                    :class="activeUploadTaskId === {{ $task->id }} ? 'bg-emerald-600 text-white border-emerald-600' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50'"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition shadow-2xs"
                                    title="رفع التصميم المنجز مباشرة لهذه المهمة"
                                >
                                    <x-heroicon-m-arrow-up-tray class="h-3.5 w-3.5" />
                                    <span>رفع التصميم</span>
                                </button>
                            @endif

                            {{-- زر تنبيه المصمم --}}
                            <button
                                type="button"
                                wire:click="notifyDesignerForTask({{ $task->id }})"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50/80 px-2.5 py-1 text-xs font-bold text-amber-800 transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300 dark:hover:bg-amber-900/50 disabled:opacity-50"
                                title="إرسال إشعار تذكيري فوري للمصمم في لوحته"
                            >
                                <x-heroicon-m-bell-alert class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                                <span>تنبيه المصمم</span>
                            </button>

                            {{-- زر النقل الفوري --}}
                            @if($canTransfer)
                                <button
                                    type="button"
                                    @click="activeTransferTaskId = (activeTransferTaskId === {{ $task->id }} ? null : {{ $task->id }}); activeUploadTaskId = null;"
                                    :class="activeTransferTaskId === {{ $task->id }} ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition shadow-2xs"
                                    title="نقل هذه المهمة فوراً لمصمم بديل"
                                >
                                    <x-heroicon-m-arrows-right-left class="h-3.5 w-3.5" />
                                    <span>نقل فوري</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- لوحة النقل الفوري المدمجة داخل السطر (Inline Quick Transfer Panel) --}}
                    @if($canTransfer)
                        <div
                            x-show="activeTransferTaskId === {{ $task->id }}"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            x-cloak
                            class="rounded-xl border border-primary-200/80 bg-primary-50/40 p-3 dark:border-primary-900/40 dark:bg-primary-950/20 space-y-2.5"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5">
                                    <span>👤 اختر المصمم البديل لنقل المهمة إليه مباشرة:</span>
                                </p>
                                <button
                                    type="button"
                                    @click="activeTransferTaskId = null"
                                    class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 font-medium"
                                >
                                    ✕ إلغاء
                                </button>
                            </div>

                            @if(empty($availableDesigners))
                                <p class="text-xs text-gray-500 dark:text-gray-400">لا يوجد مصممون آخرون متاحون للتحويل إليهم حالياً.</p>
                            @else
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach($availableDesigners as $designerOption)
                                        <button
                                            type="button"
                                            wire:click="quickReassignTask({{ $task->id }}, {{ $designerOption['id'] }})"
                                            wire:loading.attr="disabled"
                                            class="flex items-center justify-between rounded-lg border border-white bg-white/90 p-2 text-right transition hover:border-primary-400 hover:bg-primary-50/60 dark:border-gray-700 dark:bg-gray-800/90 dark:hover:border-primary-600 dark:hover:bg-primary-950/40 shadow-2xs group disabled:opacity-50"
                                        >
                                            <div class="min-w-0 flex-1">
                                                <p class="font-bold text-xs text-gray-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 truncate">
                                                    {{ $designerOption['name'] }}
                                                </p>
                                                <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                                    مهام اليوم: <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $designerOption['today_tasks'] }}</span>
                                                    @if($designerOption['daily_target'] !== 'غير محدد')
                                                        / مستهدف: {{ $designerOption['daily_target'] }}
                                                    @endif
                                                </p>
                                            </div>

                                            <div class="shrink-0 ps-2">
                                                @if($designerOption['is_busy'])
                                                    <span class="inline-flex items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                                        ممتلئ
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                                        ✓ متاح
                                                    </span>
                                                @endif
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- لوحة رفع التصميم المباشر (Inline Direct Upload Panel) --}}
                    @if($canUpload)
                        <div
                            x-show="activeUploadTaskId === {{ $task->id }}"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            x-cloak
                            class="rounded-xl border border-emerald-300/80 bg-emerald-50/50 p-3.5 dark:border-emerald-900/50 dark:bg-emerald-950/20 space-y-3"
                        >
                            <div class="flex items-center justify-between border-b border-emerald-200/60 pb-2 dark:border-emerald-900/40">
                                <p class="text-xs font-bold text-emerald-950 dark:text-emerald-200 flex items-center gap-1.5">
                                    <x-heroicon-m-arrow-up-tray class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    <span>رفع التصميم المنجز لـ <strong class="text-emerald-700 dark:text-emerald-300">{{ $clientName }}</strong> ({{ $tagName }})</span>
                                </p>
                                <button
                                    type="button"
                                    @click="activeUploadTaskId = null"
                                    wire:click="cancelDirectUpload"
                                    class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 font-medium"
                                >
                                    ✕ إلغاء
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                                {{-- منطقة رفع الملف (Dropzone) --}}
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-200">
                                        صورة التصميم <span class="text-rose-500">*</span>
                                    </label>
                                    
                                    <div
                                        x-data="{
                                            previewUrl: null,
                                            fileName: '',
                                            handleFileChange(e) {
                                                const file = e.target.files[0];
                                                if (file) {
                                                    this.previewUrl = URL.createObjectURL(file);
                                                    this.fileName = file.name;
                                                } else {
                                                    this.previewUrl = null;
                                                    this.fileName = '';
                                                }
                                            },
                                            handlePaste(e) {
                                                const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
                                                if (!items) return;
                                                for (let item of items) {
                                                    if (item.type.indexOf('image') !== -1) {
                                                        const blob = item.getAsFile();
                                                        this.previewUrl = URL.createObjectURL(blob);
                                                        this.fileName = 'clipboard_image.png';
                                                        const container = new DataTransfer();
                                                        container.items.add(blob);
                                                        const fileInput = $el.querySelector('input[type=file]');
                                                        if (fileInput) {
                                                            fileInput.files = container.files;
                                                            fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                                                        }
                                                        break;
                                                    }
                                                }
                                            }
                                        }"
                                        @paste.window="if (activeUploadTaskId === {{ $task->id }}) handlePaste($event)"
                                        class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-emerald-300 bg-white p-3 text-center transition hover:border-emerald-500 dark:border-emerald-800 dark:bg-gray-900 shadow-2xs"
                                    >
                                        <input
                                            type="file"
                                            wire:model="uploadFile"
                                            @change="handleFileChange($event)"
                                            accept="image/png,image/jpeg,image/jpg,image/webp"
                                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0 z-10"
                                        />

                                        <template x-if="previewUrl">
                                            <div class="flex flex-col items-center gap-1.5">
                                                <img :src="previewUrl" class="h-20 w-20 rounded-lg object-cover ring-2 ring-emerald-500 shadow-sm" alt="معاينة" />
                                                <span x-text="fileName" class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 truncate max-w-[180px]"></span>
                                                <span class="text-[10px] text-gray-400">انقر أو اسحب صورة أخرى للاستبدال</span>
                                            </div>
                                        </template>

                                        <template x-if="!previewUrl">
                                            <div class="flex flex-col items-center gap-1 text-gray-500 dark:text-gray-400">
                                                <x-heroicon-o-photo class="h-7 w-7 text-emerald-500" />
                                                <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">انقر لاختيار الصورة أو اسحبها وأفلتها هنا</p>
                                                <p class="text-[10px] text-gray-400">PNG, JPG, WEBP حتى 10 ميجابايت (يدعم اللصق Ctrl+V)</p>
                                            </div>
                                        </template>

                                        <div wire:loading wire:target="uploadFile" class="absolute inset-0 flex flex-col items-center justify-center rounded-xl bg-white/90 backdrop-blur-xs dark:bg-gray-900/90 z-20">
                                            <x-filament::loading-indicator class="h-6 w-6 text-emerald-600" />
                                            <span class="mt-1 text-[11px] font-bold text-emerald-700 dark:text-emerald-300">جاري معالجة الصورة...</span>
                                        </div>
                                    </div>

                                    @error('uploadFile')
                                        <p class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- خيارات الحالة والملاحظات وموعد الإرسال --}}
                                <div class="space-y-2.5">
                                    {{-- الوجهة / الحالة الناتجة --}}
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1">
                                            مسار الاعتماد / الحالة الناتجة:
                                        </label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="flex items-center gap-2 rounded-lg border p-2 cursor-pointer transition text-xs font-semibold"
                                                :class="$wire.uploadTargetStatus === 'sending' ? 'border-emerald-500 bg-emerald-100/70 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200 ring-1 ring-emerald-500' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'">
                                                <input type="radio" wire:model.live="uploadTargetStatus" value="sending" class="sr-only" />
                                                <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                                <span>جاهز للإرسال (معتمد) 🚀</span>
                                            </label>

                                            <label class="flex items-center gap-2 rounded-lg border p-2 cursor-pointer transition text-xs font-semibold"
                                                :class="$wire.uploadTargetStatus === 'reviewing' ? 'border-blue-500 bg-blue-100/70 text-blue-900 dark:bg-blue-900/40 dark:text-blue-200 ring-1 ring-blue-500' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'">
                                                <input type="radio" wire:model.live="uploadTargetStatus" value="reviewing" class="sr-only" />
                                                <span class="flex h-2 w-2 rounded-full bg-blue-500"></span>
                                                <span>إرسال للمراجعة 🔍</span>
                                            </label>
                                        </div>
                                    </div>

                                    {{-- موعد الإرسال إذا كان جاهزاً للإرسال --}}
                                    <div x-show="$wire.uploadTargetStatus === 'sending'" x-transition>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1">
                                            موعد الإرسال المجدول (اختياري - افتراضياً تلقائي):
                                        </label>
                                        <input
                                            type="datetime-local"
                                            wire:model="uploadScheduledSendingAt"
                                            class="w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-2xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        />
                                    </div>

                                    {{-- ملاحظات --}}
                                    <div>
                                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-200 mb-1">
                                            ملاحظات (اختياري):
                                        </label>
                                        <textarea
                                            wire:model="uploadNotes"
                                            rows="2"
                                            placeholder="أضف أي ملاحظات حول التصميم المرفوع..."
                                            class="w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-2xs focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            {{-- شريط أزرار الحفظ والإلغاء --}}
                            <div class="flex items-center justify-end gap-2 border-t border-emerald-200/60 pt-2.5 dark:border-emerald-900/40">
                                <button
                                    type="button"
                                    @click="activeUploadTaskId = null"
                                    wire:click="cancelDirectUpload"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 shadow-2xs"
                                >
                                    إلغاء
                                </button>

                                <button
                                    type="button"
                                    wire:click="saveDirectUpload({{ $task->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="saveDirectUpload, uploadFile"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white shadow-2xs transition hover:bg-emerald-500 disabled:opacity-50"
                                >
                                    <x-heroicon-m-check class="h-4 w-4" wire:loading.remove wire:target="saveDirectUpload" />
                                    <x-filament::loading-indicator class="h-4 w-4" wire:loading wire:target="saveDirectUpload" />
                                    <span wire:loading.remove wire:target="saveDirectUpload">حفظ واعتماد التصميم</span>
                                    <span wire:loading wire:target="saveDirectUpload">جاري الحفظ والاعتماد...</span>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

</div>
