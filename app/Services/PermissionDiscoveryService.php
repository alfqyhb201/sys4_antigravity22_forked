<?php

namespace App\Services;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * خدمة استكشاف ومزامنة الصلاحيات ديناميكياً من كود Filament.
 *
 * تقوم بقراءة كافة الـ Resources والـ Pages والـ Custom Actions المسجلة
 * ومقارنتها بقاعدة البيانات ومزامنتها تلقائياً بدون الحاجة إلى Seeder.
 */
class PermissionDiscoveryService
{
    /**
     * الإجراءات القياسية لنماذج الموارد (CRUD).
     */
    public const CRUD_ACTIONS = [
        'view_any' => 'تصفح وعرض القائمة',
        'view' => 'عرض التفاصيل',
        'create' => 'إضافة جديد',
        'update' => 'تعديل',
        'delete' => 'حذف',
    ];

    /**
     * خريطة الصلاحيات المعروفة لصفحات Filament المخصصة.
     *
     * @var array<string, array{permission: string, label: string, group?: string}>
     */
    protected static array $pagePermissionOverrides = [
        'Dashboard' => [
            'permission' => 'view_admin_dashboard',
            'label' => 'الرئيسية (لوحة المشرف العام)',
            'group' => 'لوحات التحكم المخصصة',
        ],
        'DesignerDashboard' => [
            'permission' => 'view_designer_dashboard',
            'label' => 'لوحة المصمم',
            'group' => 'لوحات التحكم المخصصة',
        ],
        'ReviewerDashboard' => [
            'permission' => 'view_reviewer_dashboard',
            'label' => 'لوحة المراجع',
            'group' => 'لوحات التحكم المخصصة',
        ],
        'SupervisorDashboard' => [
            'permission' => 'view_supervisor_dashboard',
            'label' => 'واجهة المشرف',
            'group' => 'لوحات التحكم المخصصة',
        ],
        'AccountingDashboard' => [
            'permission' => 'view_financial_reports',
            'label' => 'لوحة التحكم المالية',
            'group' => 'المالية',
        ],
        'ClientFinancialDetail' => [
            'permission' => 'view_client_financial',
            'label' => 'الملف المالي للعميل',
            'group' => 'المالية',
        ],
        'DesignerDistribution' => [
            'permission' => 'view_designer_distribution',
            'label' => 'توزيع المصممين',
            'group' => 'التخطيط',
        ],
        'TagDistribution' => [
            'permission' => 'view_tag_distribution',
            'label' => 'توزيع التاقات',
            'group' => 'التخطيط',
        ],
        'CreateOrder' => [
            'permission' => 'view_create_order',
            'label' => 'إنشاء وتعديل الطلبات',
            'group' => 'التخطيط',
        ],
        'SendingFollowUp' => [
            'permission' => 'view_sending_follow_up',
            'label' => 'واجهة الإرسال والمتابعة',
            'group' => 'العمليات',
        ],
        'SocialMediaPublishing' => [
            'permission' => 'view_social_media_publishing',
            'label' => 'واجهة السوشيال ميديا والنشر',
            'group' => 'CRM',
        ],
        'ArchivedClients' => [
            'permission' => 'view_archive',
            'label' => 'أرشيف العملاء والتصاميم',
            'group' => 'الأرشيف',
        ],
        'ArchivedClientDesigns' => [
            'permission' => 'view_archive',
            'label' => 'أرشيف تصاميم العملاء',
            'group' => 'الأرشيف',
        ],
        'RecentlySentArchive' => [
            'permission' => 'view_archive',
            'label' => 'أرشيف المنشورات والتصاميم الحديثة',
            'group' => 'الأرشيف',
        ],
        'GeneralSettingsPage' => [
            'permission' => 'manage_settings',
            'label' => 'الإعدادات العامة',
            'group' => 'الإعدادات',
        ],
        'CurrencySettingsPage' => [
            'permission' => 'manage_settings',
            'label' => 'إعدادات العملات',
            'group' => 'الإعدادات',
        ],
        'ActivityLogPage' => [
            'permission' => 'view_activity_log',
            'label' => 'سجل النشاطات',
            'group' => 'الإعدادات',
        ],
        'ActiveSessionsPage' => [
            'permission' => 'view_active_sessions',
            'label' => 'نشاط وجلسات المستخدمين',
            'group' => 'الإعدادات',
        ],
        'MediaManagerPage' => [
            'permission' => 'view_media_manager',
            'label' => 'إدارة الوسائط والتخزين',
            'group' => 'الإعدادات',
        ],
    ];

    /**
     * الصلاحيات والإجراءات الخاصة (مثل المالية، التكليفات، إلخ).
     *
     * @var array<string, array{label: string, group: string, description: string}>
     */
    protected static array $customActions = [
        'assign_roles' => [
            'label' => 'تعديل وتعيين الأدوار للمستخدمين',
            'group' => 'المستخدمون',
            'description' => 'صلاحية تغيير وتعيين الأدوار الوظيفية في شاشة تعديل المستخدم',
        ],
        'manage_user_permissions' => [
            'label' => 'تخصيص الصلاحيات المباشرة للمستخدمين',
            'group' => 'المستخدمون',
            'description' => 'صلاحية منح أو سحب صلاحيات مباشرة إضافية للمستخدمين',
        ],
        'record_payment' => [
            'label' => 'تسديد السندات والدفعات',
            'group' => 'المالية',
            'description' => 'القدرة على تسجيل حركات القبض وتأكيد استلام الدفعات',
        ],
        'approve_discount' => [
            'label' => 'الموافقة على الخصومات',
            'group' => 'المالية',
            'description' => 'صلاحية اعتماد وتمرير خصومات للعملاء في الفواتير',
        ],
        'export_financial_data' => [
            'label' => 'تصدير البيانات المالية',
            'group' => 'المالية',
            'description' => 'صلاحية تصدير كشوفات الحساب والفواتير لملفات Excel و PDF',
        ],
        'view_client_financial' => [
            'label' => 'الملف المالي وقسم المالية للعميل',
            'group' => 'المالية',
            'description' => 'الاطلاع على الملف المالي وصفحة التفاصيل والحركات المالية داخل بطاقة العميل',
        ],
        'edit_sending_time' => [
            'label' => 'تعديل وقت الإرسال',
            'group' => 'العمليات',
            'description' => 'القدرة على تغيير نافذة أوقات إرسال المهام اليومية',
        ],
    ];

    /**
     * استكشاف كافة النماذج (Resources) المسجلة في لوحة تحكم Filament.
     *
     * @return array<string, array{
     *     class: class-string<Resource>,
     *     entity: string,
     *     label: string,
     *     group: string,
     *     permissions: array<string, array{name: string, label: string}>
     * }>
     */
    public function discoverResources(): array
    {
        $panel = Filament::getPanel('admin');
        $resourceClasses = $panel->getResources();
        $discovered = [];

        foreach ($resourceClasses as $resourceClass) {
            if (! is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            // استبعاد RoleResource إذا أردنا أو تضمينها
            $baseName = class_basename($resourceClass);
            $cleanName = str_replace('Resource', '', $baseName);

            // تحديد مفتاح الكيان وفق تسميات المشروع
            $entityKey = $baseName === 'UsersResource' ? 'users' : Str::snake($cleanName);

            $label = $resourceClass::getPluralModelLabel()
                ?: $resourceClass::getModelLabel()
                ?: $cleanName;

            $group = $resourceClass::getNavigationGroup() ?: 'عام';

            $permissions = [];
            foreach (self::CRUD_ACTIONS as $actionKey => $actionLabel) {
                $permName = "{$actionKey}_{$entityKey}";
                $permissions[$actionKey] = [
                    'name' => $permName,
                    'label' => "{$actionLabel} {$label}",
                ];
            }

            $discovered[$entityKey] = [
                'class' => $resourceClass,
                'entity' => $entityKey,
                'label' => $label,
                'group' => $group,
                'permissions' => $permissions,
            ];
        }

        return $discovered;
    }

    /**
     * استكشاف كافة الصفحات المخصصة (Pages) المسجلة في Filament.
     *
     * @return array<string, array{
     *     class: class-string<Page>,
     *     name: string,
     *     label: string,
     *     group: string,
     *     permission: string
     * }>
     */
    public function discoverPages(): array
    {
        $panel = Filament::getPanel('admin');
        $pageClasses = $panel->getPages();
        $discovered = [];

        foreach ($pageClasses as $pageClass) {
            if (! is_subclass_of($pageClass, Page::class)) {
                continue;
            }

            $baseName = class_basename($pageClass);

            // تجاهل الصفحات الداخلية المؤقتة كاستيراد إن كانت تابعة لموديل
            if (Str::startsWith($baseName, 'Custom') && Str::endsWith($baseName, 'Import')) {
                continue;
            }

            // تجاهل الصفحات الفرعية التابعة لموارد أخرى وصلاحيتها مسجلة كإجراء مالي خاص
            if ($baseName === 'ClientFinancialDetail') {
                continue;
            }

            $override = self::$pagePermissionOverrides[$baseName] ?? null;

            if ($override) {
                $permission = $override['permission'];
                $label = $override['label'];
                $group = $override['group'] ?? ($pageClass::getNavigationGroup() ?: 'لوحات التحكم المخصصة');
            } else {
                $permission = 'view_'.Str::snake($baseName);
                $label = $pageClass::getNavigationLabel() ?: $baseName;
                $group = $pageClass::getNavigationGroup() ?: 'صفحات أخرى';
            }

            $discovered[$baseName] = [
                'class' => $pageClass,
                'name' => $baseName,
                'label' => $label,
                'group' => $group,
                'permission' => $permission,
            ];
        }

        return $discovered;
    }

    /**
     * استكشاف الصلاحيات المخصصة والمالية.
     *
     * @return array<string, array{label: string, group: string, description: string}>
     */
    public function discoverCustomActions(): array
    {
        return self::$customActions;
    }

    /**
     * الحصول على جميع الصلاحيات المكتشفة مع تصنيفاتها.
     *
     * @return array<string, array{name: string, label: string, group: string, type: string}>
     */
    public function getAllDiscoveredPermissions(): array
    {
        $all = [];

        // 1. الموارد (CRUD)
        foreach ($this->discoverResources() as $entityKey => $resourceData) {
            foreach ($resourceData['permissions'] as $action => $perm) {
                $all[$perm['name']] = [
                    'name' => $perm['name'],
                    'label' => $perm['label'],
                    'group' => $resourceData['group'],
                    'type' => 'resource',
                    'entity' => $entityKey,
                    'action' => $action,
                ];
            }
        }

        // 2. الصفحات المخصصة
        foreach ($this->discoverPages() as $pageName => $pageData) {
            $perm = $pageData['permission'];
            if (! isset($all[$perm])) {
                $all[$perm] = [
                    'name' => $perm,
                    'label' => "دخول {$pageData['label']}",
                    'group' => $pageData['group'],
                    'type' => 'page',
                    'page' => $pageName,
                ];
            }
        }

        // 3. الإجراءات المخصصة
        foreach ($this->discoverCustomActions() as $permName => $data) {
            if (! isset($all[$permName])) {
                $all[$permName] = [
                    'name' => $permName,
                    'label' => $data['label'],
                    'group' => $data['group'],
                    'type' => 'custom',
                    'description' => $data['description'],
                ];
            }
        }

        return $all;
    }

    /**
     * مزامنة الصلاحيات المكتشفة مع جدول الصلاحيات في قاعدة البيانات.
     *
     * تقوم بإنشاء أي صلاحية غير موجودة دون حذف أو التأثير على الصلاحيات القائمة.
     *
     * @return array{
     *     total_discovered: int,
     *     created_count: int,
     *     created_permissions: array<string>,
     *     existing_count: int
     * }
     */
    public function syncMissingPermissions(): array
    {
        $discovered = $this->getAllDiscoveredPermissions();
        $existingNames = Permission::where('guard_name', 'web')
            ->pluck('name')
            ->flip()
            ->toArray();

        $created = [];

        foreach ($discovered as $permName => $data) {
            if (! isset($existingNames[$permName])) {
                Permission::firstOrCreate([
                    'name' => $permName,
                    'guard_name' => 'web',
                ]);
                $created[] = $permName;
            }
        }

        // تفريغ كاش الصلاحيات لدى Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return [
            'total_discovered' => count($discovered),
            'created_count' => count($created),
            'created_permissions' => $created,
            'existing_count' => count($discovered) - count($created),
        ];
    }
}
