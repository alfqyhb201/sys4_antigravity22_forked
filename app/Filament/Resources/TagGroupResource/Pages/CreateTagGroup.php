<?php

namespace App\Filament\Resources\TagGroupResource\Pages;

use App\Filament\Resources\TagGroupResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTagGroup extends CreateRecord
{
    protected static string $resource = TagGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
