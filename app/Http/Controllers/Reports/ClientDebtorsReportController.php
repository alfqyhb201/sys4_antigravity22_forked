<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\ClientDebtorsReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClientDebtorsReportController extends Controller
{
    public function __construct(
        protected ClientDebtorsReportService $reportService
    ) {}

    /**
     * عرض أو تصدير تقرير مديونيات العملاء كجدول رسمي معد للطباعة.
     */
    public function index(Request $request): View|Response
    {
        $user = auth()->user();
        if (! $user || (! $user->hasRole(['admin', 'super_admin']) && ! $user->can('export_financial_data') && ! $user->can('view_financial_reports') && ! $user->can('view_any_invoice'))) {
            abort(403, 'غير مصرح لك بالوصول إلى تقرير المديونيات المالي.');
        }

        $filters = [
            'currency_id' => $request->filled('currency_id') ? (int) $request->query('currency_id') : null,
            'search' => $request->query('search'),
            'sort_by' => $request->query('sort_by', 'debt_desc'),
        ];

        $reportData = $this->reportService->getDebtorsReportData($filters);
        $currencies = Currency::where('is_active', true)->get();

        $viewData = array_merge($reportData, [
            'currencies' => $currencies,
            'filters' => $filters,
            'autoPrint' => $request->boolean('print'),
        ]);

        if ($request->query('download') === 'pdf') {
            $pdf = Pdf::loadView('reports.client-debtors-pdf', $viewData)
                ->setPaper('a4', 'portrait')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isRemoteEnabled', true);

            return $pdf->download('client-debtors-report-'.now()->format('Y-m-d').'.pdf');
        }

        return view('reports.client-debtors-report', $viewData);
    }
}
