<?php

namespace App\Filament\Resources\ClientNeedResource\Pages;

use App\Filament\Resources\ClientNeedResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClientNeed extends CreateRecord
{
    protected static string $resource = ClientNeedResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
