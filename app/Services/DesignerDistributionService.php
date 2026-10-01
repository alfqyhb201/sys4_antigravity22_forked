<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Designer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * خدمة لتوزيع المصممين على العملاء بشكل تلقائي بناءً على الاشتراكات.
 */
class DesignerDistributionService
{
    /** @var array<string, int> */
    // protected function getWeights(): array
    // {
    //     return [
    //         'specialization' => (int) config('designer_distribution.weights.specialization', 30),
    //         'rating' => (int) config('designer_distribution.weights.rating', 30),
    //         'continuity' => (int) config('designer_distribution.weights.continuity', 15),
    //         'experience' => (int) config('designer_distribution.weights.experience', 10),
    //         'capacity_balance' => (int) config('designer_distribution.weights.capacity_balance', 30),
    //     ];
    // }
    protected function getWeights(): array
    {
        return [
            'specialization' => (int) config('designer_distribution.weights.specialization', 25),
            'rating' => (int) config('designer_distribution.weights.rating', 15), // خفض التقييم
            'continuity' => (int) config('designer_distribution.weights.continuity', 15),
            'experience' => (int) config('designer_distribution.weights.experience', 10),
            'capacity_balance' => (int) config('designer_distribution.weights.capacity_balance', 35), // رفع وزن توازن السعة
        ];
    }

    /**
     * يقوم بتوزيع الاشتراكات التي تحتاج إلى مصممين بشكل تلقائي.
     *
     * @param  string  $weekStartDate  تاريخ بداية الأسبوع (Y-m-d).
     * @param  array  $options  خيارات إضافية مثل `force` لتجاهل التوزيعات الحالية.
     * @return array نتيجة عملية التوزيع.
     */
    public function autoDistribute(string $weekStartDate, array $options = []): array
    {
        try {
            $usesTransaction = DB::transactionLevel() === 0;

            if ($usesTransaction) {
                DB::beginTransaction();
            }

            // التحقق من صحة التاريخ
            $weekStart = Carbon::parse($weekStartDate)->startOfDay();
            $force = $options['force'] ?? false;

            // جلب البيانات
            $designers = $this->getAvailableDesigners();
            $contracts = $this->getContractsNeedingDistribution($weekStart, $force);

            // التحقق من وجود بيانات
            if ($designers->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'لا يوجد مصممين متاحين للتوزيع',
                    'distributed' => 0,
                    'failed' => $contracts->count(),
                    'details' => [],
                ];
            }

            if ($contracts->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'لا يوجد عقود تحتاج للتوزيع',
                    'distributed' => 0,
                    'failed' => 0,
                    'details' => [],
                ];
            }

            // تهيئة متتبعات الحمل
            $designerLoads = $this->initializeDesignerLoads($designers, $weekStart, $options);

            // ترتيب الاشتراكات حسب الأولوية (الأكبر حجماً أولاً)
            $sortedContracts = $this->sortContractsByPriority($contracts);

            // تنفيذ التوزيع
            $results = $this->performDistribution(
                $sortedContracts,
                $designers,
                $designerLoads,
                $weekStart
            );

            if ($usesTransaction) {
                DB::commit();
            }

            return $this->formatResults($results, $contracts->count());
        } catch (\Exception $e) {
            if ($usesTransaction) {
                DB::rollBack();
            }
            Log::error('خطأ في التوزيع التلقائي: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء التوزيع: '.$e->getMessage(),
                'distributed' => 0,
                'failed' => 0,
                'details' => [],
            ];
        }
    }

    /**
     * يجلب المصممين المتاحين مع علاقاتهم.
     */
    private function getAvailableDesigners(): Collection
    {
        return Designer::with(['categories', 'user'])
            ->whereNotNull('max_capacity')
            ->where('max_capacity', '>', 0)
            ->get()
            ->filter(function ($designer) {
                // التأكد من وجود بيانات المستخدم
                return $designer->user !== null;
            });
    }

    /**
     * يجلب الاشتراكات التي تحتاج إلى توزيع لهذا الأسبوع.
     */
    private function getContractsNeedingDistribution(Carbon $weekStart, bool $force = false): Collection
    {
        $weekStartDate = $weekStart->format('Y-m-d');
        $weekEndDate = $weekStart->copy()->addDays(6)->format('Y-m-d');

        $query = Contract::with(['client.category'])
            ->where('status', 'active')
            ->where('weekly_designs_count', '>', 0)
            ->where('start_date', '<=', $weekEndDate);

        // إذا لم يكن force، استبعد الاشتراكات الموزعة بالفعل
        if (! $force) {
            // جلب IDs الاشتراكات والعملاء التي تم توزيعها بالفعل لهذا الأسبوع
            $distributedContractIds = ClientDesigner::where('week_start_date', $weekStartDate)
                ->whereNotNull('contract_id')
                ->pluck('contract_id')
                ->toArray();

            $distributedClientIds = ClientDesigner::where('week_start_date', $weekStartDate)
                ->pluck('client_id')
                ->toArray();

            // استبعاد الاشتراكات والعملاء التي تم توزيعها بالفعل
            $query->whereNotIn('id', $distributedContractIds)
                ->whereNotIn('client_id', $distributedClientIds);
        }

        return $query->get()
            ->filter(function ($contract) {
                // التأكد من وجود عميل
                return $contract->client !== null;
            });
    }

    /**
     * يقوم بتهيئة الأحمال الأولية للمصممين مع دعم السعات المخصصة والحمل القائم.
     */
    private function initializeDesignerLoads(Collection $designers, Carbon $weekStart, array $options = []): array
    {
        $weekStartDate = $weekStart->format('Y-m-d');
        $existing = ClientDesigner::where('week_start_date', $weekStartDate)
            ->whereNotNull('contract_id')
            ->with('contract')
            ->get()
            ->groupBy('designer_id');

        $customCapacities = $options['custom_capacities'] ?? [];

        $loads = [];
        foreach ($designers as $designer) {
            $assignedDesigns = (int) ($existing->get($designer->id)?->sum(fn ($a) => $a->contract->weekly_designs_count ?? 0) ?? 0);
            $assignedClients = (int) ($existing->get($designer->id)?->count() ?? 0);

            $maxCap = isset($customCapacities[$designer->id])
                ? (int) $customCapacities[$designer->id]
                : (int) ($designer->max_capacity ?? 0);

            $loads[$designer->id] = [
                'current' => $assignedClients,
                'min' => $designer->min_capacity ?? 0,
                'max' => $maxCap,
                'designs_count' => $assignedDesigns,
            ];
        }

        return $loads;
    }

    /**
     * يقوم بترتيب الاشتراكات حسب الأولوية (الأعلى أولاً).
     */
    private function sortContractsByPriority(Collection $contracts): Collection
    {
        return $contracts->sortByDesc(function ($contract) {
            return $contract->weekly_designs_count ?? 0;
        });
    }

    /**
     * يقوم بتنفيذ عملية التوزيع على مرحلتين:
     * المرحلة 1: تغطية الحد الأدنى (min_capacity) لجميع المصممين المتاحين بالتوازي وبناءً على التقييم/التخصص.
     * المرحلة 2: توزيع الفائض بالتساوي (Load Balancing) بين المصممين وصولاً إلى الحد الأقصى (max_capacity).
     */
    private function performDistribution(
        Collection $contracts,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart
    ): array {
        $results = [
            'distributed' => [],
            'failed' => [],
        ];

        // 1. توزيع العملاء المثبتين أولاً (Pinned Clients)
        $this->distributePinnedClients($contracts, $designers, $designerLoads, $weekStart, $results);

        // تصفية العقود غير الموزعة وترتيبها حسب تقييم العميل والأهمية (العملاء الأعلى تقييماً أولاً)
        $remainingContracts = $contracts->reject(fn ($c) => isset($c->assigned_in_initial))
            ->sortByDesc(function ($contract) {
                $rating = (float) ($contract->client->rating ?? 0);
                $importance = (float) ($contract->client->importance_score ?? 0);
                $designs = (int) ($contract->weekly_designs_count ?? 0);

                return ($rating * 1000) + ($importance * 10) + $designs;
            });

        // 2. المرحلة الأولى: تغطية الحد الأدنى (Min Capacity) مع مطابقة التقييم والتخصص
        $unassignedAfterMinPhase = new Collection;

        foreach ($remainingContracts as $contract) {
            $bestDesigner = $this->findBestDesignerForMinCapacity(
                $contract,
                $designers,
                $designerLoads,
                $weekStart
            );

            if ($bestDesigner) {
                $this->applyContractAssignment(
                    $contract,
                    $bestDesigner,
                    $designerLoads,
                    $weekStart,
                    $results,
                    'min_capacity'
                );
            } else {
                // لم نجد مصمماً بسعة دنيا متاحة لهذا العقد، ينتقل لمرحلة الفائض
                $unassignedAfterMinPhase->push($contract);
            }
        }

        // 3. المرحلة الثانية: توزيع الفائض بالتساوي مع الحفاظ على الأفضلية بالتقييم
        foreach ($unassignedAfterMinPhase as $contract) {
            $bestDesigner = $this->findBestDesignerForRemainder(
                $contract,
                $designers,
                $designerLoads,
                $weekStart
            );

            if ($bestDesigner) {
                $this->applyContractAssignment(
                    $contract,
                    $bestDesigner,
                    $designerLoads,
                    $weekStart,
                    $results,
                    'remainder'
                );
            } else {
                $results['failed'][] = [
                    'client_id' => $contract->client_id,
                    'client_name' => $contract->client->client_name ?? $contract->client->company,
                    'reason' => 'جميع المصممين المتاحين وصلوا لحدهم الأقصى (Max Capacity)',
                ];
            }
        }

        return $results;
    }

    /**
     * يبحث عن أفضل مصمم متاح في مرحلة تغطية الحد الأدنى (Min Capacity) مع مطابقة التقييم والتخصص.
     */
    private function findBestDesignerForMinCapacity(
        Contract $contract,
        Collection $designers,
        array $designerLoads,
        Carbon $weekStart
    ): ?Designer {
        $contractDesignsCount = $contract->weekly_designs_count ?? 0;
        $previousDesigner = $this->getPreviousDesigner($contract, $weekStart);
        $clientCategoryId = $contract->client->category_id ?? null;

        $eligibleDesigners = $designers->filter(function ($designer) use ($designerLoads, $contractDesignsCount) {
            $load = $designerLoads[$designer->id];
            $minCap = $load['min'] ?? 0;
            $maxCap = $load['max'] ?? 0;

            // يجب أن يكون للمصمم حد أدنى ولم يصله بعد
            if ($minCap <= 0 || $load['designs_count'] >= $minCap) {
                return false;
            }

            // التأكد من عدم تجاوز الحد الأقصى الكلي
            return ($load['designs_count'] + $contractDesignsCount) <= $maxCap;
        });

        if ($eligibleDesigners->isEmpty()) {
            return null;
        }

        // ترتيب المرشحين: الأعلى تقييماً مع التخصص والاستمرارية أولاً لخدمة العملاء ذوي الأولوية
        return $eligibleDesigners->sortByDesc(function ($designer) use ($designerLoads, $clientCategoryId, $previousDesigner) {
            $designerRate = (float) ($designer->rate ?? 0);
            $load = $designerLoads[$designer->id];
            $minCap = max(1, $load['min']);
            $minUtilization = $load['designs_count'] / $minCap; // من 0.0 إلى 1.0

            $score = $designerRate;

            // مكافأة تطابق التخصص
            $designerCategoryIds = $designer->categories->pluck('id')->toArray();
            if ($clientCategoryId && in_array($clientCategoryId, $designerCategoryIds)) {
                $score += 1.5;
            }

            // مكافأة الاستمرارية
            if ($previousDesigner && $designer->id === $previousDesigner) {
                $score += 2.0;
            }

            // ترجيح بسيط للأقل إشغالاً في حال تطابق التقييم
            $score += (1.0 - $minUtilization) * 0.1;

            return $score;
        })->first();
    }

    /**
     * يبحث عن أفضل مصمم متاح في مرحلة توزيع الفائض بالتساوي مع مراعاة التقييم.
     */
    private function findBestDesignerForRemainder(
        Contract $contract,
        Collection $designers,
        array $designerLoads,
        Carbon $weekStart
    ): ?Designer {
        $contractDesignsCount = $contract->weekly_designs_count ?? 0;
        $previousDesigner = $this->getPreviousDesigner($contract, $weekStart);
        $clientCategoryId = $contract->client->category_id ?? null;

        $eligibleDesigners = $designers->filter(function ($designer) use ($designerLoads, $contractDesignsCount) {
            $load = $designerLoads[$designer->id];
            $maxCap = $load['max'] ?? 0;

            return ($load['designs_count'] + $contractDesignsCount) <= $maxCap;
        });

        if ($eligibleDesigners->isEmpty()) {
            return null;
        }

        // ترتيب المرشحين: الأقل نسبة إشغال للسعة القصوى، وعند تقارب الإشغال نفضل الأعلى تقييماً والتخصص
        return $eligibleDesigners->sortBy(function ($designer) use ($designerLoads, $clientCategoryId, $previousDesigner) {
            $load = $designerLoads[$designer->id];
            $maxCap = max(1, $load['max']);
            $utilization = $load['designs_count'] / $maxCap; // من 0 إلى 1
            $designerRate = (float) ($designer->rate ?? 0);

            $qualityBonus = $designerRate * 0.5;

            $designerCategoryIds = $designer->categories->pluck('id')->toArray();
            if ($clientCategoryId && in_array($clientCategoryId, $designerCategoryIds)) {
                $qualityBonus += 1.0;
            }

            if ($previousDesigner && $designer->id === $previousDesigner) {
                $qualityBonus += 1.5;
            }

            // ترتيب صاعد: الأقل إشغالاً أولاً لضمان التوازن العادل، وتفضيل الكفاءة الأعلى في حال التقارب
            return ($utilization * 100) - $qualityBonus;
        })->first();
    }

    /**
     * تطبيق تعيين العقد للمصمم وتحديث الأحمال والنتائج.
     */
    private function applyContractAssignment(
        Contract $contract,
        Designer $designer,
        array &$designerLoads,
        Carbon $weekStart,
        array &$results,
        string $phase = 'regular'
    ): void {
        $assignment = $this->assignContractToDesigner($contract, $designer, $weekStart);

        $designsCount = $contract->weekly_designs_count ?? 0;
        $designerLoads[$designer->id]['current']++;
        $designerLoads[$designer->id]['designs_count'] += $designsCount;

        $results['distributed'][] = [
            'client_id' => $contract->client_id,
            'contract_id' => $contract->id,
            'client_name' => $contract->client->client_name ?? $contract->client->company,
            'contract_type' => $contract->billing_cycle,
            'designer_id' => $designer->id,
            'designer_name' => $designer->user->name ?? '',
            'designs_count' => $designsCount,
            'score' => $designer->rate ?? 0,
            'phase' => $phase,
        ];

        $contract->assigned_in_initial = true;
    }

    /**
     * توزيع العملاء المثبتين على مصممين محددين.
     */
    private function distributePinnedClients(
        Collection $contracts,
        Collection $designers,
        array &$designerLoads,
        Carbon $weekStart,
        array &$results
    ): void {
        foreach ($contracts as $contract) {
            if (isset($contract->assigned_in_initial)) {
                continue;
            }

            $fixedDesignerId = $contract->client->fixed_designer_id ?? null;

            if ($fixedDesignerId) {
                $designer = $designers->firstWhere('id', $fixedDesignerId);

                if ($designer) {
                    $assignment = $this->assignContractToDesigner(
                        $contract,
                        $designer,
                        $weekStart
                    );

                    $designsCount = $contract->weekly_designs_count ?? 0;
                    $designerLoads[$designer->id]['current']++;
                    $designerLoads[$designer->id]['designs_count'] += $designsCount;

                    $results['distributed'][] = [
                        'client_id' => $contract->client_id,
                        'contract_id' => $contract->id,
                        'client_name' => $contract->client->client_name ?? $contract->client->company,
                        'contract_type' => $contract->billing_cycle,
                        'designer_id' => $designer->id,
                        'designer_name' => $designer->user->name ?? '',
                        'designs_count' => $designsCount,
                        'score' => 100,
                        'reason' => 'pinned',
                    ];

                    $contract->assigned_in_initial = true;
                } else {
                    $results['failed'][] = [
                        'client_id' => $contract->client_id,
                        'client_name' => $contract->client->client_name ?? $contract->client->company,
                        'reason' => 'المصمم المثبت غير متاح أو غير موجود',
                    ];
                }
            }
        }
    }

    /**
     * يتحقق مما إذا كان لدى المصمم سعة متاحة لاستيعاب اشتراك جديد.
     */
    private function hasAvailableCapacity(Designer $designer, array $load, Contract $contract): bool
    {
        $maxCapacity = $load['max'] ?? ($designer->max_capacity ?? 0);
        $contractDesignsCount = $contract->weekly_designs_count ?? 0;

        return ($load['designs_count'] + $contractDesignsCount) <= $maxCapacity;
    }

    /**
     * يحسب النقاط الإجمالية لمصمم معين بناءً على اشتراك محدد.
     */
    private function calculateDesignerScore(
        Designer $designer,
        Contract $contract,
        array $load,
        ?int $previousDesignerId
    ): float {
        $scores = [];
        $client = $contract->client;

        // 1. نقاط التخصص (40 نقطة)
        $scores['specialization'] = $this->calculateSpecializationScore($designer, $client);

        // 2. نقاط التقييم (25 نقطة)
        $scores['rating'] = $this->calculateRatingScore($designer);

        // 3. نقاط الاستمرارية (20 نقطة)
        $scores['continuity'] = $this->calculateContinuityScore($designer, $previousDesignerId);

        // 4. نقاط الخبرة (10 نقطة)
        $scores['experience'] = $this->calculateExperienceScore($designer, $contract);

        // 5. نقاط توازن السعة (5 نقاط)
        $scores['capacity_balance'] = $this->calculateCapacityBalanceScore($designer, $load);

        // حساب المجموع الكلي
        $totalScore = 0;
        $weights = $this->getWeights();
        foreach ($scores as $key => $score) {
            $weight = $weights[$key] ?? 0;
            $totalScore += ($score * $weight);
        }

        return $totalScore;
    }

    /**
     * يحسب نقاط التخصص بناءً على تطابق فئة العميل مع فئات المصمم.
     */
    private function calculateSpecializationScore(Designer $designer, Client $client): float
    {
        if (! $client->category_id) {
            return 0.5; // نقاط محايدة للعملاء بدون تصنيف
        }

        $designerCategories = $designer->categories->pluck('id')->toArray();

        if (in_array($client->category_id, $designerCategories)) {
            return 1.0; // تطابق كامل
        }

        return 0.3; // لا يوجد تطابق
    }

    /**
     * يحسب نقاط التقييم للمصمم.
     */
    private function calculateRatingScore(Designer $designer): float
    {
        $rate = $designer->rate ?? 0;
        $maxRate = 10; // افتراض أن التقييم من 10

        if ($maxRate == 0) {
            return 0.5;
        }

        return min(1.0, $rate / $maxRate);
    }

    /**
     * يحسب نقاط الاستمرارية بناءً على ما إذا كان المصمم هو نفسه المصمم السابق.
     */
    private function calculateContinuityScore(Designer $designer, ?int $previousDesignerId): float
    {
        if ($previousDesignerId === null) {
            return 0.5; // محايد للعملاء الجدد
        }

        return $designer->id === $previousDesignerId ? 1.0 : 0.0;
    }

    /**
     * يحسب نقاط الخبرة بناءً على حجم الاشتراك وخبرة المصمم.
     */
    private function calculateExperienceScore(Designer $designer, Contract $contract): float
    {
        $designerExperience = $designer->amount_of_designs ?? 0;
        $designsNeed = $contract->weekly_designs_count ?? 0;

        // المصممين ذوي الخبرة الأعلى يحصلون على نقاط أعلى للاشتراكات/الاشتراكات الكبيرة
        if ($designsNeed > 5) { // الأسبوعي أكبر من 5 تصاميم يعني اشتراك كبير
            return min(1.0, $designerExperience / 1000);
        } else {
            return 0.7;
        }
    }

    /**
     * يحسب نقاط توازن السعة لتشجيع التوزيع العادل.
     */
    // private function calculateCapacityBalanceScore(Designer $designer, array $load): float
    // {
    //     $minCapacity = $load['min'];
    //     $maxCapacity = $load['max'];
    //     $currentAssignments = $load['current'];

    //     if ($maxCapacity == 0) {
    //         return 0;
    //     }

    //     // إعطاء نقاط إضافية عالية للمصممين بدون تعيينات لضمان التضمين
    //     if ($currentAssignments == 0) {
    //         return 1.5; // دفع قوي للتضمين
    //     }

    //     // تشجيع الوصول إلى الحد الأدنى أولاً
    //     if ($currentAssignments < $minCapacity) {
    //         return 1.0;
    //     }

    //     // بعد الحد الأدنى، تقليل النقاط تدريجياً
    //     $utilizationRate = $currentAssignments / $maxCapacity;

    //     return max(0, 1.0 - $utilizationRate);
    // }
    private function calculateCapacityBalanceScore(Designer $designer, array $load): float
    {
        $maxCapacity = $load['max'];
        $currentDesigns = $load['designs_count'];

        if ($maxCapacity <= 0) {
            return 0;
        }

        // نسبة الشغل الحالي للمصمم (من 0 إلى 1)
        $utilizationRate = $currentDesigns / $maxCapacity;

        // يعطي أعلى نقاط (1.0) للمصمم الأقل إشغالاً، وتقل كلما اقترب من الماكس
        return max(0.0, 1.0 - $utilizationRate);
    }

    /**
     * يحصل على معرف المصمم السابق للاشتراك أو العميل.
     */
    private function getPreviousDesigner(Contract $contract, Carbon $currentWeekStart): ?int
    {
        $previousWeekStart = $currentWeekStart->copy()->subWeek()->format('Y-m-d');

        // البحث عن تعيين سابق لنفس الاشتراك
        $previousAssignment = ClientDesigner::where('contract_id', $contract->id)
            ->where('week_start_date', $previousWeekStart)
            ->first();

        if ($previousAssignment) {
            return $previousAssignment->designer_id;
        }

        // إذا لم يوجد، البحث عن تعيين سابق لنفس العميل (لأي اشتراك)
        $previousClientAssignment = ClientDesigner::where('client_id', $contract->client_id)
            ->where('week_start_date', $previousWeekStart)
            ->first();

        return $previousClientAssignment ? $previousClientAssignment->designer_id : null;
    }

    /**
     * يقوم بتعيين اشتراك لمصمم معين.
     */
    private function assignContractToDesigner(
        Contract $contract,
        Designer $designer,
        Carbon $weekStart
    ): array {
        $weekStartDate = $weekStart->format('Y-m-d');

        ClientDesigner::updateOrCreate(
            [
                'contract_id' => $contract->id,
                'week_start_date' => $weekStartDate,
            ],
            [
                'client_id' => $contract->client_id,
                'designer_id' => $designer->id,
            ]
        );

        return [
            'success' => true,
            'score' => 0,
        ];
    }

    /**
     * يقوم بتنسيق النتائج النهائية لعملية التوزيع.
     */
    private function formatResults(array $results, int $totalContracts): array
    {
        $distributedCount = count($results['distributed']);
        $failedCount = count($results['failed']);

        $message = sprintf(
            'تم توزيع %d من %d اشتراك بنجاح',
            $distributedCount,
            $totalContracts
        );

        if ($failedCount > 0) {
            $message .= sprintf(' (%d فشل)', $failedCount);
        }

        return [
            'success' => $distributedCount > 0,
            'message' => $message,
            'distributed' => $distributedCount,
            'failed' => $failedCount,
            'total' => $totalContracts,
            'details' => $results,
            'statistics' => $this->calculateStatistics($results),
        ];
    }

    /**
     * يحسب إحصائيات التوزيع للمصممين.
     */
    private function calculateStatistics(array $results): array
    {
        $designerStats = [];

        foreach ($results['distributed'] as $item) {
            $designerId = $item['designer_id'];

            if (! isset($designerStats[$designerId])) {
                $designerStats[$designerId] = [
                    'designer_name' => $item['designer_name'],
                    'clients_count' => 0,
                    'total_designs' => 0,
                ];
            }

            $designerStats[$designerId]['clients_count']++;
            $designerStats[$designerId]['total_designs'] += $item['designs_count'];
        }

        return [
            'designers' => $designerStats,
            'total_designers_used' => count($designerStats),
        ];
    }

    /**
     * يقوم بجلب تقرير مفصل عن توزيعات أسبوع معين.
     *
     * @param  string  $weekStartDate  تاريخ بداية الأسبوع (Y-m-d).
     * @return array تقرير التوزيع.
     */
    public function getDistributionReport(string $weekStartDate): array
    {
        $weekStart = Carbon::parse($weekStartDate)->startOfDay();
        $dateStr = $weekStart->format('Y-m-d');

        $assignments = ClientDesigner::with(['client', 'contract', 'designer.user'])
            ->where('week_start_date', $dateStr)
            ->whereNotNull('contract_id')
            ->get();

        // حساب الاشتراكات التي لم يتم توزيعها بعد
        $pendingCount = $this->getContractsNeedingDistribution($weekStart)->count();

        $report = [
            'week_start' => $dateStr,
            'total_assignments' => $assignments->count(),
            'pending_count' => $pendingCount,
            'designers' => [],
        ];

        foreach ($assignments as $assignment) {
            $designerId = $assignment->designer_id;

            if (! isset($report['designers'][$designerId])) {
                $report['designers'][$designerId] = [
                    'id' => $designerId,
                    'designer_name' => $assignment->designer->user->name ?? 'غير معروف',
                    'clients' => [],
                    'clients_count' => 0,
                    'total_designs' => 0,
                ];
            }

            $designsCount = $assignment->contract?->weekly_designs_count ?? 0;
            $contractType = $assignment->contract?->billing_cycle ?? '-';

            $report['designers'][$designerId]['clients'][] = [
                'id' => $assignment->client_id,
                'name' => ($assignment->client->client_name ?? $assignment->client->company)." ({$contractType})",
                'designs_count' => $designsCount,
            ];

            $report['designers'][$designerId]['clients_count']++;
            $report['designers'][$designerId]['total_designs'] += $designsCount;
        }

        return $report;
    }

    /**
     * يحلل العجز في السعة للاشتراكات غير الموزعة ويحسب التوزيع المتساوي على المصممين المتاحين.
     *
     * @param  string  $weekStartDate  تاريخ بداية الأسبوع (Y-m-d).
     * @return array|null بيانات تحليل العجز والتوزيع المقترح.
     */
    public function getDeficitAnalysis(string $weekStartDate): ?array
    {
        $weekStart = Carbon::parse($weekStartDate)->startOfDay();
        $unassignedContracts = $this->getContractsNeedingDistribution($weekStart, false);

        if ($unassignedContracts->isEmpty()) {
            return null;
        }

        $designers = $this->getAvailableDesigners();
        $designersCount = $designers->count();
        if ($designersCount === 0) {
            return null;
        }

        $totalDesignsNeeded = (int) $unassignedContracts->sum(fn ($c) => $c->weekly_designs_count ?? 0);
        $totalContractsCount = $unassignedContracts->count();

        // حساب الزيادة المتساوية لكل مصمم
        $baseAddition = intdiv($totalDesignsNeeded, $designersCount);
        $remainder = $totalDesignsNeeded % $designersCount;

        // جلب التعيينات الحالية لهذا الأسبوع
        $weekDateStr = $weekStart->format('Y-m-d');
        $existing = ClientDesigner::where('week_start_date', $weekDateStr)
            ->whereNotNull('contract_id')
            ->with('contract')
            ->get()
            ->groupBy('designer_id');

        $designerProjections = [];
        $i = 0;
        foreach ($designers as $designer) {
            $assignedDesigns = (int) ($existing->get($designer->id)?->sum(fn ($a) => $a->contract->weekly_designs_count ?? 0) ?? 0);
            $assignedClients = (int) ($existing->get($designer->id)?->count() ?? 0);

            // توزيع باقي القسمة تدريجياً لضمان تغطية العجز بدقة
            $extra = $baseAddition + ($i < $remainder ? 1 : 0);
            $newTempCapacity = $designer->max_capacity + $extra;

            $designerProjections[] = [
                'designer_id' => $designer->id,
                'designer_name' => $designer->user->name ?? 'غير معروف',
                'current_designs' => $assignedDesigns,
                'current_clients' => $assignedClients,
                'current_max' => $designer->max_capacity,
                'extra_capacity' => $extra,
                'new_temp_capacity' => $newTempCapacity,
            ];
            $i++;
        }

        $totalCurrentCapacity = (int) $designers->sum('max_capacity');

        return [
            'week_start_date' => $weekDateStr,
            'total_designs_needed' => $totalDesignsNeeded,
            'total_contracts_count' => $totalContractsCount,
            'total_current_capacity' => $totalCurrentCapacity,
            'designers_count' => $designersCount,
            'average_extra_per_designer' => round($totalDesignsNeeded / $designersCount, 1),
            'designer_projections' => $designerProjections,
        ];
    }

    /**
     * يقوم بتوزيع الاشتراكات المعلقة عبر زيادة مؤقتة متساوية على سعة المصممين لهذا الأسبوع فقط.
     *
     * @param  string  $weekStartDate  تاريخ بداية الأسبوع (Y-m-d).
     * @return array نتيجة التوزيع الإضافي.
     */
    public function distributeWithEqualOverload(string $weekStartDate): array
    {
        $analysis = $this->getDeficitAnalysis($weekStartDate);
        if (! $analysis) {
            return [
                'success' => false,
                'message' => 'لا توجد اشتراكات معلقة تحتاج للتوزيع لهذا الأسبوع',
                'distributed' => 0,
                'failed' => 0,
                'details' => [],
            ];
        }

        // بناء خريطة السعات المؤقتة
        $customCapacities = [];
        foreach ($analysis['designer_projections'] as $projection) {
            $customCapacities[$projection['designer_id']] = $projection['new_temp_capacity'];
        }

        return $this->autoDistribute($weekStartDate, [
            'force' => false,
            'custom_capacities' => $customCapacities,
        ]);
    }
}
