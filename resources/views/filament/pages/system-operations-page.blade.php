<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ──────────────────────────────────────────────
             1. شريط حالة وضع الصيانة (إذا كان مفعلاً)
             ────────────────────────────────────────────── --}}
        @if($healthData['maintenance']['is_down'] ?? false)
            <div class="p-5 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-200 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-amber-500 text-white rounded-xl shadow-sm">
                        <x-heroicon-s-exclamation-triangle class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-lg font-bold">⚠️ النظام قيد الصيانة حالياً (Maintenance Mode Active)</h3>
                        <p class="text-sm opacity-90 mt-0.5">
                            المستخدمون والزوار العاديون لا يمكنهم تصفح النظام، وتظهر لهم صفحة الصيانة (503).
                        </p>
                        @if(!empty($healthData['maintenance']['secret']))
                            <div class="mt-2 flex items-center gap-2 text-xs font-mono bg-white/60 dark:bg-black/40 px-3 py-1.5 rounded-lg border border-amber-500/20 w-fit">
                                <span>رابط الدخول السري:</span>
                                <a href="{{ url($healthData['maintenance']['secret']) }}" target="_blank" class="underline text-amber-700 dark:text-amber-300 font-bold">
                                    {{ url($healthData['maintenance']['secret']) }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                <x-filament::button
                    color="success"
                    icon="heroicon-m-check-circle"
                    wire:click="disableMaintenance"
                    wire:loading.attr="disabled"
                    class="shrink-0 shadow-md">
                    تعطيل وضع الصيانة فوراً والعودة للعمل
                </x-filament::button>
            </div>
        @endif

        {{-- ──────────────────────────────────────────────
             2. بطاقات معلومات السيرفر والبيئة (Server Overview Cards)
             ────────────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-server-stack class="w-5 h-5 text-primary-500" />
                    <span>مواصفات وبيئة الخادم الحية</span>
                </h2>
                <x-filament::button
                    color="gray"
                    size="xs"
                    icon="heroicon-m-arrow-path"
                    wire:click="refreshHealthData"
                    wire:loading.attr="disabled">
                    تحديث المؤشرات
                </x-filament::button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- بطاقة PHP & Laravel --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">إصدارات النظام</p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-white mt-1">
                            PHP {{ $healthData['environment']['php_version'] ?? PHP_VERSION }}
                        </p>
                        <p class="text-xs text-primary-600 dark:text-primary-400 font-semibold mt-0.5">
                            Laravel {{ $healthData['environment']['laravel_version'] ?? app()->version() }}
                        </p>
                    </div>
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-xl">
                        <x-heroicon-o-code-bracket class="w-6 h-6" />
                    </div>
                </div>

                {{-- بطاقة قاعدة البيانات --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">حجم قاعدة البيانات</p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-white mt-1">
                            {{ $healthData['database']['formatted_size'] ?? 'غير متاح' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $healthData['database']['tables_count'] ?? 0 }} جدول ({{ strtoupper($healthData['database']['driver'] ?? 'DB') }})
                        </p>
                    </div>
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl">
                        <x-heroicon-o-circle-stack class="w-6 h-6" />
                    </div>
                </div>

                {{-- بطاقة مساحة التخزين --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">مساحة القرص المتاحة</p>
                        <p class="text-base font-extrabold text-gray-900 dark:text-white mt-1">
                            {{ $healthData['storage']['formatted_free'] ?? 'غير متاح' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            الإجمالي: {{ $healthData['storage']['formatted_total'] ?? 'غير متاح' }}
                        </p>
                    </div>
                    <div class="p-3 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl">
                        <x-heroicon-o-circle-stack class="w-6 h-6" />
                    </div>
                </div>

                {{-- بطاقة البيئة ووضع التصحيح --}}
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">بيئة التشغيل (Env)</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold {{ ($healthData['environment']['app_env'] ?? '') === 'production' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' }}">
                                {{ strtoupper($healthData['environment']['app_env'] ?? 'LOCAL') }}
                            </span>
                            @if($healthData['environment']['app_debug'] ?? false)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300">
                                    Debug ON
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            الذاكرة: {{ $healthData['environment']['memory_limit'] ?? '256M' }}
                        </p>
                    </div>
                    <div class="p-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 rounded-xl">
                        <x-heroicon-o-cog class="w-6 h-6" />
                    </div>
                </div>
            </div>
        </div>

        {{-- ──────────────────────────────────────────────
             3. أدوات تفريغ الكاش بضغطة زر (Cache Management Actions)
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-bolt class="w-5 h-5 text-amber-500" />
                    <span>أدوات تنظيف وإفراغ الكاش</span>
                </h2>

                <x-filament::button
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="clearAllCache"
                    wire:loading.attr="disabled"
                    wire:confirm="هل أنت متأكد من تفريغ كافة أنواع الكاش دفعة واحدة؟">
                    تنظيف الكاش الشامل
                </x-filament::button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- 1. كاش التطبيق والإعدادات --}}
                <div class="border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 p-4 rounded-xl flex flex-col justify-between gap-3">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white">كاش التطبيق والإعدادات</h4>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-path"
                        wire:click="clearAppCache"
                        wire:loading.attr="disabled"
                        class="w-full">
                        تفريغ الإعدادات والكاش
                    </x-filament::button>
                </div>

                {{-- 2. كاش المسارات Routes --}}
                <div class="border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 p-4 rounded-xl flex flex-col justify-between gap-3">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white">كاش المسارات</h4>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-path"
                        wire:click="clearRouteCache"
                        wire:loading.attr="disabled"
                        class="w-full">
                        تفريغ كاش المسارات
                    </x-filament::button>
                </div>

                {{-- 3. كاش القوالب Views --}}
                <div class="border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 p-4 rounded-xl flex flex-col justify-between gap-3">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white">كاش القوالب</h4>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-path"
                        wire:click="clearViewCache"
                        wire:loading.attr="disabled"
                        class="w-full">
                        تفريغ كاش القوالب
                    </x-filament::button>
                </div>

                {{-- 4. كاش Filament --}}
                <div class="border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 p-4 rounded-xl flex flex-col justify-between gap-3">
                    <h4 class="font-bold text-sm text-gray-900 dark:text-white">كاش لوحة Filament</h4>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-arrow-path"
                        wire:click="clearFilamentCache"
                        wire:loading.attr="disabled"
                        class="w-full">
                        تفريغ كاش Filament
                    </x-filament::button>
                </div>
            </div>

            {{-- مخرجات آخر أمر تم تنفيذه --}}
            @if($lastCommandOutput)
                <div class="mt-5 p-4 rounded-xl bg-gray-900 text-emerald-400 font-mono text-xs border border-gray-800 overflow-x-auto">
                    <p class="text-gray-400 text-[11px] font-sans mb-1 font-bold">مخرجات تنفيذ الأمر:</p>
                    <pre class="whitespace-pre-wrap leading-relaxed">{{ trim($lastCommandOutput) }}</pre>
                </div>
            @endif
        </div>

        {{-- ──────────────────────────────────────────────
             4. إدارة وضع الصيانة (Maintenance Mode Center)
             ────────────────────────────────────────────── --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-500" />
                <h2 class="text-base font-bold text-gray-900 dark:text-white">التحكم بوضع الصيانة (Maintenance Mode)</h2>
            </div>

            @if(! ($healthData['maintenance']['is_down'] ?? false))
                <div class="space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        عند تفعيل وضع الصيانة، لن يتمكن أي مستخدم أو زائر من تصفح النظام، وستظهر لهم صفحة الصيانة (HTTP 503).
                        يمكنك استخدام <strong>مفتاح المرور السري</strong> لتسجيل الدخول والعمل بشكل طبيعي أثناء فترة الصيانة.
                    </p>

                    <div class="max-w-xl">
                        {{-- حقل مفتاح المرور السري --}}
                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                                مفتاح المرور السري (Secret Bypass Token)
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    wire:model="maintenanceSecret"
                                    placeholder="مثال: super-secret-123"
                                    class="w-full text-sm rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 font-mono focus:ring-primary-500 focus:border-primary-500"
                                />
                                <x-filament::button
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-m-arrow-path"
                                    wire:click="generateRandomSecret"
                                    class="shrink-0"
                                    title="توليد مفتاح عشوائي">
                                    توليد
                                </x-filament::button>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">
                                رابط الدخول السري أثناء الصيانة: <code class="font-mono text-primary-600 dark:text-primary-400">{{ url('/') }}/[المفتاح]</code>
                            </p>
                        </div>
                    </div>

                    <div class="pt-2">
                        <x-filament::button
                            color="warning"
                            icon="heroicon-m-power"
                            wire:click="enableMaintenance"
                            wire:loading.attr="disabled"
                            wire:confirm="هل أنت متأكد من رغبتك في إغلاق النظام وتحويله إلى وضع الصيانة؟">
                            تفعيل وضع الصيانة الآن
                        </x-filament::button>
                    </div>
                </div>
            @else
                <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
                    <div>
                        <p class="font-bold text-sm">وضع الصيانة قيد التشغيل حالياً</p>
                        <p class="text-xs opacity-90 mt-0.5">يمكنك تعطيله في أي وقت لفتح النظام مجدداً لكافة المستخدمين.</p>
                    </div>
                    <x-filament::button
                        color="success"
                        icon="heroicon-m-check-circle"
                        wire:click="disableMaintenance"
                        wire:loading.attr="disabled">
                        تعطيل وضع الصيانة والعودة للعمل
                    </x-filament::button>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
