<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Builder;

class FinancialDashboardService
{
    /**
     * @return array{startDate: ?string, endDate: ?string, label: string}
     */
    public function resolvePeriod(string $period = 'all'): array
    {
        return match ($period) {
            'today' => [
                'startDate' => now()->toDateString(),
                'endDate' => now()->toDateString(),
                'label' => 'اليوم',
            ],
            'week' => [
                'startDate' => now()->startOfWeek()->toDateString(),
                'endDate' => now()->endOfWeek()->toDateString(),
                'label' => 'هذا الأسبوع',
            ],
            'month' => [
                'startDate' => now()->startOfMonth()->toDateString(),
                'endDate' => now()->endOfMonth()->toDateString(),
                'label' => 'هذا الشهر',
            ],
            'quarter' => [
                'startDate' => now()->startOfQuarter()->toDateString(),
                'endDate' => now()->endOfQuarter()->toDateString(),
                'label' => 'هذا الربع',
            ],
            'year' => [
                'startDate' => now()->startOfYear()->toDateString(),
                'endDate' => now()->endOfYear()->toDateString(),
                'label' => 'هذه السنة',
            ],
            default => [
                'startDate' => null,
                'endDate' => null,
                'label' => 'إجمالي',
            ],
        };
    }

    /**
     * @return array{periodLabel: string, totalInvoiced: float, totalPaid: float, totalRemaining: float, totalOverdue: float, overdueCount: int, activeContracts: int, suspendedContracts: int}
     */
    public function getAccountingSnapshot(string $period = 'all', ?int $targetCurrencyId = null): array
    {
        $range = $this->resolvePeriod($period);
        $currencyService = app(CurrencyService::class);
        $baseCurrency = Currency::getBase() ?? Currency::first();
        $targetCurrencyId = $targetCurrencyId ?? $baseCurrency?->id;

        $invoicedQuery = Invoice::query()
            ->whereIn('status', ['posted', 'paid']);

        $this->applyDateRange($invoicedQuery, $range['startDate'], $range['endDate']);

        $invoices = $invoicedQuery->with(['receipts'])->get();

        $totalInvoiced = 0.0;
        $totalRemaining = 0.0;
        $totalOverdue = 0.0;
        $overdueCount = 0;

        foreach ($invoices as $invoice) {
            $invCurrencyId = $invoice->currency_id ?? $baseCurrency?->id;

            $invAmountInTarget = $this->convertAmountToTarget(
                (float) $invoice->total_amount,
                $invCurrencyId,
                $targetCurrencyId,
                $invoice->exchange_rate,
                $invoice->issue_date,
                $currencyService
            );
            $totalInvoiced += $invAmountInTarget;

            if ($invoice->status === 'posted') {
                $remInInvCurrency = $invoice->remaining;
                $remInTarget = $this->convertAmountToTarget(
                    $remInInvCurrency,
                    $invCurrencyId,
                    $targetCurrencyId,
                    $invoice->exchange_rate,
                    $invoice->issue_date,
                    $currencyService
                );
                $totalRemaining += $remInTarget;

                $graceDays = $invoice->contract?->grace_period_days ?? 7;
                if ($invoice->due_date && $invoice->due_date->copy()->addDays($graceDays)->isBefore(now()->startOfDay())) {
                    $totalOverdue += max(0, $remInTarget);
                    $overdueCount++;
                }
            }
        }

        // Receipt query
        $receiptsQuery = Receipt::query();
        if ($range['startDate'] && $range['endDate']) {
            $receiptsQuery->whereBetween('receipt_date', [$range['startDate'], $range['endDate']]);
        }

        $receipts = $receiptsQuery->get();
        $totalPaid = 0.0;
        foreach ($receipts as $receipt) {
            $recCurrencyId = $receipt->paid_currency_id ?? $baseCurrency?->id;
            $recAmount = (float) ($receipt->original_amount ?? $receipt->amount);

            $totalPaid += $this->convertAmountToTarget(
                $recAmount,
                $recCurrencyId,
                $targetCurrencyId,
                $receipt->exchange_rate,
                $receipt->receipt_date,
                $currencyService
            );
        }

        $activeContracts = Contract::query()->where('status', 'active')->count();
        $suspendedContracts = Contract::query()->where('status', 'suspended')->count();

        return [
            'periodLabel' => $range['label'],
            'totalInvoiced' => round($totalInvoiced, 2),
            'totalPaid' => round($totalPaid, 2),
            'totalRemaining' => round($totalRemaining, 2),
            'totalOverdue' => round($totalOverdue, 2),
            'overdueCount' => $overdueCount,
            'activeContracts' => $activeContracts,
            'suspendedContracts' => $suspendedContracts,
        ];
    }

    private function convertAmountToTarget(
        float $amount,
        ?int $fromCurrencyId,
        ?int $targetCurrencyId,
        ?float $frozenRate,
        mixed $date,
        CurrencyService $currencyService
    ): float {
        if (! $fromCurrencyId || ! $targetCurrencyId || $fromCurrencyId === $targetCurrencyId || $amount == 0) {
            return $amount;
        }

        return $currencyService->convert(
            $amount,
            $fromCurrencyId,
            $targetCurrencyId,
            null,
            2
        );
    }

    public function topDebtorsQuery(): Builder
    {
        return Client::query()
            ->with([
                'currentContract.currency',
                'invoices' => fn ($query) => $query->where('status', 'posted'),
            ])
            ->withCount(['invoices as invoices_count' => fn ($query) => $query->whereIn('status', ['posted', 'paid'])])
            ->addSelect([
                'total_invoiced' => Invoice::selectRaw('COALESCE(SUM(COALESCE(base_currency_amount, total_amount)), 0)')
                    ->whereColumn('client_id', 'clients.id')
                    ->whereIn('status', ['posted', 'paid']),
                'outstanding_balance' => Invoice::selectRaw('
                    COALESCE(SUM(
                        CASE 
                            WHEN (
                                COALESCE(base_currency_amount, total_amount) 
                                - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                            ) < 0 THEN 0 
                            ELSE (
                                COALESCE(base_currency_amount, total_amount) 
                                - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                            )
                        END
                    ), 0)
                ')
                    ->whereColumn('client_id', 'clients.id')
                    ->where('status', 'posted'),
                'advance_balance' => Receipt::selectRaw('COALESCE(SUM(unallocated_amount * COALESCE(exchange_rate, 1.0)), 0)')
                    ->whereColumn('client_id', 'clients.id')
                    ->whereNull('invoice_id')
                    ->where('unallocated_amount', '>', 0),
                'last_payment_date' => Receipt::selectRaw('MAX(receipt_date)')
                    ->whereColumn('client_id', 'clients.id'),
            ]);
    }

    public function prepareClientFinancialProfile(Client $client): Client
    {
        $client->loadMissing([
            'currentContract.currency',
            'invoices.receipts',
            'receipts.paidCurrency',
        ]);

        $currencyService = app(CurrencyService::class);
        $baseCurrency = Currency::getBase() ?? Currency::first();
        $targetCurrency = $baseCurrency;
        $targetCurrencyId = $targetCurrency?->id;

        $invoices = $client->invoices->whereIn('status', ['posted', 'paid']);

        $invoicedAmount = 0.0;
        $outstandingBalance = 0.0;

        foreach ($invoices as $invoice) {
            $invCurrencyId = $invoice->currency_id ?? $targetCurrencyId;

            $invAmount = $this->convertAmountToTarget(
                (float) $invoice->total_amount,
                $invCurrencyId,
                $targetCurrencyId,
                $invoice->exchange_rate,
                $invoice->issue_date,
                $currencyService
            );
            $invoicedAmount += $invAmount;

            if ($invoice->status === 'posted') {
                $remInTarget = $this->convertAmountToTarget(
                    (float) $invoice->remaining,
                    $invCurrencyId,
                    $targetCurrencyId,
                    $invoice->exchange_rate,
                    $invoice->due_date ?? $invoice->issue_date,
                    $currencyService
                );
                $outstandingBalance += max(0, $remInTarget);
            }
        }

        $paidAmount = 0.0;
        foreach ($client->receipts as $receipt) {
            $recCurrencyId = $receipt->paid_currency_id ?? $targetCurrencyId;
            $paidAmount += $this->convertAmountToTarget(
                (float) ($receipt->original_amount ?? $receipt->amount),
                $recCurrencyId,
                $targetCurrencyId,
                $receipt->exchange_rate,
                $receipt->receipt_date,
                $currencyService
            );
        }

        $client->setAttribute('invoices_count', $invoices->count());
        $client->setAttribute('total_invoiced', round($invoicedAmount, 2));
        $client->setAttribute('invoiced_amount', round($invoicedAmount, 2));
        $client->setAttribute('total_paid', round($paidAmount, 2));
        $client->setAttribute('paid_amount', round($paidAmount, 2));
        $client->setAttribute('outstanding_balance', round($outstandingBalance, 2));
        $client->setAttribute('profile_currency_symbol', $targetCurrency?->symbol ?? $targetCurrency?->currency ?? 'ر.س');
        $client->setAttribute('last_payment_date', $client->receipts->max('receipt_date'));

        return $client;
    }

    /**
     * @return array{all: int, critical_overdue: int, expiring_soon: int, expired_suspended: int}
     */
    public function getClientFinancialTabCounts(): array
    {
        $today = now()->startOfDay();

        $all = Client::query()->count();

        $criticalOverdue = Client::query()
            ->whereHas('invoices', function (Builder $q) use ($today) {
                $q->where('status', 'posted')
                    ->where('due_date', '<', $today);
            })
            ->whereRaw('(
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN (
                            COALESCE(base_currency_amount, total_amount) 
                            - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                            - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                        ) < 0 THEN 0 
                        ELSE (
                            COALESCE(base_currency_amount, total_amount) 
                            - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                            - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                        )
                    END
                ), 0)
                FROM invoices
                WHERE invoices.client_id = clients.id
                AND invoices.status = "posted"
                AND invoices.deleted_at IS NULL
            ) > 0')
            ->count();

        $expiringSoon = Client::query()
            ->whereHas('currentContract', function (Builder $q) use ($today) {
                $q->where('status', 'active')
                    ->whereBetween('end_date', [$today, $today->copy()->addDays(7)]);
            })
            ->count();

        $expiredSuspended = Client::query()
            ->where(function (Builder $query) use ($today) {
                $query->whereHas('currentContract', function (Builder $contractQ) use ($today) {
                    $contractQ->where(function (Builder $sub) use ($today) {
                        $sub->where('end_date', '<', $today)
                            ->orWhereIn('status', ['suspended', 'expired']);
                    });
                })
                    ->orWhere('status', false);
            })
            ->count();

        return [
            'all' => $all,
            'critical_overdue' => $criticalOverdue,
            'expiring_soon' => $expiringSoon,
            'expired_suspended' => $expiredSuspended,
        ];
    }

    /**
     * Get daily trends for the last 7 days to power sparkline charts with only 2 queries.
     *
     * @return array{invoiced: array<int, float>, paid: array<int, float>, labels: array<int, string>}
     */
    public function getDailyTrends(?int $targetCurrencyId = null): array
    {
        $currencyService = app(CurrencyService::class);
        $baseCurrency = Currency::getBase() ?? Currency::first();
        $targetCurrencyId = $targetCurrencyId ?? $baseCurrency?->id;

        $startDate = now()->subDays(6)->toDateString();
        $endDate = now()->toDateString();

        $invoices = Invoice::query()
            ->whereIn('status', ['posted', 'paid'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->get();

        $receipts = Receipt::query()
            ->whereBetween('receipt_date', [$startDate, $endDate])
            ->get();

        $invoicesByDate = $invoices->groupBy(fn ($inv) => \Carbon\Carbon::parse($inv->issue_date)->toDateString());
        $receiptsByDate = $receipts->groupBy(fn ($rec) => \Carbon\Carbon::parse($rec->receipt_date)->toDateString());

        $invoiced = [];
        $paid = [];
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('D');

            $dayInvTotal = 0.0;
            if (isset($invoicesByDate[$date])) {
                foreach ($invoicesByDate[$date] as $inv) {
                    $dayInvTotal += $this->convertAmountToTarget(
                        (float) $inv->total_amount,
                        $inv->currency_id ?? $baseCurrency?->id,
                        $targetCurrencyId,
                        $inv->exchange_rate,
                        $inv->issue_date,
                        $currencyService
                    );
                }
            }
            $invoiced[] = round($dayInvTotal, 2);

            $dayPaidTotal = 0.0;
            if (isset($receiptsByDate[$date])) {
                foreach ($receiptsByDate[$date] as $rec) {
                    $dayPaidTotal += $this->convertAmountToTarget(
                        (float) ($rec->original_amount ?? $rec->amount),
                        $rec->paid_currency_id ?? $baseCurrency?->id,
                        $targetCurrencyId,
                        $rec->exchange_rate,
                        $rec->receipt_date,
                        $currencyService
                    );
                }
            }
            $paid[] = round($dayPaidTotal, 2);
        }

        return [
            'invoiced' => $invoiced,
            'paid' => $paid,
            'labels' => $labels,
        ];
    }

    /**
     * استخراج بيانات كشف الحساب المالي المعتمد للعميل (مع استبعاد المسودات والملغاة).
     *
     * @return array{
     *     client: Client,
     *     items: \Illuminate\Support\Collection,
     *     totalDebit: float,
     *     totalCredit: float,
     *     finalBalance: float,
     *     unallocatedAdvanceBalance: float,
     *     baseCurrencySymbol: string,
     *     baseCurrency: ?Currency,
     *     fromDate: ?string,
     *     toDate: ?string
     * }
     */
    public function getClientStatementData(Client $client, ?string $fromDate = null, ?string $toDate = null): array
    {
        $baseCurrency = Currency::getBase() ?? Currency::first();
        $baseCurrencySymbol = $baseCurrency?->symbol ?? $baseCurrency?->currency ?? 'ر.س';
        $baseCurrencyId = $baseCurrency?->id;

        $invoicesQuery = Invoice::where('client_id', $client->id)
            ->whereIn('status', ['posted', 'paid'])
            ->with(['currency']);

        if ($fromDate) {
            $invoicesQuery->whereDate('issue_date', '>=', $fromDate);
        }
        if ($toDate) {
            $invoicesQuery->whereDate('issue_date', '<=', $toDate);
        }

        $invoices = $invoicesQuery->get()->map(function ($inv) use ($baseCurrencyId, $baseCurrencySymbol) {
            $isForeign = $baseCurrencyId && $inv->currency_id && (int) $inv->currency_id !== (int) $baseCurrencyId;
            $date = $inv->issue_date ? \Illuminate\Support\Carbon::parse($inv->issue_date) : $inv->created_at;

            $item = new Invoice;
            $item->forceFill([
                'statement_id' => 'inv-'.$inv->id,
                'id' => $inv->id,
                'transaction_date' => $date,
                'created_at' => $date,
                'type' => 'invoice',
                'reference_number' => $inv->invoice_number,
                'details' => $inv->notes ?: 'فاتورة مبيعات',
                'debit' => (float) $inv->total_amount,
                'debit_base' => (float) ($inv->base_currency_amount ?? $inv->total_amount),
                'credit' => 0.0,
                'credit_base' => 0.0,
                'currency_symbol' => $inv->currency?->symbol ?? $inv->currency?->currency ?? $baseCurrencySymbol,
                'base_currency_symbol' => $baseCurrencySymbol,
                'is_foreign_currency' => $isForeign,
            ]);

            return $item;
        });

        $receiptsQuery = Receipt::where('client_id', $client->id)
            ->with(['paidCurrency', 'invoice']);

        if ($fromDate) {
            $receiptsQuery->whereDate('receipt_date', '>=', $fromDate);
        }
        if ($toDate) {
            $receiptsQuery->whereDate('receipt_date', '<=', $toDate);
        }

        $receipts = $receiptsQuery->get()->map(function ($rec) use ($baseCurrencyId, $baseCurrencySymbol) {
            $details = $rec->notes;
            if ($rec->invoice) {
                $details = ($details ? $details.' - ' : '').'عن الفاتورة '.$rec->invoice->invoice_number;
            }

            $isForeign = $baseCurrencyId && $rec->paid_currency_id && (int) $rec->paid_currency_id !== (int) $baseCurrencyId;
            $date = $rec->receipt_date ? \Illuminate\Support\Carbon::parse($rec->receipt_date) : $rec->created_at;

            $item = new Invoice;
            $item->forceFill([
                'statement_id' => 'rec-'.$rec->id,
                'id' => $rec->id,
                'transaction_date' => $date,
                'created_at' => $date,
                'type' => 'receipt',
                'reference_number' => $rec->reference_number ?: 'سند #'.$rec->id,
                'details' => $details ?: 'سند قبض',
                'debit' => 0.0,
                'debit_base' => 0.0,
                'credit' => (float) ($rec->original_amount ?? $rec->base_currency_amount ?? $rec->amount),
                'credit_base' => (float) ($rec->base_currency_amount ?? $rec->amount),
                'currency_symbol' => $rec->paidCurrency?->symbol ?? $rec->paidCurrency?->currency ?? $baseCurrencySymbol,
                'base_currency_symbol' => $baseCurrencySymbol,
                'is_foreign_currency' => $isForeign,
            ]);

            return $item;
        });

        $combined = $invoices->concat($receipts)->sortBy('transaction_date')->values();

        $runningBalance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($combined as $item) {
            $totalDebit += $item->debit_base;
            $totalCredit += $item->credit_base;
            $runningBalance += ($item->debit_base - $item->credit_base);
            $item->running_balance = $runningBalance;
        }

        return [
            'client' => $client,
            'items' => $combined,
            'totalDebit' => round($totalDebit, 2),
            'totalCredit' => round($totalCredit, 2),
            'finalBalance' => round($runningBalance, 2),
            'unallocatedAdvanceBalance' => round((float) $client->total_advance_balance, 2),
            'baseCurrencySymbol' => $baseCurrencySymbol,
            'baseCurrency' => $baseCurrency,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ];
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    private function applyDateRange(Builder $query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate && $endDate) {
            $query->whereBetween('issue_date', [$startDate, $endDate]);
        }
    }
}
