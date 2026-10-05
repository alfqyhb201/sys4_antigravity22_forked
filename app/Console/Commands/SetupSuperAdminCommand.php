<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SetupSuperAdminCommand extends Command
{
    /**
     * اسم ووصف أمر Artisan.
     *
     * @var string
     */
    protected $signature = 'app:setup-super-admin {identifier? : معرف المستخدم أو البريد الإلكتروني أو اسم المستخدم}';

    /**
     * وصف الأمر.
     *
     * @var string
     */
    protected $description = 'تثبيت دور المدير العام الأعلى (super_admin) وترقية مستخدم لهذا الدور';

    /**
     * تنفيذ الأمر.
     */
    public function handle(): int
    {
        $this->info('بدء تهيئة دور المدير العام الأعلى (Super Admin)...');

        // 1. إعادة تعيين الكاش الخاص بالصلاحيات
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. إنشاء دور super_admin إذا لم يكن موجوداً
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        // منح الدور كل الصلاحيات المتوفرة كإجراء إضافي
        $allPermissions = Permission::all();
        if ($allPermissions->isNotEmpty()) {
            $role->syncPermissions($allPermissions);
        }

        $this->info('تم التأكد من وجود دور [super_admin] في قاعدة البيانات.');

        // 3. البحث عن المستخدم
        $identifier = $this->argument('identifier');
        $user = null;

        if ($identifier) {
            $user = User::where('id', $identifier)
                ->orWhere('email', $identifier)
                ->orWhere('username', $identifier)
                ->first();

            if (! $user) {
                $this->error("تعذر العثور على أي مستخدم يطابق: {$identifier}");

                return self::FAILURE;
            }
        } else {
            // اختيار أول مستخدم كافتراضي
            $user = User::first();
            if (! $user) {
                $this->warn('لا يوجد أي مستخدمين مسجلين في النظام بعد.');

                return self::SUCCESS;
            }
        }

        // 4. ترقية المستخدم
        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
            $this->info("تمت ترقية المستخدم [{$user->name}] ({$user->email} / {$user->username}) إلى رتبة [super_admin] بنجاح!");
        } else {
            $this->info("المستخدم [{$user->name}] لديه رتبة [super_admin] مسبقاً.");
        }

        // مسح الكاش مجدداً بعد التعيين
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return self::SUCCESS;
    }
}
