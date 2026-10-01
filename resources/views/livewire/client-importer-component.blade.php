<div class="p-6">
    <!-- ===== شريط تقدم الخطوات ===== -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @php
            $steps = [
            1 => ['label' => 'رفع الملف', 'icon' => 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5'],
            2 => ['label' => 'معاينة وتعيين', 'icon' => 'M10.125 2.25h-4.5a2.25 2.25 0 00-2.25 2.25v15a2.25 2.25 0 002.25 2.25h12.75a2.25 2.25 0 002.25-2.25v-15a2.25 2.25 0 00-2.25-2.25h-4.5M10.125 2.25a2.25 2.25 0 012.25 0m0 0a2.25 2.25 0 012.25 0M10.125 2.25H12'],
            3 => ['label' => 'الاستيراد', 'icon' => 'M4.5 12a7.5 7.5 0 0015 0m-15 0a7.5 7.5 0 1115 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 0a5.607 5.607 0 0111.138 0'],
            4 => ['label' => 'النتائج', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ];
            @endphp

            @foreach ($steps as $stepNum => $stepInfo)
            <div class="flex items-center flex-1">
                <div class="flex items-center gap-2">
                    <!-- دائرة الرقم -->
                    <div @class([ 'w-10 h-10 rounded-full flex items-center justify-center font-semibold text-sm transition-all duration-500 relative' , 'bg-gradient-to-br from-custom-500 to-custom-700 text-white shadow-lg shadow-glow-purple/30 ring-2 ring-brand-orange/40'=> $step >= $stepNum,
                        'bg-custom-100 dark:bg-custom-800/50 text-brand-muted' => $step < $stepNum,
                            ])>
                            @if ($step > $stepNum)
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            @elseif ($step === $stepNum)
                            <span class="relative z-10">{{ $stepNum }}</span>
                            @else
                            {{ $stepNum }}
                            @endif
                    </div>
                    <!-- النص -->
                    <span @class([ 'text-sm font-medium hidden sm:inline transition-all duration-300' , 'text-brand-text font-semibold'=> $step >= $stepNum,
                        'text-brand-muted' => $step < $stepNum,
                            ])>
                            {{ $stepInfo['label'] }}
                    </span>
                </div>
                <!-- الخط الرابط -->
                @if ($stepNum < 4)
                    <div class="flex-1 mx-3">
                    <div class="h-1 rounded-full bg-surface-dim overflow-hidden">
                        <div @class([ 'h-full rounded-full transition-all duration-700 ease-out' , 'bg-gradient-to-l from-brand-orange to-custom-500'=> $step > $stepNum,
                            'bg-surface-dim' => $step <= $stepNum,
                                ]) @style($step> $stepNum ? 'width: 100%' : 'width: 0%')></div>
                    </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<!-- ===== رسالة الخطأ العامة ===== -->
@if ($errorMessage)
<div class="mb-6 p-4 bg-danger-50 dark:bg-danger-900/20 border border-danger-300 dark:border-danger-700 rounded-lg">
    <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-danger-600 dark:text-danger-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $errorMessage }}</p>
    </div>
</div>
@endif

<!-- ===== الخطوة 1: رفع الملف ===== -->
@if ($step === 1)
<div class="bg-surface rounded-xl shadow-sm border border-brand-border p-8">
    <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-custom-500 to-custom-700 rounded-full flex items-center justify-center shadow-lg shadow-glow-purple/30">
            <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-brand-text mb-2">استيراد العملاء وعقودهم</h2>
        <p class="text-sm text-brand-muted">
            قم برفع ملف CSV أو Excel (.xlsx) لاستيراد العملاء مع بيانات عقودهم إلى النظام
        </p>
    </div>

    <!-- زر تحميل النموذج -->
    <div class="max-w-xl mx-auto mb-6">
        <div class="flex items-center justify-between gap-3 p-4 bg-surface-dim rounded-xl border border-brand-border">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-brand-orange to-brand-orange-light rounded-lg shadow-sm shadow-glow-orange/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-brand-text">نموذج الاستيراد الجاهز</p>
                    <p class="text-xs text-brand-muted">ملف Excel يحتوي على جميع الأعمدة مع مثال توضيحي</p>
                </div>
            </div>
            <a href="{{ route('clients.import.template') }}"
                class="px-4 py-2 text-sm font-medium text-white bg-gradient-to-l from-brand-orange to-brand-orange-light rounded-lg hover:from-brand-orange-light hover:to-brand-orange transition-all duration-200 shadow-md shadow-glow-orange/20 hover:shadow-lg">
                تحميل النموذج
            </a>
        </div>
    </div>

    <!-- منطقة رفع الملف -->
    <div class="max-w-xl mx-auto">
        <div
            x-data="{ isDragging: false }"
            x-on:dragover.prevent="isDragging = true"
            x-on:dragleave.prevent="isDragging = false"
            x-on:drop.prevent="isDragging = false"
            class="relative">
            <div
                x-bind:class="isDragging ? 'border-brand-orange bg-gradient-to-br from-brand-orange/5 to-surface-dim/50 dark:from-brand-orange/10 dark:to-surface-dim/50 shadow-lg shadow-glow-orange/20 scale-[1.02]' : 'border-brand-border'"
                class="border-2 border-dashed rounded-xl p-10 text-center transition-all duration-300 cursor-pointer hover:border-brand-orange/60 dark:hover:border-brand-orange/70 hover:bg-brand-orange/5 dark:hover:bg-brand-orange/5 hover:shadow-md">
                <input
                    type="file"
                    wire:model="uploadedFile"
                    accept=".csv,.xlsx,.xls"
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                    id="file-upload" />

                <svg class="w-14 h-14 mx-auto mb-4 text-brand-orange drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>

                <p class="text-sm text-brand-muted mb-1">
                    <span class="font-semibold bg-gradient-to-r from-brand-orange to-brand-orange-light bg-clip-text text-transparent">اختر ملفاً</span> أو اسحب الملف وأفلته هنا
                </p>
                <p class="text-xs text-brand-muted">
                    CSV, XLSX, XLS — الحد الأقصى 10 ميجابايت
                </p>
            </div>
        </div>

        <!-- مؤشر رفع الملف -->
        <div wire:loading wire:target="uploadedFile" class="mt-4">
            <div class="flex items-center gap-3 p-3 bg-surface-dim rounded-lg border border-brand-border">
                <svg class="animate-spin w-5 h-5 text-custom-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="text-sm font-medium bg-gradient-to-r from-brand-orange to-brand-orange-light bg-clip-text text-transparent">جاري قراءة الملف...</span>
            </div>
        </div>
    </div>
</div>
@endif

<!-- ===== الخطوة 2: معاينة وتعيين الأعمدة ===== -->
@if ($step === 2)
@php
$booleanFields = ['is_credit_allowed', 'contract_auto_renewal', 'contract_additional_designs_enabled', 'contract_simple_requests_enabled', 'contract_is_under_lawsuit'];
$numberFields = ['customer_rating_value', 'change_cliche_threshold', 'contract_weekly_designs_count', 'contract_monthly_designs_count', 'contract_total_amount', 'contract_marketing_amount', 'contract_additional_design_price', 'contract_simple_request_price', 'contract_grace_period_days'];
$dateFields = ['contract_start_date', 'contract_end_date'];
$currentGroup = null;
@endphp
<div class="space-y-6">
    <!-- معلومات الملف -->
    <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-brand-orange to-brand-orange-light rounded-lg shadow-sm shadow-glow-orange/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-brand-text">{{ $fileName }}</p>
                    <p class="text-xs text-brand-muted">
                        تم اكتشاف <strong>{{ count($fileColumns) }}</strong> عموداً و <strong>{{ count($previewRows) }}</strong> صفاً للمعاينة
                    </p>
                </div>
            </div>
            <button wire:click="backToUpload" class="text-sm font-medium text-brand-orange hover:text-brand-orange-light flex items-center gap-1 transition-all duration-200">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
                تغيير الملف
            </button>
        </div>
    </div>

    <!-- تعيين الأعمدة -->
    <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
        <h3 class="text-lg font-bold text-brand-text mb-1">تعيين الأعمدة</h3>
        <p class="text-sm text-brand-muted mb-6">اختر العمود المناسب من الملف لكل حقل من حقول العميل والاشتراك</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-brand-border">
                        <th class="text-right py-3 px-4 font-medium text-brand-muted">الحقل</th>
                        <th class="text-right py-3 px-4 font-medium text-brand-muted">العمود في الملف</th>
                        <th class="text-right py-3 px-4 font-medium text-brand-muted">نوع البيانات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modelFields as $field)
                    @if ($field['group'] !== $currentGroup)
                    @php $currentGroup = $field['group']; @endphp
                    <tr class="bg-surface-dim/60">
                        <td colspan="3" class="py-2.5 px-4">
                            <span class="text-xs font-bold tracking-wide text-custom-600 dark:text-custom-400 uppercase">{{ $field['group'] }}</span>
                        </td>
                    </tr>
                    @endif
                    <tr class="border-b border-brand-border hover:bg-surface-dim transition-colors">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <span class="text-brand-text">{{ $field['label'] }}</span>
                                @if ($field['required'])
                                <span class="text-xs text-danger-500">*</span>
                                @endif
                            </div>
                            <span class="text-xs text-brand-muted font-mono">{{ $field['field'] }}</span>
                        </td>
                        <td class="py-3 px-4">
                            <select
                                wire:change="updateColumnMap('{{ $field['field'] }}', $event.target.value)"
                                class="w-full max-w-xs rounded-lg border-brand-border dark:bg-surface-dim text-sm focus:border-custom-500 focus:ring-custom-500">
                                <option value="">-- لا شيء --</option>
                                @foreach ($fileColumns as $column)
                                <option
                                    value="{{ $column }}"
                                    @selected(($columnMap[$field['field']] ?? '' )===$column)>{{ $column }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="py-3 px-4">
                            <span class="text-xs text-brand-muted">
                                @if (in_array($field['field'], $booleanFields))
                                منطقي (نعم/لا)
                                @elseif (in_array($field['field'], $dateFields))
                                تاريخ
                                @elseif (in_array($field['field'], $numberFields))
                                رقم
                                @else
                                نص
                                @endif
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- معاينة البيانات -->
    @if (!empty($previewRows))
    <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-brand-text">معاينة البيانات</h3>
            <span class="text-xs text-brand-muted">أول {{ count($previewRows) }} صفوف</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-brand-border">
                        <th class="text-right py-2 px-3 font-medium text-brand-muted">#</th>
                        @foreach ($columnMap as $field => $column)
                        @php
                        $fieldInfo = collect($modelFields)->firstWhere('field', $field);
                        @endphp
                        <th class="text-right py-2 px-3 font-medium text-custom-500 dark:text-custom-400">
                            {{ $fieldInfo['label'] ?? $field }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($previewRows as $rowIndex => $row)
                    <tr class="border-b border-brand-border">
                        <td class="py-2 px-3 text-brand-muted">{{ $rowIndex + 1 }}</td>
                        @foreach ($columnMap as $field => $column)
                        <td class="py-2 px-3 text-brand-text max-w-xs truncate">
                            {{ $row[$field] ?? '' }}
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- أزرار التنقل -->
    <div class="flex items-center justify-between gap-4">
        <button wire:click="backToUpload" class="px-6 py-2.5 text-sm font-medium text-brand-muted bg-surface border border-brand-border rounded-lg hover:bg-surface-dim transition-all duration-200">
            السابق
        </button>
        <button
            wire:click="startImport"
            wire:loading.attr="disabled"
            class="px-8 py-2.5 text-sm font-medium text-white bg-gradient-to-l from-custom-600 to-brand-orange rounded-lg hover:from-custom-500 hover:to-brand-orange-light disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 shadow-md shadow-glow-purple/30 hover:shadow-lg hover:shadow-glow-orange/30 active:scale-[0.98]">
            <span wire:loading.remove wire:target="startImport">
                بدء الاستيراد
            </span>
            <span wire:loading wire:target="startImport">
                جاري التحميل...
            </span>
        </button>
    </div>
</div>
@endif

<!-- ===== الخطوة 3: الاستيراد قيد التنفيذ ===== -->
@if ($step === 3)
<div class="bg-surface rounded-xl shadow-sm border border-brand-border p-12">
    <div class="text-center max-w-md mx-auto">
        <!-- أيقونة متحركة -->
        <div class="w-20 h-20 mx-auto mb-6 relative">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-orange to-custom-500 rounded-full animate-ping opacity-20"></div>
            <div class="absolute inset-2 bg-gradient-to-br from-custom-500 to-custom-700 rounded-full blur-sm opacity-40 animate-pulse"></div>
            <div class="relative w-20 h-20 bg-gradient-to-br from-custom-500 to-custom-700 rounded-full flex items-center justify-center shadow-xl shadow-glow-purple/30">
                <svg class="w-10 h-10 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                </svg>
            </div>
        </div>

        <h3 class="text-xl font-bold text-brand-text mb-2">جاري استيراد البيانات...</h3>
        <p class="text-sm text-brand-muted mb-8">يرجى الانتظار بينما يتم استيراد العملاء وعقودهم من الملف</p>

        <!-- شريط التقدم -->
        <div class="w-full bg-surface-dim rounded-full h-3 overflow-hidden shadow-inner">
            <div
                class="h-full bg-gradient-to-l from-brand-orange via-custom-600 to-custom-500 rounded-full transition-all duration-500 ease-out shadow-sm shadow-glow-orange/30"
                x-init="
                            let progress = 0;
                            let interval = setInterval(() => {
                                progress += Math.random() * 15;
                                if (progress > 90) progress = 90;
                                $el.style.width = progress + '%';
                            }, 800);

                            // عندما يكتمل الاستيراد
                            Livewire.on('import-complete', () => {
                                clearInterval(interval);
                                $el.style.width = '100%';
                            });
                        "
                style="width: 10%"></div>
        </div>

        <p class="text-xs text-brand-muted mt-3" wire:loading.remove wire:target="startImport">
            جاري معالجة الصفوف...
        </p>
    </div>
</div>
@endif

<!-- ===== الخطوة 4: النتائج ===== -->
@if ($step === 4 && $importResultData)
<div class="space-y-6">
    <!-- بطاقات النتائج -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- الإجمالي -->
        <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-surface-dim rounded-lg">
                    <svg class="w-6 h-6 text-custom-600 dark:text-custom-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-brand-text">{{ $importResultData['total'] }}</p>
                    <p class="text-xs text-brand-muted">إجمالي الصفوف</p>
                </div>
            </div>
        </div>

        <!-- الناجح -->
        <div class="bg-surface rounded-xl shadow-sm border border-green-200 dark:border-green-900 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $importResultData['successful'] }}</p>
                    <p class="text-xs text-brand-muted">تم الاستيراد بنجاح</p>
                </div>
            </div>
        </div>

        <!-- الفاشل -->
        <div class="bg-surface rounded-xl shadow-sm border border-red-200 dark:border-red-900 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $importResultData['failed'] }}</p>
                    <p class="text-xs text-brand-muted">فشل الاستيراد</p>
                </div>
            </div>
        </div>
    </div>

    <!-- الأخطاء -->
    @if (!empty($importResultData['errors']))
    <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
        <h3 class="text-lg font-bold text-brand-text mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            تفاصيل الأخطاء
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-brand-border">
                        <th class="text-right py-2 px-3 font-medium text-brand-muted">رقم الصف</th>
                        <th class="text-right py-2 px-3 font-medium text-brand-muted">رسالة الخطأ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($importResultData['errors'] as $rowNum => $error)
                    <tr class="border-b border-brand-border">
                        <td class="py-2 px-3 text-brand-text font-mono">{{ $rowNum }}</td>
                        <td class="py-2 px-3 text-red-600 dark:text-red-400">{{ $error }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- التحذيرات -->
    @if (!empty($importResultData['warnings']))
    <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
        <h3 class="text-lg font-bold text-brand-text mb-4 flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            تحذيرات
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-brand-border">
                        <th class="text-right py-2 px-3 font-medium text-brand-muted">رقم الصف</th>
                        <th class="text-right py-2 px-3 font-medium text-brand-muted">الرسالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($importResultData['warnings'] as $rowNum => $warning)
                    <tr class="border-b border-brand-border">
                        <td class="py-2 px-3 text-brand-text font-mono">{{ $rowNum }}</td>
                        <td class="py-2 px-3 text-yellow-600 dark:text-yellow-400">{{ $warning }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- أزرار النتائج -->
    <div class="flex items-center justify-between gap-4">
        <button wire:click="resetImport" class="px-6 py-2.5 text-sm font-medium text-brand-orange bg-surface border border-brand-orange/40 dark:border-brand-orange/30 rounded-lg hover:bg-brand-orange/5 dark:hover:bg-brand-orange/10 transition-all duration-200 hover:shadow-md hover:border-brand-orange/60">
            استيراد جديد
        </button>
        <button wire:click="reimport" class="px-6 py-2.5 text-sm font-medium text-white bg-gradient-to-l from-brand-orange to-brand-orange-light rounded-lg hover:from-brand-orange-light hover:to-brand-orange transition-all duration-200 shadow-md shadow-glow-orange/20 hover:shadow-lg hover:shadow-glow-orange/30 active:scale-[0.98]">
            تعديل التعيين وإعادة الاستيراد
        </button>
    </div>
</div>
@endif
</div>