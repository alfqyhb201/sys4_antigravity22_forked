<?php

namespace App\Console\Commands;

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\InvoiceResource;
use App\Mail\ContractRenewalReportMail;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RenewContractsAndGenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:renew-and-bill {--email=* : البريد الإلكتروني لإرسال تقرير HTML مفصل (يمكن تمرير أكثر من بريد أو فصلها بفاصلة)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew active contracts that reached their end date (if auto-renewal is enabled) and generate their next cycle invoices.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('');
        $this->info('================================================================================');
        $this->info('  📊 تقرير دورة تجديد الاشتراكات وإصدار الفواتير - TrueERP');
        $this->info('  تاريخ ووقت بدء التشغيل: '.now()->format('Y-m-d H:i:s'));
        $this->info('================================================================================');
        $this->line('');

        $today = now()->startOfDay();

        // 1. Process contracts that need auto-renewal
        $contractsToRenew = Contract::query()
            ->with(['client', 'currency'])
            ->where('status', 'active')
            ->where('auto_renewal', true)
            ->where('end_date', '<=', $today)
            ->get();

        $renewedRows = [];
        $renewedMailData = [];
        $failedRows = [];
        $failedMailData = [];
        $renewCount = 0;

        foreach ($contractsToRenew as $contract) {
            $clientName = $contract->client?->company ?: ($contract->client?->client_name ?: "عميل #{$contract->client_id}");
            try {
                $newContract = $contract->createRenewalContract();
                $renewCount++;

                $latestInvoice = Invoice::where('contract_id', $newContract->id)->latest()->first();
                $invoiceNumber = $latestInvoice ? $latestInvoice->invoice_number : 'N/A';
                $currencySymbol = $newContract->currency?->symbol ?? $contract->currency?->symbol ?? 'ر.س';
                $formattedAmount = number_format((float) ($newContract->total_amount ?? 0), 2).' '.$currencySymbol;

                $oldContractUrl = null;
                $newContractUrl = null;
                $invoiceUrl = null;
                try {
                    $oldContractUrl = ContractResource::getUrl('edit', ['record' => $contract]);
                    $newContractUrl = ContractResource::getUrl('edit', ['record' => $newContract]);
                    $invoiceUrl = $latestInvoice ? InvoiceResource::getUrl('edit', ['record' => $latestInvoice]) : null;
                } catch (\Throwable) {
                }

                $renewedRows[] = [
                    $renewCount,
                    $clientName,
                    "#{$contract->id}",
                    "#{$newContract->id}",
                    $invoiceNumber,
                    $formattedAmount,
                    $newContract->end_date?->format('Y-m-d') ?? '-',
                ];

                $renewedMailData[] = [
                    'client_name' => $clientName,
                    'old_contract_id' => $contract->id,
                    'old_contract_url' => $oldContractUrl,
                    'new_contract_id' => $newContract->id,
                    'new_contract_url' => $newContractUrl,
                    'invoice_number' => $invoiceNumber,
                    'invoice_url' => $invoiceUrl,
                    'formatted_amount' => $formattedAmount,
                    'end_date' => $newContract->end_date?->format('Y-m-d') ?? '-',
                ];
            } catch (\Exception $e) {
                $failedRows[] = [
                    count($failedRows) + 1,
                    $clientName." (عقد #{$contract->id})",
                    'تجديد العقد',
                    $e->getMessage(),
                ];
                $failedMailData[] = [
                    'client_name' => $clientName." (عقد #{$contract->id})",
                    'operation' => 'تجديد العقد',
                    'error_message' => $e->getMessage(),
                ];
                $this->error("فشل تجديد العقد #{$contract->id}: {$e->getMessage()}");
            }
        }

        if (! empty($renewedRows)) {
            $this->info('🔹 العقود التي تم تجديدها وإصدار فواتيرها بنجاح:');
            $this->table(
                ['#', 'اسم العميل / المنشأة', 'العقد السابق', 'العقد الجديد', 'رقم الفاتورة', 'المبلغ', 'نهاية الاشتراك الجديد'],
                $renewedRows
            );
            $this->line('');
        } else {
            $this->line('ℹ️ لا توجد عقود مستحقة للتجديد التلقائي اليوم.');
            $this->line('');
        }

        // 2. Process contracts that expired and have auto-renewal disabled
        $expiredContracts = Contract::query()
            ->with(['client'])
            ->where('status', 'active')
            ->where('auto_renewal', false)
            ->where('end_date', '<=', $today)
            ->get();

        $expiredRows = [];
        $expiredMailData = [];
        $expireCount = 0;

        foreach ($expiredContracts as $contract) {
            $clientName = $contract->client?->company ?: ($contract->client?->client_name ?: "عميل #{$contract->client_id}");
            try {
                $contract->update(['status' => 'suspended']);

                // إذا كان للاشتراك المنتهي فاتورة مسودة (اشتراك مؤخر)، نحولها إلى مستحقة/مرحلة عند الانتهاء
                $draftInvoice = Invoice::where('contract_id', $contract->id)
                    ->where('status', 'draft')
                    ->latest()
                    ->first();

                $actionNote = 'تم إيقاف الاشتراك (معلق)';
                if ($draftInvoice) {
                    $draftInvoice->update(['status' => 'posted']);
                    $contract->applyWalletToInvoice($draftInvoice);
                    $actionNote .= " + ترحيل فاتورة #{$draftInvoice->invoice_number}";
                }

                $expireCount++;
                $expiredRows[] = [
                    $expireCount,
                    $clientName,
                    "#{$contract->id}",
                    $contract->end_date?->format('Y-m-d') ?? '-',
                    $actionNote,
                ];

                $expiredMailData[] = [
                    'client_name' => $clientName,
                    'contract_id' => $contract->id,
                    'end_date' => $contract->end_date?->format('Y-m-d') ?? '-',
                    'action_note' => $actionNote,
                ];
            } catch (\Exception $e) {
                $failedRows[] = [
                    count($failedRows) + 1,
                    $clientName." (عقد #{$contract->id})",
                    'إيقاف العقد المنتهي',
                    $e->getMessage(),
                ];
                $failedMailData[] = [
                    'client_name' => $clientName." (عقد #{$contract->id})",
                    'operation' => 'إيقاف العقد المنتهي',
                    'error_message' => $e->getMessage(),
                ];
                $this->error("فشل إيقاف العقد #{$contract->id}: {$e->getMessage()}");
            }
        }

        if (! empty($expiredRows)) {
            $this->warn('⚠️ عقود انتهت وتم إيقافها (التجديد التلقائي معطل):');
            $this->table(
                ['#', 'اسم العميل / المنشأة', 'رقم العقد', 'تاريخ الانتهاء', 'الإجراء المتخذ'],
                $expiredRows
            );
            $this->line('');
        }

        // 3. Process contracts expiring in 1 day or less and send notifications
        $tomorrow = now()->addDay()->toDateString();
        $contractsNearExpiry = Contract::query()
            ->with(['client'])
            ->where('status', 'active')
            ->whereDate('end_date', '<=', $tomorrow)
            ->whereNull('notification_date')
            ->get();

        $notifiedRows = [];
        $notifiedMailData = [];
        $notifyCount = 0;

        try {
            $usersToNotify = User::where('status', 1)
                ->role(['admin', 'accountant'])
                ->get();
        } catch (\Throwable $e) {
            // Fallback if roles/Spatie tables are not seeded (e.g. during tests)
            $usersToNotify = User::where('status', 1)->get();
        }

        foreach ($contractsNearExpiry as $contract) {
            $clientName = $contract->client?->company ?: ($contract->client?->client_name ?: "عميل #{$contract->client_id}");
            try {
                foreach ($usersToNotify as $user) {
                    Notification::make()
                        ->title('تنبيه: قرب انتهاء اشتراك ⚠️')
                        ->body("اشتراك العميل ({$clientName}) سينتهي بتاريخ {$contract->end_date?->format('Y-m-d')}.")
                        ->warning()
                        ->actions([
                            Action::make('view')
                                ->label('عرض الاشتراك')
                                ->url(ContractResource::getUrl('edit', ['record' => $contract])),
                        ])
                        ->sendToDatabase($user, isEventDispatched: true);
                }

                $contract->update([
                    'notification_date' => now(),
                ]);
                $notifyCount++;

                $notifiedRows[] = [
                    $notifyCount,
                    $clientName,
                    "#{$contract->id}",
                    $contract->end_date?->format('Y-m-d') ?? '-',
                    'تم إرسال إشعار للنظام',
                ];

                $notifiedMailData[] = [
                    'client_name' => $clientName,
                    'contract_id' => $contract->id,
                    'end_date' => $contract->end_date?->format('Y-m-d') ?? '-',
                ];
            } catch (\Exception $e) {
                $failedRows[] = [
                    count($failedRows) + 1,
                    $clientName." (عقد #{$contract->id})",
                    'إرسال إشعار قرب الانتهاء',
                    $e->getMessage(),
                ];
                $failedMailData[] = [
                    'client_name' => $clientName." (عقد #{$contract->id})",
                    'operation' => 'إرسال إشعار قرب الانتهاء',
                    'error_message' => $e->getMessage(),
                ];
                $this->error("فشل إرسال إشعار للعقد #{$contract->id}: {$e->getMessage()}");
            }
        }

        if (! empty($notifiedRows)) {
            $this->comment('🔔 تنبيهات قرب انتهاء الاشتراكات (تم إرسال إشعارات):');
            $this->table(
                ['#', 'اسم العميل / المنشأة', 'رقم العقد', 'تاريخ الانتهاء', 'الحالة'],
                $notifiedRows
            );
            $this->line('');
        }

        if (! empty($failedRows)) {
            $this->error('❌ عمليات واجهت أخطاء:');
            $this->table(
                ['#', 'العميل / العقد', 'نوع العملية', 'سبب الخطأ'],
                $failedRows
            );
            $this->line('');
        }

        // جدول ملخص نتائج التشغيل
        $this->info('📊 ملخص نتائج التنفيذ:');
        $this->table(
            ['البيان', 'العدد / القيمة'],
            [
                ['عقود تم تجديدها وإصدار فواتيرها', (string) $renewCount],
                ['عقود منتهية تم إيقافها', (string) $expireCount],
                ['إشعارات قرب الانتهاء المرسلة', (string) $notifyCount],
                ['عمليات فشلت (أخطاء)', (string) count($failedRows)],
                ['حالة المعالجة', count($failedRows) === 0 ? 'ناجحة بالكامل ✅' : 'اكتملت مع وجود أخطاء ⚠️'],
                ['وقت انتهاء التشغيل', now()->format('Y-m-d H:i:s')],
            ]
        );
        $this->line('');

        // إرسال تقرير البريد الإلكتروني بصيغة HTML إن تم تحديد الإيميل
        $emailOption = $this->option('email');
        $recipients = [];

        if (! empty($emailOption)) {
            $rawList = is_array($emailOption) ? $emailOption : explode(',', (string) $emailOption);
            foreach ($rawList as $item) {
                foreach (explode(',', (string) $item) as $address) {
                    $address = trim($address);
                    if (! empty($address) && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                        $recipients[] = $address;
                    }
                }
            }
            $recipients = array_values(array_unique($recipients));
        }

        if (! empty($recipients)) {
            try {
                $summaryStats = [
                    'renewed_count' => $renewCount,
                    'expired_count' => $expireCount,
                    'notified_count' => $notifyCount,
                    'failed_count' => count($failedRows),
                    'executed_at' => now()->format('Y-m-d H:i:s'),
                    'status' => count($failedRows) === 0 ? 'ناجحة بالكامل ✅' : 'اكتملت مع ملاحظات ⚠️',
                ];

                Mail::to($recipients)->send(
                    new ContractRenewalReportMail(
                        renewedContracts: $renewedMailData,
                        expiredContracts: $expiredMailData,
                        notifiedContracts: $notifiedMailData,
                        failedOperations: $failedMailData,
                        summaryStats: $summaryStats,
                    )
                );

                $recipientList = implode(', ', $recipients);
                $this->info("📧 تم إرسال تقرير HTML بنجاح إلى: {$recipientList}");
            } catch (\Throwable $e) {
                $recipientList = implode(', ', $recipients);
                $this->error("❌ فشل إرسال البريد الإلكتروني إلى ({$recipientList}): {$e->getMessage()}");
            }
        }

        $this->info("Process completed. Renewed: {$renewCount}, Expired/Suspended: {$expireCount}, Notified: {$notifyCount}.");

        return Command::SUCCESS;
    }
}
