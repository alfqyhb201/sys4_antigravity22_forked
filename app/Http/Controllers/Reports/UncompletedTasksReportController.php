<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UncompletedTasksReportController extends Controller
{
    /**
     * عرض تقرير المهام غير المنجزة اليومي بصيغة مبسطة وجاهزة للطباعة والتصوير.
     */
    public function __invoke(Request $request): View
    {
        $user = auth()->user();

        if (! $user || (! $user->hasRole('supervisor') && ! $user->hasRole('admin') && ! $user->can('view_supervisor_dashboard'))) {
            abort(403, 'غير مصرح لك بالوصول إلى تقرير المهام غير المنجزة.');
        }

        $dateParam = $request->query('date');
        if ($dateParam && preg_match('/^(\d{4}-\d{2}-\d{2})/', $dateParam, $matches)) {
            $targetDate = $matches[1];
        } else {
            $targetDate = Carbon::now()->format('Y-m-d');
        }

        $aggregatedStats = ClientTagDistribution::query()
            ->join('client_designer', 'client_designer.id', '=', 'client_tag_distributions.client_designer_id')
            ->selectRaw('
                client_designer.designer_id,
                COUNT(CASE WHEN client_tag_distributions.distribution_date = ? AND client_tag_distributions.status IN (\'pending\', \'in_progress\') THEN 1 END) as today_uncompleted
            ', [$targetDate])
            ->groupBy('client_designer.designer_id')
            ->get()
            ->keyBy('designer_id');

        $reportRows = [];

        if ($aggregatedStats->isNotEmpty()) {
            $designers = Designer::with('user')
                ->whereIn('id', $aggregatedStats->keys())
                ->get();

            foreach ($designers as $designer) {
                $stats = $aggregatedStats->get($designer->id);
                $todayCount = (int) ($stats->today_uncompleted ?? 0);

                if ($todayCount > 0) {
                    $reportRows[] = [
                        'designer_name' => $designer->user->name ?? 'مصمم',
                        'today_uncompleted' => $todayCount,
                    ];
                }
            }

            usort($reportRows, fn ($a, $b) => $b['today_uncompleted'] <=> $a['today_uncompleted']);
        }

        $carbon = Carbon::parse($targetDate)->locale('ar');
        $day = $carbon->format('j');
        $month = $carbon->format('n');
        $year = $carbon->format('Y');
        $dayName = $carbon->translatedFormat('l');
        $fullHeaderTitle = "كشف جرد التصاميم بتاريخ {$day} / {$month} / {$year} م الموافق يوم {$dayName}";

        return view('reports.uncompleted-tasks-report', [
            'targetDate' => $targetDate,
            'fullHeaderTitle' => $fullHeaderTitle,
            'day' => $day,
            'month' => $month,
            'year' => $year,
            'dayName' => $dayName,
            'reportRows' => $reportRows,
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
