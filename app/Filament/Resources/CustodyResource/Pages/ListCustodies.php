<?php

namespace App\Filament\Resources\CustodyResource\Pages;

use App\Filament\Resources\CustodyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustodies extends ListRecords
{
    protected static string $resource = CustodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
