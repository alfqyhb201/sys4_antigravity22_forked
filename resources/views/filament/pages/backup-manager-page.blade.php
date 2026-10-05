<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ──────────────────────────────────────────────
             1. بطاقات إحصائيات النسخ الاحتياطي
             ────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- إجمالي عدد النسخ --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي النسخ الاحتياطية</p>
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-1">
                        {{ $statistics['total_count'] ?? 0 }}
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5">ملف أرشيف مضغوط</p>
                </div>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-xl">
                    <x-heroicon-o-archive-box class="w-6 h-6" />
                </div>
            </div>

            {{-- إجمالي حجم النسخ --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إجمالي المساحة المستهلكة</p>
                    <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-1">
                        {{ $statistics['total_size_formatted'] ?? '0 B' }}
                    </p>
                    <p class="text-[11px] text-gray-400 mt-0.5">حجم النسخ الإجمالي</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl">
                    <x-heroicon-o-circle-stack class="w-6 h-6" />
                </div>
            </div>

            {{-- تاريخ آخر نسخة --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">آخر نسخة احتياطية</p>
                    <p class="text-sm font-bold text-gray-900 dark:text-white mt-1">
                        {{ $statistics['latest_backup'] ?? 'لا توجد' }}
                    </p>
                    <p class="text-[11px] text-primary-600 dark:text-primary-400 font-semibold mt-0.5">
                        {{ $statistics['latest_age'] ?? 'غير متوفر' }}
                    </p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl">
                    <x-heroicon-o-clock class="w-6 h-6" />
                </div>
            </div>

            {{-- قرص التخزين النشط --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">أقراص التخزين المعتمدة</p>
                    <div class="flex flex-wrap gap-1 mt-1.5">
                        @forelse($statistics['active_disks'] ?? ['local'] as $disk)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                                {{ strtoupper($disk) }}
                            </span>
                        @empty
                            <span class="text-xs text-gray-400">غير محدد</span>
                        @endforelse
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">تخزين آمن ومحمي</p>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 rounded-xl">
                    <x-heroicon-o-cloud class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────
             2. أزرار وأدوات إنشاء النسخ الاحتياطية
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-wrench class="w-5 h-5 text-primary-500" />
                        <span>إجراءات وعمليات النسخ الاحتياطي</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        يمكنك أخذ نسخة سريعة لقاعدة البيانات فقط أو أخذ نسخة شاملة مع الملفات والمرفقات.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- زر نسخة قاعدة البيانات --}}
                    <x-filament::button
                        color="primary"
                        icon="heroicon-m-circle-stack"
                        wire:click="openBackupModal('db')"
                        wire:loading.attr="disabled">
                        نسخة قاعدة البيانات فقط
                    </x-filament::button>

                    {{-- زر نسخة كاملة --}}
                    <x-filament::button
                        color="gray"
                        icon="heroicon-m-archive-box-arrow-down"
                        wire:click="openBackupModal('full')"
                        wire:loading.attr="disabled">
                        نسخة كاملة (قاعدة البيانات + الملفات)
                    </x-filament::button>

                    {{-- زر تنظيف النسخ القديمة --}}
                    <x-filament::button
                        color="danger"
                        outlined
                        icon="heroicon-m-trash"
                        wire:click="cleanOldBackups"
                        wire:loading.attr="disabled"
                        wire:confirm="هل أنت متأكد من تنظيف وحذف النسخ الاحتياطية القديمة؟">
                        تنظيف النسخ القديمة
                    </x-filament::button>
                </div>
            </div>

            {{-- مخرجات آخر أمر إن وجدت --}}
            @if($lastCommandOutput)
                <div class="mt-4 p-4 rounded-xl bg-gray-900 text-emerald-400 font-mono text-xs border border-gray-800 overflow-x-auto">
                    <p class="text-gray-400 text-[11px] font-sans mb-1 font-bold">تقرير إنجاز العملية:</p>
                    <pre class="whitespace-pre-wrap leading-relaxed">{{ trim($lastCommandOutput) }}</pre>
                </div>
            @endif
        </div>

        {{-- ──────────────────────────────────────────────
             3. جدول استعراض وتحميل وحذف النسخ الاحتياطية
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-list-bullet class="w-5 h-5 text-gray-500" />
                    <span>ملفات النسخ الاحتياطية المتوفرة ({{ count($backups) }})</span>
                </h3>

                <x-filament::button
                    color="gray"
                    size="xs"
                    icon="heroicon-m-arrow-path"
                    wire:click="refreshData"
                    wire:loading.attr="disabled">
                    تحديث القائمة
                </x-filament::button>
            </div>

            @if(empty($backups))
                <div class="p-12 text-center">
                    <div class="inline-flex p-4 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 mb-3">
                        <x-heroicon-o-archive-box class="w-8 h-8" />
                    </div>
                    <h4 class="text-base font-bold text-gray-800 dark:text-gray-200">لا توجد أي نسخ احتياطية مسجلة بعد</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        قم بالضغط على أحد أزرار الإنشاء أعلاه لتوليد أول نسخة احتياطية لقاعدة بيانات النظام.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-50/70 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 text-xs font-bold border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="py-3.5 px-4">اسم ملف النسخة</th>
                                <th class="py-3.5 px-4">حجم الملف</th>
                                <th class="py-3.5 px-4">تاريخ الإنشاء</th>
                                <th class="py-3.5 px-4">العمر الزمني</th>
                                <th class="py-3.5 px-4">القرص</th>
                                <th class="py-3.5 px-4 text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                            @foreach($backups as $backup)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                                    {{-- اسم الملف --}}
                                    <td class="py-3.5 px-4 font-mono text-xs font-semibold flex items-center gap-2">
                                        <x-heroicon-s-archive-box class="w-4 h-4 text-amber-500 shrink-0" />
                                        <span>{{ $backup['filename'] }}</span>
                                    </td>

                                    {{-- حجم الملف --}}
                                    <td class="py-3.5 px-4 font-bold text-gray-900 dark:text-white">
                                        {{ $backup['size_formatted'] }}
                                    </td>

                                    {{-- تاريخ الإنشاء --}}
                                    <td class="py-3.5 px-4 text-xs font-mono text-gray-600 dark:text-gray-400">
                                        {{ $backup['created_at_formatted'] }}
                                    </td>

                                    {{-- العمر الزمني --}}
                                    <td class="py-3.5 px-4 text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                            {{ $backup['age'] }}
                                        </span>
                                    </td>

                                    {{-- القرص --}}
                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                                            {{ strtoupper($backup['disk']) }}
                                        </span>
                                    </td>

                                    {{-- الإجراءات --}}
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center justify-center gap-2">
                                            {{-- زر التحميل --}}
                                            <x-filament::button
                                                size="xs"
                                                color="primary"
                                                icon="heroicon-m-arrow-down-tray"
                                                wire:click="downloadBackup('{{ $backup['disk'] }}', '{{ $backup['path'] }}')">
                                                تحميل
                                            </x-filament::button>

                                            {{-- زر الحذف --}}
                                            <x-filament::button
                                                size="xs"
                                                color="danger"
                                                outlined
                                                icon="heroicon-m-trash"
                                                wire:click="deleteBackup('{{ $backup['disk'] }}', '{{ $backup['path'] }}')"
                                                wire:confirm="هل أنت متأكد تماماً من حذف هذه النسخة الاحتياطية نهائياً؟">
                                                حذف
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ──────────────────────────────────────────────
             4. مودال خيارات إنشاء النسخة الاحتياطية
             ────────────────────────────────────────────── --}}
        @if($showBackupModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-900/70 backdrop-blur-xs transition-opacity" wire:click="closeBackupModal"></div>

                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 text-right shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-200 dark:border-gray-800">
                        {{-- رأس المودال --}}
                        <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 rounded-xl {{ $backupType === 'db' ? 'bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' }}">
                                    @if($backupType === 'db')
                                        <x-heroicon-o-circle-stack class="w-6 h-6" />
                                    @else
                                        <x-heroicon-o-archive-box-arrow-down class="w-6 h-6" />
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900 dark:text-white" id="modal-title">
                                        {{ $backupType === 'db' ? 'إنشاء نسخة احتياطية لقاعدة البيانات' : 'إنشاء نسخة احتياطية شاملة للنظام' }}
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ $backupType === 'db' ? 'تصدير وضغط كافة بيانات وجداول النظام (~600 KB)' : 'تصدير قاعدة البيانات وأرشفة ملفات ومرفقات النظام (~750 MB)' }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="closeBackupModal"
                                class="p-1 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <x-heroicon-m-x-mark class="w-6 h-6" />
                            </button>
                        </div>

                        {{-- محتوى المودال --}}
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="text-xs font-bold text-gray-700 dark:text-gray-300 block mb-2">
                                    حدد أقراص وجهة التخزين المطلوبة:
                                </label>

                                <div class="space-y-2.5">
                                    {{-- خيار التخزين المحلي --}}
                                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:border-primary-500 transition-all">
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                value="local"
                                                wire:model="targetDisks"
                                                class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                            <div>
                                                <p class="text-xs font-bold text-gray-900 dark:text-white">القرص المحلي (Local Storage)</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400">حفظ فوري على السيرفر لتنزيلها بسرعة فائقة.</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">سريع جداً</span>
                                    </label>

                                    {{-- خيار جوجل درايف --}}
                                    <label class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 cursor-pointer hover:border-primary-500 transition-all">
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                value="google"
                                                wire:model="targetDisks"
                                                class="w-4 h-4 rounded text-primary-600 focus:ring-primary-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                            <div>
                                                <p class="text-xs font-bold text-gray-900 dark:text-white">سحابة جوجل درايف (Google Drive)</p>
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400">تخزين سحابي آمن ومشفر خارج السيرفر.</p>
                                            </div>
                                        </div>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">سحابي آمن</span>
                                    </label>
                                </div>
                            </div>

                            @if($backupType === 'full')
                                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2">
                                    <x-heroicon-m-exclamation-triangle class="w-4 h-4 shrink-0 mt-0.5" />
                                    <span>
                                        <strong>تنبيه:</strong> رفع النسخة الشاملة (~750 MB) إلى Google Drive يعتمد على سرعة رفع الإنترنت لديكم وقد يستغرق بضع دقائق. يفضل اختيار القرص المحلي للتحميل السريع.
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- أزرار الإجراءات --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2">
                            <x-filament::button
                                color="gray"
                                wire:click="closeBackupModal">
                                إلغاء
                            </x-filament::button>

                            <x-filament::button
                                color="primary"
                                icon="heroicon-m-arrow-path"
                                wire:click="runSelectedBackup"
                                wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="runSelectedBackup">بدء النسخ الآن</span>
                                <span wire:loading wire:target="runSelectedBackup">جارٍ إنشاء وحفظ النسخة...</span>
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
