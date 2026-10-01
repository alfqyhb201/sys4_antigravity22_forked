<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Resources\ReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditReceipt extends EditRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['amount']) && ! empty($data['original_amount'])) {
            $data['amount'] = $data['original_amount'];
        }
        if (empty($data['original_amount']) && ! empty($data['amount'])) {
            $data['original_amount'] = $data['amount'];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
