<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'تفاصيل العميل';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        $contract = $this->record->currentContract;
        $isContractActive = $contract && $contract->status === 'active';
        $isClientActive = (bool) $this->record->status;

        return ($isContractActive && $isClientActive) ? '🟢 العميل شغال' : '🔴 العميل موقّف';
    }
}
