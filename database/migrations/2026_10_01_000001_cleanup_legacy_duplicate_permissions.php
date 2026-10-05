<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. ترحيل الصلاحيات الملغاة إلى الصلاحيات الموحدة البديلة
        $replacements = [
            'view_client_financial_detail' => 'view_client_financial',
            'view_recently_sent_archive' => 'view_archive',
        ];

        foreach ($replacements as $oldName => $newName) {
            $oldPerm = Permission::where('name', $oldName)->where('guard_name', 'web')->first();
            $newPerm = Permission::where('name', $newName)->where('guard_name', 'web')->first();

            if ($oldPerm && $newPerm) {
                // ترحيل الأدوار المرتبطة بالصلاحية القديمة
                foreach ($oldPerm->roles as $role) {
                    if (! $role->hasPermissionTo($newPerm)) {
                        $role->givePermissionTo($newPerm);
                    }
                }

                // ترحيل المستخدمين المرتبطين بالصلاحية القديمة مباشرة
                foreach ($oldPerm->users as $user) {
                    if (! $user->hasDirectPermission($newPerm)) {
                        $user->givePermissionTo($newPerm);
                    }
                }
            }

            // 3. حذف الصلاحية القديمة إن وُجدت
            if ($oldPerm) {
                $oldPerm->delete();
            }
        }

        // 4. تفريغ كاش الصلاحيات
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // إعادة إنشاء الصلاحيات الملغاة في حال التراجع
        Permission::firstOrCreate(['name' => 'view_client_financial_detail', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_recently_sent_archive', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
