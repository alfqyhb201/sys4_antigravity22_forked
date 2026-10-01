<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * لوحة التحكم الرئيسية للنظام (Executive Overview).
 *
 * تظهر للإدارة والمشرفين لمتابعة الأداء العام والشكاوى.
 * عند محاولة وصول أي دور تنفيذي (مصمم، مراجع، محاسب، سوشيال ميديا)،
 * يتم توجيهه تلقائياً إلى لوحة التحكم المخصصة لدوره.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم الرئيسية';

    protected static ?string $navigationLabel = 'الرئيسية';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        // إذا كان المستخدم مديراً أو مشرفاً أو يمتلك صلاحية لوحة الإدارة العامة، يبقى في الصفحة
        if ($user->hasRole(['admin', 'super_admin']) || $user->can('view_admin_dashboard')) {
            return;
        }

        // توجيه المستخدم إلى لوحته المخصصة بحسب دوره
        $destination = $user->getDefaultDashboardUrl();
        if ($destination !== static::getUrl()) {
            redirect()->to($destination);
        }
    }
}
