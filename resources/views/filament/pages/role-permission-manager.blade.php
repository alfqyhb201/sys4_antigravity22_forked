<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        {{-- شريط التحكم الرئيسي العلوي --}}
        <div class="p-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm space-y-4">
            {{-- الصف العلوي: اختيار الدور وإجراءات الدور --}}
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {{-- قائمة الأدوار مع شارات الأعداد --}}
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-m-user-group class="w-5 h-5 text-primary-500" />
                            <span>اختر الدور المراد تخصيص صلاحياته:</span>
                        </label>
                        <button
                            type="button"
                            wire:click="$set('showNewRoleModal', true)"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline"
                        >
                            <x-heroicon-m-plus-circle class="w-4 h-4" />
                            <span>إنشاء دور جديد</span>
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->roles as $role)
                            @php
                                $isSelected = $selectedRoleId === $role->id;
                            @endphp
                            <button
                                type="button"
                                wire:click="selectRole({{ $role->id }})"
                                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all border {{ $isSelected ? 'bg-primary-500 text-white border-primary-500 shadow-md ring-2 ring-primary-500/20' : 'bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                            >
                                <span>{{ $role->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $isSelected ? 'bg-white/25 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                                    {{ $role->permissions_count }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- أزرار الإجراءات السريعة --}}
                <div class="flex items-center flex-wrap gap-2 pt-2 lg:pt-0 border-t lg:border-t-0 border-gray-100 dark:border-gray-800">
                    {{-- زر المزامنة التلقائية من النظام --}}
                    <x-filament::button
                        wire:click="syncSystemPermissions"
                        color="gray"
                        icon="heroicon-m-arrow-path"
                        size="sm"
                        title="فحص كود Filament واكتشاف أي صفحات أو نماذج جديدة تلقائياً ومزامنتها"
                    >
                        <span wire:loading.remove wire:target="syncSystemPermissions">مزامنة من النظام</span>
                        <span wire:loading wire:target="syncSystemPermissions">جارٍ الفحص والمزامنة...</span>
                    </x-filament::button>

                    {{-- زر فحص وتجربة الصلاحيات --}}
                    <x-filament::button
                        wire:click="$set('showTesterModal', true)"
                        color="warning"
                        icon="heroicon-m-beaker"
                        size="sm"
                    >
                        فحص واختبار الصلاحيات
                    </x-filament::button>

                    {{-- زر حفظ التغييرات --}}
                    <x-filament::button
                        wire:click="saveRolePermissions"
                        color="success"
                        icon="heroicon-m-check-badge"
                        size="sm"
                        class="shadow-sm"
                    >
                        <span wire:loading.remove wire:target="saveRolePermissions">حفظ صلاحيات الدور</span>
                        <span wire:loading wire:target="saveRolePermissions">جارٍ الحفظ...</span>
                    </x-filament::button>
                </div>
            </div>

            {{-- الصف الثاني: البحث والتبويبات والإجراءات الجماعية --}}
            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-col md:flex-row items-center justify-between gap-4">
                {{-- أزرار التبويبات --}}
                <div class="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl w-full md:w-auto">
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'resources')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 {{ $activeTab === 'resources' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-rectangle-stack class="w-4 h-4" />
                        <span>النماذج والموارد ({{ count($this->discoveredData['resources']) }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'pages')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 {{ $activeTab === 'pages' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-document-duplicate class="w-4 h-4" />
                        <span>الصفحات المخصصة ({{ count($this->discoveredData['pages']) }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'custom')"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 {{ $activeTab === 'custom' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-bolt class="w-4 h-4" />
                        <span>إجراءات خاصة ومالية ({{ count($this->discoveredData['custom']) }})</span>
                    </button>
                </div>

                {{-- مربع البحث والتحكم السريع --}}
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="relative flex-1 md:w-64">
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="searchQuery"
                            placeholder="بحث في الصلاحيات..."
                            class="w-full text-xs rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 pr-9 pl-3 py-2 focus:ring-primary-500 focus:border-primary-500 shadow-sm"
                        />
                        <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute right-3 top-2.5" />
                    </div>

                    <div class="flex items-center gap-1 text-xs">
                        <button
                            type="button"
                            wire:click="selectAllPermissions"
                            class="px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold transition-colors"
                        >
                            تحديد الكل
                        </button>
                        <button
                            type="button"
                            wire:click="deselectAllPermissions"
                            class="px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold transition-colors"
                        >
                            إلغاء الكل
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- عداد الصلاحيات المحددة للدور الحالي --}}
        <div class="flex items-center justify-between px-4 py-2.5 bg-primary-50/50 dark:bg-primary-950/20 border border-primary-100 dark:border-primary-900/30 rounded-xl text-xs text-primary-800 dark:text-primary-300">
            <div class="flex items-center gap-2">
                <x-heroicon-m-information-circle class="w-4 h-4 text-primary-500" />
                <span>
                    الدور المختار حالياً:
                    <strong class="font-bold text-primary-700 dark:text-primary-200">
                        {{ $this->roles->firstWhere('id', $selectedRoleId)?->name ?? 'لم يُحدد' }}
                    </strong>
                </span>
            </div>
            <div class="font-semibold">
                الصلاحيات المحددة: <span class="font-bold text-primary-600 dark:text-primary-400">{{ count($selectedPermissions) }}</span>
            </div>
        </div>

        {{-- التبويب 1: النماذج والموارد (Resources) --}}
        @if ($activeTab === 'resources')
            <div class="space-y-6">
                @forelse ($this->groupedResources as $groupName => $resources)
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm space-y-4">
                        {{-- رأس المجموعة --}}
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary-500"></span>
                                <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">{{ $groupName }}</h3>
                                <span class="text-xs text-gray-400 font-medium">({{ count($resources) }} نموذج)</span>
                            </div>
                            <button
                                type="button"
                                wire:click="toggleGroupAll('{{ $groupName }}')"
                                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline"
                            >
                                تبديل تحديد المجموعة
                            </button>
                        </div>

                        {{-- شبكة النماذج داخل المجموعة --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach ($resources as $entityKey => $res)
                                @php
                                    $crudPerms = array_column($res['permissions'], 'name');
                                    $selectedCount = count(array_intersect($crudPerms, $selectedPermissions));
                                    $allCrudSelected = count($crudPerms) === $selectedCount;
                                @endphp
                                <div class="bg-gray-50/80 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700/60 rounded-xl p-4 space-y-3 transition-all hover:border-primary-300 dark:hover:border-primary-700">
                                    {{-- رأس كرت النموذج --}}
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="p-1.5 bg-white dark:bg-gray-700 rounded-lg text-gray-600 dark:text-gray-300 shadow-2xs">
                                                <x-heroicon-m-folder class="w-4 h-4 text-primary-500" />
                                            </span>
                                            <div>
                                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $res['label'] }}</h4>
                                                <span class="text-[11px] text-gray-400 font-mono">{{ $entityKey }}</span>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="toggleResourceAll('{{ $entityKey }}')"
                                            class="text-[11px] px-2 py-0.5 rounded font-semibold transition-colors {{ $allCrudSelected ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300' }}"
                                            title="تحديد أو إلغاء كل عمليات هذا النموذج"
                                        >
                                            {{ $allCrudSelected ? 'محدد بالكامل' : ($selectedCount > 0 ? "{$selectedCount}/" . count($crudPerms) : 'الكل') }}
                                        </button>
                                    </div>

                                    {{-- خيارات CRUD --}}
                                    <div class="grid grid-cols-2 gap-1.5 pt-1">
                                        @foreach ($res['permissions'] as $actionKey => $perm)
                                            @php
                                                $isActionChecked = in_array($perm['name'], $selectedPermissions);
                                                $actionLabels = [
                                                    'view_any' => 'تصفح (قائمة)',
                                                    'view' => 'عرض تفاصيل',
                                                    'create' => 'إضافة جديد',
                                                    'update' => 'تعديل',
                                                    'delete' => 'حذف',
                                                ];
                                            @endphp
                                            <label class="flex items-center gap-2 p-1.5 rounded-lg cursor-pointer transition-colors text-xs {{ $isActionChecked ? 'bg-primary-50 dark:bg-primary-950/40 text-primary-800 dark:text-primary-200 font-bold' : 'hover:bg-gray-200/50 dark:hover:bg-gray-700/50 text-gray-600 dark:text-gray-400' }}">
                                                <input
                                                    type="checkbox"
                                                    wire:click="togglePermission('{{ $perm['name'] }}')"
                                                    @checked($isActionChecked)
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-3.5 h-3.5"
                                                />
                                                <span>{{ $actionLabels[$actionKey] ?? $actionKey }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl text-gray-500 text-sm">
                        لا توجد نماذج مطابقة للبحث.
                    </div>
                @endforelse
            </div>
        @endif

        {{-- التبويب 2: الصفحات ولوحات التحكم المخصصة (Pages) --}}
        @if ($activeTab === 'pages')
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-document-duplicate class="w-5 h-5 text-primary-500" />
                        <span>الصفحات ولوحات التحكم المكتشفة تلقائياً من النظام</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">تتيح هذه الصلاحيات إمكانية الدخول للوحات القيادة وصفحات الإعدادات والأرشيف الخاصة بالنظام.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse ($this->discoveredData['pages'] as $pageName => $page)
                        @php
                            $isPermChecked = in_array($page['permission'], $selectedPermissions);
                        @endphp
                        <div
                            wire:click="togglePermission('{{ $page['permission'] }}')"
                            class="flex items-start justify-between p-4 rounded-xl border cursor-pointer transition-all {{ $isPermChecked ? 'bg-primary-50/60 dark:bg-primary-950/30 border-primary-300 dark:border-primary-800 shadow-2xs' : 'bg-gray-50/80 dark:bg-gray-800/60 border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ $page['label'] }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-semibold">{{ $page['group'] }}</span>
                                </div>
                                @if (! empty($page['pages']) && count($page['pages']) > 1)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">تشمل: {{ implode('، ', $page['pages']) }}</p>
                                @endif
                                <p class="text-[11px] text-gray-400 font-mono">{{ $page['permission'] }}</p>
                            </div>

                            <input
                                type="checkbox"
                                @checked($isPermChecked)
                                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 w-4 h-4 mt-0.5 pointer-events-none"
                            />
                        </div>
                    @empty
                        <div class="col-span-full p-8 text-center text-gray-500 text-sm">
                            لا توجد صفحات مطابقة للبحث.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- التبويب 3: إجراءات خاصة ومالية (Custom Actions) --}}
        @if ($activeTab === 'custom')
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm space-y-4">
                <div class="border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-bolt class="w-5 h-5 text-amber-500" />
                        <span>الإجراءات الخاصة والصلاحيات المالية والتشغيلية</span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">صلاحيات وظيفية حساسة متعلقة بالدفع، الخصومات، التصدير، وإدارة التوزيع.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse ($this->discoveredData['custom'] as $permKey => $custom)
                        @php
                            $isPermChecked = in_array($permKey, $selectedPermissions);
                        @endphp
                        <div
                            wire:click="togglePermission('{{ $permKey }}')"
                            class="flex items-start justify-between p-4 rounded-xl border cursor-pointer transition-all {{ $isPermChecked ? 'bg-amber-50/60 dark:bg-amber-950/20 border-amber-300 dark:border-amber-800 shadow-2xs' : 'bg-gray-50/80 dark:bg-gray-800/60 border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ $custom['label'] }}</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 font-semibold">{{ $custom['group'] }}</span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $custom['description'] }}</p>
                                <p class="text-[11px] text-gray-400 font-mono">{{ $permKey }}</p>
                            </div>

                            <input
                                type="checkbox"
                                @checked($isPermChecked)
                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500 w-4 h-4 mt-0.5 pointer-events-none"
                            />
                        </div>
                    @empty
                        <div class="col-span-full p-8 text-center text-gray-500 text-sm">
                            لا توجد إجراءات مطابقة للبحث.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- شريط حفظ سفلي عائم --}}
        <div class="sticky bottom-4 z-10 flex items-center justify-between p-4 bg-white/95 dark:bg-gray-900/95 backdrop-blur border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xl">
            <div class="text-xs text-gray-600 dark:text-gray-300">
                <span>تم تحديد</span>
                <strong class="font-bold text-primary-600 dark:text-primary-400">{{ count($selectedPermissions) }}</strong>
                <span>صلاحية للدور:</span>
                <strong class="font-bold text-gray-800 dark:text-gray-100">{{ $this->roles->firstWhere('id', $selectedRoleId)?->name }}</strong>
            </div>

            <div class="flex items-center gap-2">
                <x-filament::button
                    wire:click="saveRolePermissions"
                    color="success"
                    icon="heroicon-m-check-badge"
                    size="md"
                >
                    <span wire:loading.remove wire:target="saveRolePermissions">حفظ كافة التغييرات</span>
                    <span wire:loading wire:target="saveRolePermissions">جارٍ الحفظ...</span>
                </x-filament::button>
            </div>
        </div>

        {{-- مودال إنشاء دور جديد --}}
        @if ($showNewRoleModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 max-w-md w-full border border-gray-200 dark:border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-m-shield-check class="w-5 h-5 text-primary-500" />
                            <span>إنشاء دور جديد</span>
                        </h3>
                        <button type="button" wire:click="$set('showNewRoleModal', false)" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">اسم الدور (باللغة الإنجليزية أو العربية):</label>
                        <input
                            type="text"
                            wire:model="newRoleName"
                            placeholder="مثال: quality_inspector أو مشرف الجودة"
                            class="w-full text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-2.5 focus:ring-primary-500 focus:border-primary-500"
                        />
                        @error('newRoleName')
                            <span class="text-xs text-red-500">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button
                            type="button"
                            wire:click="$set('showNewRoleModal', false)"
                            color="gray"
                            size="sm"
                        >
                            إلغاء
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            wire:click="createNewRole"
                            color="primary"
                            size="sm"
                        >
                            إنشاء وحفظ
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- مودال فحص وتجربة الصلاحيات (Permission Tester) --}}
        @if ($showTesterModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 max-w-lg w-full border border-gray-200 dark:border-gray-800 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                        <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <x-heroicon-m-beaker class="w-5 h-5 text-warning-500" />
                            <span>محاكي فحص واختبار الصلاحيات (Live Simulator)</span>
                        </h3>
                        <button type="button" wire:click="$set('showTesterModal', false)" class="text-gray-400 hover:text-gray-600">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="space-y-4 text-xs">
                        {{-- نوع الاختبار: دور أو مستخدم --}}
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" wire:model.live="testSubjectType" value="role" class="text-primary-600 focus:ring-primary-500" />
                                <span>فحص صلاحية لدور (Role)</span>
                            </label>
                            <label class="flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                                <input type="radio" wire:model.live="testSubjectType" value="user" class="text-primary-600 focus:ring-primary-500" />
                                <span>فحص صلاحية لمستخدم (User)</span>
                            </label>
                        </div>

                        {{-- اختيار الهدف --}}
                        @if ($testSubjectType === 'role')
                            <div class="space-y-1">
                                <label class="font-bold text-gray-700 dark:text-gray-300">اختر الدور:</label>
                                <select
                                    wire:model="testSubjectId"
                                    class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-2.5"
                                >
                                    <option value="">-- اختر دوراً --</option>
                                    @foreach ($this->roles as $r)
                                        <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->permissions_count }} صلاحية)</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="space-y-1">
                                <label class="font-bold text-gray-700 dark:text-gray-300">اختر المستخدم:</label>
                                <select
                                    wire:model="testSubjectId"
                                    class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-2.5"
                                >
                                    <option value="">-- اختر مستخدماً --</option>
                                    @foreach ($this->users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- اختيار الصلاحية مع إمكانية البحث الفوري --}}
                        <div
                            x-data="{
                                open: false,
                                search: '',
                                selected: @entangle('testPermission'),
                                permissions: {{ \Illuminate\Support\Js::from(
                                    collect(app(\App\Services\PermissionDiscoveryService::class)->getAllDiscoveredPermissions())->map(function ($pData, $pName) {
                                        return [
                                            'name' => $pName,
                                            'label' => $pData['label'] ?? $pName,
                                            'group' => $pData['group'] ?? '',
                                        ];
                                    })->values()
                                ) }},
                                get filteredPermissions() {
                                    if (! this.search) return this.permissions;
                                    const q = this.search.toLowerCase().trim();
                                    return this.permissions.filter(p =>
                                        p.name.toLowerCase().includes(q) ||
                                        p.label.toLowerCase().includes(q) ||
                                        p.group.toLowerCase().includes(q)
                                    );
                                },
                                get selectedLabel() {
                                    const found = this.permissions.find(p => p.name === this.selected);
                                    return found ? `${found.label} (${found.name})` : '';
                                },
                                selectPermission(name) {
                                    this.selected = name;
                                    this.open = false;
                                    this.search = '';
                                },
                                clearSelection() {
                                    this.selected = '';
                                    this.search = '';
                                }
                            }"
                            class="space-y-1 relative"
                            @click.away="open = false"
                        >
                            <label class="font-bold text-gray-700 dark:text-gray-300 flex items-center justify-between text-xs">
                                <span>اختر الصلاحية المراد التحقق منها:</span>
                                <span x-show="selected" class="text-[11px] font-mono text-primary-600 dark:text-primary-400 font-semibold" x-text="selected"></span>
                            </label>

                            {{-- الزر / الحقل الرئيسي --}}
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="open = !open; if(open) { $nextTick(() => $refs.permissionSearchInput.focus()); }"
                                    class="w-full flex items-center justify-between text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-2.5 shadow-sm hover:border-primary-500 transition-colors"
                                >
                                    <span class="truncate font-mono" x-text="selectedLabel || '-- اضغط للبحث واختيار الصلاحية --'"></span>
                                    <div class="flex items-center gap-1">
                                        <template x-if="selected">
                                            <span
                                                @click.stop="clearSelection()"
                                                class="p-0.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400 hover:text-red-500"
                                                title="إلغاء التحديد"
                                            >
                                                <x-heroicon-m-x-mark class="w-4 h-4" />
                                            </span>
                                        </template>
                                        <x-heroicon-m-chevron-up-down class="w-4 h-4 text-gray-400" />
                                    </div>
                                </button>

                                {{-- القائمة المنسدلة للبحث الفوري --}}
                                <div
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="opacity-100 scale-100"
                                    x-transition:leave-end="opacity-0 scale-95"
                                    class="absolute z-50 mt-1 w-full rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-xl p-2 space-y-2"
                                    style="display: none;"
                                >
                                    {{-- حقل البحث الفوري --}}
                                    <div class="relative">
                                        <input
                                            x-ref="permissionSearchInput"
                                            type="text"
                                            x-model="search"
                                            placeholder="ابحث بالاسم العربي أو الإنجليزي (مثل: social, فواتير, نشر)..."
                                            class="w-full text-xs rounded-lg border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 pr-8 pl-8 py-2 focus:ring-primary-500 focus:border-primary-500"
                                            @keydown.escape="open = false"
                                        />
                                        <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute right-2.5 top-2.5" />
                                        <button
                                            type="button"
                                            x-show="search"
                                            @click="search = ''; $refs.permissionSearchInput.focus()"
                                            class="absolute left-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        >
                                            <x-heroicon-m-x-circle class="w-4 h-4" />
                                        </button>
                                    </div>

                                    {{-- شريط عداد النتائج --}}
                                    <div class="flex items-center justify-between text-[10px] text-gray-400 px-1">
                                        <span>النتائج المطابقة:</span>
                                        <span class="font-bold text-primary-500" x-text="filteredPermissions.length"></span>
                                    </div>

                                    {{-- قائمة الصلاحيات القابلة للتمرير --}}
                                    <div class="max-h-56 overflow-y-auto space-y-1 divide-y divide-gray-50 dark:divide-gray-800/60">
                                        <template x-for="perm in filteredPermissions" :key="perm.name">
                                            <button
                                                type="button"
                                                @click="selectPermission(perm.name)"
                                                class="w-full text-right px-2.5 py-1.5 rounded-lg text-xs hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors flex items-center justify-between gap-2 group"
                                                :class="selected === perm.name ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-bold' : 'text-gray-700 dark:text-gray-300'"
                                            >
                                                <div class="flex flex-col min-w-0">
                                                    <span class="truncate" x-text="perm.label"></span>
                                                    <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 truncate" x-text="perm.name"></span>
                                                </div>
                                                <template x-if="perm.group">
                                                    <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400" x-text="perm.group"></span>
                                                </template>
                                            </button>
                                        </template>

                                        <div x-show="filteredPermissions.length === 0" class="py-4 text-center text-xs text-gray-400">
                                            لا توجد صلاحية مطابقة لبحثك
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- نتيجة الاختبار --}}
                        @if ($testResult)
                            <div class="p-4 rounded-xl border {{ $testResult['hasAccess'] ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-300 text-emerald-900 dark:text-emerald-200' : 'bg-red-50 dark:bg-red-950/30 border-red-300 text-red-900 dark:text-red-200' }} space-y-1">
                                <div class="flex items-center gap-2 font-bold text-sm">
                                    @if ($testResult['hasAccess'])
                                        <x-heroicon-m-check-circle class="w-5 h-5 text-emerald-500" />
                                        <span>نتيجة الفحص: مسموح وممنوح بالوصول (Granted)</span>
                                    @else
                                        <x-heroicon-m-x-circle class="w-5 h-5 text-red-500" />
                                        <span>نتيجة الفحص: غير مسموح بالوصول (Denied)</span>
                                    @endif
                                </div>
                                <p class="text-xs">{{ $testResult['reason'] }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <x-filament::button
                            type="button"
                            wire:click="$set('showTesterModal', false)"
                            color="gray"
                            size="sm"
                        >
                            إغلاق
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            wire:click="runPermissionTest"
                            color="warning"
                            size="sm"
                            icon="heroicon-m-magnifying-glass"
                        >
                            بدء الفحص
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
