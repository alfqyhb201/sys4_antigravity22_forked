<?php

namespace App\Filament\Resources\SocialMediaResource\Pages;

use App\Filament\Resources\SocialMediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateSocialMedia extends CreateRecord
{
    protected static string $resource = SocialMediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }
}
