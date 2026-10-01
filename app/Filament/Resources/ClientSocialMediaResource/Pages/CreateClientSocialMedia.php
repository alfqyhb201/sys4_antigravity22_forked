<?php

namespace App\Filament\Resources\ClientSocialMediaResource\Pages;

use App\Filament\Resources\ClientSocialMediaResource;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClientSocialMedia extends CreateRecord
{
    protected static string $resource = ClientSocialMediaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $clientId = $data['id'] ?? null;

        if (! $clientId) {
            throw new \InvalidArgumentException('يجب اختيار عميل');
        }

        $client = Client::findOrFail($clientId);

        if (array_key_exists('notes', $data)) {
            $client->update(['notes' => $data['notes']]);
        }

        if (isset($data['clientSocialMedia']) && is_array($data['clientSocialMedia'])) {
            $client->clientSocialMedia()->delete();
            foreach ($data['clientSocialMedia'] as $item) {
                if (! empty($item['social_media_id'])) {
                    $client->clientSocialMedia()->create([
                        'social_media_id' => $item['social_media_id'],
                        'account_url' => $item['account_url'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }
        }

        return $client;
    }
}
