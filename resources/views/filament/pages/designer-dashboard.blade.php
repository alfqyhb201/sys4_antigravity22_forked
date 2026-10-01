<x-filament-panels::page>
    <div class="mx-1 max-w-[1600px] space-y-6 font-sans" dir="rtl" x-data="{ activeIdea: null, activeTab: 'active', activeDescription: null, activeOverdueModal: null, activeCliche: null }"
        wire:poll.30s.keep-alive>

        {{-- Dashboard Header --}}
        <div
            class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-purple via-brand-purple-medium to-brand-purple-deep text-white shadow-2xl ring-1 ring-white/10">
            {{-- Subtle texture overlay --}}
            <div class="pointer-events-none absolute inset-0 z-0 opacity-[0.04]"
                style="background-image: radial-gradient(circle at 25px 25px, white 1px, transparent 0); background-size: 50px 50px;">
            </div>

            <div class="relative z-10 space-y-3 p-5">
                {{-- Primary row: Greeting --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur-sm ring-1 ring-white/20">
                            <x-heroicon-m-paint-brush class="h-5 w-5 text-white" />
                        </div>
                        <p class="text-xl font-medium md:text-2xl">
                            أهلاً بك <span class="text-brand-orange-light font-bold">{{ $user->name }}</span>
                        </p>
                    </div>
                </div>

                {{-- Secondary row: Date filter (less prominent) --}}
                <div class="flex items-center justify-between border-t border-white/10 pt-3">
                    <span class="text-xs font-medium text-white/50">
                        @if($designer)
                        {{ $pendingDailyTasks->count() + $pendingDesignTasks->count() + $pendingTemplateTasks->count() }}
                        مهام
                        @endif
                    </span>
                    <div
                        class="flex min-w-[140px] items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5">
                        <x-heroicon-o-calendar-days class="h-4 w-4 text-white/40 shrink-0" />
                        <input type="date" wire:model.live="filterDate"
                            class="w-full cursor-pointer border-0 bg-transparent p-0 text-center font-mono text-sm font-medium tracking-wide text-white/80 placeholder-white/30 focus:ring-0 focus-visible:ring-2 focus-visible:ring-white/30 [&::-webkit-calendar-picker-indicator]:invert" />
                    </div>
                </div>
            </div>
        </div>

        @if(!$designer)
        <div
            class="flex flex-col items-center gap-6 rounded-xl border border-red-200 bg-red-50 p-6 text-center md:flex-row md:text-right dark:border-red-800 dark:bg-red-900/10">
            <div class="bg-red-100 dark:bg-red-900/30 p-4 rounded-full">
                <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-red-600 dark:text-red-400" />
            </div>
            <div>
                <h3 class="font-bold text-lg text-red-700 dark:text-red-400 mb-1">حساب غير مرتبط</h3>
                <p class="text-red-600/80 dark:text-red-400/70">يرجى التواصل مع الإدارة لربط حسابك بملف مصمم.</p>
            </div>
        </div>
        @else
        {{-- ============================================= --}}
        {{-- Overdue Alert Card — redesigned --}}
        {{-- ============================================= --}}
        @php
        $hasOverdue = $overdueCount > 0;
        @endphp
        <div @click="activeOverdueModal = true"
            class="group relative isolate max-w-sm cursor-pointer overflow-hidden rounded-2xl border-2 transition-all duration-500 ease-out hover:scale-[1.02] hover:shadow-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/60"
            :class="activeOverdueModal ? 'scale-[0.97] opacity-80' : ''"
            tabindex="0" role="button" aria-label="عرض المهام المتأخرة">

            {{-- Layered background — warm crimson-to-ember gradient --}}
            <div class="absolute inset-0 bg-gradient-to-br from-amber-900 via-red-800 to-rose-950"></div>

            {{-- Geometric mesh overlay — subtle diamond pattern --}}
            <div class="absolute inset-0 opacity-[0.07]"
                style="background-image:
                    linear-gradient(45deg, rgba(255,255,255,0.3) 25%, transparent 25%),
                    linear-gradient(-45deg, rgba(255,255,255,0.3) 25%, transparent 25%),
                    linear-gradient(45deg, transparent 75%, rgba(255,255,255,0.3) 75%),
                    linear-gradient(-45deg, transparent 75%, rgba(255,255,255,0.3) 75%);
                    background-size: 24px 24px;
                    background-position: 0 0, 0 12px, 12px -12px, -12px 0px;">
            </div>

            {{-- Edge glare --}}
            <div class="pointer-events-none absolute -inset-px rounded-2xl ring-1 ring-inset ring-white/10"></div>

            <div class="relative z-10 flex items-center gap-5 p-5">
                {{-- Icon vessel with pulse --}}
                <div class="relative flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 backdrop-blur-md ring-1 ring-white/20">
                    <x-heroicon-m-clock class="h-8 w-8 text-amber-300 transition-transform duration-700 group-hover:scale-110"
                        :class="$hasOverdue ? 'animate-pulse' : ''" style="animation-duration: 2.5s" />
                    {{-- Status dot --}}
                    <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full border-2 border-amber-900/50 {{ $hasOverdue ? 'bg-red-400 shadow-[0_0_8px_rgba(248,113,113,0.6)]' : 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]' }}"></span>
                </div>

                {{-- Text side --}}
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold tracking-widest text-amber-200/70 uppercase">متأخر</p>
                    <p class="text-4xl font-black tracking-tight text-white sm:text-5xl {{ $hasOverdue ? '' : 'opacity-60' }}">
                        {{ $overdueCount }}
                        <span class="mr-1 text-base font-bold text-white/60">مهام</span>
                    </p>
                    <div class="mt-1.5 flex items-center gap-2 text-xs text-amber-200/60 group-hover:text-amber-200/90 transition-colors">
                        <span>{{ $hasOverdue ? 'اضغط لعرض التفاصيل' : 'لا توجد مهام متأخرة' }}</span>
                        <x-heroicon-m-arrow-left class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-1" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab Navigation: Vertical Sidebar (Desktop) / Horizontal Tabs (Mobile) --}}
        <div class="flex flex-col gap-6 lg:flex-row">
            {{-- Sidebar --}}
            <div class="shrink-0 lg:w-56 lg:sticky lg:top-6 lg:self-start">
                <div
                    class="flex gap-1 rounded-xl border border-gray-200 bg-gray-100/50 p-1 dark:border-gray-800 dark:bg-gray-900/50 lg:flex-col lg:p-1.5">
                    <button @click="activeTab = 'active'"
                        class="flex items-center gap-2.5 rounded-lg px-4 py-2.5 text-sm font-bold transition-all lg:px-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40"
                        :class="activeTab === 'active' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <x-heroicon-m-clipboard-document-list class="hidden h-5 w-5 shrink-0 lg:block" />
                        المهام النشطة
                    </button>
                    <button @click="activeTab = 'changes'"
                        class="flex items-center gap-2.5 rounded-lg px-4 py-2.5 text-sm font-bold transition-all lg:px-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40"
                        :class="activeTab === 'changes' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <x-heroicon-m-arrow-path class="hidden h-5 w-5 shrink-0 lg:block" />
                        @if($changesCount > 0)
                        <span class="inline-flex items-center gap-1">
                            طلبات التعديل
                            <span
                                class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-600 dark:bg-red-900/30 dark:text-red-400">{{ $changesCount }}</span>
                        </span>
                        @else
                        طلبات التعديل
                        @endif
                    </button>
                    <button @click="activeTab = 'review'"
                        class="flex items-center gap-2.5 rounded-lg px-4 py-2.5 text-sm font-bold transition-all lg:px-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40"
                        :class="activeTab === 'review' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <x-heroicon-m-eye class="hidden h-5 w-5 shrink-0 lg:block" />
                        @if($reviewingCount > 0)
                        <span class="inline-flex items-center gap-1">
                            قيد المراجعة
                            <span
                                class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">{{ $reviewingCount }}</span>
                        </span>
                        @else
                        قيد المراجعة
                        @endif
                    </button>
                </div>
            </div>

            {{-- Content Area --}}
            <div class="min-w-0 flex-1">
                {{-- ============================================= --}}
                {{-- Tab 1: المهام النشطة --}}
                {{-- ============================================= --}}
                <div x-show="activeTab === 'active'" x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-cloak>
                    <div class="space-y-6">
                        {{-- Section: الطلبات --}}
                        @if($pendingOrders->isNotEmpty())
                        <div class="space-y-3">
                            <div class="flex items-center gap-2 text-orange-600 dark:text-orange-400">
                                <x-heroicon-m-shopping-cart class="w-5 h-5" />
                                <h2 class="text-lg font-bold">الطلبات</h2>
                                <!-- <span
                                    class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">{{ $pendingOrders->count() }}</span> -->
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($pendingOrders as $order)
                                <x-order-card wireKey="order-{{ $order->id }}" clientName="{{ $order->client_name }}"
                                    assignerName="{{ $order->assigner?->name ?? 'غير محدد' }}"
                                    :description="$order->description" :status="$order->status"
                                    actionWireClick="mountAction('submitOrderForReview', { order_id: {{ $order->id }} })"
                                    :actionDisabled="false" />
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Section: المهام --}}
                        @php
                        $hasActiveTasks = $pendingDailyTasks->isNotEmpty() || $pendingDesignTasks->isNotEmpty() || $pendingTemplateTasks->isNotEmpty();
                        @endphp

                        @if($hasActiveTasks)
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                <x-heroicon-m-clipboard-document-list class="w-5 h-5" />
                                <h2 class="text-lg font-bold">المهام</h2>
                                <!-- <span
                                    class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    {{ $pendingDailyTasks->count() + $pendingDesignTasks->count() + $pendingTemplateTasks->count() }}
                                </span> -->
                            </div>

                            @if($pendingDailyTasks->isEmpty() && $pendingDesignTasks->isEmpty() && $pendingTemplateTasks->isEmpty())
                            <div
                                class="flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white/70 py-12 text-center dark:border-gray-800 dark:bg-gray-900/30">
                                <div
                                    class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                    <x-heroicon-o-check-badge class="w-10 h-10 text-gray-400" />
                                </div>
                                <p class="text-gray-500 dark:text-gray-400">لا توجد مهام إضافية لليوم</p>
                            </div>
                            @else
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {{-- Design tasks (المهام الجانبية أولاً) --}}
                                @foreach($pendingDesignTasks as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="purple"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']"
                                    :priority="$task['priority_label']" :isExtra="$task['is_extra']"
                                    :extraAmount="$task['extra_amount']" :referenceFiles="$task['reference_files']"
                                    :designFiles="$task['design_files']">
                                    @if(!empty($task['cliche_data']))
                                    <x-slot:bodyExtra>
                                        <div class="px-4 pb-3">
                                            <button @click="activeCliche = @js($task['cliche_data'])"
                                                title="معاينة كليشة العميل ومسار الملف"
                                                class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 hover:bg-teal-100/80 px-3 py-2 text-xs font-bold text-teal-700 transition-all dark:border-teal-800/40 dark:bg-teal-950/30 dark:text-teal-300">
                                                <x-heroicon-m-swatch class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                <span>نموذج الكليشة المعتمد</span>
                                            </button>
                                        </div>
                                    </x-slot:bodyExtra>
                                    @endif
                                </x-task-card>
                                @endforeach

                                {{-- Daily tasks for today --}}
                                @foreach($pendingDailyTasks as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="purple"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']">
                                    <x-slot:bodyExtra>
                                        <div class="px-4 pb-3 flex items-center gap-2">
                                            <button @click="activeIdea = @js($task['idea_data'])"
                                                class="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-purple-100 bg-purple-50/50 hover:bg-purple-100/80 px-2.5 py-2 text-xs font-bold text-purple-700 transition-all dark:border-purple-900/30 dark:bg-purple-950/20 dark:text-purple-400">
                                                <x-heroicon-m-light-bulb class="h-4 w-4 text-amber-500 animate-pulse" />
                                                <span>عرض تفاصيل الفكرة</span>
                                            </button>

                                            @if(!empty($task['cliche_data']))
                                            <button @click="activeCliche = @js($task['cliche_data'])"
                                                title="معاينة كليشة العميل ومسار الملف"
                                                class="flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 hover:bg-teal-100/80 px-3 py-2 text-xs font-bold text-teal-700 transition-all dark:border-teal-800/40 dark:bg-teal-950/30 dark:text-teal-300">
                                                <x-heroicon-m-swatch class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                <span>الكليشة</span>
                                            </button>
                                            @endif
                                        </div>
                                    </x-slot:bodyExtra>
                                </x-task-card>
                                @endforeach

                                {{-- Template tasks --}}
                                @foreach($pendingTemplateTasks as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="teal"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}"
                                    :actionDisabled="$task['action_disabled']"
                                    templateTypeLabel="{{ $task['template_type_label'] }}" />
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endif

                        {{-- Empty state for active tab --}}
                        @if($pendingOrders->isEmpty() && !$hasActiveTasks)
                        <div
                            class="flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white/70 py-20 text-center dark:border-gray-800 dark:bg-gray-900/30">
                            <div
                                class="mb-6 flex h-32 w-32 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                <x-heroicon-o-check-badge class="w-16 h-16 text-gray-400" />
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">لا توجد مهام اليوم!</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">يبدو أن جدول اليوم مكتمل.</p>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ============================================= --}}
                {{-- Tab 2: طلبات التعديل --}}
                {{-- ============================================= --}}
                <div x-show="activeTab === 'changes'" x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-cloak>
                    @php
                    $hasChanges = $changesRequestedDaily->isNotEmpty() || $needsRevisionDesignTasks->isNotEmpty() || $needsRevisionTemplateTasks->isNotEmpty();
                    @endphp

                    @if(!$hasChanges)
                    <div
                        class="flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white/70 py-20 text-center dark:border-gray-800 dark:bg-gray-900/30">
                        <div
                            class="mb-6 flex h-32 w-32 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <x-heroicon-o-check-badge class="w-16 h-16 text-gray-400" />
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">لا توجد طلبات تعديل</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">كل المهام معتمدة، ممتاز!</p>
                    </div>
                    @else
                    <div class="space-y-6">
                        {{-- Design tasks with needs_revision (مهام التصميم / الجانبية أولاً) --}}
                        @if($needsRevisionDesignTasks->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-orange-600 dark:text-orange-400">
                                <x-heroicon-m-clipboard-document-list class="w-5 h-5" />
                                <h2 class="text-lg font-bold">مهام تصميم</h2>
                                <span
                                    class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">{{ $needsRevisionDesignTasks->count() }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($needsRevisionDesignTasks as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="purple"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']"
                                    :priority="$task['priority_label']" :isExtra="$task['is_extra']"
                                    :extraAmount="$task['extra_amount']" :revisionNotes="$task['revision_notes']"
                                    :revisionAttachments="$task['revision_attachments'] ?? []"
                                    :referenceFiles="$task['reference_files']">
                                    @if(!empty($task['cliche_data']))
                                    <x-slot:bodyExtra>
                                        <div class="px-4 pb-3">
                                            <button @click="activeCliche = @js($task['cliche_data'])"
                                                title="معاينة كليشة العميل ومسار الملف"
                                                class="w-full flex items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 hover:bg-teal-100/80 px-3 py-2 text-xs font-bold text-teal-700 transition-all dark:border-teal-800/40 dark:bg-teal-950/30 dark:text-teal-300">
                                                <x-heroicon-m-swatch class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                <span>نموذج الكليشة المعتمد</span>
                                            </button>
                                        </div>
                                    </x-slot:bodyExtra>
                                    @endif
                                </x-task-card>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Daily tasks with changes --}}
                        @if($changesRequestedDaily->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-red-600 dark:text-red-400">
                                <x-heroicon-m-exclamation-circle class="w-5 h-5" />
                                <h2 class="text-lg font-bold">مهام يومية</h2>
                                <span
                                    class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/30 dark:text-red-300">{{ $changesRequestedDaily->count() }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($changesRequestedDaily as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="purple"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']"
                                    :revisionNotes="$task['reviewer_feedback']"
                                    :revisionAttachments="$task['revision_attachments'] ?? []">
                                    <x-slot:bodyExtra>
                                        <div class="px-4 pb-3 space-y-3">
                                            <div class="flex items-center gap-2">
                                                <button @click="activeIdea = @js($task['idea_data'])"
                                                    class="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-purple-100 bg-purple-50/50 hover:bg-purple-100/80 px-2.5 py-2 text-xs font-bold text-purple-700 transition-all dark:border-purple-900/30 dark:bg-purple-950/20 dark:text-purple-400">
                                                    <x-heroicon-m-light-bulb class="h-4 w-4 text-amber-500 animate-pulse" />
                                                    <span>عرض تفاصيل الفكرة</span>
                                                </button>

                                                @if(!empty($task['cliche_data']))
                                                <button @click="activeCliche = @js($task['cliche_data'])"
                                                    title="معاينة كليشة العميل ومسار الملف"
                                                    class="flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 hover:bg-teal-100/80 px-3 py-2 text-xs font-bold text-teal-700 transition-all dark:border-teal-800/40 dark:bg-teal-950/30 dark:text-teal-300">
                                                    <x-heroicon-m-swatch class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                    <span>الكليشة</span>
                                                </button>
                                                @endif
                                            </div>

                                            @if($task['attachment_path'])
                                            <div>
                                                <label class="block text-[10px] font-extrabold text-gray-400 dark:text-gray-550 mb-1.5 uppercase tracking-wider">التصميم المسلّم</label>
                                                <div class="group/image relative aspect-video overflow-hidden rounded-xl border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-black/50">
                                                    <img src="{{ Storage::url($task['attachment_path']) }}" alt="التصميم المسلّم للمراجعة"
                                                        class="h-full w-full object-cover transition-transform duration-300 group-hover/image:scale-[1.03]" />
                                                    <a href="{{ Storage::url($task['attachment_path']) }}" target="_blank"
                                                        class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/image:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                                                        <x-heroicon-o-eye class="w-8 h-8 text-white" />
                                                    </a>
                                                </div>
                                            </div>
                                            @if($task['designer_notes'])
                                            <p class="mt-2 block rounded-lg bg-gray-50/80 p-2 text-xs text-gray-500 border border-gray-100 dark:bg-gray-800 dark:border-gray-800/80 dark:text-gray-400">
                                                <span class="font-bold">ملاحظاتك:</span> {{ $task['designer_notes'] }}
                                            </p>
                                            @endif
                                            @endif
                                        </div>
                                    </x-slot:bodyExtra>
                                </x-task-card>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Template tasks with needs_revision --}}
                        @if($needsRevisionTemplateTasks->isNotEmpty())
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 text-teal-600 dark:text-teal-400">
                                <x-heroicon-m-swatch class="w-5 h-5" />
                                <h2 class="text-lg font-bold">تحديث القوالب</h2>
                                <span
                                    class="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-bold text-teal-700 dark:bg-teal-900/30 dark:text-teal-300">{{ $needsRevisionTemplateTasks->count() }}</span>
                            </div>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($needsRevisionTemplateTasks as $task)
                                <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                    taskType="{{ $task['task_type'] }}" avatarTheme="teal"
                                    avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                    subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                    descriptionLabel="{{ $task['description_label'] }}"
                                    actionWireClick="{{ $task['action_wire_click'] }}"
                                    actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                    actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']"
                                    :revisionNotes="$task['revision_notes']"
                                    :revisionAttachments="$task['revision_attachments'] ?? []"
                                    templateTypeLabel="{{ $task['template_type_label'] }}" />
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- ============================================= --}}
                {{-- Tab 3: قيد المراجعة --}}
                {{-- ============================================= --}}
                <div x-show="activeTab === 'review'" x-transition:enter="ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-cloak>
                    @if($reviewingDailyTasks->isEmpty())
                    <div
                        class="flex flex-col items-center justify-center rounded-2xl border border-gray-200 bg-white/70 py-20 text-center dark:border-gray-800 dark:bg-gray-900/30">
                        <div
                            class="mb-6 flex h-32 w-32 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <x-heroicon-o-check-badge class="w-16 h-16 text-gray-400" />
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">لا توجد مهام قيد المراجعة</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">كل ما سلمته تمت مراجعته.</p>
                    </div>
                    @else
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400">
                            <x-heroicon-m-eye class="w-5 h-5" />
                            <h2 class="text-lg font-bold">قيد المراجعة</h2>
                            <span
                                class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ $reviewingDailyTasks->count() }}</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($reviewingDailyTasks as $task)
                            <x-task-card wireKey="{{ $task['wire_key'] }}" status="{{ $task['status'] }}"
                                taskType="{{ $task['task_type'] }}" avatarTheme="purple"
                                avatarLetter="{{ $task['avatar_letter'] }}" :avatarUrl="$task['avatar_url']" title="{{ $task['title'] }}"
                                subtitle="{{ $task['subtitle'] }}" :description="$task['description']"
                                descriptionLabel="{{ $task['description_label'] }}"
                                actionWireClick="{{ $task['action_wire_click'] }}"
                                actionLabel="{{ $task['action_label'] }}" actionTheme="{{ $task['action_theme'] }}"
                                actionIcon="{{ $task['action_icon'] }}" :actionDisabled="$task['action_disabled']">
                                <x-slot:bodyExtra>
                                    <div class="px-4 pb-3 space-y-2">
                                        <div class="flex items-center gap-2">
                                            <button @click="activeIdea = @js($task['idea_data'])"
                                                class="flex-1 flex items-center justify-center gap-1.5 rounded-lg border border-purple-100 bg-purple-50/50 hover:bg-purple-100/80 px-2.5 py-2 text-xs font-bold text-purple-700 transition-all dark:border-purple-900/30 dark:bg-purple-950/20 dark:text-purple-400">
                                                <x-heroicon-m-light-bulb class="h-4 w-4 text-amber-500 animate-pulse" />
                                                <span>عرض تفاصيل الفكرة</span>
                                            </button>

                                            @if(!empty($task['cliche_data']))
                                            <button @click="activeCliche = @js($task['cliche_data'])"
                                                title="معاينة كليشة العميل ومسار الملف"
                                                class="flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 hover:bg-teal-100/80 px-3 py-2 text-xs font-bold text-teal-700 transition-all dark:border-teal-800/40 dark:bg-teal-950/30 dark:text-teal-300">
                                                <x-heroicon-m-swatch class="h-4 w-4 text-teal-600 dark:text-teal-400" />
                                                <span>الكليشة</span>
                                            </button>
                                            @endif
                                        </div>

                                        @if($task['attachment_path'])
                                        <div class="mt-3">
                                            <label class="block text-[10px] font-extrabold text-gray-400 dark:text-gray-550 mb-1.5 uppercase tracking-wider">التصميم المسلّم</label>
                                            <div class="group/image relative aspect-video overflow-hidden rounded-xl border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-black/50">
                                                <img src="{{ Storage::url($task['attachment_path']) }}" alt="التصميم المسلّم للمراجعة"
                                                    class="h-full w-full object-cover transition-transform duration-300 group-hover/image:scale-[1.03]" />
                                                <a href="{{ Storage::url($task['attachment_path']) }}" target="_blank"
                                                    class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/image:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
                                                    <x-heroicon-o-eye class="w-8 h-8 text-white" />
                                                </a>
                                            </div>
                                        </div>
                                        @if($task['designer_notes'])
                                        <p class="mt-2 block rounded-lg bg-gray-50/80 p-2 text-xs text-gray-500 border border-gray-100 dark:bg-gray-800 dark:border-gray-800/80 dark:text-gray-400">
                                            <span class="font-bold">ملاحظاتك:</span> {{ $task['designer_notes'] }}
                                        </p>
                                        @endif
                                        @endif
                                    </div>
                                </x-slot:bodyExtra>
                            </x-task-card>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Idea Modal --}}
        <div x-show="activeIdea" style="display: none;" wire:ignore
            class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="activeIdea = null">
            </div>
            <div class="relative w-full overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
                :class="activeIdea?.is_image ? 'max-w-5xl' : 'max-w-lg'">
                <div
                    class="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-6 py-5 dark:border-gray-800 dark:bg-gray-800/50">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-light-bulb class="w-5 h-5 text-brand-purple" />
                        <span x-text="activeIdea?.name"></span>
                    </h3>
                    <button @click="activeIdea = null"
                        class="text-gray-400 hover:text-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40 dark:hover:text-gray-300 rounded-lg"
                        aria-label="إغلاق">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 max-h-[85vh] overflow-y-auto">
                    <div :class="activeIdea?.is_image ? 'grid grid-cols-1 md:grid-cols-2 gap-8' : ''">

                        <div class="space-y-6">
                            <template x-if="activeIdea?.is_custom">
                                <div
                                    class="p-3 bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 text-sm rounded-lg border border-orange-100 dark:border-orange-900/30 flex items-center gap-2">
                                    <x-heroicon-m-information-circle class="w-5 h-5" />
                                    هذه فكرة مخصصة للعميل.
                                </div>
                            </template>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">المحتوى
                                    المقترح</label>
                                <div class="text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-[var(--surface-dim)] p-4 rounded-xl border border-gray-100 dark:border-gray-800 text-sm leading-relaxed whitespace-pre-wrap"
                                    x-text="activeIdea?.content || 'لا يوجد محتوى نصي.'"></div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-2 uppercase">الوصف /
                                    التوجيهات</label>
                                <div class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed whitespace-pre-wrap"
                                    x-text="activeIdea?.description || 'لا توجد توجيهات.'"></div>
                            </div>

                            <template x-if="activeIdea?.idea_file_url && !activeIdea?.is_image">
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                                    <label
                                        class="mb-2 flex items-center gap-1 text-xs font-bold text-blue-600 dark:text-blue-400 uppercase">
                                        <x-heroicon-m-paper-clip class="w-4 h-4" />
                                        مرفق الفكرة
                                    </label>
                                    <a :href="activeIdea?.idea_file_url" target="_blank"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 dark:bg-blue-900/20 dark:text-blue-400 dark:hover:bg-blue-900/40 transition-colors font-medium text-sm border border-blue-200 dark:border-blue-800/50">
                                        <x-heroicon-m-arrow-top-right-on-square class="w-5 h-5" />
                                        عرض الملف المرفق
                                    </a>
                                </div>
                            </template>

                            <template x-if="activeIdea?.client_notes">
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                                    <label
                                        class="mb-2 flex items-center gap-1 text-xs font-bold text-purple-600 dark:text-purple-400 uppercase">
                                        <x-heroicon-m-pencil-square class="w-4 h-4" />
                                        ملاحظات العميل الثابتة
                                    </label>
                                    <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 bg-purple-50 dark:bg-purple-900/10 p-4 rounded-xl border border-purple-100 dark:border-purple-900/30 leading-relaxed"
                                        x-html="activeIdea?.client_notes"></div>
                                </div>
                            </template>
                        </div>

                        <div x-show="activeIdea?.is_image" style="display: none;"
                            class="relative rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 flex items-center justify-center min-h-[300px]">
                            <img :src="activeIdea?.idea_file_url"
                                class="max-w-full max-h-[75vh] object-contain rounded-xl shadow-sm" alt="مرفق الفكرة" />
                            <a :href="activeIdea?.idea_file_url" target="_blank"
                                class="absolute bottom-4 right-4 bg-white/90 dark:bg-black/80 backdrop-blur-md p-2.5 rounded-xl shadow-lg hover:scale-105 hover:bg-white dark:hover:bg-black transition-all ring-1 ring-gray-200 dark:ring-gray-700"
                                title="فتح الصورة">
                                <x-heroicon-m-arrows-pointing-out class="w-5 h-5 text-gray-700 dark:text-gray-300" />
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Description Modal --}}
        <div x-show="activeDescription" style="display: none;" wire:ignore
            class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="activeDescription = null">
            </div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-xl transition-all duration-300 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-6 py-5 dark:border-gray-800 dark:bg-gray-800/50">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-document-text class="w-5 h-5 text-brand-purple dark:text-brand-purple-light" />
                        <span x-text="activeDescription?.title || 'تفاصيل المهمة'"></span>
                    </h3>
                    <button @click="activeDescription = null"
                        class="text-gray-400 hover:text-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple/40 dark:hover:text-gray-300 rounded-lg"
                        aria-label="إغلاق">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 max-h-[80vh] overflow-y-auto space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2 uppercase" x-text="activeDescription?.label || 'تفاصيل الملاحظة'"></label>
                        <div class="text-gray-850 dark:text-gray-200 bg-gray-50 dark:bg-[var(--surface-dim)] p-4 rounded-xl border border-gray-100 dark:border-gray-800 text-sm leading-relaxed whitespace-pre-wrap"
                            x-text="activeDescription?.content"></div>
                    </div>

                    {{-- Attached Images / Revision Attachments in Modal --}}
                    <template x-if="activeDescription?.attachments && activeDescription.attachments.length > 0">
                        <div class="space-y-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                            <label class="block text-xs font-bold text-red-600 dark:text-red-400 uppercase">
                                🖼️ صور ومرفقات التعديل (<span x-text="activeDescription.attachments.length"></span>)
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                <template x-for="(imgUrl, idx) in activeDescription.attachments" :key="idx">
                                    <a :href="imgUrl" target="_blank"
                                        class="group/attach relative aspect-square overflow-hidden rounded-xl border border-red-200 bg-gray-50 dark:border-red-900/40 dark:bg-gray-800 hover:ring-2 hover:ring-red-400 transition-all">
                                        <img :src="imgUrl" alt="صورة التعديل" class="h-full w-full object-cover transition-transform duration-300 group-hover/attach:scale-105" loading="lazy" />
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover/attach:opacity-100">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-5 h-5 text-white" />
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ============================================= --}}
        {{-- Overdue Tasks Modal — redesigned --}}
        {{-- ============================================= --}}
        <div x-show="activeOverdueModal" style="display: none;" wire:ignore
            class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/70 to-slate-900/80 backdrop-blur-sm transition-opacity" @click="activeOverdueModal = null"></div>

            {{-- Panel --}}
            <div class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-950 shadow-[0_0_60px_rgba(239,68,68,0.15)] transition-all duration-300">
                {{-- Top ember glow --}}
                <div class="pointer-events-none absolute -top-24 left-1/2 h-48 w-3/4 -translate-x-1/2 rounded-full bg-red-500/10 blur-[80px]"></div>

                {{-- Header --}}
                <div class="relative flex items-center justify-between border-b border-white/5 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-red-500/15 ring-1 ring-red-500/20">
                            <x-heroicon-m-clock class="h-5 w-5 text-red-400" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">المهام المتأخرة</h3>
                            @if($overdueCount > 0)
                            <p class="text-xs text-red-400/70">{{ $overdueCount }} {{ $overdueCount == 1 ? 'مهمة' : 'مهام' }} لم ترسل بعد</p>
                            @endif
                        </div>
                    </div>
                    <button @click="activeOverdueModal = null"
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/5 text-white/40 transition-all hover:bg-white/10 hover:text-white/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/50"
                        aria-label="إغلاق">
                        <x-heroicon-m-x-mark class="h-4 w-4" />
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-6 max-h-[75vh] overflow-y-auto">
                    @forelse($overdueGrouped as $group)
                    <div class="group/date mb-8 last:mb-0">
                        {{-- Date header with timeline line --}}
                        <div class="sticky -top-3 z-10 mb-4 flex items-center gap-3 rounded-xl bg-slate-900/90 backdrop-blur-md px-3 py-2.5 border border-white/5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/15 ring-1 ring-amber-500/20">
                                <x-heroicon-m-calendar-days class="h-4 w-4 text-amber-400" />
                            </div>
                            <h4 class="text-sm font-bold text-amber-200/90">{{ $group['formatted_date'] }}</h4>
                            <span class="mr-auto rounded-full bg-red-500/15 px-2.5 py-0.5 text-[11px] font-bold text-red-400/90">{{ count($group['tasks']) }} {{ count($group['tasks']) == 1 ? 'مهمة' : 'مهام' }}</span>
                        </div>

                        {{-- Tasks list — timeline style --}}
                        <div class="space-y-2 pr-4">
                            @foreach($group['tasks'] as $task)
                            <div class="relative flex items-start gap-3 rounded-xl border border-white/5 bg-white/[0.03] p-3.5 transition-all duration-200 hover:bg-white/[0.06] hover:border-white/10">
                                {{-- Type badge --}}
                                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-black tracking-wider {{ $task['type'] === 'daily' ? 'bg-violet-500/15 text-violet-300 ring-1 ring-violet-500/20' : ($task['type'] === 'template' ? 'bg-teal-500/15 text-teal-300 ring-1 ring-teal-500/20' : 'bg-amber-500/15 text-amber-300 ring-1 ring-amber-500/20') }}">
                                    {{ $task['type'] === 'daily' ? 'ي' : ($task['type'] === 'template' ? 'ق' : 'ت') }}
                                </div>

                                {{-- Content --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-bold text-white/90">{{ $task['client_name'] }}</p>
                                        @if($task['tag_name'])
                                        <span class="text-xs text-white/30">•</span>
                                        <span class="text-xs font-medium text-white/40">{{ $task['tag_name'] }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-white/5 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white/40">{{ $task['task_type_label'] }}</span>
                                        @if($task['description'])
                                        <span class="text-xs text-white/30 truncate max-w-[220px]">{{ $task['description'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Arrow hint --}}
                                <x-heroicon-m-chevron-left class="mt-1 h-4 w-4 shrink-0 text-white/10 transition-all group-hover/date:translate-x-0.5 group-hover/date:text-white/30" />
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    {{-- Empty state with celebration --}}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="relative mb-6">
                            <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-b from-emerald-500/20 to-emerald-500/5 ring-1 ring-emerald-500/20">
                                <x-heroicon-o-check-badge class="h-12 w-12 text-emerald-400" />
                            </div>
                            <div class="absolute -inset-2 rounded-full bg-emerald-500/5 blur-xl"></div>
                        </div>
                        <h4 class="text-xl font-bold text-white mb-2">كل المهام منجزة في وقتها!</h4>
                        <p class="text-sm text-white/40">لا توجد أي مهام متأخرة، عمل رائع 👏</p>
                    </div>
                    @endforelse

                    {{-- Tip footer --}}
                    @if(!empty($overdueGrouped))
                    <div class="mt-6 rounded-2xl border border-amber-500/10 bg-gradient-to-r from-amber-500/5 to-transparent p-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/10">
                                <x-heroicon-m-light-bulb class="h-4 w-4 text-amber-400" />
                            </div>
                            <div>
                                <p class="text-xs font-bold text-amber-300/80 mb-0.5">💡 نصيحة سريعة</p>
                                <p class="text-xs text-amber-200/50 leading-relaxed">
                                    استخدم <strong class="text-amber-300/70">التقويم</strong> في أعلى الصفحة لاختيار التاريخ وعرض المهام الخاصة بذلك اليوم.
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================= --}}
        {{-- Cliche Preview Modal --}}
        {{-- ============================================= --}}
        <div x-show="activeCliche" style="display: none;" wire:ignore
            class="fixed inset-0 z-50 flex items-center justify-center px-4 sm:px-6"
            x-data="{ copiedPath: false }"
            x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity" @click="activeCliche = null">
            </div>

            {{-- Panel --}}
            <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl transition-all duration-300 dark:border-gray-800 dark:bg-gray-900">
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-6 py-4 dark:border-gray-800 dark:bg-gray-800/50">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                            <x-heroicon-m-swatch class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                <span>نموذج الكليشة المعتمد</span>
                                <span class="text-gray-400 dark:text-gray-500 font-normal text-sm"> — </span>
                                <span class="text-teal-600 dark:text-teal-400" x-text="activeCliche?.client_name"></span>
                            </h3>
                            <template x-if="activeCliche?.updated_at">
                                <p class="text-[11px] text-gray-400 dark:text-gray-500">
                                    آخر تحديث: <span x-text="activeCliche?.updated_at"></span>
                                </p>
                            </template>
                        </div>
                    </div>
                    <button @click="activeCliche = null"
                        class="text-gray-400 hover:text-gray-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-400/40 dark:hover:text-gray-300 rounded-lg p-1"
                        aria-label="إغلاق">
                        <x-heroicon-m-x-mark class="w-6 h-6" />
                    </button>
                </div>

                {{-- Content Body --}}
                <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                    {{-- Image Container --}}
                    <div class="relative flex min-h-[220px] max-h-[50vh] w-full items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-950/5 p-2 shadow-inner dark:border-gray-800 dark:bg-gray-950/40">
                        <template x-if="activeCliche?.file_url">
                            <img :src="activeCliche?.file_url"
                                :alt="'كليشة ' + activeCliche?.client_name"
                                class="max-h-[45vh] max-w-full rounded-lg object-contain transition-transform duration-300 hover:scale-[1.02]"
                                loading="lazy" />
                        </template>
                    </div>

                    {{-- Local Path Section (If Available) --}}
                    <template x-if="activeCliche?.local_path">
                        <div class="flex items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-gray-800/40">
                            <div class="flex items-center gap-2 overflow-hidden text-xs text-gray-700 dark:text-gray-300">
                                <x-heroicon-m-folder class="h-4 w-4 shrink-0 text-amber-500" />
                                <span class="shrink-0 font-bold">المسار المحلي:</span>
                                <code class="truncate rounded bg-white px-2 py-0.5 font-mono text-[11px] text-gray-800 shadow-sm dark:bg-gray-900 dark:text-gray-200"
                                    x-text="activeCliche?.local_path"></code>
                            </div>
                            <button
                                type="button"
                                @click="
                                    navigator.clipboard.writeText(activeCliche?.local_path || '');
                                    copiedPath = true;
                                    setTimeout(() => copiedPath = false, 2000);
                                "
                                class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                <template x-if="!copiedPath">
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" />
                                        <span>نسخ المسار</span>
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

                    {{-- Actions Footer --}}
                    <div class="flex items-center justify-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <template x-if="activeCliche?.file_url">
                            <a :href="activeCliche?.file_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" />
                                <span>فتح بالحجم الكامل</span>
                            </a>
                        </template>

                        <template x-if="activeCliche?.file_url">
                            <a :href="activeCliche?.file_url"
                                :download="activeCliche?.client_name + '-كليشة'"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-500 focus:outline-none dark:bg-teal-500 dark:hover:bg-teal-400">
                                <x-heroicon-m-arrow-down-tray class="h-4 w-4" />
                                <span>تحميل الكليشة</span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>