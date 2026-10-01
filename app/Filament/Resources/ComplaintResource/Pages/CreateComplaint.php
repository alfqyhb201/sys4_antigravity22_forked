<?php

namespace App\Filament\Resources\ComplaintResource\Pages;

use App\Filament\Resources\ComplaintResource;
use Filament\Resources\Pages\CreateRecord;

class CreateComplaint extends CreateRecord
{
    protected static string $resource = ComplaintResource::class;

    /**
     * تعيين المستخدم الذي أنشأ الشكوى تلقائياً.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = auth()->id();
        $data['status'] = \App\Filament\Enums\ComplaintStatus::New->value;

        return $data;
    }
}
