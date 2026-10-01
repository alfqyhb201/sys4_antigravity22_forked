<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $data = $this->form->getRawState();
        $permissions = [];

        foreach ($data as $key => $value) {
            if (Str::startsWith($key, 'permissions_') && is_array($value)) {
                $permissions = array_merge($permissions, $value);
            }
        }

        // Ensure integer IDs for Spatie
        $permissions = array_map(fn ($id) => (int) $id, $permissions);

        $this->record->syncPermissions($permissions);

        Notification::make()
            ->title('تم حفظ الصلاحيات: '.count($permissions))
            ->success()
            ->send();
    }
}
