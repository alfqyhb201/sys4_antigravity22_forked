<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\CurrencyService;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * أمر تصحيح وترحيل سندات الدفعات المقدمة التاريخية وتخصيصها آلياً على فواتير العملاء.
 */
class RepairHistoricalAdvanceReceiptsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receipts:repair-historical-advances
                            {--dry-run : معاينة التغييرات والعمليات بدون الحفظ في قاعدة البيانات}
                            {--receipt= : رقم سند محدد لتصحيحه فقط}
                            {--force : تنفيذ العملية مباشرة دون طلب تأكيد}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تصحيح أرصدة سندات الدفعات المقدمة التاريخية وتخصيصها على فواتير العملاء المستحقة آلياً';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $specificReceiptId = $this->option('receipt');
        $force = (bool) $this->option('force');

        $this->info('🔍 جاري البحث عن سندات الدفعات المقدمة التاريخية التي تحتاج إلى تصحيح...');

        $query = Receipt::query()
            ->whereNull('invoice_id')
            ->where('unallocated_amount', '<=', 0)
            ->doesntHave('allocations')
            ->with(['client', 'paidCurrency']);

        if ($specificReceiptId) {
            $query->where('id', (int) $specificReceiptId);
        } else {
            $query->where(function ($q) {
                $q->whereDate('created_at', '<', '2026-08-26')
                    ->orWhereDate('receipt_date', '<', '2026-08-26');
            });
        }

        $receipts = $query->get();

        if ($receipts->isEmpty()) {
            $this->info('✅ لا توجد سندات تاريخية معلقة بحاجة إلى تصحيح.');

            return self::SUCCESS;
        }

        $this->warn("تم العثور على {$receipts->count()} سند دفعة مقدمة تاريخي بحاجة للتصحيح والتخصيص.");

        if ($isDryRun) {
            $this->alert('وضع المعاينة مفعل (--dry-run) — لن يتم كتابة أي تعديلات في قاعدة البيانات.');
        } elseif (! $force && ! $this->confirm('هل أنت متأكد من رغبتك في تصحيح هذه السندات وتخصيص فواتيرها؟', true)) {
            $this->info('تم إلغاء العملية.');

            return self::SUCCESS;
        }

        $paymentService = app(PaymentService::class);
        $currencyService = app(CurrencyService::class);
        $resultsTable = [];

        DB::beginTransaction();

        try {
            foreach ($receipts as $receipt) {
                $client = $receipt->client;
                $curr = $receipt->paidCurrency?->symbol ?? $receipt->paidCurrency?->currency ?? 'ر.س';
                $initialAmount = (float) $receipt->amount;

                // إعادة ضبط رصيد السند مؤقتاً بكامل المبلغ الأصلي في قاعدة البيانات
                Receipt::where('id', $receipt->id)->update(['unallocated_amount' => $initialAmount]);
                $receipt->refresh();

                $allocatedTotal = 0.0;
                $invoicesSummary = [];

                if ($client) {
                    // جلب فواتير العميل بالترتيب الزمني
                    $invoices = Invoice::query()
                        ->where('client_id', $client->id)
                        ->orderBy('issue_date', 'asc')
                        ->orderBy('id', 'asc')
                        ->get();

                    foreach ($invoices as $invoice) {
                        $remaining = round((float) $invoice->remaining, 2);
                        $currentReceiptAvailable = round((float) $receipt->unallocated_amount, 2);

                        if ($remaining <= 0 || $currentReceiptAvailable <= 0) {
                            continue;
                        }

                        // حساب المبلغ المتاح بعملة الفاتورة
                        $availableInInvoiceCurrency = $currentReceiptAvailable;
                        if ($receipt->paid_currency_id && $invoice->currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id) {
                            $availableInInvoiceCurrency = round($currencyService->convert(
                                $currentReceiptAvailable,
                                $receipt->paid_currency_id,
                                $invoice->currency_id
                            ), 2);
                        }

                        $allocAmount = min($availableInInvoiceCurrency, $remaining);

                        if ($allocAmount > 0) {
                            $userId = $receipt->created_by_user && \App\Models\User::where('id', $receipt->created_by_user)->exists()
                                ? $receipt->created_by_user
                                : \App\Models\User::first()?->id;

                            $paymentService->allocateReceiptToInvoice(
                                $receipt,
                                $invoice,
                                $allocAmount,
                                $userId
                            );

                            $allocatedTotal += $allocAmount;
                            $invoicesSummary[] = "{$invoice->invoice_number} ({$allocAmount} {$curr})";
                        }
                    }
                }

                $receipt->refresh();
                $finalUnallocated = (float) $receipt->unallocated_amount;

                $resultsTable[] = [
                    'رقم السند' => '#'.$receipt->id,
                    'العميل' => $client?->name ?? $client?->company ?? ('عميل #'.$receipt->client_id),
                    'المبلغ الأصلي' => number_format($initialAmount, 2).' '.$curr,
                    'المخصص للفواتير' => number_format($allocatedTotal, 2).' '.$curr,
                    'الفواتير المسددة' => count($invoicesSummary) ? implode(', ', $invoicesSummary) : 'لا يوجد',
                    'الرصيد المتبقي المتاح' => number_format($finalUnallocated, 2).' '.$curr,
                ];
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info('تم التراجع عن جميع التغييرات بنجاح (وضع المعاينة).');
            } else {
                DB::commit();
                $this->info('✅ تم حفظ التغييرات وتخصيص الفواتير بنجاح.');
            }

            $this->table(
                ['رقم السند', 'العميل', 'المبلغ الأصلي', 'المخصص للفواتير', 'الفواتير المسددة', 'الرصيد المتبقي المتاح'],
                $resultsTable
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('حدث خطأ أثناء تنفيذ عملية التصحيح: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
