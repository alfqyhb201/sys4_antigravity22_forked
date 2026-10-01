<?php

namespace App\Filament\Resources\DesignTaskResource\Pages;

use App\Filament\Enums\DesignTaskStatus;
use App\Filament\Resources\DesignTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDesignTask extends CreateRecord
{
    protected static string $resource = DesignTaskResource::class;

    /**
     * تعيين المسؤول الذي أنشأ المهمة والحالة الافتراضية تلقائياً.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['assigner_id'] = auth()->id();
        $data['status'] = DesignTaskStatus::Pending->value;

        return $data;
    }
}
