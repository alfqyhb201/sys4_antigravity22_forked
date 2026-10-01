<?php

namespace App\Filament\Pages;

use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithFileUploads;

/**
 * الوصف العام: صفحة واجهة المشرف لعرض الإحصائيات الأساسية للتصاميم والتقرير اليومي للمصممين.
 */
class SupervisorDashboard extends Page implements HasTable
{
    use InteractsWithTable, WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'واجهة المشرف';

    protected static ?string $title = ' ';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.supervisor-dashboard';

    /** @var string|null تاريخ الفلتر المحدد — Livewire property تُحدَّث عند تغيير DatePicker أو أزرار التنقل */
    public ?string $filterDate = null;

    /** @var string نطاق الإحصائيات للبطاقات العلوية: 'date' (حسب تاريخ التقرير) أو 'cumulative' (التراكمي الشامل) */
    public string $statsScope = 'date';

    /** @var int|null معرف المهمة الجاري رفع تصميمها مباشرة */
    public ?int $uploadTaskId = null;

    /** @var mixed ملف التصميم المرفوع مؤقتاً */
    public $uploadFile = null;

    /** @var string|null ملاحظات المشرف على التصميم المرفوع */
    public ?string $uploadNotes = null;

    /** @var string وجهة الحالة بعد الرفع: 'sending' (جاهز للإرسال مباشرة) أو 'reviewing' (إرسال للمراجعة) */
    public string $uploadTargetStatus = 'sending';

    /** @var string|null موعد الإرسال المجدول المخصص (اختياري) */
    public ?string $uploadScheduledSendingAt = null;

    private ?array $cachedDesignerStats = null;

    private ?array $cachedStatsMap = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('supervisor')
            || $user->hasRole('admin')
            || $user->can('view_supervisor_dashboard');
    }

    public function mount(): void
    {
        $this->filterDate = Carbon::now()->format('Y-m-d');
        $this->tableFilters = [
            'filter_date' => [
                'date' => $this->filterDate,
            ],
        ];
    }

    /**
     * تغيير نطاق حساب البطاقات العلوية (تاريخ التقرير / التراكمي الشامل).
     */
    public function setStatsScope(string $scope): void
    {
        $this->statsScope = in_array($scope, ['date', 'cumulative', 'today', 'all'])
            ? (($scope === 'all' || $scope === 'cumulative') ? 'cumulative' : 'date')
            : 'date';
    }

    /**
     * الانتقال إلى تاريخ محدد وتحديث الجدول والإحصائيات.
     */
    public function setDate(string $date): void
    {
        $this->filterDate = $date;
        $this->tableFilters['filter_date']['date'] = $date;
        $this->cachedDesignerStats = null;
        $this->cachedStatsMap = null;
        $this->resetTable();
    }

    /**
     * الانتقال لليوم السابق (الأمس).
     */
    public function setPreviousDay(): void
    {
        $current = Carbon::parse($this->getFilterDate());
        $this->setDate($current->subDay()->format('Y-m-d'));
    }

    /**
     * العودة لتاريخ اليوم الحالي.
     */
    public function setToday(): void
    {
        $this->setDate(Carbon::now()->format('Y-m-d'));
    }

    /**
     * الانتقال لليوم التالي (الغد).
     */
    public function setNextDay(): void
    {
        $current = Carbon::parse($this->getFilterDate());
        $this->setDate($current->addDay()->format('Y-m-d'));
    }

    /**
     * التحقق مما إذا كان التاريخ المحدد هو تاريخ اليوم.
     */
    public function isCurrentDateToday(): bool
    {
        return $this->getFilterDate() === Carbon::now()->format('Y-m-d');
    }

    /**
     * استخراج التاريخ المحدد من خاصية Livewire filterDate أو فلتر الجدول.
     */
    public function getFilterDate(): string
    {
        $tableFilterDate = $this->tableFilters['filter_date']['date'] ?? null;

        if ($tableFilterDate && preg_match('/^(\d{4}-\d{2}-\d{2})/', $tableFilterDate, $matches)) {
            return $matches[1];
        }

        if ($this->filterDate && preg_match('/^(\d{4}-\d{2}-\d{2})/', $this->filterDate, $matches)) {
            return $matches[1];
        }

        return Carbon::now()->format('Y-m-d');
    }

    protected function getViewData(): array
    {
        $targetDate = $this->getFilterDate();
        $isCumulative = ($this->statsScope === 'cumulative');

        // استعلام تجميعي لحساب إحصائيات البطاقات العلوية
        $overallStatsQuery = ClientTagDistribution::query();

        if (! $isCumulative) {
            $overallStatsQuery->where('distribution_date', $targetDate);
        }

        $overallStats = $overallStatsQuery
            ->selectRaw('
                COUNT(CASE WHEN status = \'completed\' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = \'sending\' THEN 1 END) as sending_count,
                COUNT(CASE WHEN status = \'reviewing\' THEN 1 END) as reviewing_count,
                COUNT(CASE WHEN status IN (\'pending\', \'in_progress\') OR status IS NULL OR status = \'\' THEN 1 END) as pending_count
            ')
            ->first();

        $designerStats = $this->getDesignerStats();

        return [
            'completedCount' => (int) ($overallStats->completed_count ?? 0),
            'sendingCount' => (int) ($overallStats->sending_count ?? 0),
            'reviewingCount' => (int) ($overallStats->reviewing_count ?? 0),
            'pendingCount' => (int) ($overallStats->pending_count ?? 0),
            'totalToday' => collect($designerStats)->sum('today_tasks'),
            'totalOverdue' => collect($designerStats)->sum('overdue_tasks'),
            'totalCompleted' => collect($designerStats)->sum('completed_today'),
            'totalDesigners' => count($designerStats),
            'currentFilterDate' => $targetDate,
            'isToday' => $this->isCurrentDateToday(),
            'statsScope' => $this->statsScope,
            'isCumulative' => $isCumulative,
        ];
    }

    /**
     * حساب إحصائيات المصممين عبر استعلام SQL تجميعي موحد وفائق السرعة (بدون N+1).
     */
    public function getDesignerStats(): array
    {
        if ($this->cachedDesignerStats !== null) {
            return $this->cachedDesignerStats;
        }

        $targetDate = $this->getFilterDate();

        // استعلام تجميعي واحد يربط توزيع المهام بالمصممين
        $aggregatedStats = ClientTagDistribution::query()
            ->join('client_designer', 'client_designer.id', '=', 'client_tag_distributions.client_designer_id')
            ->selectRaw('
                client_designer.designer_id,
                COUNT(CASE WHEN client_tag_distributions.distribution_date = ? AND client_tag_distributions.status IN (\'pending\', \'in_progress\') THEN 1 END) as today_tasks,
                COUNT(CASE WHEN client_tag_distributions.distribution_date < ? AND client_tag_distributions.status IN (\'pending\', \'in_progress\', \'changes_requested\') THEN 1 END) as overdue_tasks,
                COUNT(CASE WHEN client_tag_distributions.distribution_date = ? AND client_tag_distributions.status IN (\'sending\', \'completed\') THEN 1 END) as completed_today
            ', [$targetDate, $targetDate, $targetDate])
            ->groupBy('client_designer.designer_id')
            ->get()
            ->keyBy('designer_id');

        if ($aggregatedStats->isEmpty()) {
            $this->cachedDesignerStats = [];

            return [];
        }

        $designers = Designer::with('user')
            ->whereIn('id', $aggregatedStats->keys())
            ->get();

        $designerStats = [];

        foreach ($designers as $designer) {
            $stats = $aggregatedStats->get($designer->id);
            $todayTasks = (int) ($stats->today_tasks ?? 0);
            $overdueTasks = (int) ($stats->overdue_tasks ?? 0);
            $completedToday = (int) ($stats->completed_today ?? 0);

            if (($todayTasks + $overdueTasks + $completedToday) === 0) {
                continue;
            }

            $designerStats[] = [
                'designer' => $designer,
                'today_tasks' => $todayTasks,
                'overdue_tasks' => $overdueTasks,
                'completed_today' => $completedToday,
            ];
        }

        // ترتيب: الأكثر مهام (اليوم + المتأخرات) أولاً
        usort($designerStats, fn ($a, $b) => ($b['today_tasks'] + $b['overdue_tasks']) <=> ($a['today_tasks'] + $a['overdue_tasks']));

        $this->cachedDesignerStats = $designerStats;

        return $designerStats;
    }

    /**
     * خريطة سريعة للبحث عن إحصائيات مصمم بواسطة معرفه (ID).
     */
    public function getDesignerStatsMap(): array
    {
        if ($this->cachedStatsMap !== null) {
            return $this->cachedStatsMap;
        }

        $map = [];
        foreach ($this->getDesignerStats() as $stat) {
            $map[$stat['designer']->id] = $stat;
        }

        $this->cachedStatsMap = $map;

        return $map;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Designer::query()->with('user')
            )
            ->columns([
                Tables\Columns\TextColumn::make('row_id')
                    ->label('#')
                    ->rowIndex(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('المصمم')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('today_tasks')
                    ->label('مهام اليوم')
                    ->getStateUsing(function (Designer $record) {
                        $map = $this->getDesignerStatsMap();

                        return $map[$record->id]['today_tasks'] ?? 0;
                    })
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-m-clock'),

                Tables\Columns\TextColumn::make('overdue_tasks')
                    ->label('متأخرات')
                    ->getStateUsing(function (Designer $record) {
                        $map = $this->getDesignerStatsMap();

                        return $map[$record->id]['overdue_tasks'] ?? 0;
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-exclamation-triangle' : null),

                Tables\Columns\TextColumn::make('completed_today')
                    ->label('منجز اليوم')
                    ->getStateUsing(function (Designer $record) {
                        $map = $this->getDesignerStatsMap();

                        return $map[$record->id]['completed_today'] ?? 0;
                    })
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-check-badge' : null),
            ])
            ->actions([
                Tables\Actions\Action::make('viewTasks')
                    ->label('تفاصيل المهام')
                    ->icon('heroicon-o-queue-list')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (Designer $record) => 'مهام المصمم: '.($record->user->name ?? ''))
                    ->modalDescription(fn (Designer $record) => 'عرض تفاصيل مهام المصمم ليوم '.Carbon::parse($this->getFilterDate())->translatedFormat('d F Y').' وإدارتها مباشرة.')
                    ->modalWidth(MaxWidth::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(function (Designer $record) {
                        $targetDate = $this->getFilterDate();
                        $designerIds = $record->clientDesigners()->pluck('id');

                        $todayTasks = ClientTagDistribution::whereIn('client_designer_id', $designerIds)
                            ->where('distribution_date', $targetDate)
                            ->where('status', '!=', 'frozen')
                            ->with(['clientDesigner.client', 'clientDesigner.designer.user', 'tag', 'idea'])
                            ->orderBy('status')
                            ->get();

                        $overdueTasks = ClientTagDistribution::whereIn('client_designer_id', $designerIds)
                            ->where('distribution_date', '<', $targetDate)
                            ->whereIn('status', ['pending', 'in_progress', 'changes_requested', null, ''])
                            ->with(['clientDesigner.client', 'clientDesigner.designer.user', 'tag', 'idea'])
                            ->orderBy('distribution_date', 'desc')
                            ->get();

                        return view('filament.modals.designer-tasks-modal', [
                            'record' => $record,
                            'targetDate' => $targetDate,
                            'todayTasks' => $todayTasks,
                            'overdueTasks' => $overdueTasks,
                            'availableDesigners' => $this->getAvailableDesignersForQuickTransfer($record->id),
                            'uploadTaskId' => $this->uploadTaskId,
                            'uploadFile' => $this->uploadFile,
                        ]);
                    }),

                Tables\Actions\Action::make('reassignTasks')
                    ->label('نقل المهام')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->modalHeading(fn (Designer $record) => 'نقل مهام المصمم: '.($record->user->name ?? ''))
                    ->modalDescription('اختر المصمم البديل والمهام المراد تحويلها إليه.')
                    ->modalWidth('xl')
                    ->form(function (Designer $record) {
                        $targetDate = $this->getFilterDate();
                        $statsMap = $this->getDesignerStatsMap();
                        $designerIds = $record->clientDesigners->pluck('id');

                        $uncompletedDistributions = ClientTagDistribution::whereIn('client_designer_id', $designerIds)
                            ->where('distribution_date', '<=', $targetDate)
                            ->whereIn('status', ['pending', 'in_progress', 'changes_requested', null, ''])
                            ->with(['clientDesigner.client', 'tag', 'idea'])
                            ->orderBy('distribution_date', 'desc')
                            ->get();

                        $todayTasksCount = $uncompletedDistributions->where('distribution_date', $targetDate)->count();
                        $overdueTasksCount = $uncompletedDistributions->where('distribution_date', '<', $targetDate)->count();
                        $allTasksCount = $uncompletedDistributions->count();

                        $customOptions = $uncompletedDistributions->mapWithKeys(function ($item) use ($targetDate) {
                            $isToday = $item->distribution_date === $targetDate;
                            $prefix = $isToday ? '📌 [اليوم]' : "⏰ [متأخر: {$item->distribution_date}]";
                            $client = $item->clientDesigner->client->company ?? 'عميل';
                            $tag = $item->tag->name ?? 'تاق';
                            $idea = $item->idea ? " (💡 {$item->idea->name})" : '';

                            return [$item->id => "{$prefix} {$client} - {$tag}{$idea}"];
                        })->toArray();

                        // إظهار مهام اليوم والمستهدف اليومي (الحد الأقصى الأسبوعي ÷ 6) لكل مصمم بديل
                        $targetDesigners = Designer::where('id', '!=', $record->id)
                            ->with('user')
                            ->get()
                            ->mapWithKeys(function ($d) use ($statsMap) {
                                $todayCount = $statsMap[$d->id]['today_tasks'] ?? 0;
                                $dailyTarget = $d->max_capacity ? (float) round($d->max_capacity / 6, 1) : 'غير محدد';
                                $designerName = $d->user->name ?? 'مصمم';

                                return [$d->id => "{$designerName} (مهام اليوم: {$todayCount} / المستهدف اليومي: {$dailyTarget})"];
                            });

                        return [
                            Forms\Components\Select::make('target_designer_id')
                                ->label('المصمم البديل (المستلم)')
                                ->options($targetDesigners)
                                ->searchable()
                                ->live()
                                ->required()
                                ->placeholder('اختر المصمم الذي سينقل العمل إليه...'),

                            Forms\Components\Radio::make('transfer_scope')
                                ->label('نطاق نقل المهام')
                                ->options([
                                    'today' => "مهام اليوم غير المكتملة ({$todayTasksCount} مهام)",
                                    'overdue' => "المتأخرات غير المكتملة ({$overdueTasksCount} مهام)",
                                    'all' => "جميع المهام غير المكتملة ({$allTasksCount} مهام)",
                                    'custom' => 'تحديد مهام معينة يدوياً',
                                ])
                                ->default('today')
                                ->live(),

                            Forms\Components\Select::make('selected_distribution_ids')
                                ->label('حدد المهام المراد نقلها')
                                ->options($customOptions)
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->placeholder('ابحث أو اختر المهام...')
                                ->visible(fn (Forms\Get $get) => $get('transfer_scope') === 'custom')
                                ->live()
                                ->required(fn (Forms\Get $get) => $get('transfer_scope') === 'custom'),

                            Forms\Components\Placeholder::make('workload_simulation')
                                ->label('محاكاة عبء العمل للمصمم المستلم')
                                ->visible(fn (Forms\Get $get) => filled($get('target_designer_id')))
                                ->content(function (Forms\Get $get) use ($statsMap, $uncompletedDistributions, $targetDate) {
                                    $targetDesignerId = $get('target_designer_id');
                                    if (! $targetDesignerId) {
                                        return '';
                                    }

                                    $targetDesigner = Designer::with('user')->find($targetDesignerId);
                                    if (! $targetDesigner) {
                                        return '';
                                    }

                                    $currentTasks = $statsMap[$targetDesignerId]['today_tasks'] ?? 0;
                                    $dailyTarget = $targetDesigner->max_capacity ? (float) round($targetDesigner->max_capacity / 6, 1) : null;

                                    $scope = $get('transfer_scope') ?? 'today';
                                    $transferCount = match ($scope) {
                                        'today' => $uncompletedDistributions->where('distribution_date', $targetDate)->count(),
                                        'overdue' => $uncompletedDistributions->where('distribution_date', '<', $targetDate)->count(),
                                        'custom' => count($get('selected_distribution_ids') ?? []),
                                        default => $uncompletedDistributions->count(),
                                    };

                                    $totalAfter = $currentTasks + $transferCount;
                                    $designerName = $targetDesigner->user->name ?? 'المصمم';

                                    $isExceeded = $dailyTarget && $totalAfter > $dailyTarget;
                                    $diff = $isExceeded ? ($totalAfter - $dailyTarget) : 0;

                                    $statusBadge = $dailyTarget
                                        ? ($isExceeded
                                            ? "<span class='inline-flex items-center gap-1 rounded-md bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'>⚠️ يتجاوز المستهدف اليومي بـ {$diff} مهام</span>"
                                            : "<span class='inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'>✓ ضمن الطاقة الاستيعابية اليومية</span>")
                                        : "<span class='inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300'>المستهدف غير محدد</span>";

                                    $dailyTargetText = $dailyTarget ?? 'غير محدد';

                                    return new \Illuminate\Support\HtmlString("
                                        <div class='rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 space-y-2 dark:border-gray-800 dark:bg-gray-900/60'>
                                            <div class='flex flex-wrap items-center justify-between gap-2 border-b border-gray-200/80 pb-2 dark:border-gray-800'>
                                                <div class='font-bold text-gray-900 dark:text-white flex items-center gap-2'>
                                                    <span>👤 {$designerName}</span>
                                                </div>
                                                {$statusBadge}
                                            </div>
                                            <div class='grid grid-cols-3 gap-2 text-center pt-1'>
                                                <div class='rounded-lg bg-white p-2 border border-gray-100 dark:bg-gray-800 dark:border-gray-700'>
                                                    <p class='text-[10px] text-gray-500 dark:text-gray-400'>المهام الحالية</p>
                                                    <p class='text-xs font-bold text-gray-800 dark:text-gray-200'>{$currentTasks} مهام</p>
                                                </div>
                                                <div class='rounded-lg bg-white p-2 border border-gray-100 dark:bg-gray-800 dark:border-gray-700'>
                                                    <p class='text-[10px] text-gray-500 dark:text-gray-400'>المهام المنقولة</p>
                                                    <p class='text-xs font-bold text-amber-600 dark:text-amber-400'>+{$transferCount} مهام</p>
                                                </div>
                                                <div class='rounded-lg bg-white p-2 border border-gray-100 dark:bg-gray-800 dark:border-gray-700'>
                                                    <p class='text-[10px] text-gray-500 dark:text-gray-400'>المتوقع / المستهدف</p>
                                                    <p class='text-xs font-bold text-gray-900 dark:text-white'>{$totalAfter} / {$dailyTargetText}</p>
                                                </div>
                                            </div>
                                        </div>
                                    ");
                                }),
                        ];
                    })
                    ->action(function (Designer $record, array $data) {
                        $targetDesignerId = $data['target_designer_id'];
                        $scope = $data['transfer_scope'];
                        $customIds = $data['selected_distribution_ids'] ?? [];

                        $designerIds = $record->clientDesigners->pluck('id');
                        $targetDate = $this->getFilterDate();

                        $query = ClientTagDistribution::whereIn('client_designer_id', $designerIds)
                            ->whereIn('status', ['pending', 'in_progress', 'changes_requested', null, '']);

                        if ($scope === 'today') {
                            $query->where('distribution_date', $targetDate);
                        } elseif ($scope === 'overdue') {
                            $query->where('distribution_date', '<', $targetDate);
                        } elseif ($scope === 'custom') {
                            $query->whereIn('id', $customIds);
                        } else {
                            $query->where('distribution_date', '<=', $targetDate);
                        }

                        $distributionsToTransfer = $query->with('clientDesigner')->get();

                        if ($distributionsToTransfer->isEmpty()) {
                            Notification::make()
                                ->title('لا توجد مهام مطابقة لنقلها')
                                ->warning()
                                ->send();

                            return;
                        }

                        $targetDesigner = Designer::with('user')->find($targetDesignerId);
                        $count = 0;

                        foreach ($distributionsToTransfer as $distribution) {
                            $currentAssignment = $distribution->clientDesigner;
                            if (! $currentAssignment) {
                                continue;
                            }

                            $primaryAssignment = ClientDesigner::where('client_id', $currentAssignment->client_id)
                                ->where('week_start_date', $currentAssignment->week_start_date)
                                ->where('is_side', false)
                                ->first();

                            $isSide = $primaryAssignment && ($primaryAssignment->designer_id != $targetDesignerId);

                            $newAssignment = ClientDesigner::firstOrCreate(
                                [
                                    'client_id' => $currentAssignment->client_id,
                                    'designer_id' => $targetDesignerId,
                                    'week_start_date' => $currentAssignment->week_start_date,
                                ],
                                [
                                    'contract_id' => $currentAssignment->contract_id,
                                    'is_side' => $isSide,
                                ]
                            );

                            $distribution->update(['client_designer_id' => $newAssignment->id]);
                            $count++;
                        }

                        $cleanedWeek = $distributionsToTransfer->first()?->clientDesigner?->week_start_date ?? Carbon::parse($targetDate)->startOfWeek()->format('Y-m-d');
                        app(\App\Services\TagDistributionService::class)->cleanupEmptyDuplicateAssignments($cleanedWeek);

                        $this->cachedDesignerStats = null;
                        $this->cachedStatsMap = null;

                        Notification::make()
                            ->title("تم نقل {$count} مهمة بنجاح إلى المصمم ".($targetDesigner->user->name ?? ''))
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
            ])
            ->filters([
                Filter::make('filter_date')
                    ->label('تاريخ التقرير')
                    ->form([
                        DatePicker::make('date')
                            ->label('اختر التاريخ')
                            ->default(fn () => $this->filterDate ?? Carbon::now()->format('Y-m-d'))
                            ->native(false)
                            ->displayFormat('d / m / Y')
                            ->closeOnDateSelection()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['date'])) {
                            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $data['date'], $matches)) {
                                $this->filterDate = $matches[1];
                            } else {
                                $this->filterDate = $data['date'];
                            }
                            $this->cachedDesignerStats = null;
                            $this->cachedStatsMap = null;
                        }

                        $designerIds = array_keys($this->getDesignerStatsMap());

                        if (empty($designerIds)) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->whereIn('id', $designerIds);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['date'])) {
                            return null;
                        }

                        return 'التاريخ: '.Carbon::parse($data['date'])->translatedFormat('d F Y');
                    }),

                Filter::make('uncompleted_today')
                    ->label('غير منجزين اليوم (مهام معلقة)')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $statsMap = $this->getDesignerStatsMap();
                        $uncompletedDesignerIds = collect($statsMap)
                            ->filter(fn ($stat) => ($stat['today_tasks'] ?? 0) > 0)
                            ->keys()
                            ->all();

                        if (empty($uncompletedDesignerIds)) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->whereIn('id', $uncompletedDesignerIds);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['isActive'] ?? false)) {
                            return null;
                        }

                        return 'غير منجزين اليوم (مهام معلقة)';
                    }),

                Filter::make('has_overdue')
                    ->label('لديهم متأخرات')
                    ->toggle()
                    ->query(function (Builder $query): Builder {
                        $statsMap = $this->getDesignerStatsMap();
                        $overdueDesignerIds = collect($statsMap)
                            ->filter(fn ($stat) => ($stat['overdue_tasks'] ?? 0) > 0)
                            ->keys()
                            ->all();

                        if (empty($overdueDesignerIds)) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->whereIn('id', $overdueDesignerIds);
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! ($data['isActive'] ?? false)) {
                            return null;
                        }

                        return 'لديهم متأخرات';
                    }),
            ])
            ->filtersFormColumns(['sm' => 1, 'md' => 3])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->emptyStateHeading('لا توجد نتائج مطابقة')
            ->emptyStateDescription('لم يتم العثور على مصممين يطابقون خيارات الفلترة المحددة في هذا اليوم.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('user.name');
    }

    /**
     * قائمة المصممين المتاحين للنقل الفوري مع مؤشرات عبء العمل.
     *
     * @return array<int, array{id: int, name: string, today_tasks: int, daily_target: float|string, is_busy: bool}>
     */
    public function getAvailableDesignersForQuickTransfer(int $excludeDesignerId): array
    {
        $statsMap = $this->getDesignerStatsMap();

        return Designer::where('id', '!=', $excludeDesignerId)
            ->with('user')
            ->get()
            ->map(function (Designer $designer) use ($statsMap) {
                $todayCount = (int) ($statsMap[$designer->id]['today_tasks'] ?? 0);
                $dailyTarget = $designer->max_capacity ? (float) round($designer->max_capacity / 6, 1) : null;
                $isBusy = $dailyTarget ? ($todayCount >= $dailyTarget) : false;

                return [
                    'id' => $designer->id,
                    'name' => $designer->user->name ?? 'مصمم',
                    'today_tasks' => $todayCount,
                    'daily_target' => $dailyTarget ?? 'غير محدد',
                    'is_busy' => $isBusy,
                ];
            })
            ->toArray();
    }

    /**
     * نقل مهمة فردية فوراً إلى مصمم آخر من الدرج الجانبي.
     */
    public function quickReassignTask(int $distributionId, int $targetDesignerId): void
    {
        $distribution = ClientTagDistribution::with(['clientDesigner.client', 'tag'])->find($distributionId);

        if (! $distribution) {
            Notification::make()
                ->title('المهمة غير موجودة')
                ->danger()
                ->send();

            return;
        }

        $currentAssignment = $distribution->clientDesigner;
        if (! $currentAssignment) {
            Notification::make()
                ->title('بيانات تعيين المهمة غير مكتملة')
                ->danger()
                ->send();

            return;
        }

        if ($currentAssignment->designer_id == $targetDesignerId) {
            Notification::make()
                ->title('المهمة مسندة بالفعل لهذا المصمم')
                ->warning()
                ->send();

            return;
        }

        $targetDesigner = Designer::with('user')->find($targetDesignerId);
        if (! $targetDesigner) {
            Notification::make()
                ->title('المصمم المحدد غير موجود')
                ->danger()
                ->send();

            return;
        }

        $primaryAssignment = ClientDesigner::where('client_id', $currentAssignment->client_id)
            ->where('week_start_date', $currentAssignment->week_start_date)
            ->where('is_side', false)
            ->first();

        $isSide = $primaryAssignment && ($primaryAssignment->designer_id != $targetDesignerId);

        $newAssignment = ClientDesigner::firstOrCreate(
            [
                'client_id' => $currentAssignment->client_id,
                'designer_id' => $targetDesignerId,
                'week_start_date' => $currentAssignment->week_start_date,
            ],
            [
                'contract_id' => $currentAssignment->contract_id,
                'is_side' => $isSide,
            ]
        );

        $distribution->update(['client_designer_id' => $newAssignment->id]);

        // تنظيف التعيينات المكررة الفارغة
        app(\App\Services\TagDistributionService::class)->cleanupEmptyDuplicateAssignments($currentAssignment->week_start_date);

        // إرسال إشعار للمصمم المستلم في لوحته
        if ($targetDesigner->user) {
            $clientName = $distribution->clientDesigner?->client?->company ?? 'عميل';
            $tagName = $distribution->tag?->name ?? 'تاق';

            Notification::make()
                ->title('تم إسناد مهمة جديدة لك 🎨')
                ->body("قام المشرف بتحويل تصميم العميل: {$clientName} ({$tagName}) إليك.")
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->iconColor('success')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('عرض لوحة المصمم')
                        ->url('/admin/designer-dashboard'),
                ])
                ->sendToDatabase($targetDesigner->user, isEventDispatched: true);
        }

        // تصفير الكاش وتحديث الجدول
        $this->cachedDesignerStats = null;
        $this->cachedStatsMap = null;

        $targetDesignerName = $targetDesigner->user->name ?? 'المصمم';
        Notification::make()
            ->title("تم نقل المهمة بنجاح إلى {$targetDesignerName}")
            ->success()
            ->send();

        $this->resetTable();
    }

    /**
     * إرسال تنبيه فوري للمصمم بخصوص مهمة معينة من الدرج الجانبي.
     */
    public function notifyDesignerForTask(int $distributionId, ?string $customMessage = null): void
    {
        $distribution = ClientTagDistribution::with([
            'clientDesigner.designer.user',
            'clientDesigner.client',
            'tag',
            'idea',
        ])->find($distributionId);

        if (! $distribution) {
            Notification::make()
                ->title('المهمة غير موجودة')
                ->danger()
                ->send();

            return;
        }

        $designerUser = $distribution->clientDesigner?->designer?->user;
        if (! $designerUser) {
            Notification::make()
                ->title('المصمم غير مرتبط بحساب مستخدم لإرسال الإشعار')
                ->warning()
                ->send();

            return;
        }

        $clientName = $distribution->clientDesigner?->client?->company ?? 'عميل';
        $tagName = $distribution->tag?->name ?? 'تاق';
        $targetDate = $this->getFilterDate();
        $isOverdue = $distribution->distribution_date < $targetDate && ! in_array($distribution->status, ['sending', 'completed']);

        $title = $isOverdue
            ? 'تنبيه مهمة متأخرة ⏰'
            : 'تذكير بمهمة تصميم 📌';

        $body = $customMessage ?: (
            $isOverdue
                ? "يرجى الإسراع بإنجاز تصميم العميل: {$clientName} ({$tagName})، فالمهمة متأخرة منذ تاريخ {$distribution->distribution_date}."
                : "تذكير من المشرف بمتابعة العمل على تصميم العميل: {$clientName} ({$tagName}) لتاريخ {$distribution->distribution_date}."
        );

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($isOverdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-bell-alert')
            ->iconColor($isOverdue ? 'danger' : 'warning')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('عرض لوحة المصمم')
                    ->url('/admin/designer-dashboard'),
            ])
            ->sendToDatabase($designerUser, isEventDispatched: true);

        Notification::make()
            ->title("تم إرسال التنبيه إلى المصمم {$designerUser->name} بنجاح")
            ->success()
            ->send();
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            static::getUrl() => 'واجهة المشرف',
        ];
    }

    /**
     * تجهيز بيانات رفع التصميم المباشر للمهمة من الدرج الجانبي.
     */
    public function prepareDirectUpload(int $taskId): void
    {
        $this->uploadTaskId = $taskId;
        $this->uploadFile = null;
        $this->uploadTargetStatus = 'sending';

        $distribution = ClientTagDistribution::find($taskId);
        $this->uploadNotes = $distribution?->designer_notes;
        $this->uploadScheduledSendingAt = $distribution?->scheduled_sending_at?->format('Y-m-d\TH:i');
    }

    /**
     * حفظ واعتماد التصميم المرفوع مباشرة من واجهة المشرف.
     */
    public function saveDirectUpload(int $taskId): void
    {
        $this->validate([
            'uploadFile' => 'required|image|max:10240',
        ], [
            'uploadFile.required' => 'يرجى اختيار أو رفع صورة التصميم المنجز.',
            'uploadFile.image' => 'يجب أن يكون الملف صورة صالحة (PNG, JPG, JPEG, WEBP).',
            'uploadFile.max' => 'الحد الأقصى لحجم الصورة هو 10 ميجابايت.',
        ]);

        $distribution = ClientTagDistribution::with(['clientDesigner.client', 'tag'])->find($taskId);

        if (! $distribution) {
            Notification::make()
                ->title('المهمة غير موجودة')
                ->danger()
                ->send();

            return;
        }

        $clientId = $distribution->clientDesigner?->client_id ?? 'unknown';
        $path = $this->uploadFile->store("clients/{$clientId}/submissions/{$distribution->id}", 'public');

        $targetStatus = in_array($this->uploadTargetStatus, ['sending', 'reviewing'], true)
            ? $this->uploadTargetStatus
            : 'sending';

        $updateData = [
            'attachment_path' => $path,
            'status' => $targetStatus,
        ];

        if ($this->uploadNotes !== null) {
            $updateData['designer_notes'] = $this->uploadNotes;
        }

        if ($targetStatus === 'sending') {
            $updateData['reviewer_id'] = auth()->id();

            if (! empty($this->uploadScheduledSendingAt)) {
                $updateData['scheduled_sending_at'] = Carbon::parse($this->uploadScheduledSendingAt);
            } elseif (! $distribution->scheduled_sending_at) {
                $sendDate = Carbon::now()->hour < 6 ? Carbon::today() : Carbon::tomorrow();
                $weeklyTime = $distribution->tag?->weekly_time ? Carbon::parse($distribution->tag->weekly_time) : null;

                if ($weeklyTime) {
                    $updateData['scheduled_sending_at'] = $sendDate->setTime($weeklyTime->hour, $weeklyTime->minute, $weeklyTime->second);
                } else {
                    $updateData['scheduled_sending_at'] = $sendDate->setTime(12, 0, 0);
                }
            }
        }

        $distribution->update($updateData);

        // تصفير الكاش والمتغيرات وتحديث الواجهة
        $this->cachedDesignerStats = null;
        $this->cachedStatsMap = null;
        $this->reset(['uploadTaskId', 'uploadFile', 'uploadNotes', 'uploadScheduledSendingAt', 'uploadTargetStatus']);

        $clientName = $distribution->clientDesigner?->client?->company ?? 'العميل';
        $tagName = $distribution->tag?->name ?? 'تاق';
        $statusMsg = $targetStatus === 'sending' ? 'وجاهزاً للإرسال والنشر 🚀' : 'قيد المراجعة 🔍';

        Notification::make()
            ->title("تم رفع واعتماد التصميم بنجاح ({$clientName} - {$tagName})")
            ->body("أصبح التصميم الآن بحالة: {$statusMsg}")
            ->success()
            ->send();

        $this->resetTable();
    }

    /**
     * إلغاء نافذة رفع التصميم المباشر.
     */
    public function cancelDirectUpload(): void
    {
        $this->reset(['uploadTaskId', 'uploadFile', 'uploadNotes', 'uploadScheduledSendingAt', 'uploadTargetStatus']);
    }
}
