<?php

namespace App\Services;

use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Designer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeeklyDistributionAuditService
{
    public function __construct(
        protected DesignerDistributionService $designerDistributionService,
        protected TagDistributionService $tagDistributionService,
    ) {}

    /**
     * الحصول على تاريخ بداية الأسبوع ونهايته.
     *
     * @return array{start: string, end: string, weekStartCarbon: Carbon}
     */
    public function resolveWeek(?string $weekStartDate = null): array
    {
        $weekStartCarbon = $weekStartDate
            ? Carbon::parse($weekStartDate)->startOfWeek()
            : Carbon::now()->startOfWeek();

        return [
            'start' => $weekStartCarbon->format('Y-m-d'),
            'end' => $weekStartCarbon->copy()->addDays(6)->format('Y-m-d'),
            'weekStartCarbon' => $weekStartCarbon,
        ];
    }

    /**
     * الحصول على ملخص تدقيق التوزيع للأسبوع المحدد.
     *
     * @return array{
     *     week_start: string,
     *     week_end: string,
     *     unassigned_count: int,
     *     missing_tags_count: int,
     *     total_issues: int,
     *     is_fully_distributed: bool
     * }
     */
    public function getAuditSummary(?string $weekStartDate = null): array
    {
        $week = $this->resolveWeek($weekStartDate);

        $unassignedCount = $this->getUnassignedContracts($week['start'])->count();
        $missingTagsCount = $this->getClientsWithMissingTags($week['start'])->count();
        $totalIssues = $unassignedCount + $missingTagsCount;

        return [
            'week_start' => $week['start'],
            'week_end' => $week['end'],
            'unassigned_count' => $unassignedCount,
            'missing_tags_count' => $missingTagsCount,
            'total_issues' => $totalIssues,
            'is_fully_distributed' => ($totalIssues === 0),
        ];
    }

    /**
     * جلب العقود النشطة التي تحتاج لمصمم ولم يتم توزيعها لهذا الأسبوع.
     *
     * @return Collection<int, array{
     *     contract_id: int,
     *     client_id: int,
     *     client_name: string,
     *     category_name: string,
     *     weekly_designs_count: int,
     *     billing_cycle: string,
     *     start_date: ?string,
     *     reason: string
     * }>
     */
    public function getUnassignedContracts(?string $weekStartDate = null): Collection
    {
        $week = $this->resolveWeek($weekStartDate);

        // مزامنة أي تعيينات سابقة مرتبطة باشتراكات قديمة مجددة
        $this->syncStaleContractAssignments($week['start']);

        // معرفات الاشتراكات والعملاء الموزعة بالفعل لهذا الأسبوع
        $assignedContractIds = ClientDesigner::where('week_start_date', $week['start'])
            ->whereNotNull('contract_id')
            ->pluck('contract_id')
            ->toArray();

        $assignedClientIds = ClientDesigner::where('week_start_date', $week['start'])
            ->pluck('client_id')
            ->toArray();

        $contracts = Contract::with(['client.category'])
            ->whereHas('client', function ($query) {
                $query->where('status', 1);
            })
            ->where('status', 'active')
            ->where('weekly_designs_count', '>', 0)
            ->where('start_date', '<=', $week['end'])
            ->whereNotIn('id', $assignedContractIds)
            ->whereNotIn('client_id', $assignedClientIds)
            ->orderBy('weekly_designs_count', 'desc')
            ->get();

        return $contracts->map(function (Contract $contract) use ($week) {
            $client = $contract->client;
            $clientName = $client ? ($client->company ?: $client->client_name) : "عميل #{$contract->client_id}";
            $categoryName = $client?->category?->name ?? 'بدون تصنيف';

            $startDate = $contract->start_date?->format('Y-m-d');
            $createdRecently = $contract->created_at && $contract->created_at->gte(Carbon::parse($week['start']));

            $reason = 'غير موزع';
            if ($createdRecently) {
                $reason = 'اشتراك أضيف حديثاً';
            } elseif ($startDate && $startDate >= $week['start']) {
                $reason = 'بدأ خلال هذا الأسبوع';
            }

            return [
                'contract_id' => $contract->id,
                'client_id' => $contract->client_id,
                'client_name' => $clientName,
                'category_name' => $categoryName,
                'weekly_designs_count' => (int) ($contract->weekly_designs_count ?? 0),
                'billing_cycle' => match ($contract->billing_cycle) {
                    'weekly' => 'أسبوعي',
                    'yearly' => 'سنوي',
                    default => 'شهري',
                },
                'start_date' => $startDate,
                'reason' => $reason,
            ];
        });
    }

    /**
     * مزامنة التعيينات الأسبوعية للاشتراكات المجددة لترتبط بالعقد النشط الحالي للعميل.
     */
    public function syncStaleContractAssignments(string $weekStartDate): void
    {
        $assignments = ClientDesigner::where('week_start_date', $weekStartDate)
            ->with(['contract', 'client.contracts' => function ($q) {
                $q->where('status', 'active');
            }])
            ->get();

        foreach ($assignments as $assignment) {
            $activeContract = $assignment->client?->contracts->first();
            if ($activeContract && (! $assignment->contract || $assignment->contract->status !== 'active' || $assignment->contract_id !== $activeContract->id)) {
                $assignment->updateQuietly([
                    'contract_id' => $activeContract->id,
                ]);
            }
        }
    }

    /**
     * جلب العملاء المعينين لمصمم ولكن تاقاتهم لم توزع بعد أو أقل من حصتهم.
     *
     * @return Collection<int, array{
     *     client_designer_id: int,
     *     contract_id: ?int,
     *     client_id: int,
     *     client_name: string,
     *     category_name: string,
     *     designer_id: int,
     *     designer_name: string,
     *     required_designs: int,
     *     distributed_tags: int,
     *     missing_count: int
     * }>
     */
    public function getClientsWithMissingTags(?string $weekStartDate = null): Collection
    {
        $week = $this->resolveWeek($weekStartDate);

        // مزامنة التعيينات الأسبوعية للتأكد من ربطها بالعقود النشطة الحالية
        $this->syncStaleContractAssignments($week['start']);

        $assignments = ClientDesigner::with([
            'client.category',
            'contract',
            'designer.user',
            'distributions',
        ])
            ->where('week_start_date', $week['start'])
            ->get();

        return $assignments->filter(function (ClientDesigner $assignment) {
            $required = (int) ($assignment->contract?->weekly_designs_count ?? 0);
            $actual = $assignment->distributions->count();

            return $required > 0 && $actual < $required;
        })->map(function (ClientDesigner $assignment) {
            $client = $assignment->client;
            $clientName = $client ? ($client->company ?: $client->client_name) : "عميل #{$assignment->client_id}";
            $categoryName = $client?->category?->name ?? 'بدون تصنيف';
            $designerName = $assignment->designer?->user?->name ?? 'غير محدد';
            $required = (int) ($assignment->contract?->weekly_designs_count ?? 0);
            $actual = $assignment->distributions->count();

            return [
                'client_designer_id' => $assignment->id,
                'contract_id' => $assignment->contract_id,
                'client_id' => $assignment->client_id,
                'client_name' => $clientName,
                'category_name' => $categoryName,
                'designer_id' => $assignment->designer_id,
                'designer_name' => $designerName,
                'required_designs' => $required,
                'distributed_tags' => $actual,
                'missing_count' => max(0, $required - $actual),
            ];
        })->values();
    }

    /**
     * جلب قائمة المصممين مع السعات المتاحة لهذا الأسبوع لتسهيل التعيين السريع.
     *
     * @return Collection<int, array{id: int, name: string, available_capacity: int, max_capacity: int, current_load: int, label: string}>
     */
    public function getAvailableDesignersWithCapacity(?string $weekStartDate = null): Collection
    {
        $week = $this->resolveWeek($weekStartDate);

        $designers = Designer::with('user')
            ->whereNotNull('max_capacity')
            ->where('max_capacity', '>', 0)
            ->get()
            ->filter(fn ($d) => $d->user !== null);

        $assignments = ClientDesigner::where('week_start_date', $week['start'])
            ->with('contract')
            ->get()
            ->groupBy('designer_id');

        return $designers->map(function (Designer $designer) use ($assignments) {
            $designerAssignments = $assignments->get($designer->id) ?? collect();
            $currentLoad = (int) $designerAssignments->sum(fn ($a) => $a->contract->weekly_designs_count ?? 0);
            $maxCapacity = (int) ($designer->max_capacity ?? 0);
            $available = max(0, $maxCapacity - $currentLoad);

            return [
                'id' => $designer->id,
                'name' => $designer->user->name,
                'available_capacity' => $available,
                'max_capacity' => $maxCapacity,
                'current_load' => $currentLoad,
                'label' => "{$designer->user->name} (متاح: {$available} / السعة: {$maxCapacity})",
            ];
        })->sortByDesc('available_capacity')->values();
    }

    /**
     * تعيين اشتراك معين لمصمم بشكل مباشر.
     */
    public function assignContractToDesigner(int $contractId, int $designerId, ?string $weekStartDate = null): ClientDesigner
    {
        $week = $this->resolveWeek($weekStartDate);
        $contract = Contract::findOrFail($contractId);

        return ClientDesigner::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'week_start_date' => $week['start'],
            ],
            [
                'client_id' => $contract->client_id,
                'designer_id' => $designerId,
                'is_side' => false,
            ]
        );
    }

    /**
     * تنفيذ توزيع تلقائي للاشتراكات المتبقية غير الموزعة.
     *
     * @return array{success: bool, message: string, distributed: int, failed: int}
     */
    public function autoDistributeUnassigned(?string $weekStartDate = null): array
    {
        $week = $this->resolveWeek($weekStartDate);

        return $this->designerDistributionService->autoDistribute($week['start'], ['force' => false]);
    }

    /**
     * توزيع تاقات لعميل محدد باستخدام خوارزمية التوزيع الذكي المتدرج الكاملة من TagDistributionService.
     */
    public function distributeTagsForAssignment(int $clientDesignerId, ?string $weekStartDate = null): int
    {
        $week = $this->resolveWeek($weekStartDate);

        $this->tagDistributionService->cleanupEmptyDuplicateAssignments($week['start']);

        $assignment = ClientDesigner::with([
            'designer.user',
            'client.category',
            'client.location',
            'client.tags',
            'client.clientNeeds.tags',
            'client.tagGroups',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])->findOrFail($clientDesignerId);

        $designerId = $assignment->designer_id;

        // جلب جميع تعيينات هذا المصمم لضمان توازن السعة والأيام
        $assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'client.location',
            'client.tags',
            'client.clientNeeds.tags',
            'client.tagGroups',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])
            ->where('week_start_date', $week['start'])
            ->where('designer_id', $designerId)
            ->get();

        // 1. تشغيل التوزيع الذكي المتدرج الكامل (عالية جداً -> عالية -> متوسطة ومنخفضة -> أفكار)
        $count = $this->tagDistributionService->distributeSmartForDesigner($assignments, (int) $designerId, $week['start']);

        // 2. إذا لم يتم ملء كافة الحصص، نستخدم autoDistribute لتكملة التاقات المتبقية
        $assignment->refresh();
        $required = (int) ($assignment->contract?->weekly_designs_count ?? 0);
        $actual = $assignment->distributions()->count();

        if ($actual < $required) {
            $extra = $this->tagDistributionService->autoDistribute(new \Illuminate\Database\Eloquent\Collection([$assignment]), $week['start']);
            $count += $extra;
            if ($extra > 0) {
                $this->tagDistributionService->autoAssignIdeas(new \Illuminate\Database\Eloquent\Collection([$assignment]), $week['start']);
            }
        }

        return $count;
    }

    /**
     * توزيع التاقات لجميع العملاء لهذا الأسبوع باستخدام التوزيع الذكي المتدرج لجميع المصممين.
     */
    public function distributeTagsForAllMissing(?string $weekStartDate = null): int
    {
        $week = $this->resolveWeek($weekStartDate);

        $this->tagDistributionService->cleanupEmptyDuplicateAssignments($week['start']);

        $assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'client.location',
            'client.tags',
            'client.clientNeeds.tags',
            'client.tagGroups',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])
            ->where('week_start_date', $week['start'])
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        $designerIds = $assignments->pluck('designer_id')->unique()->filter();
        $totalCount = 0;

        // 1. تشغيل التوزيع الذكي المتدرج لكل مصمم (Very High -> High -> Medium/Low -> Ideas)
        foreach ($designerIds as $designerId) {
            $totalCount += $this->tagDistributionService->distributeSmartForDesigner($assignments, (int) $designerId, $week['start']);
        }

        // 2. فحص أي اشتراكات لم تستوفِ كامل حصتها بعد وتكميلها بـ autoDistribute
        $assignments->each->refresh();
        $stillMissing = $assignments->filter(function (ClientDesigner $a) {
            $required = (int) ($a->contract?->weekly_designs_count ?? 0);

            return $required > 0 && $a->distributions()->count() < $required;
        });

        if ($stillMissing->isNotEmpty()) {
            $extraCount = $this->tagDistributionService->autoDistribute($stillMissing, $week['start']);
            $totalCount += $extraCount;

            if ($extraCount > 0) {
                $this->tagDistributionService->autoAssignIdeas($stillMissing, $week['start']);
            }
        }

        return $totalCount;
    }
}
