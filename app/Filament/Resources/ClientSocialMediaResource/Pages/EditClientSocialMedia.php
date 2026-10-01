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
            Actions\DeleteAction::make(),
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
