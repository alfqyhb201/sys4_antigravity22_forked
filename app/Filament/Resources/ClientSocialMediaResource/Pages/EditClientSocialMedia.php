<?php

namespace App\Filament\Resources\ClientSocialMediaResource\Pages;

use App\Filament\Resources\ClientSocialMediaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditClientSocialMedia extends EditRecord
{
    protected static string $resource = ClientSocialMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clearSocialMedia')
                ->label('مسح جميع المنصات')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('مسح جميع منصات التواصل للعميل')
                ->modalDescription('سيتم مسح جميع حسابات التواصل الاجتماعي المرتبطة بهذا العميل فقط، دون حذف العميل من النظام. هل أنت متأكد؟')
                ->modalSubmitActionLabel('نعم، امسح المنصات')
                ->visible(fn () => auth()->user() ? app(\App\Policies\ClientSocialMediaPolicy::class)->delete(auth()->user(), $this->record) : false)
                ->action(function () {
                    $this->record->clientSocialMedia()->delete();
                    $this->record->update(['notes' => null]);

                    \Filament\Notifications\Notification::make()
                        ->title('تم مسح منصات التواصل بنجاح')
                        ->body('تمت إزالة منصات التواصل دون المساس ببيانات العميل الأساسية.')
                        ->success()
                        ->send();

                    $this->redirect(ClientSocialMediaResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['id'] = $this->record->id;
        $data['notes'] = $this->record->notes;
        $data['clientSocialMedia'] = $this->record->clientSocialMedia->map(function ($item) {
            return [
                'social_media_id' => $item->social_media_id,
                'account_url' => $item->account_url,
                'notes' => $item->notes,
            ];
        })->toArray();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (array_key_exists('notes', $data)) {
            $record->update(['notes' => $data['notes']]);
        }

        if (isset($data['clientSocialMedia']) && is_array($data['clientSocialMedia'])) {
            $record->clientSocialMedia()->delete();
            foreach ($data['clientSocialMedia'] as $item) {
                if (! empty($item['social_media_id'])) {
                    $record->clientSocialMedia()->create([
                        'social_media_id' => $item['social_media_id'],
                        'account_url' => $item['account_url'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }
        }

        return $record;
    }
}
