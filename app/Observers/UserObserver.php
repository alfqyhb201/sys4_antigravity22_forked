<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    /**
     * مسح الكاش الخاص بعداد المستخدمين عند إنشاء مستخدم جديد.
     */
    public function created(User $user): void
    {
        Cache::forget('filament_users_count');
    }

    /**
     * مسح الكاش الخاص بعداد المستخدمين عند حذف مستخدم.
     */
    public function deleted(User $user): void
    {
        Cache::forget('filament_users_count');
    }

    /**
     * مسح الكاش الخاص بعداد المستخدمين عند استعادة مستخدم.
     */
    public function restored(User $user): void
    {
        Cache::forget('filament_users_count');
    }

    /**
     * مسح الكاش الخاص بعداد المستخدمين عند الحذف النهائي.
     */
    public function forceDeleted(User $user): void
    {
        Cache::forget('filament_users_count');
    }
}
