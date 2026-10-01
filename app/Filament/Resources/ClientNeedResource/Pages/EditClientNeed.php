<?php

namespace App\Filament\Resources\ClientNeedResource\Pages;

use App\Filament\Resources\ClientNeedResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClientNeed extends EditRecord
{
    protected static string $resource = ClientNeedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
