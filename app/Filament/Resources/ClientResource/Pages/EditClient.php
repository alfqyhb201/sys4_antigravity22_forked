<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ViewAction::make()->color('info'),
            // سيتم إضافة أزرار التجديد والإيقاف لاحقاً بناءً على نظام الاشتراكات الجديد
        ];
    }

    public function getSubheading(): ?string
    {
        $contract = $this->record->currentContract;
        $isContractActive = $contract && $contract->status === 'active';
        $isClientActive = (bool) $this->record->status;

        return ($isContractActive && $isClientActive) ? '🟢 العميل شغال' : '🔴 العميل موقّف';
    }

    /**
     * تعبئة حقول الاشتراك الحالي عند فتح صفحة التعديل.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $contract = $this->record->currentContract;

        if ($contract) {
            $data['contract_status'] = $contract->status === 'active';
            $data['contract_payment_type'] = $contract->payment_type;
            $data['contract_billing_cycle'] = $contract->billing_cycle;
            $data['contract_start_date'] = $contract->start_date?->format('Y-m-d');
            $data['contract_end_date'] = $contract->end_date?->format('Y-m-d');
            $data['contract_weekly_designs_count'] = $contract->weekly_designs_count;
            $data['contract_monthly_designs_count'] = $contract->monthly_designs_count;
            $data['contract_grace_period_days'] = $contract->grace_period_days;
            $data['contract_total_amount'] = $contract->total_amount;
            $data['contract_currency_id'] = $contract->currency_id;
            $data['contract_marketing_amount'] = $contract->marketing_amount;
            $data['contract_auto_renewal'] = (bool) $contract->auto_renewal;
            $data['contract_additional_designs_enabled'] = (bool) $contract->additional_designs_enabled;
            $data['contract_additional_design_price'] = $contract->additional_design_price;
            $data['contract_additional_designs_count'] = $contract->additional_designs_count;
            $data['contract_simple_requests_enabled'] = (bool) $contract->simple_requests_enabled;
            $data['contract_simple_request_price'] = $contract->simple_request_price;
            $data['contract_simple_requests_count'] = $contract->simple_requests_count;
            $data['contract_is_under_lawsuit'] = (bool) $contract->is_under_lawsuit;
            $data['contract_legal_notes'] = $contract->legal_notes;
        } else {
            $data['contract_status'] = true;
            $data['contract_payment_type'] = 'advance';
            $data['contract_billing_cycle'] = 'monthly';
            $data['contract_start_date'] = now()->format('Y-m-d');
            $data['contract_end_date'] = now()->addMonth()->format('Y-m-d');
            $data['contract_weekly_designs_count'] = 1;
            $data['contract_monthly_designs_count'] = 4;
            $data['contract_currency_id'] = \App\Models\Currency::first()?->id;
            $data['contract_auto_renewal'] = false;
            $data['contract_additional_designs_enabled'] = false;
            $data['contract_simple_requests_enabled'] = false;
        }

        return $data;
    }

    protected function beforeValidate(): void
    {
        if (isset($this->record)) {
            $this->record->captureOriginalRelations();
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by_user'] = Auth::id();
        if ($data['is_credit_allowed'] ?? false) {
            $data['contract_grace_period_days'] = null;
        }

        return $data;
    }

    /**
     * بعد حفظ بيانات العميل، يتم حفظ/تحديث الاشتراك الحالي وتسجيل نشاط العلاقات.
     */
    protected function afterSave(): void
    {
        $this->record->logRelationshipChanges();

        $formData = $this->data;

        $hasContractData = ! empty($formData['contract_total_amount']);

        if (! $hasContractData) {
            return;
        }

        $contractData = [
            'client_id' => $this->record->id,
            'status' => ($formData['contract_status'] ?? true) ? 'active' : 'suspended',
            'payment_type' => $formData['contract_payment_type'] ?? 'advance',
            'billing_cycle' => $formData['contract_billing_cycle'] ?? 'monthly',
            'start_date' => $formData['contract_start_date'] ?? now()->format('Y-m-d'),
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
            'simple_requests_enabled' => (bool) ($formData['contract_simple_requests_enabled'] ?? false),
            'simple_request_price' => (float) ($formData['contract_simple_request_price'] ?? 0) ?: null,
            'is_under_lawsuit' => (bool) ($formData['contract_is_under_lawsuit'] ?? false),
            'legal_notes' => $formData['contract_legal_notes'] ?? null,
            'updated_by_user' => Auth::id(),
        ];

        $existingContract = $this->record->currentContract;

        if ($existingContract) {
            $hasInvoices = $this->record->invoices()->where('status', 'posted')->exists();

            if ($hasInvoices) {
                $protectedFields = [];

                if ((float) ($contractData['total_amount'] ?? 0) !== (float) $existingContract->total_amount) {
                    $contractData['total_amount'] = (float) $existingContract->total_amount;
                    $protectedFields[] = 'المبلغ الإجمالي';
                }

                if ((float) ($contractData['marketing_amount'] ?? 0) !== (float) $existingContract->marketing_amount) {
                    $contractData['marketing_amount'] = (float) $existingContract->marketing_amount;
                    $protectedFields[] = 'مبلغ التسويق';
                }

                if (($contractData['start_date'] ?? null) !== $existingContract->start_date?->format('Y-m-d')) {
                    $contractData['start_date'] = $existingContract->start_date?->format('Y-m-d');
                    $protectedFields[] = 'تاريخ البداية';
                }

                if (($contractData['end_date'] ?? null) !== $existingContract->end_date?->format('Y-m-d')) {
                    $contractData['end_date'] = $existingContract->end_date?->format('Y-m-d');
                    $protectedFields[] = 'تاريخ النهاية';
                }

                if (! empty($protectedFields)) {
                    Notification::make()
                        ->warning()
                        ->title('تم حفظ التعديلات ما عدا:')
                        ->body(implode('، ', $protectedFields).' — هذه الحقول محمية بعد إصدار فواتير. استخدم زر "اشتراك جديد" لتغييرها.')
                        ->send();
                }
            }

            $existingContract->update($contractData);
        } else {
            $contractData['created_by_user'] = Auth::id();
            $contractData['simple_requests_count'] = 0;
            $contractData['additional_designs_count'] = 0;
            $contract = $this->record->currentContract()->create($contractData);

            $this->createInvoiceForContract($contract, $formData);
        }

        $shouldBeActive = (bool) ($formData['contract_status'] ?? true);
        $lifecycle = app(\App\Services\ClientLifecycleService::class);
        if (! $shouldBeActive) {
            $lifecycle->suspend($this->record);
        } else {
            $lifecycle->resume($this->record);
        }
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
