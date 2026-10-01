<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountingStats;
use App\Filament\Widgets\TopDebtorsWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;

class AccountingDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.accounting-dashboard';

    protected static ?string $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'لوحة التحكم المالية';

    protected static ?string $title = 'لوحة التحكم المالية';

    protected function getHeaderWidgets(): array
    {
        return [
            AccountingStats::class,
            TopDebtorsWidget::class,
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user->can('view_financial_reports');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printDebtorsReport')
                ->label('تقرير مديونيات العملاء (PDF)')
                ->icon('heroicon-o-printer')
                ->color('danger')
                ->url(route('reports.client-debtors'), shouldOpenInNewTab: true),
            Action::make('filter')
                ->label('تصفية الفترة')
                ->icon('heroicon-m-funnel')
                ->form([
                    Select::make('period')
                        ->label('الفترة الزمنية')
                        ->options([
                            'all' => 'الكل',
                            'today' => 'اليوم',
                            'week' => 'هذا الأسبوع',
                            'month' => 'هذا الشهر',
                            'quarter' => 'هذا الربع',
                            'year' => 'هذه السنة',
                        ])
                        ->default('all')
                        ->selectablePlaceholder(false)
                        ->live(),
                ])
                ->action(function (array $data): void {
                    $this->dispatch('filterAccountingStats', period: $data['period']);
                }),
        ];
    }
}
