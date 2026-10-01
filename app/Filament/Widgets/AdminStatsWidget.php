<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ReviewerDashboard;
use App\Filament\Pages\TagDistribution;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ContractResource;
use App\Services\AdminDashboardService;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * ودجة الإحصائيات الرئيسية للوحة تحكم الأدمن.
 *
 * تعرض 4 بطاقات تفاعلية تعطي نظرة سريعة عن حالة النظام:
 * العملاء النشطين، الاشتراكات النشطة، التصاميم بانتظار المراجعة،
 * والمهام المتأخرة.
 * كل بطاقة قابلة للنقر لتنتقل إلى الصفحة المختصة.
 */
class AdminStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $clientContractStats = app(AdminDashboardService::class)->getClientContractStats();
        $workflowStats = app(AdminDashboardService::class)->getWorkflowStats();

        $stats = [
            Stat::make('العملاء النشطين', $clientContractStats['activeClients'])
                ->description('عدد العملاء المرتبطين باشتراكات نشطة')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary')
                ->chart([7, 5, 8, 6, 9, 7, $clientContractStats['activeClients']])
                ->url(ClientResource::getUrl('index'), shouldOpenInNewTab: false)
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-primary-500 bg-gradient-to-br from-primary-50/40 to-white dark:from-primary-950/10 dark:to-gray-900 cursor-pointer transition-all duration-300 hover:scale-[1.02] hover:shadow-lg',
                ]),
        ];

        if (auth()->user()?->hasRole('admin')) {
            $stats[] = Stat::make('الاشتراكات النشطة', $clientContractStats['activeContracts'])
                ->description('إجمالي الاشتراكات قيد التنفيذ')
                ->descriptionIcon('heroicon-o-document-check')
                ->color(Color::Teal)
                ->chart([10, 9, 11, 10, 12, 11, $clientContractStats['activeContracts']])
                ->url(ContractResource::getUrl('index'), shouldOpenInNewTab: false)
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-teal-500 bg-gradient-to-br from-teal-50/40 to-white dark:from-teal-950/10 dark:to-gray-900 cursor-pointer transition-all duration-300 hover:scale-[1.02] hover:shadow-lg',
                ]);
        }

        $stats[] = Stat::make('بانتظار المراجعة', $workflowStats['pendingReview'])
            ->description('تصاميم بانتظار تدقيق المراجع')
            ->descriptionIcon('heroicon-o-eye')
            ->color(Color::Amber)
            ->chart([3, 5, 4, 6, 3, 5, $workflowStats['pendingReview']])
            ->url(ReviewerDashboard::getUrl(), shouldOpenInNewTab: false)
            ->extraAttributes([
                'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-amber-500 bg-gradient-to-br from-amber-50/40 to-white dark:from-amber-950/10 dark:to-gray-900 cursor-pointer transition-all duration-300 hover:scale-[1.02] hover:shadow-lg',
            ]);

        $stats[] = Stat::make('المهام المتأخرة', $workflowStats['overdueTasks'])
            ->description('مهام تجاوزت موعدها المحدد وتحتاج متابعة')
            ->descriptionIcon('heroicon-o-exclamation-triangle')
            ->color(Color::Rose)
            ->chart([2, 4, 3, 5, 4, 6, $workflowStats['overdueTasks']])
            ->url(TagDistribution::getUrl(), shouldOpenInNewTab: false)
            ->extraAttributes([
                'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-rose-500 bg-gradient-to-br from-rose-50/40 to-white dark:from-rose-950/10 dark:to-gray-900 cursor-pointer transition-all duration-300 hover:scale-[1.02] hover:shadow-lg',
            ]);

        return $stats;
    }
}
