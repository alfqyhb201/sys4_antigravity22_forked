<?php

namespace App\Services;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * مساعد مركزي لترجمة وتجميع الصلاحيات.
 *
 * يستخدم من قبل RoleResource و UsersResource لتوحيد
 * عرض الصلاحيات في نفس المجموعات وبنفس الترجمات.
 */
class PermissionHelper
{
    /**
     * اسم بديل لترجمة الصلاحية إلى العربية.
     */
    public static function getArabicLabel(string $name): string
    {
        return static::translatePermission($name);
    }

    /**
     * ترجمة اسم صلاحية إلى العربية.
     */
    public static function translatePermission(string $name): string
    {
        $overrides = [
            // لوحات التحكم
            'view_admin_dashboard' => 'الرئيسية (لوحة المشرف العام)',
            'view_designer_dashboard' => 'لوحة المصمم',
            'view_reviewer_dashboard' => 'لوحة المراجع',
            'view_supervisor_dashboard' => 'واجهة المشرف',
            'view_accounting_dashboard' => 'لوحة التحكم المالية',
            'view_financial_reports' => 'لوحة التحكم المالية',
            'view_designer_distribution' => 'توزيع المصممين',
            'view_tag_distribution' => 'توزيع التاقات',
            'view_create_order' => 'إنشاء وتعديل الطلبات',
            'view_sending_follow_up' => 'واجهة الإرسال والمتابعة',
            'view_social_media_publishing' => 'واجهة السوشيال ميديا والنشر',

            // الأرشيف
            'view_archive' => 'أرشيف العملاء والتصاميم والمنشورات',

            // الإعدادات والنظام
            'manage_settings' => 'الإعدادات العامة وإعدادات العملات',
            'view_activity_log' => 'سجل النشاطات',
            'view_active_sessions' => 'نشاط وجلسات المستخدمين',
            'view_media_manager' => 'إدارة الوسائط والتخزين',
            'view_role_permission_manager' => 'إدارة وتخصيص الصلاحيات (مطور)',

            // الصلاحيات المالية والمستخدمين
            'assign_roles' => 'تعديل وتعيين الأدوار للمستخدمين',
            'manage_user_permissions' => 'تخصيص الصلاحيات المباشرة للمستخدمين',
            'record_payment' => 'تسديد السندات والدفعات',
            'approve_discount' => 'الموافقة على الخصومات',
            'export_financial_data' => 'تصدير البيانات المالية',
            'view_client_financial' => 'الملف المالي وقسم المالية للعميل',
            'edit_sending_time' => 'تعديل وقت الإرسال',
        ];

        if (isset($overrides[$name])) {
            return $overrides[$name];
        }

        $actions = [
            'view_any' => 'تصفح وعرض',
            'view' => 'عرض تفاصيل',
            'create' => 'إضافة',
            'update' => 'تعديل',
            'delete' => 'حذف',
        ];

        $entities = [
            'users' => 'المستخدمين',
            'role' => 'الأدوار',
            'category' => 'التصنيفات',
            'currency' => 'العملات',
            'location' => 'المواقع',
            'tag' => 'التاقات',
            'tag_group' => 'مجموعات التاقات',
            'client' => 'العملاء',
            'client_need' => 'أنواع العملاء',
            'client_template' => 'قوالب العملاء',
            'client_social_media' => 'حسابات تواصل العملاء',
            'designer' => 'المصممين',
            'idea' => 'الأفكار',
            'complaint' => 'الشكاوى',
            'custody' => 'العهد',
            'social_media' => 'منصات السوشيال ميديا',
            'invoice' => 'الفواتير',
            'receipt' => 'السندات',
            'contract' => 'الاشتراكات',
            'design_task' => 'مهام التصميم',
            'order' => 'الطلبات',
            'designer_dashboard' => 'لوحة المصمم',
            'reviewer_dashboard' => 'لوحة المراجع',
            'supervisor_dashboard' => 'لوحة المشرف',
            'accounting_dashboard' => 'لوحة المحاسبة',
            'designer_distribution' => 'توزيع المصممين',
            'tag_distribution' => 'توزيع التاجات',
            'archive' => 'الأرشيف',
            'create_order' => 'إنشاء طلب',
        ];

        foreach ($actions as $actionKey => $actionLabel) {
            if (Str::startsWith($name, $actionKey.'_')) {
                $entityKey = Str::replaceFirst($actionKey.'_', '', $name);
                $entityLabel = $entities[$entityKey] ?? $entityKey;

                return $actionLabel.' '.$entityLabel;
            }
        }

        return $entities[$name] ?? $name;
    }

    /**
     * تحديد المجموعة التي تنتمي إليها الصلاحية.
     */
    public static function groupPermission(string $name): string
    {
        if (Str::contains($name, 'client_social_media') || Str::contains($name, 'social_media')) {
            return 'السوشيال ميديا';
        }
        if (Str::contains($name, 'client_need')) {
            return 'أنواع العملاء';
        }
        if (Str::contains($name, 'client_template')) {
            return 'قوالب العملاء';
        }
        if (Str::contains($name, 'tag_group')) {
            return 'مجموعات التاقات';
        }
        if (Str::contains($name, 'admin_dashboard') || Str::contains($name, 'designer_dashboard') || Str::contains($name, 'reviewer_dashboard') || Str::contains($name, 'supervisor_dashboard') || Str::contains($name, 'accounting_dashboard')) {
            return 'لوحات التحكم المخصصة';
        }
        if (Str::contains($name, 'archive') || Str::contains($name, 'recently_sent_archive')) {
            return 'الأرشيف';
        }
        if (Str::contains($name, 'sending_follow_up') || Str::contains($name, 'edit_sending_time')) {
            return 'العمليات والمتابعة';
        }
        if (Str::contains($name, 'distribution')) {
            return 'التوزيع والتكليفات';
        }
        if (Str::contains($name, 'assign_roles') || Str::contains($name, 'manage_user_permissions') || Str::contains($name, 'users')) {
            return 'المستخدمين';
        }
        if (Str::contains($name, 'role_permission_manager') || Str::contains($name, 'role')) {
            return 'الأدوار';
        }
        if (Str::contains($name, 'manage_settings') || Str::contains($name, 'activity_log') || Str::contains($name, 'active_sessions') || Str::contains($name, 'media_manager')) {
            return 'الإعدادات';
        }
        if (Str::contains($name, 'complaint')) {
            return 'الشكاوى';
        }
        if (Str::contains($name, 'category')) {
            return 'التصنيفات';
        }
        if (Str::contains($name, 'currency')) {
            return 'العملات';
        }
        if (Str::contains($name, 'location')) {
            return 'المواقع';
        }
        if (Str::contains($name, 'tag')) {
            return 'التاقات';
        }
        if (Str::contains($name, 'client')) {
            return 'العملاء';
        }
        if (Str::contains($name, 'designer')) {
            return 'المصممين';
        }
        if (Str::contains($name, 'idea')) {
            return 'الأفكار';
        }
        if (Str::contains($name, 'custody')) {
            return 'العهد';
        }
        if (Str::contains($name, 'invoice')) {
            return 'الفواتير';
        }
        if (Str::contains($name, 'receipt')) {
            return 'السندات';
        }
        if (Str::contains($name, 'contract')) {
            return 'الاشتراكات';
        }
        if (Str::contains($name, 'financial') || Str::contains($name, 'payment') || Str::contains($name, 'discount')) {
            return 'المالية';
        }
        if (Str::contains($name, 'design_task') || Str::contains($name, 'order')) {
            return 'المهام والطلبات';
        }

        return 'أخرى';
    }

    /**
     * الحصول على كل الصلاحيات مجمعة حسب المجموعة مع ترجمتها.
     *
     * @return array<string, array<int, array{id: int, name: string, translation: string}>>
     */
    public static function getGroupedPermissions(): array
    {
        $permissions = Permission::all();

        return $permissions
            ->groupBy(fn ($p) => self::groupPermission($p->name))
            ->map(function ($group) {
                return $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'translation' => self::translatePermission($p->name),
                ])->toArray();
            })
            ->toArray();
    }

    /**
     * ألوان المجموعات لعرضها في الـ Infolist.
     */
    public static function groupColors(): array
    {
        return [
            'العملاء' => 'info',
            'أنواع العملاء' => 'info',
            'لوحات التحكم المخصصة' => 'warning',
            'التوزيع والتكليفات' => 'warning',
            'المستخدمين' => 'danger',
            'الأدوار' => 'danger',
            'الشكاوى' => 'danger',
            'السوشيال ميديا' => 'success',
            'مجموعات التاقات' => 'success',
            'التاقات' => 'success',
            'المصممين' => 'primary',
            'الأفكار' => 'primary',
            'التصنيفات' => 'gray',
            'العملات' => 'gray',
            'المواقع' => 'gray',
            'العهد' => 'gray',
            'الفواتير' => 'success',
            'السندات' => 'success',
            'الاشتراكات' => 'success',
            'المالية' => 'success',
            'المهام والطلبات' => 'gray',
            'الأرشيف' => 'gray',
            'أخرى' => 'gray',
        ];
    }
}
