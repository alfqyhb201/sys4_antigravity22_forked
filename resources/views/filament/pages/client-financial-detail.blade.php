<x-filament-panels::page>
    @php
    $currency = \App\Models\Currency::getBase()?->symbol
    ?? \App\Models\Currency::getBase()?->currency
    ?? $client->profile_currency_symbol
    ?? 'ر.س';
    $balance = $client->outstanding_balance ?? 0;
    $activityStatus = $client->activity_status;

    $statusLabel = match ($activityStatus) {
    'active' => 'نشط',
    'pending_arrears' => 'نشط (متأخرات)',
    'auto_suspended' => 'موقف تلقائياً',
    'manually_suspended' => 'موقف يدوياً',
    'suspended' => 'موقف',
    'expired' => 'منتهي',
    default => $activityStatus,
    };

    $statusColor = match ($activityStatus) {
    'active' => 'success',
    'pending_arrears' => 'warning',
    'auto_suspended' => 'danger',
    'manually_suspended' => 'gray',
    'suspended' => 'danger',
    'expired' => 'warning',
    default => 'gray',
    };

    $summaryCards = [
    [
    'label' => 'إجمالي الفواتير',
    'value' => number_format($client->invoiced_amount ?? 0),
    'meta' => 'عدد الفواتير: '.($client->invoices_count ?? 0),
    'icon' => 'heroicon-o-document-text',
    'tone' => 'primary',
    'bg' => 'bg-primary-50 dark:bg-primary-950/30',
    'text' => 'text-primary-700 dark:text-primary-300',
    ],
    [
    'label' => 'إجمالي المدفوع',
    'value' => number_format($client->paid_amount ?? 0),
    'meta' => 'سندات المقبوضات المعتمدة',
    'icon' => 'heroicon-o-banknotes',
    'tone' => 'success',
    'bg' => 'bg-success-50 dark:bg-success-950/30',
    'text' => 'text-success-700 dark:text-success-300',
    ],
    [
    'label' => 'الرصيد المتبقي',
    'value' => number_format($balance),
    'meta' => $balance > 0 ? 'مستحق السداد فوراً' : 'مسدد بالكامل',
    'icon' => 'heroicon-o-credit-card',
    'tone' => $balance > 0 ? 'danger' : 'success',
    'bg' => $balance > 0 ? 'bg-danger-50 dark:bg-danger-950/30' : 'bg-success-50 dark:bg-success-950/30',
    'text' => $balance > 0 ? 'text-danger-700 dark:text-danger-300' : 'text-success-700 dark:text-success-300',
    ],
    [
    'label' => 'التصاميم الإضافية',
    'value' => (string) ($client->additional_designs_balance ?? 0),
    'meta' => 'رصيد مسبق الدفع متاح',
    'icon' => 'heroicon-o-paint-brush',
    'tone' => 'warning',
    'bg' => 'bg-warning-50 dark:bg-warning-950/30',
    'text' => 'text-warning-700 dark:text-warning-300',
    ],
    ];
    @endphp

    <div x-data="{
        tab: 'overview',
    }" class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- بطاقة العميل الجانبية --}}
        <div class="w-full shrink-0 lg:w-80">
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {{-- رأس البطاقة بالشعار --}}
                <div class="relative bg-gradient-to-br from-primary-500 to-primary-700 p-6 text-center dark:from-primary-600 dark:to-primary-900">
                    {{-- صورة الشعار --}}
                    @if ($client->logo_path)
                    <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-2xl border-4 border-white/60 bg-white shadow-lg">
                        <img src="{{ asset('storage/' . $client->logo_path) }}" alt="{{ $client->company }}"
                            class="h-full w-full object-contain p-1">
                    </div>
                    @else
                    <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-2xl border-4 border-white/60 bg-white/20 text-3xl font-bold text-white shadow-lg">
                        {{ mb_substr($client->company, 0, 1, 'utf-8') }}
                    </div>
                    @endif

                    {{-- اسم الشركة --}}
                    <h2 class="mt-4 text-lg font-bold text-white">{{ $client->company }}</h2>

                    {{-- حالة النشاط --}}
                    <div class="mt-2">
                        <x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge>
                    </div>

                    @if ($client->isUnderLawsuit())
                    <div class="mx-auto mt-3 flex max-w-max items-center gap-2 rounded-xl bg-danger-500/30 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur-sm">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4" />
                        قيد المقاضاة
                    </div>
                    @endif
                </div>

                {{-- تفاصيل العميل --}}
                <div class="space-y-4 p-5">
                    {{-- اسم المسؤول --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/30 dark:text-primary-300">
                            <x-filament::icon icon="heroicon-o-user" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">المسؤول</p>
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $client->client_name ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- رقم الاتصال --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-success-50 text-success-600 dark:bg-success-950/30 dark:text-success-300">
                            <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">رقم الاتصال</p>
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" dir="ltr">{{ $client->contact_number ? '+967 '.$client->contact_number : '—' }}</p>
                        </div>
                    </div>

                    {{-- العنوان --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning-50 text-warning-600 dark:bg-warning-950/30 dark:text-warning-300">
                            <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">العنوان</p>
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $client->address ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- الموقع --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-info-50 text-info-600 dark:bg-info-950/30 dark:text-info-300">
                            <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">الموقع</p>
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $client->location?->name ?? '—' }}</p>
                        </div>
                    </div>

                    {{-- التصنيف --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-950/30 dark:text-fuchsia-300">
                            <x-filament::icon icon="heroicon-o-tag" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">التصنيف</p>
                            <div class="mt-0.5">
                                <x-filament::badge color="primary">{{ $client->category?->name ?? '—' }}</x-filament::badge>
                            </div>
                        </div>
                    </div>

                    {{-- خط فاصل --}}
                    <div class="border-t border-gray-100 dark:border-gray-800"></div>

                    {{-- الاشتراك النشط --}}
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <x-filament::icon icon="heroicon-o-document-text" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">الاشتراك النشط</p>
                            @if ($client->currentContract)
                            <p class="text-sm font-semibold text-gray-900 dark:text-white" dir="ltr">
                                {{ $client->currentContract->start_date?->format('Y-m-d') }}
                                <span class="text-xs text-gray-400" dir="rtl">→</span>
                                {{ $client->currentContract->end_date?->format('Y-m-d') }}
                            </p>
                            @else
                            <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">لا يوجد</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- المحتوى الرئيسي --}}
        <div class="min-w-0 flex-1 space-y-6">
            @if ($client->isUnderLawsuit())
            <div class="flex items-start gap-3 rounded-2xl border border-danger-200 bg-danger-50 p-4 dark:border-danger-900 dark:bg-danger-950/20">
                <div class="rounded-xl bg-danger-100 p-2 text-danger-600 dark:bg-danger-900/40 dark:text-danger-300">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6" />
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-danger-800 dark:text-danger-200">تنبيه قانوني</h3>
                    <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">هذا العميل يخضع للمقاضاة القانونية حالياً.</p>
                    @if ($client->currentContract?->legal_notes)
                    <p class="mt-2 text-sm text-danger-700 dark:text-danger-300">{{ $client->currentContract->legal_notes }}</p>
                    @endif
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($summaryCards as $card)
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/40">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $card['value'] }} <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['tone'] === 'warning' && $card['label'] === 'التصاميم الإضافية' ? 'تصاميم' : $currency }}</span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['meta'] }}</div>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl {{ $card['bg'] }} {{ $card['text'] }}">
                            <x-filament::icon icon="{{ $card['icon'] }}" class="h-6 w-6" />
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Financial client sections">
                    <button
                        type="button"
                        @click="tab = 'overview'"
                        :class="tab === 'overview' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        نظرة عامة
                    </button>
                    <button
                        type="button"
                        @click="tab = 'invoices'"
                        :class="tab === 'invoices' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        الفواتير
                    </button>
                    <button
                        type="button"
                        @click="tab = 'receipts'"
                        :class="tab === 'receipts' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        سندات القبض
                    </button>
                    <button
                        type="button"
                        @click="tab = 'statement'"
                        :class="tab === 'statement' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition">
                        كشف الحساب التفصيلي
                    </button>
                </div>
            </div>

            <section x-show="tab === 'overview'" x-cloak x-transition.opacity.duration.150ms class="space-y-6">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                            <div class="rounded-xl bg-primary-50 p-2 text-primary-700 dark:bg-primary-950/30 dark:text-primary-300">
                                <x-filament::icon icon="heroicon-o-document-duplicate" class="h-5 w-5" />
                            </div>
                            <div class="grow">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">تفاصيل الاشتراك الحالي</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">ملخص بيانات الاشتراك والاشتراك.</p>
                            </div>
                        </div>

                        @if ($client->currentContract)
                        <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            @php
                                $contractStart = $client->currentContract->start_date;
                                $contractEnd = $client->currentContract->end_date;
                                $now = now();

                                $totalDays = $contractStart && $contractEnd ? $contractStart->diffInDays($contractEnd) : 0;
                                $elapsedDays = $contractStart ? $contractStart->diffInDays($now) : 0;
                                $remainingDays = $contractEnd ? (int) $now->diffInDays($contractEnd, false) : 0;
                                $remainingDays = max($remainingDays, 0);
                                $progressPercent = $totalDays > 0 ? min(round(($elapsedDays / $totalDays) * 100), 100) : 0;

                                $barColor = match (true) {
                                    $remainingDays <= 0 => 'bg-danger-500',
                                    $remainingDays <= 7 => 'bg-danger-500',
                                    $remainingDays <= 30 => 'bg-warning-500',
                                    default => 'bg-success-500',
                                };
                                $textColor = match (true) {
                                    $remainingDays <= 0 => 'text-danger-600 dark:text-danger-400',
                                    $remainingDays <= 7 => 'text-danger-600 dark:text-danger-400',
                                    $remainingDays <= 30 => 'text-warning-600 dark:text-warning-400',
                                    default => 'text-success-600 dark:text-success-400',
                                };
                            @endphp
                            <div class="col-span-full rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">مدة الاشتراك المتبقية</dt>
                                    <dd class="text-sm font-bold {{ $textColor }}">
                                        @if ($remainingDays <= 0)
                                            منتهي
                                        @else
                                            {{ $remainingDays }} يوم
                                        @endif
                                    </dd>
                                </div>
                                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="{{ $barColor }} h-full rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                                </div>
                                <div class="mt-2 flex justify-between text-xs text-gray-400 dark:text-gray-500" dir="ltr">
                                    <span>{{ $contractEnd?->format('Y-m-d') ?? '—' }}</span>
                                    <span>{{ $contractStart?->format('Y-m-d') ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">نوع السداد</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $client->currentContract->payment_type === 'advance' ? 'مقدم' : 'مؤخر' }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">دورة الفوترة</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ match ($client->currentContract->billing_cycle) {
                                    'weekly' => 'أسبوعي',
                                    'monthly' => 'شهري',
                                    'yearly' => 'سنوي',
                                    default => $client->currentContract->billing_cycle,
                                } }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">عدد التصاميم</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $client->currentContract->monthly_designs_count }} تصاميم/شهر</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">المبلغ</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    {{ number_format(($client->currentContract->total_amount ?? 0) + ($client->currentContract->marketing_amount ?? 0)) }} {{ $client->currentContract->currency?->symbol ?? $client->currentContract->currency?->currency ?? $currency }}
                                </dd>
                            </div>
                        </dl>
                        @else
                        <div class="mt-5 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-950/30 dark:text-gray-400">
                            لا يوجد اشتراك نشط حالياً لهذا العميل.
                        </div>
                        @endif
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center gap-3 border-b border-gray-100 pb-4 dark:border-gray-800">
                            <div class="rounded-xl bg-success-50 p-2 text-success-700 dark:bg-success-950/30 dark:text-success-300">
                                <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">الحالة الزمنية والتواريخ</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">مؤشرات الأداء الزمني للعميل.</p>
                            </div>
                        </div>

                        <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">تاريخ آخر سداد</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    @if ($client->last_payment_date)
                                    <span dir="ltr">{{ $client->last_payment_date->format('Y-m-d') }}</span>
                                    @else
                                    لا يوجد سداد سابق
                                    @endif
                                </dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">أيام التأخير عن السداد</dt>
                                <dd class="mt-1 font-semibold {{ $client->days_overdue > 0 ? 'text-danger-600 dark:text-danger-300' : 'text-success-600 dark:text-success-300' }}">
                                    {{ $client->days_overdue > 0 ? 'متأخر '.$client->days_overdue.' يوم' : 'منتظم' }}
                                </dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">مهلة السداد (سماح)</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $client->currentContract?->grace_period_days ?? 7 }} أيام</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950/40">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">التوقيف التلقائي</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    @if ($client->currentContract)
                                    {{ $client->currentContract->auto_suspension_enabled ? 'مفعل بعد ' . $client->currentContract->suspension_period_days . ' يوم' : 'غير مفعل' }}
                                    @else
                                    لا يوجد اشتراك نشط
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section x-show="tab === 'invoices'" x-cloak x-transition.opacity.duration.150ms class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">الفواتير</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">سجل الفواتير الصادرة لهذا العميل.</p>
                        </div>
                        <div class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $client->invoices_count ?? 0 }} فاتورة
                        </div>
                    </div>
                </div>

                @livewire(\App\Livewire\ClientInvoicesTable::class, ['client' => $client], key('invoices-' . $client->id))
            </section>

            <section x-show="tab === 'receipts'" x-cloak x-transition.opacity.duration.150ms class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">سندات القبض</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">كل عمليات السداد المربوطة بهذا العميل.</p>
                        </div>
                        <div class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $client->paid_amount ?? 0 }} {{ $currency }}
                        </div>
                    </div>
                </div>

                @livewire(\App\Livewire\ClientReceiptsTable::class, ['client' => $client], key('receipts-' . $client->id))
            </section>

            <section x-show="tab === 'statement'" x-cloak x-transition.opacity.duration.150ms class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">كشف حساب مالي تفصيلي</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">الحركات المالية المسجلة بالترتيب الزمني (فواتير مرحلة ومدفوعة + سندات قبض)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('export_financial_data'))
                            <a href="{{ route('reports.client-statement', ['client' => $client->id, 'print' => 1]) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700">
                                <x-filament::icon icon="heroicon-o-printer" class="h-4 w-4" />
                                <span>طباعة كشف الحساب / حفظ PDF</span>
                            </a>
                            @endif
                            <a href="{{ route('reports.client-statement', ['client' => $client->id]) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-300 dark:border-gray-700 px-4 py-2 text-sm font-bold shadow-sm transition hover:bg-gray-200 dark:hover:bg-gray-700">
                                <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                <span>معاينة الكشف</span>
                            </a>
                        </div>
                    </div>
                </div>

                @livewire(\App\Livewire\ClientStatementTable::class, ['client' => $client], key('statement-' . $client->id))
            </section>
        </div>{{-- نهاية المحتوى الرئيسي --}}
    </div>
</x-filament-panels::page>