<?php

namespace App\Filament\Resources\ClientTemplateResource\Pages;

use App\Filament\Resources\ClientTemplateResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClientTemplate extends EditRecord
{
    protected static string $resource = ClientTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('updateTimestamp')
                ->label('تحديث التاريخ')
                ->icon('heroicon-m-clock')
                ->color('success')
                ->action(function () {
                    $this->record->update(['updated_at' => now()]);
                    $this->refreshFormData(['updated_at']);

                    Notification::make()
                        ->title('تم تحديث التاريخ')
                        ->success()
                        ->send();
                }),
        ];
    }
}
