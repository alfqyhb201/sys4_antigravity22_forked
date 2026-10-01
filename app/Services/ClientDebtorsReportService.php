<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientDebtorsReportService
{
    /**
     * جلب بيانات تقرير مديونيات العملاء مع تفصيل فواتيرهم وإجمالياتهم.
     *
     * @param  array{currency_id?: ?int, search?: ?string, sort_by?: ?string}  $filters
     * @return array{
     *     debtors: array<int, array{
     *         client_id: int,
     *         company_name: string,
     *         client_notes: ?string,
     *         total_debt_formatted: string,
     *         totals_by_currency: array<string, array{amount: float, symbol: string, code: string}>,
     *         invoices: array<int, array{
     *             id: int,
     *             debt_amount: float,
     *             debt_amount_formatted: string,
     *             currency_code: string,
     *             currency_symbol: string,
     *             debt_date: string,
     *             debt_date_type: string,
     *             is_overdue: bool,
     *             overdue_days: int,
     *             notes: ?string,
     *         }>,
     *     }>,
     *     grand_totals_by_currency: array<string, array{amount: float, symbol: string, code: string}>,
     *     total_debtor_clients: int,
     *     total_unpaid_invoices: int,
     *     generated_at: string,
     *     generated_by: ?string,
     * }
     */
    public function getDebtorsReportData(array $filters = []): array
    {
        $currencyId = $filters['currency_id'] ?? null;
        $search = $filters['search'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'debt_desc';

        $clientsQuery = Client::query()
            ->whereHas('invoices', function ($query) use ($currencyId) {
                $query->where('status', 'posted');
                if ($currencyId) {
                    $query->where('currency_id', $currencyId);
                }
            })
            ->with([
                'invoices' => function ($query) use ($currencyId) {
                    $query->where('status', 'posted')
                        ->with(['currency', 'receipts', 'allocations'])
                        ->orderBy('due_date', 'asc')
                        ->orderBy('issue_date', 'asc');

                    if ($currencyId) {
                        $query->where('currency_id', $currencyId);
                    }
                },
                'currentContract.currency',
            ]);

        if (! empty($search)) {
            $clientsQuery->where(function ($q) use ($search) {
                $q->where('company', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('invoices', function ($invQ) use ($search) {
                        $invQ->where('notes', 'like', "%{$search}%");
                    });
            });
        }

        $clients = $clientsQuery->get();

        $today = Carbon::now()->startOfDay();
        $debtorsList = [];
        $grandTotals = [];
        $totalInvoicesCount = 0;

        foreach ($clients as $client) {
            /** @var Collection<int, Invoice> $unpaidInvoices */
            $unpaidInvoices = $client->invoices->filter(fn (Invoice $inv) => $inv->remaining > 0);

            if ($unpaidInvoices->isEmpty()) {
                continue;
            }

            $clientTotalsByCurrency = [];
            $invoicesData = [];
            $maxClientDebtAmount = 0.0;
            $oldestDueDate = null;

            foreach ($unpaidInvoices as $invoice) {
                $rem = (float) $invoice->remaining;
                $maxClientDebtAmount += $rem;
                $curr = $invoice->currency;
                $currCode = $curr?->currency ?? 'YER';
                $currSymbol = $curr?->symbol ?? $curr?->currency ?? 'ر.ي';

                // تجميع إجماليات العميل لكل عملة
                if (! isset($clientTotalsByCurrency[$currCode])) {
                    $clientTotalsByCurrency[$currCode] = [
                        'amount' => 0.0,
                        'symbol' => $currSymbol,
                        'code' => $currCode,
                    ];
                }
                $clientTotalsByCurrency[$currCode]['amount'] += $rem;

                // تجميع الإجمالي العام للتقرير لكل عملة
                if (! isset($grandTotals[$currCode])) {
                    $grandTotals[$currCode] = [
                        'amount' => 0.0,
                        'symbol' => $currSymbol,
                        'code' => $currCode,
                    ];
                }
                $grandTotals[$currCode]['amount'] += $rem;

                $totalInvoicesCount++;

                // تحديد تاريخ المديونية
                $debtDateCarbon = $invoice->due_date ? Carbon::parse($invoice->due_date) : ($invoice->issue_date ? Carbon::parse($invoice->issue_date) : null);
                $debtDateFormatted = $debtDateCarbon ? $debtDateCarbon->format('Y-m-d') : '-';
                $debtDateType = $invoice->due_date ? 'تاريخ الاستحقاق' : 'تاريخ الإصدار';

                $isOverdue = false;
                $overdueDays = 0;
                if ($debtDateCarbon && $debtDateCarbon->lt($today)) {
                    $isOverdue = true;
                    $overdueDays = (int) $debtDateCarbon->diffInDays($today);
                }

                if ($debtDateCarbon && ($oldestDueDate === null || $debtDateCarbon->lt($oldestDueDate))) {
                    $oldestDueDate = $debtDateCarbon;
                }

                $invoicesData[] = [
                    'id' => $invoice->id,
                    'debt_amount' => $rem,
                    'debt_amount_formatted' => number_format($rem, 2).' '.$currSymbol,
                    'currency_code' => $currCode,
                    'currency_symbol' => $currSymbol,
                    'debt_date' => $debtDateFormatted,
                    'debt_date_type' => $debtDateType,
                    'is_overdue' => $isOverdue,
                    'overdue_days' => $overdueDays,
                    'notes' => $invoice->notes,
                ];
            }

            // صياغة إجمالي مديونية العميل
            $clientDebtParts = [];
            foreach ($clientTotalsByCurrency as $currData) {
                $clientDebtParts[] = number_format($currData['amount'], 2).' '.$currData['symbol'];
            }
            $totalDebtFormatted = implode(' + ', $clientDebtParts);

            $companyName = trim($client->company ?: ($client->client_name ?: 'عميل #'.$client->id));

            $debtorsList[] = [
                'client_id' => $client->id,
                'company_name' => $companyName,
                'client_notes' => $client->notes,
                'total_debt_amount' => $maxClientDebtAmount,
                'total_debt_formatted' => $totalDebtFormatted,
                'totals_by_currency' => $clientTotalsByCurrency,
                'oldest_due_date' => $oldestDueDate,
                'invoices' => $invoicesData,
            ];
        }

        // الترتيب
        usort($debtorsList, function ($a, $b) use ($sortBy) {
            return match ($sortBy) {
                'debt_asc' => $a['total_debt_amount'] <=> $b['total_debt_amount'],
                'date_asc' => ($a['oldest_due_date']?->timestamp ?? 0) <=> ($b['oldest_due_date']?->timestamp ?? 0),
                'date_desc' => ($b['oldest_due_date']?->timestamp ?? 0) <=> ($a['oldest_due_date']?->timestamp ?? 0),
                'name_asc' => strcmp($a['company_name'], $b['company_name']),
                default => $b['total_debt_amount'] <=> $a['total_debt_amount'], // debt_desc
            };
        });

        return [
            'debtors' => $debtorsList,
            'grand_totals_by_currency' => $grandTotals,
            'total_debtor_clients' => count($debtorsList),
            'total_unpaid_invoices' => $totalInvoicesCount,
            'generated_at' => Carbon::now()->locale('ar')->isoFormat('D MMMM YYYY - hh:mm A'),
            'generated_by' => auth()->user()?->name ?? 'النظام',
        ];
    }
}
