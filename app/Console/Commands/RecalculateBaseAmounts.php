<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\CurrencyService;
use Illuminate\Console\Command;

/**
 * أمر إعادة حساب المبالغ بالعملة الأساسية لجميع الفواتير والسندات.
 *
 * يُستخدم عند تغيير العملة الأساسية أو تعديل أسعار الصرف التاريخية.
 */
class RecalculateBaseAmounts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'currency:recalculate-base-amounts
                            {--dry-run : معاينة التغييرات بدون حفظها}
                            {--invoices-only : إعادة حساب الفواتير فقط}
                            {--receipts-only : إعادة حساب السندات فقط}
                            {--update-rates : تحديث أسعار الصرف المجمّدة بسعر السوق الحالي}';

    /**
     * @var string
     */
    protected $description = 'إعادة حساب base_currency_amount لجميع الفواتير والسندات بناءً على العملة الأساسية مع الحفاظ على أسعار الصرف المجمّدة';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $invoicesOnly = $this->option('invoices-only');
        $receiptsOnly = $this->option('receipts-only');
        $updateRates = $this->option('update-rates');
        $service = app(CurrencyService::class);
        $baseCurrency = Currency::getBase();

        if (! $baseCurrency) {
            $this->error('لم يتم تحديد عملة أساسية للنظام. يرجى تعيين عملة أساسية أولاً.');

            return self::FAILURE;
        }

        $this->info("العملة الأساسية: {$baseCurrency->currency_name} ({$baseCurrency->currency})");

        if ($isDryRun) {
            $this->warn('وضع المعاينة مفعل — لن يتم حفظ أي تغييرات.');
        }

        $totalUpdated = 0;

        // إعادة حساب الفواتير
        if (! $receiptsOnly) {
            $this->info('جاري إعادة حساب مبالغ الفواتير...');
            $invoices = Invoice::whereNotNull('currency_id')->cursor();
            $invoiceCount = 0;

            foreach ($invoices as $invoice) {
                // الحفاظ على سعر الصرف المجمّد للعميل، وجلبه من الجدول فقط إذا لم يُحدد من قبل أو طُلِب تحديث الأسعار صراحةً
                $rate = (float) $invoice->exchange_rate;
                if ($updateRates || $rate <= 0 || $invoice->currency_id === $baseCurrency->id) {
                    $rate = $service->getRateForDate(
                        $invoice->currency_id,
                        $baseCurrency->id,
                        $invoice->issue_date ?? now()
                    );
                }

                $newBaseAmount = $service->convert(
                    (float) $invoice->total_amount,
                    $invoice->currency_id,
                    $baseCurrency->id,
                    $rate
                );

                $oldBaseAmount = (float) $invoice->base_currency_amount;
                $rateChanged = $updateRates && abs((float) $invoice->exchange_rate - $rate) > 0.000001;

                if (abs($oldBaseAmount - $newBaseAmount) > 0.01 || $rateChanged) {
                    if (! $isDryRun) {
                        Invoice::withoutEvents(function () use ($invoice, $rate, $newBaseAmount, $updateRates) {
                            $updateData = ['base_currency_amount' => $newBaseAmount];
                            if ($updateRates) {
                                $updateData['exchange_rate'] = $rate;
                            }
                            $invoice->update($updateData);
                        });
                    }

                    $this->line("  فاتورة #{$invoice->invoice_number}: {$oldBaseAmount} → {$newBaseAmount}");
                    $invoiceCount++;
                }
            }

            $this->info("تم تحديث {$invoiceCount} فاتورة.");
            $totalUpdated += $invoiceCount;
        }

        // إعادة حساب السندات
        if (! $invoicesOnly) {
            $this->info('جاري إعادة حساب مبالغ السندات...');
            $receipts = Receipt::whereNotNull('paid_currency_id')->cursor();
            $receiptCount = 0;

            foreach ($receipts as $receipt) {
                // الحفاظ على سعر الصرف المجمّد للسند
                $rate = (float) $receipt->exchange_rate;
                if ($updateRates || $rate <= 0 || $receipt->paid_currency_id === $baseCurrency->id) {
                    $rate = $service->getRateForDate(
                        $receipt->paid_currency_id,
                        $baseCurrency->id,
                        $receipt->receipt_date ?? now()
                    );
                }

                $sourceAmount = (float) ($receipt->original_amount ?? $receipt->amount);
                $newBaseAmount = $service->convert(
                    $sourceAmount,
                    $receipt->paid_currency_id,
                    $baseCurrency->id,
                    $rate
                );

                $oldBaseAmount = (float) $receipt->base_currency_amount;
                $rateChanged = $updateRates && abs((float) $receipt->exchange_rate - $rate) > 0.000001;

                if (abs($oldBaseAmount - $newBaseAmount) > 0.01 || $rateChanged) {
                    if (! $isDryRun) {
                        Receipt::withoutEvents(function () use ($receipt, $rate, $newBaseAmount, $updateRates) {
                            $updateData = ['base_currency_amount' => $newBaseAmount];
                            if ($updateRates) {
                                $updateData['exchange_rate'] = $rate;
                            }
                            $receipt->update($updateData);
                        });
                    }

                    $this->line("  سند #{$receipt->id}: {$oldBaseAmount} → {$newBaseAmount}");
                    $receiptCount++;
                }
            }

            $this->info("تم تحديث {$receiptCount} سند.");
            $totalUpdated += $receiptCount;
        }

        $this->newLine();
        if ($isDryRun) {
            $this->warn("إجمالي السجلات التي ستتغير: {$totalUpdated}");
        } else {
            $this->info("✅ تم إعادة حساب {$totalUpdated} سجل بنجاح.");
        }

        return self::SUCCESS;
    }
}
