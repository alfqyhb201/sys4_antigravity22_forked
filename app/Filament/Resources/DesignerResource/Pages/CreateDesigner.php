<?php

namespace App\Filament\Resources\DesignerResource\Pages;

use App\Filament\Resources\DesignerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDesigner extends CreateRecord
{
    protected static string $resource = DesignerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
