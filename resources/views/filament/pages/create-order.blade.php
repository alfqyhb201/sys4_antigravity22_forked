<x-filament-panels::page>
    <div
        x-data="{
            selected: @js(array_values(array_map('intval', $selectedDesigners))),
            allIds: @js(array_values(array_map('intval', $this->getDesignerIds()))),
            toggle(id) {
                id = parseInt(id);
                if (this.selected.includes(id)) {
                    this.selected = this.selected.filter(i => i !== id);
                } else {
                    this.selected.push(id);
                }
                $wire.toggleDesigner(id);
            },
            selectAll() {
                this.selected = [...this.allIds];
                $wire.selectAllDesigners();
            },
            deselectAll() {
                this.selected = [];
                $wire.deselectAllDesigners();
            }
        }"
    >
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between w-full">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-m-user-group" class="w-5 h-5 text-primary-500" />
                        <span>جاهزية المصممين للتوزيع التلقائي</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-filament::button
                            type="button"
                            @click="selectAll()"
                            color="gray"
                            size="xs"
                            variant="outlined"
                        >
                            تحديد الكل
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            @click="deselectAll()"
                            color="gray"
                            size="xs"
                            variant="outlined"
                        >
                            تفريغ
                        </x-filament::button>
                    </div>
                </div>
            </x-slot>

            <div class="flex flex-wrap items-center gap-2">
                @foreach($this->getDesigners() as $designer)
                    <button
                        type="button"
                        @click="toggle({{ $designer->id }})"
                        :class="selected.includes({{ $designer->id }})
                            ? 'bg-primary-50 dark:bg-primary-950/40 border-primary-500 text-primary-700 dark:text-primary-300 ring-1 ring-primary-500/30 font-semibold'
                            : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700'"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all duration-75 cursor-pointer select-none"
                    >
                        <span
                            :class="selected.includes({{ $designer->id }}) ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'"
                            class="w-2 h-2 rounded-full transition-colors"
                        ></span>
                        <span>{{ $designer->user->name }}</span>
                    </button>
                @endforeach
            </div>

            <div class="mt-2.5 text-xs text-gray-500 flex items-center justify-between">
                <span>
                    المحدد: <strong class="text-gray-800 dark:text-gray-200" x-text="selected.length"></strong> من أصل {{ count($this->getDesigners()) }} مصمم
                </span>
                <span x-show="selected.length === 0" x-cloak class="text-amber-600 dark:text-amber-400">
                    ⚠️ سيتم التوزيع افتراضياً على جميع المصممين المتاحين
                </span>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <span class="font-bold text-base">إنشاء طلب جديد</span>
                
                {{-- أزرار التبديل بين الإدخال الفردي واللصق المجمع --}}
                <div class="inline-flex p-1 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        wire:click="$set('creationMode', 'single')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $creationMode === 'single' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-xs ring-1 ring-black/5' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-user class="w-4 h-4" />
                        <span>طلب فردي</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('creationMode', 'bulk')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer {{ $creationMode === 'bulk' ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 shadow-xs ring-1 ring-black/5' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}"
                    >
                        <x-heroicon-m-clipboard-document-list class="w-4 h-4" />
                        <span>لصق مجمع (عدة عملاء)</span>
                    </button>
                </div>
            </div>
        </x-slot>

        @if($creationMode === 'single')
            {{-- وضع الإدخال الفردي --}}
            <x-filament-panels::form wire:submit="create">
                {{ $this->form }}

                <div class="flex justify-start mt-4">
                    <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                        إرسال الطلب
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        @else
            {{-- وضع اللصق المجمع لعدة عملاء --}}
            <form wire:submit.prevent="createBulk" class="space-y-4" x-data="{
                bulkText: @entangle('bulk_companies'),
                get companiesCount() {
                    if (!this.bulkText) return 0;
                    return this.bulkText.split('\n')
                        .map(l => l.replace(/^[\d\.\-\*\•\(\)\[\]\:\s]+/u, '').trim())
                        .filter(l => l.length > 0).length;
                }
            }">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {{-- حقل لصق أسماء العملاء --}}
                    <div class="lg:col-span-2 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                قائمة أسماء العملاء <span class="text-danger-600">*</span>
                            </label>
                            <span
                                x-show="companiesCount > 0"
                                x-cloak
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-200 dark:border-primary-800"
                            >
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-500 animate-pulse"></span>
                                <span x-text="'تم التعرف على ' + companiesCount + ' عميل'"></span>
                            </span>
                        </div>

                        <textarea
                            wire:model.defer="bulk_companies"
                            rows="6"
                            placeholder="الصق أسماء العملاء هنا (كل اسم في سطر جديد)...&#10;مثال:&#10;شركة الأمل للتجارة&#10;مؤسسة النور للدعاية&#10;متجر الأناقة"
                            class="block w-full rounded-xl border-gray-300 shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:text-sm font-sans placeholder:text-gray-400"
                            required
                        ></textarea>

                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                            <x-heroicon-m-information-circle class="w-4 h-4 text-gray-400 shrink-0" />
                            <span>يمكنك نسخ ولصق أسماء العملاء مباشرة من Excel أو WhatsApp أو أي قائمة — سيتم استخراج كل سطر كطلب منفصل تلقائياً.</span>
                        </p>
                    </div>

                    {{-- إعدادات التكليف والوصف --}}
                    <div class="space-y-4 rounded-xl p-4 bg-gray-50/70 dark:bg-gray-800/40 border border-gray-200/80 dark:border-gray-800">
                        {{-- تحديد المصمم --}}
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                تعيين لمصمم محدد
                            </label>
                            <select
                                wire:model.defer="bulk_designer_id"
                                class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:text-sm"
                            >
                                <option value="">🔄 توزيع تلقائي ذكي (Round Robin)</option>
                                @foreach($this->getDesigners() as $designer)
                                    <option value="{{ $designer->id }}">{{ $designer->user->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-500">إذا تُرك فارغاً سيتم توزيع الطلبات بالتساوي بين المصممين الجاهزين.</p>
                        </div>

                        {{-- وصف موحد للطلبات --}}
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                وصف موحد للطلبات (اختياري)
                            </label>
                            <textarea
                                wire:model.defer="bulk_description"
                                rows="2"
                                placeholder="وصف أو تعليمات مشتركة لجميع الطلبات..."
                                class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:text-sm placeholder:text-gray-400"
                            ></textarea>
                        </div>
                    </div>
                </div>

                {{-- زر الإرسال المجمع --}}
                <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-800">
                    <div class="text-xs text-gray-500">
                        <span>سيتم إنشاء طلب مستقل لكل عميل في القائمة وإرسال الإشعارات للمصممين.</span>
                    </div>

                    <x-filament::button type="submit" icon="heroicon-o-paper-airplane" color="primary">
                        <span x-show="companiesCount > 0" x-cloak x-text="'إرسال ' + companiesCount + ' طلبات دفعة واحدة 🚀'"></span>
                        <span x-show="companiesCount === 0">إرسال الطلبات المجمعة</span>
                    </x-filament::button>
                </div>
            </form>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            قائمة الطلبات
        </x-slot>

        <x-slot name="headerEnd">
            <x-filament::tabs label="تبويبات الطلبات">
                @foreach($this->getTabs() as $key => $tab)
                    <x-filament::tabs.item
                        :active="$activeTab === (string) $key"
                        wire:click="$set('activeTab', '{{ $key }}')"
                        :badge="$tab['badge']"
                    >
                        {{ $tab['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
