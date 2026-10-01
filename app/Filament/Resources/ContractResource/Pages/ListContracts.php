<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع الاشتراكات'),
            'active' => Tab::make('النشطة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'suspended' => Tab::make('الموقوفة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'suspended')),
            'expired' => Tab::make('المنتهية')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'expired')),
            'renewed' => Tab::make('المجددة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'renewed')),
        ];
    }
}
