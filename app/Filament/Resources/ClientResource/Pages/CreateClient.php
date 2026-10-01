<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['added_by_user'] = Auth::id();
        $data['updated_by_user'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->logRelationshipChanges();

        $formData = $this->data;

        if (empty($formData['contract_total_amount'])) {
            return;
        }

        $startDate = $formData['contract_start_date'] ?? now()->format('Y-m-d');

        $contract = $this->record->currentContract()->create([
            'status' => ($formData['contract_status'] ?? true) ? 'active' : 'suspended',
            'payment_type' => $formData['contract_payment_type'] ?? 'advance',
            'billing_cycle' => $formData['contract_billing_cycle'] ?? 'monthly',
            'start_date' => $startDate,
            'end_date' => $formData['contract_end_date'] ?? now()->addMonth()->format('Y-m-d'),
            'weekly_designs_count' => (int) ($formData['contract_weekly_designs_count'] ?? 1),
            'monthly_designs_count' => (int) ($formData['contract_monthly_designs_count'] ?? 4),
            'grace_period_days' => ($formData['is_credit_allowed'] ?? false) ? null : (int) ($formData['contract_grace_period_days'] ?? 0),
            'total_amount' => $formData['contract_total_amount'] ?? 0,
            'currency_id' => $formData['contract_currency_id'] ?? null,
            'marketing_amount' => $formData['contract_marketing_amount'] ?? 0,
            'auto_renewal' => (bool) ($formData['contract_auto_renewal'] ?? false),
            'additional_designs_enabled' => (bool) ($formData['contract_additional_designs_enabled'] ?? false),
            'additional_design_price' => (float) ($formData['contract_additional_design_price'] ?? 0) ?: null,
            'additional_designs_count' => 0,
            'simple_requests_enabled' => (bool) ($formData['contract_simple_requests_enabled'] ?? false),
            'simple_request_price' => (float) ($formData['contract_simple_request_price'] ?? 0) ?: null,
            'is_under_lawsuit' => (bool) ($formData['contract_is_under_lawsuit'] ?? false),
            'legal_notes' => $formData['contract_legal_notes'] ?? null,
            'created_by_user' => Auth::id(),
            'updated_by_user' => Auth::id(),
        ]);

        $this->createInvoiceForContract($contract, $formData);
    }

    private function createInvoiceForContract($contract, array $formData): void
    {
        $totalAmount = (float) ($formData['contract_total_amount'] ?? 0);
        $marketingAmount = (float) ($formData['contract_marketing_amount'] ?? 0);
        $paymentType = $formData['contract_payment_type'] ?? 'advance';

        $issueDate = now();
        $dueDate = $paymentType === 'advance'
            ? now()->addDays(7)
            : ($contract->end_date ?? now()->addMonth());

        $invoice = Invoice::create([
            'client_id' => $this->record->id,
            'contract_id' => $contract->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totalAmount + $marketingAmount,
            'status' => $paymentType === 'deferred' ? 'draft' : 'posted',
            'notes' => 'فاتورة أولى للاشتراك',
            'created_by_user' => Auth::id(),
            'updated_by_user' => Auth::id(),
        ]);

        $items = [];

        if ($totalAmount > 0) {
            $items[] = new InvoiceItem([
                'description' => 'خدمات التصميم - '.number_format($contract->monthly_designs_count).' تصاميم شهرياً',
                'quantity' => 1,
                'unit_amount' => $totalAmount,
                'total' => $totalAmount,
            ]);
        }

        if ($marketingAmount > 0) {
            $items[] = new InvoiceItem([
                'description' => 'خدمات التسويق',
                'quantity' => 1,
                'unit_amount' => $marketingAmount,
                'total' => $marketingAmount,
            ]);
        }

        if (! empty($items)) {
            $invoice->items()->saveMany($items);
        }
    }
}
