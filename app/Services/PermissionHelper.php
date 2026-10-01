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
     * ترجمة اسم صلاحية إلى العربية.
     */
    public static function translatePermission(string $name): string
    {
        $overrides = [
            // لوحات التحكم
            'view_designer_dashboard' => 'إدارة لوحة المصمم',
            'view_reviewer_dashboard' => 'إدارة لوحة المراجع',
            'view_supervisor_dashboard' => 'إدارة لوحة المشرف',
            'view_designer_distribution' => 'إدارة لوحة توزيع المصممين',
            'view_tag_distribution' => 'إدارة لوحة توزيع التاقات',
            'view_create_order' => 'إنشاء طلب',

            // الأرشيف
            'view_archive' => 'استعراض الأرشيف',

            // الصلاحيات المالية المخصصة
            'view_financial_reports' => 'عرض لوحة التحكم المالية',
            'record_payment' => 'تسديد',
            'approve_discount' => 'الموافقة على الخصم',
            'export_financial_data' => 'تصدير البيانات المالية',
            'view_client_financial' => 'عرض قسم المالية في العميل',
        ];

        if (isset($overrides[$name])) {
            return $overrides[$name];
        }

        $actions = [
            'view_any' => 'تصفح وعرض',
            'view' => 'عرض التفاصيل لـ',
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
            'designer' => 'المصممين',
            'idea' => 'الأفكار',
            'complaint' => 'الشكاوى',
            'custody' => 'العهد',
            'social_media' => 'السوشيال ميديا',
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
        if (Str::contains($name, 'client_need')) {
            return 'أنواع العملاء';
        }
        if (Str::contains($name, 'tag_group')) {
            return 'مجموعات التاقات';
        }
        if (Str::contains($name, 'social_media')) {
            return 'السوشيال ميديا';
        }
        if (Str::contains($name, 'designer_dashboard') || Str::contains($name, 'reviewer_dashboard') || Str::contains($name, 'supervisor_dashboard') || Str::contains($name, 'accounting_dashboard')) {
            return 'لوحات التحكم المخصصة';
        }
        if (Str::contains($name, 'archive')) {
            return 'الأرشيف';
        }
        if (Str::contains($name, 'distribution')) {
            return 'التوزيع والتكليفات';
        }
        if (Str::contains($name, 'users')) {
            return 'المستخدمين';
        }
        if (Str::contains($name, 'role')) {
            return 'الأدوار';
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
        if (Str::contains($name, 'tag') && ! Str::contains($name, 'tag_group') && ! Str::contains($name, 'tag_distribution')) {
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
        if (Str::contains($name, 'financial_reports') || Str::contains($name, 'record_payment') || Str::contains($name, 'approve_discount') || Str::contains($name, 'export_financial_data') || Str::contains($name, 'client_financial')) {
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
