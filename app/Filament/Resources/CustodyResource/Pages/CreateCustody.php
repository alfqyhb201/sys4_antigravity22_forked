<?php

namespace App\Filament\Resources\CustodyResource\Pages;

use App\Filament\Resources\CustodyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCustody extends CreateRecord
{
    protected static string $resource = CustodyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
