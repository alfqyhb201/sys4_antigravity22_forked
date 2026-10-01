<?php

namespace App\Filament\Resources\DesignTaskResource\Pages;

use App\Filament\Resources\DesignTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDesignTask extends EditRecord
{
    protected static string $resource = DesignTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
