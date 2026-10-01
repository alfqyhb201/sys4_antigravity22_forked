<?php

namespace App\Filament\Widgets;

use App\Services\FinancialDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountingStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    public ?string $period = 'all';

    public ?string $startDate = null;

    public ?string $endDate = null;

    protected function getListeners(): array
    {
        return [
            'filterAccountingStats' => 'applyPeriod',
        ];
    }

    public function applyPeriod(string $period): void
    {
        $this->period = $period;

        $range = app(FinancialDashboardService::class)->resolvePeriod($period);

        $this->startDate = $range['startDate'];
        $this->endDate = $range['endDate'];
    }

    protected function getStats(): array
    {
        $service = app(FinancialDashboardService::class);
        $snapshot = $service->getAccountingSnapshot($this->period);
        $trends = $service->getDailyTrends();
        $baseSymbol = \App\Models\Currency::getBase()?->symbol ?? \App\Models\Currency::getBase()?->currency ?? 'ريال';

        return [
            Stat::make('إجمالي الفواتير الصادرة', number_format($snapshot['totalInvoiced']).' '.$baseSymbol)
                ->description($snapshot['periodLabel'] === 'إجمالي' ? 'إجمالي قيمة الفواتير المستحقة' : "خلال {$snapshot['periodLabel']}")
                ->descriptionIcon('heroicon-m-document-text')
                ->chart($trends['invoiced'])
                ->color('info')
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-info-500 bg-gradient-to-br from-info-50/40 to-white dark:from-info-950/10 dark:to-gray-900',
                ]),

            Stat::make('إجمالي المقبوضات الفعلية', number_format($snapshot['totalPaid']).' '.$baseSymbol)
                ->description($snapshot['periodLabel'] === 'إجمالي' ? 'إجمالي المبالغ المدفوعة فعلياً' : "خلال {$snapshot['periodLabel']}")
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart($trends['paid'])
                ->color('success')
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-success-500 bg-gradient-to-br from-success-50/40 to-white dark:from-success-950/10 dark:to-gray-900',
                ]),

            Stat::make('إجمالي المستحقات والمديونيات', number_format($snapshot['totalRemaining']).' '.$baseSymbol)
                ->description($snapshot['periodLabel'] === 'إجمالي' ? 'إجمالي الديون المتبقية على العملاء' : "خلال {$snapshot['periodLabel']}")
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($trends['invoiced'])
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-warning-500 bg-gradient-to-br from-warning-50/40 to-white dark:from-warning-950/10 dark:to-gray-900',
                ]),

            Stat::make('الديون المتأخرة', number_format($snapshot['totalOverdue']).' '.$baseSymbol)
                ->description("مستحقة على {$snapshot['overdueCount']} فواتير متجاوزة للمهلة")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-danger-500 bg-gradient-to-br from-danger-50/40 to-white dark:from-danger-950/10 dark:to-gray-900',
                ]),

            Stat::make('الاشتراكات النشطة', $snapshot['activeContracts'])
                ->description("مقابل {$snapshot['suspendedContracts']} اشتراكات موقوفة")
                ->descriptionIcon('heroicon-m-document-check')
                ->color('primary')
                ->extraAttributes([
                    'class' => 'relative overflow-hidden rounded-xl border-l-[3px] border-primary-500 bg-gradient-to-br from-primary-50/40 to-white dark:from-primary-950/10 dark:to-gray-900',
                ]),
        ];
    }
}
