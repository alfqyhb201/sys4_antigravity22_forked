<?php

namespace App\Filament\Resources\DesignerResource\Pages;

use App\Filament\Resources\DesignerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDesigner extends EditRecord
{
    protected static string $resource = DesignerResource::class;

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
