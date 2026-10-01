<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class ClientCount extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
    // protected static string $view = 'filament.resources.client-resource.widgets.client-count';

    protected function getCards(): array
    {
        return [
            Card::make('عدد العملاء', Client::count())
                ->description('إجمالي عدد العملاء المسجلين')
                ->icon('heroicon-o-user-group')
                ->color('secondary'),

            Card::make('العملاء النشطين', Client::whereHas('currentContract', fn ($query) => $query->where('status', 'active'))->count())
                ->description('العملاء النشطين حاليًا')
                ->icon('heroicon-o-user-plus')
                ->color('success'),
            // Card::make('العملاء النشطين', Client::where('status', true)->count())
            //     ->description('العملاء النشطين حاليًا')
            //     ->icon('heroicon-o-user-plus')
            //     ->color('success'),
            Card::make('العملاء الموقوفين', Client::whereDoesntHave('currentContract', fn ($query) => $query->where('status', 'active'))->count())
                ->description('العملاء غير النشطين حاليًا')
                ->icon('heroicon-o-user-minus')
                ->color('danger'),
        ];
    }
}
