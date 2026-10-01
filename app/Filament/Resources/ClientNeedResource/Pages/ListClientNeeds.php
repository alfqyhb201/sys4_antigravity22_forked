<?php

namespace App\Filament\Resources\ClientNeedResource\Pages;

use App\Filament\Resources\ClientNeedResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientNeeds extends ListRecords
{
    protected static string $resource = ClientNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
