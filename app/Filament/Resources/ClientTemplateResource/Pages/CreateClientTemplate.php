<?php

namespace App\Filament\Resources\ClientTemplateResource\Pages;

use App\Filament\Resources\ClientTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientTemplate extends CreateRecord
{
    protected static string $resource = ClientTemplateResource::class;

    protected function afterCreate(): void
    {
        $this->record->update(['updated_at' => now()]);
    }
}
