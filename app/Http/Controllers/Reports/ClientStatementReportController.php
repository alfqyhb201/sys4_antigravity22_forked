<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\FinancialDashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClientStatementReportController extends Controller
{
    public function __construct(
        protected FinancialDashboardService $dashboardService
    ) {}

    /**
     * عرض أو تصدير كشف حساب العميل المالي كجدول رسمي معد للطباعة ولـ PDF.
     */
    public function show(Request $request, Client $client): View|Response
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole(['admin', 'super_admin']) && ! $user->can('export_financial_data') && ! $user->can('view_financial_reports') && ! $user->can('view_any_invoice') && ! $user->can('view_client') && ! $user->can('view_client_financial'))) {
            abort(403, 'غير مصرح لك بالوصول إلى كشف حساب العميل.');
        }

        $client->loadMissing(['location', 'category', 'currentContract.currency']);

        $fromDate = $request->filled('from_date') ? $request->query('from_date') : null;
        $toDate = $request->filled('to_date') ? $request->query('to_date') : null;

        $statementData = $this->dashboardService->getClientStatementData($client, $fromDate, $toDate);

        $viewData = array_merge($statementData, [
            'autoPrint' => $request->boolean('print') || $request->query('download') === 'pdf',
            'generatedAt' => now()->format('Y-m-d H:i'),
            'generatedBy' => $user->name ?? 'مدير النظام',
        ]);

        return view('reports.client-statement-report', $viewData);
    }
}
