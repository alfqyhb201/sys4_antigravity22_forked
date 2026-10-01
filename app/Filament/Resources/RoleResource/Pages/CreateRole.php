<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterCreate(): void
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
