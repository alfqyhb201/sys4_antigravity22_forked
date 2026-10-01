<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Pages\ClientFinancialDetail;
use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getRedirectUrl(): string
    {
        if ($this->record->client_id) {
            return ClientFinancialDetail::getUrl(['client' => $this->record->client_id]);
        }

        return parent::getRedirectUrl();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $totalAmount = 0;
            foreach ($data['items'] as &$item) {
                $item['total'] = (float) ($item['quantity'] ?? 1) * (float) ($item['unit_amount'] ?? 0);
                $totalAmount += $item['total'];
            }
            $data['total_amount'] = $totalAmount;
        }

        return $data;
    }
}
