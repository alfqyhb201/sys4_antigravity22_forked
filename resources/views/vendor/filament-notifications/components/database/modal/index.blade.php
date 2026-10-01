@props([
    'notifications',
    'unreadNotificationsCount',
])

@php
    use Filament\Support\Enums\Alignment;

    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();

    $totalCount = $notifications->count();
    $newOrdersCount = 0;
    $revisionsCount = 0;
    $unreadCount = $unreadNotificationsCount;

    foreach ($notifications as $notification) {
        $title = $notification->data['title'] ?? '';
        $body = $notification->data['body'] ?? '';
        $text = $title . ' ' . $body;

        if (
            str_contains($text, 'تعديل') ||
            str_contains($text, 'تعديلات') ||
            str_contains(strtolower($text), 'revision') ||
            str_contains(strtolower($text), 'changes')
        ) {
            $revisionsCount++;
        } elseif (
            str_contains($text, 'جديد') ||
            str_contains($text, 'إسناد') ||
            str_contains($text, 'طلب') ||
            str_contains(strtolower($text), 'new') ||
            str_contains(strtolower($text), 'assigned')
        ) {
            $newOrdersCount++;
        }
    }
@endphp

<x-filament::modal
    :alignment="$hasNotifications ? null : Alignment::Center"
    close-button
    :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')"
    :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
    :icon="$hasNotifications ? null : 'heroicon-o-bell-slash'"
    :icon-alias="$hasNotifications ? null : 'notifications::database.modal.empty-state'"
    :icon-color="$hasNotifications ? null : 'gray'"
    id="database-notifications"
    slide-over
    :sticky-header="$hasNotifications"
    width="md"
>
    @if ($hasNotifications)
        <div x-data="{ activeTab: 'all' }" class="flex flex-col gap-y-4">
            {{-- رأس النافذة: العنوان وأزرار التحكم --}}
            <div class="border-b border-gray-200 pb-3 dark:border-white/10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold text-gray-950 dark:text-white">الإشعارات والتنبيهات</span>
                        @if ($unreadNotificationsCount)
                            <span class="inline-flex items-center justify-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white dark:bg-primary-500">
                                {{ $unreadNotificationsCount }} جديد
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-x-2 text-xs">
                        @if ($unreadNotificationsCount)
                            <button
                                type="button"
                                wire:click="markAllNotificationsAsRead"
                                class="font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
                            >
                                تحديد الكل كمقروء
                            </button>
                            <span class="text-gray-300 dark:text-gray-600">•</span>
                        @endif
                        <button
                            type="button"
                            wire:click="clearNotifications"
                            x-on:click="close()"
                            class="font-medium text-red-600 hover:text-red-500 hover:underline dark:text-red-400"
                        >
                            مسح الكل
                        </button>
                    </div>
                </div>

                {{-- 📊 1. الشريط الإحصائي المختصر (Mini Summary Bar) --}}
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <div class="flex flex-col items-center justify-center rounded-lg border border-amber-200/60 bg-amber-50/50 p-2 text-center dark:border-amber-500/20 dark:bg-amber-950/20">
                        <span class="text-xs text-amber-700 dark:text-amber-400 font-medium">غير مقروء</span>
                        <span class="text-sm font-bold text-amber-900 dark:text-amber-300">{{ $unreadCount }}</span>
                    </div>

                    <div class="flex flex-col items-center justify-center rounded-lg border border-emerald-200/60 bg-emerald-50/50 p-2 text-center dark:border-emerald-500/20 dark:bg-emerald-950/20">
                        <span class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">طلبات جديدة</span>
                        <span class="text-sm font-bold text-emerald-900 dark:text-emerald-300">{{ $newOrdersCount }}</span>
                    </div>

                    <div class="flex flex-col items-center justify-center rounded-lg border border-rose-200/60 bg-rose-50/50 p-2 text-center dark:border-rose-500/20 dark:bg-rose-950/20">
                        <span class="text-xs text-rose-700 dark:text-rose-400 font-medium">طلبات تعديل</span>
                        <span class="text-sm font-bold text-rose-900 dark:text-rose-300">{{ $revisionsCount }}</span>
                    </div>
                </div>

                {{-- 📑 2. شريط التبويبات (Interactive Filter Tabs) --}}
                <div class="mt-3 flex items-center gap-1 rounded-lg bg-gray-100 p-1 text-xs font-medium dark:bg-white/5">
                    <button
                        type="button"
                        x-on:click="activeTab = 'all'"
                        :class="activeTab === 'all' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white font-bold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="flex-1 rounded-md px-2 py-1.5 text-center transition-all"
                    >
                        الكل ({{ $totalCount }})
                    </button>

                    <button
                        type="button"
                        x-on:click="activeTab = 'unread'"
                        :class="activeTab === 'unread' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white font-bold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="flex-1 rounded-md px-2 py-1.5 text-center transition-all"
                    >
                        غير مقروء ({{ $unreadCount }})
                    </button>

                    <button
                        type="button"
                        x-on:click="activeTab = 'new_orders'"
                        :class="activeTab === 'new_orders' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white font-bold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="flex-1 rounded-md px-2 py-1.5 text-center transition-all"
                    >
                        طلبات جديدة ({{ $newOrdersCount }})
                    </button>

                    <button
                        type="button"
                        x-on:click="activeTab = 'revisions'"
                        :class="activeTab === 'revisions' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white font-bold' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                        class="flex-1 rounded-md px-2 py-1.5 text-center transition-all"
                    >
                        طلبات تعديل ({{ $revisionsCount }})
                    </button>
                </div>
            </div>

            {{-- 🔔 قائمة الإشعارات المفروزة --}}
            <div
                @class([
                    '-mx-6 -mt-2 divide-y divide-gray-200 dark:divide-white/10',
                    '-mb-6' => ! $isPaginated,
                    'border-b border-gray-200 dark:border-white/10' => $isPaginated,
                ])
            >
                @foreach ($notifications as $notification)
                    @php
                        $title = $notification->data['title'] ?? '';
                        $body = $notification->data['body'] ?? '';
                        $text = $title . ' ' . $body;

                        $isRevision = (
                            str_contains($text, 'تعديل') ||
                            str_contains($text, 'تعديلات') ||
                            str_contains(strtolower($text), 'revision') ||
                            str_contains(strtolower($text), 'changes')
                        );

                        $isNewOrder = ! $isRevision && (
                            str_contains($text, 'جديد') ||
                            str_contains($text, 'إسناد') ||
                            str_contains($text, 'طلب') ||
                            str_contains(strtolower($text), 'new') ||
                            str_contains(strtolower($text), 'assigned')
                        );

                        $isUnread = $notification->unread();
                    @endphp

                    <div
                        x-show="
                            activeTab === 'all' ||
                            (activeTab === 'unread' && {{ $isUnread ? 'true' : 'false' }}) ||
                            (activeTab === 'new_orders' && {{ $isNewOrder ? 'true' : 'false' }}) ||
                            (activeTab === 'revisions' && {{ $isRevision ? 'true' : 'false' }})
                        "
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        @class([
                            'relative before:absolute before:start-0 before:h-full before:w-1 before:bg-primary-600 dark:before:bg-primary-500' => $isUnread,
                            'bg-primary-50/20 dark:bg-primary-950/10' => $isUnread,
                        ])
                    >
                        {{ $this->getNotification($notification)->inline() }}
                    </div>
                @endforeach
            </div>

            @if ($isPaginated)
                <div class="mt-2">
                    <x-filament::pagination :paginator="$notifications" />
                </div>
            @endif
        </div>
    @endif
</x-filament::modal>
