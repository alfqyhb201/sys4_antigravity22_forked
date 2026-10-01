<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * ودجة Filament لعرض إحصائيات سريعة.
 *
 * تعرض هذه الودجة إجمالي عدد المستخدمين في النظام.
 */
class UserCountWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    /**
     * يقوم بإرجاع مصفوفة من كائنات `Stat` لعرضها في الودجة.
     */
    protected function getStats(): array
    {
        return [
            Stat::make('عدد المستخدمين', User::count())
                ->description('إجمالي عدد المستخدمين')
                ->color('success'),
        ];
    }
}
