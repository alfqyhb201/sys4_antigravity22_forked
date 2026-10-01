<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Invoice;

/**
 * خدمة لوحة التحكم الرئيسية للأدمن
 *
 * توفر إحصائيات سريعة ومختصرة تعرض في الصفحة الرئيسية لدور Admin.
 */
class AdminDashboardService
{
    /**
     * إحصائيات العملاء والاشتراكات
     *
     * @return array{activeClients: int, activeContracts: int}
     */
    public function getClientContractStats(): array
    {
        $activeClients = Client::query()
            ->whereHas('currentContract', fn ($q) => $q->where('status', 'active'))
            ->count();

        $activeContracts = Contract::query()
            ->where('status', 'active')
            ->count();

        return [
            'activeClients' => $activeClients,
            'activeContracts' => $activeContracts,
        ];
    }

    /**
     * إحصائيات سير العمل (التصاميم)
     *
     * @return array{pendingReview: int, overdueTasks: int}
     */
    public function getWorkflowStats(): array
    {
        // التصاميم بانتظار المراجعة
        $pendingReview = ClientTagDistribution::query()
            ->where('status', 'reviewing')
            ->count();

        // المهام المتأخرة: changes_requested + pending/in_progress من أيام سابقة
        $overdueTasks = ClientTagDistribution::query()
            ->where(function ($q) {
                $q->where('status', 'changes_requested')
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', ['pending', 'in_progress'])
                            ->where('distribution_date', '<', now()->toDateString());
                    });
            })
            ->count();

        return [
            'pendingReview' => $pendingReview,
            'overdueTasks' => $overdueTasks,
        ];
    }

    /**
     * لمحة مالية سريعة
     *
     * @return array{totalInvoiced: float, totalOverdue: float}
     */
    public function getFinancialSnapshot(): array
    {
        // إجمالي المستحق (مجموع الفواتير posted + paid)
        $totalInvoiced = (float) Invoice::query()
            ->whereIn('status', ['posted', 'paid'])
            ->sum('total_amount');

        // الديون المتأخرة: فواتير posted تجاوزت تاريخ الاستحقاق + مهلة السماح
        $overdueInvoices = Invoice::query()
            ->where('status', 'posted')
            ->withSum('receipts', 'amount')
            ->with('contract')
            ->get();

        $totalOverdue = 0.0;
        foreach ($overdueInvoices as $invoice) {
            $graceDays = $invoice->contract?->grace_period_days ?? 7;
            if ($invoice->due_date && $invoice->due_date->copy()->addDays($graceDays)->isBefore(now()->startOfDay())) {
                $totalOverdue += (float) $invoice->remaining;
            }
        }

        return [
            'totalInvoiced' => $totalInvoiced,
            'totalOverdue' => $totalOverdue,
        ];
    }

    /**
     * جميع إحصائيات لوحة الأدمن في مصفوفة واحدة
     *
     * @return array{activeClients: int, activeContracts: int, pendingReview: int, overdueTasks: int, totalInvoiced: float, totalOverdue: float}
     */
    public function getAllStats(): array
    {
        return array_merge(
            $this->getClientContractStats(),
            $this->getWorkflowStats(),
            $this->getFinancialSnapshot()
        );
    }
}
