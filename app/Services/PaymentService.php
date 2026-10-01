<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * خدمة تسجيل السداد.
 *
 * تتعامل مع سيناريوهين رئيسيين:
 * 1. سداد فاتورة محددة (كامل أو جزئي).
 * 2. سداد عام (FIFO) بتوزيع المبلغ على أقدم الفواتير غير المسددة.
 */
class PaymentService
{
    /**
     * تسجيل سداد لفاتورة محددة.
     *
     * @param  array{amount: float, payment_method: string, receipt_date: string, paid_currency_id?: int, original_amount?: float, exchange_rate?: float, reference_number?: string, notes?: string}  $data
     *
     * @throws \InvalidArgumentException إذا كان المبلغ أكبر من المتبقي
     */
    public function payInvoice(Invoice $invoice, array $data): Receipt
    {
        $baseCurrency = Currency::getBase();
        $currencyService = app(CurrencyService::class);
        $paidCurrencyId = $data['paid_currency_id'] ?? $invoice->currency_id ?? $baseCurrency?->id;
        $originalAmount = round((float) ($data['original_amount'] ?? $data['amount']), 2);

        // تحويل المبلغ لعملة الفاتورة إذا كانت عملة الدفع مختلفة
        if ($paidCurrencyId && $invoice->currency_id && (int) $paidCurrencyId !== (int) $invoice->currency_id) {
            $amount = $currencyService->convert($originalAmount, $paidCurrencyId, $invoice->currency_id);
        } else {
            $amount = round((float) $data['amount'], 2);
        }

        $remaining = round($invoice->remaining, 2);

        if ($amount > $remaining) {
            throw new \InvalidArgumentException(
                "المبلغ ({$amount}) أكبر من المتبقي للفاتورة ({$remaining})."
            );
        }

        // حساب سعر الصرف (عملة الدفع → العملة الأساسية)
        $exchangeRate = $data['exchange_rate'] ?? null;
        if ((! $exchangeRate || (float) $exchangeRate === 1.0) && $baseCurrency && $paidCurrencyId && (int) $paidCurrencyId !== (int) $baseCurrency->id) {
            $exchangeRate = $currencyService->getLatestRate($paidCurrencyId, $baseCurrency->id);
        }
        $exchangeRate = $exchangeRate ?? 1.0;

        return DB::transaction(function () use ($invoice, $data, $amount, $paidCurrencyId, $originalAmount, $exchangeRate) {
            return Receipt::create([
                'client_id' => $invoice->client_id,
                'invoice_id' => $invoice->id,
                'paid_currency_id' => $paidCurrencyId,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'exchange_rate' => $exchangeRate,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user' => $data['created_by_user'] ?? null,
                'updated_by_user' => $data['updated_by_user'] ?? null,
            ]);
        });
    }

    /**
     * سداد عام (FIFO) - توزيع المبلغ على أقدم الفواتير غير المسددة.
     *
     * يتم ترتيب الفواتير حسب تاريخ الاستحقاق (الأقدم أولاً)،
     * ثم يتم تخصيص المبلغ بالتتابع حتى نفاده، مع إيداع الفائض بالمحفظة.
     *
     * @param  array{amount: float, payment_method: string, receipt_date: string, paid_currency_id?: int, exchange_rate?: float, bank_account_id?: int, reference_number?: string, notes?: string}  $data
     * @return Collection<int, Receipt> مجموعة سندات القبض المُنشأة
     */
    public function payClientFifo(Client $client, array $data): Collection
    {
        $baseCurrency = Currency::getBase();
        $currencyService = app(CurrencyService::class);

        $unpaidInvoices = $client->invoices()
            ->where('status', 'posted')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $paidCurrencyId = $data['paid_currency_id'] ?? $client->currentContract?->currency_id ?? $unpaidInvoices->first()?->currency_id ?? $baseCurrency?->id;

        $payAmountInput = round((float) $data['amount'], 2);
        $remainingAmount = $payAmountInput;

        return DB::transaction(function () use ($client, $unpaidInvoices, $data, $payAmountInput, &$remainingAmount, $paidCurrencyId, $currencyService, $baseCurrency) {
            $receipts = collect();

            // حساب سعر الصرف (عملة الدفع → الأساسية)
            $exchangeRate = $data['exchange_rate'] ?? null;
            if ((! $exchangeRate || (float) $exchangeRate === 1.0) && $baseCurrency && $paidCurrencyId && (int) $paidCurrencyId !== (int) $baseCurrency->id) {
                $exchangeRate = $currencyService->getLatestRate($paidCurrencyId, $baseCurrency->id);
            }
            $exchangeRate = $exchangeRate ?? 1.0;

            if ($unpaidInvoices->isEmpty()) {
                if ($payAmountInput > 0 && ($data['payment_method'] ?? null) !== 'discount') {
                    $receipt = Receipt::create([
                        'client_id' => $client->id,
                        'invoice_id' => null,
                        'paid_currency_id' => $paidCurrencyId,
                        'bank_account_id' => $data['bank_account_id'] ?? null,
                        'amount' => $payAmountInput,
                        'unallocated_amount' => $payAmountInput,
                        'original_amount' => $payAmountInput,
                        'exchange_rate' => $exchangeRate,
                        'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                        'payment_method' => $data['payment_method'],
                        'reference_number' => $data['reference_number'] ?? null,
                        'notes' => $data['notes'] ?? 'سند دفعة مقدمة (لعدم وجود فواتير مستحقة)',
                        'created_by_user' => $data['created_by_user'] ?? null,
                        'updated_by_user' => $data['updated_by_user'] ?? null,
                    ]);
                    $receipts->push($receipt);
                }

                return $receipts;
            }

            foreach ($unpaidInvoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $invoiceRemaining = $invoice->remaining;
                if ($invoiceRemaining <= 0) {
                    continue;
                }

                // تحويل المبلغ إذا كانت عملة الدفع تختلف عن عملة الفاتورة
                $payAmountInInvoiceCurrency = $remainingAmount;
                if ($paidCurrencyId && $invoice->currency_id && (int) $paidCurrencyId !== (int) $invoice->currency_id) {
                    $payAmountInInvoiceCurrency = $currencyService->convert(
                        $remainingAmount,
                        $paidCurrencyId,
                        $invoice->currency_id
                    );
                }

                $payAmount = min($payAmountInInvoiceCurrency, $invoiceRemaining);

                // حساب المبلغ الأصلي بعملة الدفع
                $originalPayAmount = $payAmount;
                if ($paidCurrencyId && $invoice->currency_id && (int) $paidCurrencyId !== (int) $invoice->currency_id) {
                    $originalPayAmount = $currencyService->convert(
                        $payAmount,
                        $invoice->currency_id,
                        $paidCurrencyId
                    );
                }

                $receipt = Receipt::create([
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                    'paid_currency_id' => $paidCurrencyId,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'amount' => $payAmount,
                    'unallocated_amount' => 0,
                    'original_amount' => $originalPayAmount,
                    'exchange_rate' => $exchangeRate,
                    'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                    'payment_method' => $data['payment_method'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? ($data['fifo_note'] ?? 'فاتورة #'.$invoice->invoice_number),
                    'created_by_user' => $data['created_by_user'] ?? null,
                    'updated_by_user' => $data['updated_by_user'] ?? null,
                ]);

                $receipts->push($receipt);
                $remainingAmount -= $originalPayAmount;
            }

            // إنشاء سند دفعة مقدمة بالفائض (فقط للمدفوعات النقدية والتحويلات، وليس للخصم)
            if ($remainingAmount > 0 && ($data['payment_method'] ?? null) !== 'discount') {
                $advanceReceipt = Receipt::create([
                    'client_id' => $client->id,
                    'invoice_id' => null,
                    'paid_currency_id' => $paidCurrencyId,
                    'bank_account_id' => $data['bank_account_id'] ?? null,
                    'amount' => $remainingAmount,
                    'unallocated_amount' => $remainingAmount,
                    'original_amount' => $remainingAmount,
                    'exchange_rate' => $exchangeRate,
                    'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                    'payment_method' => $data['payment_method'],
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => trim(($data['notes'] ?? '').' (سند دفعة مقدمة - فائض سداد)'),
                    'created_by_user' => $data['created_by_user'] ?? null,
                    'updated_by_user' => $data['updated_by_user'] ?? null,
                ]);
                $receipts->push($advanceReceipt);

                // تسجيل نشاط سند الدفعة المقدمة
                activity()
                    ->performedOn($client)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'amount' => $remainingAmount,
                        'receipt_id' => $advanceReceipt->id,
                        'source' => 'advance_payment',
                    ])
                    ->log('تسجيل سند دفعة مقدمة (فائض سداد): '.number_format($remainingAmount, 2));
            }

            return $receipts;
        });
    }

    /**
     * حساب المبلغ الشهري للاشتراك.
     */
    public function calculateMonthlyAmount(?\App\Models\Contract $contract): float
    {
        if (! $contract) {
            return 0.0;
        }

        return (float) (($contract->total_amount ?? 0.0) + ($contract->marketing_amount ?? 0.0));
    }

    /**
     * حساب مبلغ فترة محددة للاشتراك.
     */
    public function calculatePeriodAmount(?\App\Models\Contract $contract, ?string $startDate, ?string $endDate): float
    {
        if (! $contract || ! $startDate || ! $endDate) {
            return 0.0;
        }

        $start = \Illuminate\Support\Carbon::parse($startDate);
        $end = \Illuminate\Support\Carbon::parse($endDate);

        if ($start->isAfter($end)) {
            return 0.0;
        }

        $days = $start->diffInDays($end) + 1;

        return $this->calculateCountAmount($contract, $days);
    }

    /**
     * حساب مبلغ عدد أيام معين للاشتراك — تستخدم BCMath لدقة عالية.
     */
    public function calculateCountAmount(?\App\Models\Contract $contract, int $days): float
    {
        if (! $contract || $days <= 0) {
            return 0.0;
        }

        $monthlyAmount = (string) $this->calculateMonthlyAmount($contract);
        $dailyRate = bcdiv($monthlyAmount, '30', 4);

        return (float) bcmul($dailyRate, (string) $days, 2);
    }

    /**
     * تطبيق الخصم وحساب الصافي.
     */
    public function applyDiscount(float $amount, float $discount): float
    {
        return max(0.0, round($amount - $discount, 2));
    }

    /**
     * تسجيل سداد مع خصم (إن وجد) وتوزيعه FIFO.
     *
     * @param  array{
     *     amount: float,
     *     discount?: float,
     *     payment_method: string,
     *     receipt_date: string,
     *     reference_number?: string,
     *     notes?: string,
     *     created_by_user?: int,
     *     updated_by_user?: int
     * }  $data
     * @return Collection<int, Receipt>
     */
    public function recordPaymentWithDiscount(Client $client, array $data): Collection
    {
        $netAmount = (float) ($data['amount'] ?? 0.0);
        $discountAmount = (float) ($data['discount'] ?? 0.0);

        // التقريب إلى منزلتين عشريتين لتجنب أخطاء الفاصلة العائمة (float precision)
        $netAmount = round($netAmount, 2);
        $discountAmount = round($discountAmount, 2);

        // التحقق من صحة الخصم: لا يمكن أن يتجاوز إجمالي المديونية
        $totalOutstanding = round($client->outstanding_balance, 2);

        if ($discountAmount > $totalOutstanding && ($discountAmount - $totalOutstanding) > 1) {
            throw new \InvalidArgumentException(
                'الخصم ('.number_format($discountAmount, 2).') أكبر من إجمالي المديونية ('.number_format($totalOutstanding, 2).').'
            );
        }

        return DB::transaction(function () use ($client, $data, $netAmount, $discountAmount) {
            $receipts = collect();

            // 1. Pay Discount Amount FIRST using 'discount' method (يُطبّق الخصم أولاً لتخفيض المديونية)
            if ($discountAmount > 0) {
                $discountData = array_merge($data, [
                    'amount' => $discountAmount,
                    'payment_method' => 'discount',
                    'notes' => trim(($data['notes'] ?? '').' (خصم ممنوح)'),
                    'reference_number' => null,
                ]);
                $discountReceipts = $this->payClientFifo($client, $discountData);
                $receipts = $receipts->merge($discountReceipts);
            }

            // 2. Pay Net Amount using the chosen payment method (يسدد المبلغ المقبوض المتبقي وأي فائض يتحول لسند دفعة مقدمة)
            if ($netAmount > 0) {
                $netData = array_merge($data, [
                    'amount' => $netAmount,
                ]);
                $netReceipts = $this->payClientFifo($client, $netData);
                $receipts = $receipts->merge($netReceipts);
            }

            return $receipts;
        });
    }

    /**
     * شراء تصاميم إضافية وتوليد فاتورة بها.
     *
     * @param  array{design_quantity: int, per_design_price: float, pay_from_wallet: bool, created_by_user?: int, updated_by_user?: int}  $data
     */
    public function buyAdditionalDesigns(Client $client, array $data): Invoice
    {
        $quantity = (int) $data['design_quantity'];
        $price = (float) $data['per_design_price'];
        $payFromWallet = (bool) ($data['pay_from_wallet'] ?? false);
        $totalAmount = round($quantity * $price, 2);
        $userId = $data['created_by_user'] ?? null;
        $contract = $client->currentContract;

        return DB::transaction(function () use ($client, $contract, $quantity, $price, $totalAmount, $payFromWallet, $userId) {
            // قفل سجل العميل لمنع التحديث المتزامن
            $lockedClient = Client::lockForUpdate()->findOrFail($client->id);

            // 1. Create Invoice
            $invoice = Invoice::create([
                'client_id' => $lockedClient->id,
                'contract_id' => $contract?->id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'total_amount' => $totalAmount,
                'status' => 'posted',
                'notes' => "شراء تصاميم إضافية (عدد {$quantity})",
                'created_by_user' => $userId,
                'updated_by_user' => $userId,
            ]);

            // 2. Create Invoice Item
            $invoice->items()->create([
                'description' => "شراء تصاميم إضافية (عدد {$quantity} × ".number_format($price, 2).')',
                'quantity' => $quantity,
                'unit_amount' => $price,
                'total' => $totalAmount,
            ]);

            // 3. Increment client's prepaid design balance (الرصيد مقفل الآن)
            $oldDesignBalance = $lockedClient->additional_designs_balance;
            $lockedClient->increment('additional_designs_balance', $quantity);

            // تسجيل نشاط شراء تصاميم إضافية
            activity()
                ->performedOn($lockedClient)
                ->causedBy(Auth::user())
                ->withProperties([
                    'old_balance' => $oldDesignBalance,
                    'new_balance' => $oldDesignBalance + $quantity,
                    'change' => $quantity,
                    'source' => 'additional_designs',
                    'invoice_id' => $invoice->id,
                ])
                ->log('شراء تصاميم إضافية: '.$quantity.' تصميم بمبلغ '.number_format($totalAmount, 2));

            // 4. Pay from advances or legacy wallet if requested
            if ($payFromWallet) {
                if ($lockedClient->total_advance_balance > 0) {
                    $this->autoAllocateAdvances($invoice, $userId);
                } elseif ((float) ($lockedClient->getRawOriginal('wallet_balance') ?? 0) > 0) {
                    $legacyBalance = (float) $lockedClient->getRawOriginal('wallet_balance');
                    $applyAmount = min($legacyBalance, $totalAmount);
                    if ($applyAmount > 0) {
                        DB::update(
                            'UPDATE clients SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?',
                            [$applyAmount, $lockedClient->id, $applyAmount]
                        );
                        Receipt::create([
                            'client_id' => $lockedClient->id,
                            'invoice_id' => $invoice->id,
                            'amount' => $applyAmount,
                            'receipt_date' => now()->toDateString(),
                            'payment_method' => 'wallet',
                            'notes' => 'سداد تلقائي من رصيد المحفظة لشراء تصاميم إضافية',
                            'created_by_user' => $userId,
                            'updated_by_user' => $userId,
                        ]);
                        $invoice->refresh();
                        if ($invoice->remaining <= 0) {
                            $invoice->update(['status' => 'paid']);
                        }
                    }
                }
            }

            return $invoice;
        });
    }

    /**
     * تخصيص مبلغ من سند دفعة مقدمة على فاتورة محددة.
     *
     * @throws \InvalidArgumentException إذا كان المبلغ غير متاح أو يتجاوز متبقي الفاتورة
     */
    public function allocateReceiptToInvoice(Receipt $receipt, Invoice $invoice, float $amount, ?int $userId = null): ReceiptAllocation
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ التخصيص يجب أن يكون أكبر من الصفر.');
        }

        $invoiceRemaining = round($invoice->remaining, 2);
        if ($amount > $invoiceRemaining && ($amount - $invoiceRemaining) > 0.01) {
            throw new \InvalidArgumentException(
                "المبلغ المطلوب تخصيصه ({$amount}) يتجاوز متبقي الفاتورة ({$invoiceRemaining})."
            );
        }

        $currencyService = app(CurrencyService::class);
        $baseCurrency = Currency::getBase();

        // حساب المبلغ المطلوب خصمه من رصيد السند (بعملة السند)
        $deductFromReceipt = $amount;
        if ($receipt->paid_currency_id && $invoice->currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id) {
            $deductFromReceipt = round($currencyService->convert(
                $amount,
                $invoice->currency_id,
                $receipt->paid_currency_id
            ), 2);
        }

        $unallocated = round((float) $receipt->unallocated_amount, 2);
        if ($deductFromReceipt > $unallocated && ($deductFromReceipt - $unallocated) > 0.01) {
            $isCrossCurrency = $receipt->paid_currency_id && $invoice->currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id;
            // إذا كان الفارق ناتجاً عن كسور تقريب التحويل بين عملتين مختلفتين، نضبط الخصم على كامل الرصيد المتاح بالسند
            if ($isCrossCurrency && ($deductFromReceipt - $unallocated) <= 1.0) {
                $deductFromReceipt = $unallocated;
            } else {
                throw new \InvalidArgumentException(
                    "المبلغ المطلوب خصمه من السند ({$deductFromReceipt}) يتجاوز الرصيد غير المخصص المتاح في السند ({$unallocated})."
                );
            }
        }

        // حساب سعر الصرف (عملة السند -> عملة الفاتورة)
        $exchangeRate = 1.0;
        if ($receipt->paid_currency_id && $invoice->currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id) {
            $exchangeRate = $currencyService->getLatestRate($receipt->paid_currency_id, $invoice->currency_id);
        }

        return DB::transaction(function () use ($receipt, $invoice, $amount, $deductFromReceipt, $exchangeRate, $userId) {
            // خصم المبلغ غير المخصص من السند
            $receipt->decrement('unallocated_amount', $deductFromReceipt);

            // إنشاء سجل التخصيص
            $allocation = ReceiptAllocation::create([
                'receipt_id' => $receipt->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'allocated_currency_id' => $invoice->currency_id,
                'exchange_rate' => $exchangeRate,
                'notes' => 'تسوية وتخصيص من سند دفعة مقدمة #'.$receipt->id,
                'created_by_user' => $userId ?? auth()->id(),
            ]);

            // تحديث حالة الفاتورة إذا تم سدادها بالكامل
            $freshInvoice = $invoice->fresh(['receipts', 'allocations']);
            if ($freshInvoice && $freshInvoice->remaining <= 0) {
                $freshInvoice->update(['status' => 'paid']);
            }

            // تسجيل النشاط
            activity()
                ->performedOn($invoice)
                ->causedBy(Auth::user())
                ->withProperties([
                    'receipt_id' => $receipt->id,
                    'allocation_id' => $allocation->id,
                    'allocated_amount' => $amount,
                    'deducted_from_receipt' => $deductFromReceipt,
                ])
                ->log("تخصيص مبلغ {$amount} من سند دفعة مقدمة #{$receipt->id} لصالح الفاتورة #{$invoice->invoice_number}");

            return $allocation;
        });
    }

    /**
     * تخصيص الدفعات المقدمة المتاحة للعميل آلياً على فاتورة (FIFO).
     *
     * @return Collection<int, ReceiptAllocation>
     */
    public function autoAllocateAdvances(Invoice $invoice, ?int $userId = null): Collection
    {
        $client = $invoice->client;
        if (! $client) {
            return collect();
        }

        $unallocatedReceipts = $client->unallocatedReceipts()
            ->orderBy('receipt_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($unallocatedReceipts->isEmpty()) {
            return collect();
        }

        $currencyService = app(CurrencyService::class);
        $allocations = collect();

        return DB::transaction(function () use ($unallocatedReceipts, $invoice, $currencyService, $userId, $allocations) {
            foreach ($unallocatedReceipts as $receipt) {
                $freshInvoice = $invoice->fresh(['receipts', 'allocations']);
                $remaining = round($freshInvoice->remaining, 2);
                if ($remaining <= 0) {
                    break;
                }

                $availableReceiptAmount = round((float) $receipt->unallocated_amount, 2);
                if ($availableReceiptAmount <= 0) {
                    continue;
                }

                // تحويل الرصيد المتاح لعملة الفاتورة
                $availableInInvoiceCurrency = $availableReceiptAmount;
                if ($receipt->paid_currency_id && $invoice->currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id) {
                    $availableInInvoiceCurrency = round($currencyService->convert(
                        $availableReceiptAmount,
                        $receipt->paid_currency_id,
                        $invoice->currency_id
                    ), 2);
                }

                $allocAmount = min($availableInInvoiceCurrency, $remaining);
                if ($allocAmount > 0) {
                    $allocation = $this->allocateReceiptToInvoice($receipt, $invoice, $allocAmount, $userId);
                    $allocations->push($allocation);
                }
            }

            return $allocations;
        });
    }
}
