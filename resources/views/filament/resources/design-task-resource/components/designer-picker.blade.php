@php
    $statePath = $getStatePath();
    $designers = \App\Models\Designer::with('user')->get();
@endphp

<div
    x-data="{
        state: $wire.{{ $applyStateBindingModifiers('entangle(\'' . $statePath . '\')') }},
        designers: @js($designers->map(fn ($d) => ['id' => $d->id, 'name' => $d->user?->name ?? 'مصمم #' . $d->id])->values()),
        select(id) {
            this.state = (this.state == id) ? null : id;
        },
        getSelectedName() {
            const found = this.designers.find(d => d.id == this.state);
            return found ? found.name : '';
        }
    }"
    class="space-y-2 w-full pt-1"
>
    {{-- السطر الأول: عنوان الحقل وشارة التحديد --}}
    <div class="flex items-center justify-between gap-2 w-full">
        <div class="flex items-center gap-1.5">
            <label class="text-sm font-semibold text-gray-950 dark:text-white">
                المصمم الموكل
            </label>
            <span class="text-danger-600 dark:text-danger-400 font-bold">*</span>
            <span class="text-xs text-gray-400 dark:text-gray-500 hidden md:inline">
                (اختيار فردي)
            </span>
        </div>

        {{-- شارة المصمم المحدد حالياً --}}
        <div x-show="state" x-cloak>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300 border border-primary-300 dark:border-primary-700">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span>
                <span x-text="getSelectedName()"></span>
                <button
                    type="button"
                    @click="state = null"
                    class="text-gray-400 hover:text-danger-600 transition-colors mr-0.5 cursor-pointer"
                    title="إلغاء التحديد"
                >
                    ✕
                </button>
            </span>
        </div>
    </div>

    {{-- على شاشة الهاتف: قائمة منسدلة مدمجة وسريعة لا تأخذ أي حيز --}}
    <div class="block md:hidden w-full">
        <select
            x-model="state"
            class="block w-full py-2 px-3 text-xs sm:text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-xs focus:border-primary-500 focus:ring-primary-500 text-gray-900 dark:text-white"
        >
            <option value="">-- اختر المصمم الموكل --</option>
            @foreach($designers as $designer)
                <option value="{{ $designer->id }}">
                    {{ $designer->user?->name ?? 'مصمم #' . $designer->id }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- على الشاشات الكبيرة (كمبيوتر/تابلت): أزرار وبطاقات المصمّمين المطابقة لواجهة الطلبات --}}
    <div class="hidden md:flex flex-wrap items-center gap-2 w-full">
        @foreach($designers as $designer)
            <button
                type="button"
                @click="select({{ $designer->id }})"
                :class="state == {{ $designer->id }}
                    ? 'bg-primary-50 dark:bg-primary-950/40 border-primary-500 text-primary-700 dark:text-primary-300 ring-1 ring-primary-500/30 font-semibold shadow-xs'
                    : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-850'"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all duration-75 cursor-pointer select-none"
            >
                <span
                    :class="state == {{ $designer->id }} ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600'"
                    class="w-2 h-2 rounded-full transition-colors shrink-0"
                ></span>
                <span>{{ $designer->user?->name ?? 'مصمم #' . $designer->id }}</span>
            </button>
        @endforeach
    </div>

    @error($statePath)
        <p class="text-xs text-danger-600 dark:text-danger-400 mt-1">{{ $message }}</p>
    @enderror
</div>
