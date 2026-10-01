<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('customImport')
                ->label('استيراد متقدم')
                ->icon('heroicon-o-arrow-up-on-square-stack')
                ->color('success')
                ->url(\App\Filament\Pages\CustomCategoryImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
