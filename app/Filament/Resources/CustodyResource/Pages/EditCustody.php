<?php

namespace App\Filament\Resources\CustodyResource\Pages;

use App\Filament\Resources\CustodyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCustody extends EditRecord
{
    protected static string $resource = CustodyResource::class;

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
