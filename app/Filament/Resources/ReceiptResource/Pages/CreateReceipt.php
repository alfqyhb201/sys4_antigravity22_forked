<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Pages\ClientFinancialDetail;
use App\Filament\Resources\ReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected function getRedirectUrl(): string
    {
        if ($this->record->client_id) {
            return ClientFinancialDetail::getUrl(['client' => $this->record->client_id]);
        }

        return parent::getRedirectUrl();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['amount']) && ! empty($data['original_amount'])) {
            $data['amount'] = $data['original_amount'];
        }
        if (empty($data['original_amount']) && ! empty($data['amount'])) {
            $data['original_amount'] = $data['amount'];
        }

        return $data;
    }
}
