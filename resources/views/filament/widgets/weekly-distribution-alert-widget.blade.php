<x-filament-widgets::widget>
    @php
        $summary = $this->audit_summary;
        $unassigned = $this->unassigned_contracts;
        $missingTags = $this->clients_with_missing_tags;
        $designers = $this->available_designers;
    @endphp

    <div class="space-y-4" dir="rtl" wire:poll.60s>
        @if($summary['is_fully_distributed'])
            {{-- حالة الاكتمال (100% مكتمل) --}}
            <div class="relative overflow-hidden rounded-2xl border border-emerald-500/20 bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-transparent p-4 sm:p-5 dark:from-emerald-950/30 dark:via-emerald-900/10">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/30 dark:bg-emerald-500/20 dark:text-emerald-400">
                            <x-heroicon-m-check-badge class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    التوزيع الأسبوعي مكتمل بالكامل
                                </h3>
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                    100% جاهز
                                </span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">
                                جميع العملاء النشطين تم تعيينهم لمصممين وتوزيع تاقاتهم للأسبوع الحالي ({{ $summary['week_start'] }} إلى {{ $summary['week_end'] }}).
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="{{ \App\Filament\Pages\DesignerDistribution::getUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300/80 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-m-arrows-right-left class="h-3.5 w-3.5" />
                            توزيع المصممين
                        </a>
                        <a href="{{ \App\Filament\Pages\TagDistribution::getUrl() }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300/80 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <x-heroicon-m-tag class="h-3.5 w-3.5" />
                            توزيع التاقات
                        </a>
                    </div>
                </div>
            </div>
        @else
            {{-- حالة وجود نقص (Alert Card البارز) --}}
            <div class="relative overflow-hidden rounded-2xl border border-amber-500/30 bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-rose-500/10 p-5 shadow-sm dark:border-amber-500/20 dark:from-amber-950/40 dark:via-gray-900 dark:to-rose-950/20">
                {{-- Decorative background glow --}}
                <div class="pointer-events-none absolute -left-10 -top-10 h-32 w-32 rounded-full bg-amber-500/10 blur-2xl"></div>
                <div class="pointer-events-none absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-rose-500/10 blur-2xl"></div>

                <div class="relative z-10 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    {{-- Title & Context --}}
                    <div class="flex items-start gap-3.5">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-500/20 text-amber-600 shadow-inner ring-1 ring-amber-500/30 dark:bg-amber-500/20 dark:text-amber-400">
                            <x-heroicon-m-exclamation-triangle class="h-7 w-7 animate-pulse" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-extrabold text-gray-900 dark:text-white">
                                    تنبيه: التوزيع الأسبوعي بحاجة لمتابعة
                                </h3>
                                <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-900/60 dark:text-rose-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-ping"></span>
                                    {{ $summary['total_issues'] }} حالات معلقة
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                الأسبوع الحالي: <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ $summary['week_start'] }}</span> إلى <span class="font-mono font-medium text-gray-800 dark:text-gray-200">{{ $summary['week_end'] }}</span>.
                                هناك عملاء نشطون بحاجة لتعيين مصمم أو تاقات غير موزعة.
                            </p>
                        </div>
                    </div>

                    {{-- Badges / Action trigger --}}
                    <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
                        {{-- Unassigned counter chip --}}
                        <button type="button" wire:click="openAuditModal('unassigned')"
                            class="group flex items-center gap-2.5 rounded-xl border border-rose-200 bg-white/80 px-3.5 py-2 text-right shadow-sm backdrop-blur-sm transition-all hover:border-rose-300 hover:bg-rose-50/50 hover:shadow dark:border-rose-500/30 dark:bg-gray-800/80 dark:hover:bg-rose-950/30">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400">
                                <x-heroicon-m-user-minus class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400">نشطون بدون مصمم</div>
                                <div class="text-sm font-black text-rose-600 dark:text-rose-400">
                                    {{ $summary['unassigned_count'] }} عميل
                                </div>
                            </div>
                        </button>

                        {{-- Missing tags counter chip --}}
                        <button type="button" wire:click="openAuditModal('missing_tags')"
                            class="group flex items-center gap-2.5 rounded-xl border border-amber-200 bg-white/80 px-3.5 py-2 text-right shadow-sm backdrop-blur-sm transition-all hover:border-amber-300 hover:bg-amber-50/50 hover:shadow dark:border-amber-500/30 dark:bg-gray-800/80 dark:hover:bg-amber-950/30">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400">
                                <x-heroicon-m-tag class="h-4 w-4" />
                            </div>
                            <div>
                                <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400">تاقات غير مكتملة</div>
                                <div class="text-sm font-black text-amber-600 dark:text-amber-400">
                                    {{ $summary['missing_tags_count'] }} عميل
                                </div>
                            </div>
                        </button>

                        {{-- Primary Call to Action Button --}}
                        <button type="button" wire:click="openAuditModal"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary-500/25 transition-all hover:scale-[1.02] hover:shadow-xl hover:shadow-primary-500/30 active:scale-[0.98]">
                            <x-heroicon-m-sparkles class="h-4 w-4 text-primary-200" />
                            فحص ومعالجة التوزيع الآن
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- النافذة التفاعلية لمعالجة التوزيع الأسبوعي (Weekly Distribution Audit Modal) --}}
        {{-- ========================================================================= --}}
        <x-filament::modal id="weekly-distribution-audit-modal" width="5xl">
            <x-slot name="heading">
                <div class="flex items-center gap-2 text-lg font-black text-gray-900 dark:text-white">
                    <x-heroicon-m-wrench-screwdriver class="h-5 w-5 text-primary-500" />
                    <span>فحص ومعالجة التوزيع الأسبوعي</span>
                </div>
            </x-slot>

            <x-slot name="description">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    أسبوع: <strong class="text-gray-700 dark:text-gray-200">{{ $summary['week_start'] }}</strong> إلى <strong class="text-gray-700 dark:text-gray-200">{{ $summary['week_end'] }}</strong>
                </span>
            </x-slot>

            <div class="space-y-4 pt-2">
                {{-- Tabs Switcher --}}
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button type="button" wire:click="$set('activeModalTab', 'unassigned')"
                        class="flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-bold transition-colors {{ $activeModalTab === 'unassigned' ? 'border-rose-500 text-rose-600 dark:text-rose-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <x-heroicon-m-user-minus class="h-4 w-4" />
                        <span>عملاء بحاجة لمصمم</span>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-700 dark:bg-rose-900/50 dark:text-rose-300">
                            {{ $summary['unassigned_count'] }}
                        </span>
                    </button>

                    <button type="button" wire:click="$set('activeModalTab', 'missing_tags')"
                        class="flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-bold transition-colors {{ $activeModalTab === 'missing_tags' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <x-heroicon-m-tag class="h-4 w-4" />
                        <span>عملاء بحاجة لتوزيع التاقات</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-black text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                            {{ $summary['missing_tags_count'] }}
                        </span>
                    </button>
                </div>

                {{-- ==================== TAB 1: عملاء بحاجة لمصمم ==================== --}}
                @if($activeModalTab === 'unassigned')
                    <div class="space-y-3">
                        @if($unassigned->isNotEmpty())
                            {{-- Header actions bar --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-200/80 dark:bg-gray-900/60 dark:ring-gray-800">
                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                    يوجد <strong class="font-bold text-rose-600 dark:text-rose-400">{{ $unassigned->count() }}</strong> عميل نشط لم يتم تعيينهم لمصمم هذا الأسبوع.
                                </div>
                                <button type="button" wire:click="autoDistributeRemaining" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="autoDistributeRemaining">
                                        <x-heroicon-m-sparkles class="h-3.5 w-3.5 inline" />
                                    </span>
                                    <span wire:loading wire:target="autoDistributeRemaining">
                                        <svg class="h-3.5 w-3.5 animate-spin inline" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    </span>
                                    توزيع تلقائي ذكي للمتبقين
                                </button>
                            </div>

                            {{-- Table of Unassigned Contracts --}}
                            <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-800">
                                <table class="w-full text-right text-xs">
                                    <thead class="bg-gray-50/80 text-gray-600 dark:bg-gray-800/80 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3.5 py-2.5 font-bold">العميل / الشركة</th>
                                            <th class="px-3.5 py-2.5 font-bold">التصنيف</th>
                                            <th class="px-3.5 py-2.5 font-bold">التصاميم المطلوبة</th>
                                            <th class="px-3.5 py-2.5 font-bold">السبب / الحالة</th>
                                            <th class="px-3.5 py-2.5 font-bold">تعيين لمصمم</th>
                                            <th class="px-3.5 py-2.5 font-bold text-center">إجراء</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @foreach($unassigned as $item)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                                <td class="px-3.5 py-2.5 font-bold text-gray-900 dark:text-white">
                                                    {{ $item['client_name'] }}
                                                    <span class="block text-[10px] text-gray-400 font-normal">اشتراك #{{ $item['contract_id'] }} ({{ $item['billing_cycle'] }})</span>
                                                </td>
                                                <td class="px-3.5 py-2.5 text-gray-600 dark:text-gray-300">
                                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-medium dark:bg-gray-800">
                                                        {{ $item['category_name'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5">
                                                    <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:ring-blue-800">
                                                        {{ $item['weekly_designs_count'] }} تصميم
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5">
                                                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-800">
                                                        {{ $item['reason'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5 min-w-[200px]">
                                                    <select wire:model="designerSelections.{{ $item['contract_id'] }}"
                                                        class="block w-full rounded-lg border-gray-300 py-1 px-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                                        <option value="">-- اختر مصمماً --</option>
                                                        @foreach($designers as $designer)
                                                            <option value="{{ $designer['id'] }}">
                                                                {{ $designer['name'] }} (متاح: {{ $designer['available_capacity'] }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <button type="button" wire:click="assignContract({{ $item['contract_id'] }})"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:opacity-50">
                                                        <x-heroicon-m-check class="h-3.5 w-3.5" />
                                                        تعيين
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 mb-2">
                                    <x-heroicon-m-check-badge class="h-7 w-7" />
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">جميع العملاء النشطين تم تعيينهم لمصممين</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">لا يوجد أي عقد نشط معلق بدون مصمم لهذا الأسبوع.</p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ==================== TAB 2: عملاء بحاجة لتوزيع التاقات ==================== --}}
                @if($activeModalTab === 'missing_tags')
                    <div class="space-y-3">
                        @if($missingTags->isNotEmpty())
                            {{-- Header actions bar --}}
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 p-3 ring-1 ring-gray-200/80 dark:bg-gray-900/60 dark:ring-gray-800">
                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                    يوجد <strong class="font-bold text-amber-600 dark:text-amber-400">{{ $missingTags->count() }}</strong> عميل تم تعيينهم لمصمم ولكن لم توزع تاقاتهم بالكامل.
                                </div>
                                <button type="button" wire:click="distributeTagsForAll" wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="distributeTagsForAll">
                                        <x-heroicon-m-tag class="h-3.5 w-3.5 inline" />
                                    </span>
                                    <span wire:loading wire:target="distributeTagsForAll">
                                        <svg class="h-3.5 w-3.5 animate-spin inline" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                    </span>
                                    توزيع التاقات للجميع بنقرة واحدة
                                </button>
                            </div>

                            {{-- Table of Missing Tags --}}
                            <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-800">
                                <table class="w-full text-right text-xs">
                                    <thead class="bg-gray-50/80 text-gray-600 dark:bg-gray-800/80 dark:text-gray-300">
                                        <tr>
                                            <th class="px-3.5 py-2.5 font-bold">العميل</th>
                                            <th class="px-3.5 py-2.5 font-bold">المصمم المعين</th>
                                            <th class="px-3.5 py-2.5 font-bold text-center">المطلوب</th>
                                            <th class="px-3.5 py-2.5 font-bold text-center">الموزع حالياً</th>
                                            <th class="px-3.5 py-2.5 font-bold text-center">المتبقي</th>
                                            <th class="px-3.5 py-2.5 font-bold text-center">إجراء المعالجة</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                        @foreach($missingTags as $item)
                                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition">
                                                <td class="px-3.5 py-2.5 font-bold text-gray-900 dark:text-white">
                                                    {{ $item['client_name'] }}
                                                    <span class="block text-[10px] text-gray-400 font-normal">{{ $item['category_name'] }}</span>
                                                </td>
                                                <td class="px-3.5 py-2.5 font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ $item['designer_name'] }}
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                                        {{ $item['required_designs'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <span class="inline-flex items-center rounded-md {{ $item['distributed_tags'] > 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }} px-2 py-0.5 text-xs font-bold">
                                                        {{ $item['distributed_tags'] }}
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-black text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800">
                                                        {{ $item['missing_count'] }} تاق
                                                    </span>
                                                </td>
                                                <td class="px-3.5 py-2.5 text-center">
                                                    <button type="button" wire:click="distributeTagsForClient({{ $item['client_designer_id'] }})"
                                                        wire:loading.attr="disabled"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1 text-xs font-bold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-50">
                                                        <x-heroicon-m-sparkles class="h-3.5 w-3.5" />
                                                        توزيع تاقات العميل
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 p-8 text-center dark:border-gray-700">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 mb-2">
                                    <x-heroicon-m-check-badge class="h-7 w-7" />
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white">جميع العملاء الموزعين اكتملت تاقاتهم</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">تمت جدولة وتوزيع التاقات لجميع العملاء المعينين لهذا الأسبوع.</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot name="footer">
                <div class="flex flex-wrap items-center justify-between w-full gap-2 pt-2">
                    <div class="flex items-center gap-2">
                        <a href="{{ \App\Filament\Pages\DesignerDistribution::getUrl() }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">
                            فتح صفحة توزيع المصممين الكاملة &larr;
                        </a>
                        <span class="text-gray-300 dark:text-gray-700">|</span>
                        <a href="{{ \App\Filament\Pages\TagDistribution::getUrl() }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">
                            فتح صفحة جدول التاقات &larr;
                        </a>
                    </div>

                    <x-filament::button color="gray" wire:click="closeAuditModal">
                        إغلاق النافذة
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-widgets::widget>
