<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('customImport')
                ->label('استيراد متقدم')
                ->icon('heroicon-o-arrow-up-on-square-stack')
                ->color('success')
                ->url(\App\Filament\Pages\CustomLocationImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
