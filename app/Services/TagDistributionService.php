<?php

namespace App\Services;

use App\Enums\ImportanceLevel;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Idea;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TagDistributionService
{
    public function __construct(
        protected ImportanceRatioService $importanceRatioService,
    ) {}

    public function getWeekDays(string $selectedWeek): array
    {
        $weekStart = Carbon::parse($selectedWeek);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            if ($day->dayOfWeek !== Carbon::FRIDAY) {
                $days[] = $day->format('Y-m-d');
            }
        }

        return $days;
    }

    protected array $collectedTagsCache = [];

    protected array $clientHistoryCache = [];

    protected array $groupTagsCache = [];

    protected ?Collection $allActiveTagsCache = null;

    protected array $categoryTagsMap = [];

    protected array $locationTagsMap = [];

    public function getAllActiveTags(): Collection
    {
        if ($this->allActiveTagsCache === null) {
            $this->allActiveTagsCache = Tag::with(['categories:id', 'locations:id'])
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            $this->categoryTagsMap = [];
            $this->locationTagsMap = [];

            foreach ($this->allActiveTagsCache as $tag) {
                if ($tag->is_auto_assigned) {
                    if ($tag->categories) {
                        foreach ($tag->categories as $cat) {
                            $this->categoryTagsMap[$cat->id][$tag->id] = $tag;
                        }
                    }
                    if ($tag->locations) {
                        foreach ($tag->locations as $loc) {
                            $this->locationTagsMap[$loc->id][$tag->id] = $tag;
                        }
                    }
                }
            }
        }

        return $this->allActiveTagsCache;
    }

    public function clearTagsCache(): void
    {
        $this->collectedTagsCache = [];
        $this->clientHistoryCache = [];
        $this->groupTagsCache = [];
        $this->allActiveTagsCache = null;
        $this->categoryTagsMap = [];
        $this->locationTagsMap = [];
    }

    public function clearHistoryCache(): void
    {
        $this->clientHistoryCache = [];
    }

    public function preloadClientTagUsageHistory(array $clientIds, ?string $currentWeek = null): void
    {
        $uncachedClientIds = [];
        foreach ($clientIds as $clientId) {
            $cacheKey = "{$clientId}_".($currentWeek ?? 'all');
            if (! isset($this->clientHistoryCache[$cacheKey])) {
                $uncachedClientIds[] = $clientId;
                $this->clientHistoryCache[$cacheKey] = [];
            }
        }

        if (empty($uncachedClientIds)) {
            return;
        }

        $query = ClientTagDistribution::join('client_designer', 'client_designer.id', '=', 'client_tag_distributions.client_designer_id')
            ->whereIn('client_designer.client_id', $uncachedClientIds)
            ->whereNotIn('client_tag_distributions.status', ['cancelled']);

        if ($currentWeek) {
            $query->where('client_designer.week_start_date', '<', $currentWeek);
        }

        $rows = $query->select(
            'client_designer.client_id',
            'client_tag_distributions.tag_id',
            DB::raw('COUNT(*) as usage_count'),
            DB::raw('MAX(client_tag_distributions.distribution_date) as last_used_date')
        )
            ->groupBy('client_designer.client_id', 'client_tag_distributions.tag_id')
            ->get();

        foreach ($rows as $row) {
            $cacheKey = "{$row->client_id}_".($currentWeek ?? 'all');
            $this->clientHistoryCache[$cacheKey][$row->tag_id] = [
                'tag_id' => (int) $row->tag_id,
                'usage_count' => (int) $row->usage_count,
                'last_used_date' => (string) $row->last_used_date,
            ];
        }
    }

    public function getTagsByGroupIds(array $groupIds): Collection
    {
        if (empty($groupIds)) {
            return collect();
        }

        return $this->getAllActiveTags()
            ->whereIn('tag_group_id', $groupIds)
            ->where('is_auto_assigned', true)
            ->values();
    }

    public function getClientTagUsageHistory(int $clientId, ?string $currentWeek = null): array
    {
        $cacheKey = "{$clientId}_".($currentWeek ?? 'all');
        if (isset($this->clientHistoryCache[$cacheKey])) {
            return $this->clientHistoryCache[$cacheKey];
        }

        $query = ClientTagDistribution::whereHas('clientDesigner', function ($q) use ($clientId, $currentWeek) {
            $q->where('client_id', $clientId);
            if ($currentWeek) {
                $q->where('week_start_date', '<', $currentWeek);
            }
        })
            ->whereNotIn('status', ['cancelled']);

        $history = $query->select('tag_id', DB::raw('COUNT(*) as usage_count'), DB::raw('MAX(distribution_date) as last_used_date'))
            ->groupBy('tag_id')
            ->get()
            ->keyBy('tag_id')
            ->map(function ($item) {
                return [
                    'tag_id' => (int) $item->tag_id,
                    'usage_count' => (int) $item->usage_count,
                    'last_used_date' => (string) $item->last_used_date,
                ];
            })
            ->toArray();

        return $this->clientHistoryCache[$cacheKey] = $history;
    }

    public function sortTagsByHistoryAndDiversity(
        Collection $tags,
        array $priorityTagIds = [],
        array $tagHistory = [],
        array $currentAssignedGroupCounts = [],
        int $clientSeed = 0,
        array $batchTagUsage = [],
        array $batchGroupUsage = []
    ): Collection {
        return $tags->sort(function ($a, $b) use ($priorityTagIds, $tagHistory, $currentAssignedGroupCounts, $clientSeed, $batchTagUsage, $batchGroupUsage) {
            $isPriA = in_array($a->id, $priorityTagIds) ? 0 : 1;
            $isPriB = in_array($b->id, $priorityTagIds) ? 0 : 1;
            if ($isPriA !== $isPriB) {
                return $isPriA <=> $isPriB;
            }

            $grpA = $a->tag_group_id ?? 0;
            $grpB = $b->tag_group_id ?? 0;
            $grpCountA = ($currentAssignedGroupCounts[$grpA] ?? 0) * 100 + ($batchGroupUsage[$grpA] ?? 0);
            $grpCountB = ($currentAssignedGroupCounts[$grpB] ?? 0) * 100 + ($batchGroupUsage[$grpB] ?? 0);
            if ($grpCountA !== $grpCountB) {
                return $grpCountA <=> $grpCountB;
            }

            $batchA = $batchTagUsage[$a->id] ?? 0;
            $batchB = $batchTagUsage[$b->id] ?? 0;
            if ($batchA !== $batchB) {
                return $batchA <=> $batchB;
            }

            $usageA = (int) ($tagHistory[$a->id]['usage_count'] ?? 0);
            $usageB = (int) ($tagHistory[$b->id]['usage_count'] ?? 0);
            if ($usageA !== $usageB) {
                return $usageA <=> $usageB;
            }

            $dateA = $tagHistory[$a->id]['last_used_date'] ?? '';
            $dateB = $tagHistory[$b->id]['last_used_date'] ?? '';
            if ($dateA !== $dateB) {
                return $dateA <=> $dateB;
            }

            if ($clientSeed > 0) {
                $hashA = (int) sprintf('%u', crc32($a->id.'_'.$clientSeed));
                $hashB = (int) sprintf('%u', crc32($b->id.'_'.$clientSeed));
                if ($hashA !== $hashB) {
                    return $hashA <=> $hashB;
                }
            }

            return $a->id <=> $b->id;
        })->values();
    }

    public function getAssignedGroupCounts(Collection $distributions): array
    {
        $counts = [];
        foreach ($distributions as $dist) {
            $groupId = $dist->tag?->tag_group_id ?? 0;
            $counts[$groupId] = ($counts[$groupId] ?? 0) + 1;
        }

        return $counts;
    }

    public function ensureRelationsLoaded(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return $assignments;
        }

        $relations = [
            'designer.user',
            'client.category',
            'client.location',
            'client.tags',
            'client.clientNeeds.tags',
            'client.tagGroups',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ];

        if ($assignments instanceof \Illuminate\Database\Eloquent\Collection) {
            $assignments->loadMissing($relations);
        } else {
            (new \Illuminate\Database\Eloquent\Collection($assignments->all()))->loadMissing($relations);
        }

        return $assignments;
    }

    public function collectTagsForAssignment($assignment): Collection
    {
        if (isset($this->collectedTagsCache[$assignment->id])) {
            return $this->collectedTagsCache[$assignment->id];
        }

        $allTags = collect();

        if (! $assignment->client) {
            return $allTags;
        }

        $client = $assignment->client;
        $this->getAllActiveTags();

        // 1. Tags from client's tag groups or category
        if ($client->tagGroups && $client->tagGroups->isNotEmpty()) {
            $clientGroupIds = $client->tagGroups->pluck('id')->toArray();

            if ($client->category_id) {
                // تاقات التصنيف التابعة لمجموعات العميل
                $categoryTags = collect($this->categoryTagsMap[$client->category_id] ?? [])
                    ->filter(fn ($tag) => in_array($tag->tag_group_id, $clientGroupIds));

                // تاقات المجموعات المحددة للعميل
                $groupTags = $this->getTagsByGroupIds($clientGroupIds);

                $allTags = $allTags->merge($categoryTags)->merge($groupTags);
            } else {
                $groupTags = $this->getTagsByGroupIds($clientGroupIds);
                $allTags = $allTags->merge($groupTags);
            }
        } elseif ($client->category_id) {
            $categoryTags = collect($this->categoryTagsMap[$client->category_id] ?? []);
            $allTags = $allTags->merge($categoryTags);
        }

        // 2. Tags from client's location
        if ($client->location_id) {
            $locationTags = collect($this->locationTagsMap[$client->location_id] ?? []);
            $allTags = $allTags->merge($locationTags);
        }

        // 3. Tags from client needs (explicit assignment — bypasses AND filter)
        if ($client->clientNeeds) {
            $clientNeedsTags = $client->clientNeeds->flatMap(fn ($need) => $need->tags ?? collect())
                ->unique('id')
                ->filter(fn ($tag) => $tag->is_active);
            $allTags = $allTags->merge($clientNeedsTags);
        }

        // 4. Tags manually assigned to client (explicit assignment — bypasses AND filter)
        if ($client->tags) {
            $manualTags = $client->tags->filter(fn ($tag) => $tag->is_active);
            $allTags = $allTags->merge($manualTags);
        }

        $allTags = $allTags->unique('id');

        // تطبيق شرط AND: يجب أن يطابق التاق تصنيف العميل وموقعه معاً
        // (يُستثنى من ذلك تاقات الاحتياجات واليدوية فهي مرتبطة بالعميل صراحةً)
        $filteredTags = $allTags->filter(function ($tag) use ($assignment, $client) {
            // Skip AND filter for client needs and manual tags
            if ($this->isExplicitlyAssignedToClient($tag, $assignment)) {
                return true;
            }

            // إذا كان للعميل مجموعات تاقات محددة، يجب أن ينتمي التاق (إن كان له مجموعة) إلى مجموعات العميل
            if ($client->tagGroups && $client->tagGroups->isNotEmpty() && $tag->tag_group_id) {
                $clientGroupIds = $client->tagGroups->pluck('id')->toArray();
                if (! in_array($tag->tag_group_id, $clientGroupIds)) {
                    return false;
                }
            }

            $categoryMatch = true;
            $locationMatch = true;

            // التحقق من تصنيف العميل
            if ($client->category_id) {
                $categoryMatch = $this->tagMatchesClientCategory($tag, $assignment);
            }

            // التحقق من موقع العميل
            if ($client->location_id) {
                $locationMatch = $this->tagMatchesClientLocation($tag, $assignment);
            }

            return $categoryMatch && $locationMatch;
        });

        return $this->collectedTagsCache[$assignment->id] = $filteredTags->values();
    }

    /**
     * تحقق مما إذا كان التاق مرتبطاً بالعميل بشكل صريح (احتياج أو يدوي).
     */
    private function isExplicitlyAssignedToClient($tag, $assignment): bool
    {
        $client = $assignment->client;
        if (! $client) {
            return false;
        }

        // تحقق من التاجات اليدوية
        if ($client->tags && $client->tags->contains('id', $tag->id)) {
            return true;
        }

        // تحقق من تاجات احتياجات العميل
        if ($client->clientNeeds) {
            foreach ($client->clientNeeds as $need) {
                if ($need->tags && $need->tags->contains('id', $tag->id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * تحقق مما إذا كان التاق يطابق تصنيف العميل.
     */
    private function tagMatchesClientCategory($tag, $assignment): bool
    {
        $client = $assignment->client;
        if (! $client || ! $client->category_id) {
            return true;
        }

        if ($tag->assign_all_categories) {
            return true;
        }

        // إذا كان للتاق تصنيفات محددة، يجب أن تحتوي على تصنيف العميل
        if ($tag->relationLoaded('categories') && $tag->categories->isNotEmpty()) {
            return $tag->categories->contains('id', $client->category_id);
        }

        // استخدام الكاش
        $this->getAllActiveTags();

        return isset($this->categoryTagsMap[$client->category_id][$tag->id]);
    }

    /**
     * تحقق مما إذا كان التاق يطابق موقع العميل.
     */
    private function tagMatchesClientLocation($tag, $assignment): bool
    {
        $client = $assignment->client;
        if (! $client || ! $client->location_id) {
            return true;
        }

        if ($tag->assign_all_locations) {
            return true;
        }

        // إذا كان للتاق مواقع محددة، يجب أن تحتوي على موقع العميل
        if ($tag->relationLoaded('locations') && $tag->locations->isNotEmpty()) {
            return $tag->locations->contains('id', $client->location_id);
        }

        // استخدام الكاش
        $this->getAllActiveTags();

        return isset($this->locationTagsMap[$client->location_id][$tag->id]);
    }

    public function autoDistribute(Collection $assignments, string $selectedWeek): int
    {
        $assignments = $this->ensureRelationsLoaded($assignments);
        $this->clearTagsCache();

        $clientIds = $assignments->pluck('client_id')->filter()->unique()->toArray();
        $this->preloadClientTagUsageHistory($clientIds, $selectedWeek);

        $weekStart = Carbon::parse($selectedWeek);
        $days = $this->getWeekDays($selectedWeek);

        $count = 0;
        $assignmentsByDesigner = $assignments->groupBy('designer_id');

        foreach ($assignmentsByDesigner as $designerAssignments) {
            $dailyLoad = array_fill_keys($days, 0);
            $assignmentUsage = [];
            $designerBatchTagUsage = [];
            $designerBatchGroupUsage = [];

            $allNewRecords = [];
            $recordSequence = [];

            foreach ($designerAssignments as $assignment) {
                if ($assignment->is_side) {
                    continue;
                }

                $assignmentId = $assignment->id;
                $newRecords = [];
                $weeklySchedule = [];

                // استرجاع أي توزيعات أخرى للعميل في هذا الأسبوع (مثل المهام الجانبية المحولة لمصممين آخرين)
                $sideDistributions = ClientTagDistribution::whereHas('clientDesigner', function ($q) use ($assignment, $selectedWeek) {
                    $q->where('client_id', $assignment->client_id)
                        ->where('week_start_date', $selectedWeek)
                        ->where('id', '!=', $assignment->id);
                })->whereNotIn('status', ['cancelled'])->get();

                $contractDesignsCount = $assignment->contract->weekly_designs_count ?? 0;
                $designsCount = max(0, $contractDesignsCount - $sideDistributions->count());

                if ($designsCount <= 0) {
                    continue;
                }

                $sideDates = $sideDistributions->pluck('distribution_date')
                    ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                    ->toArray();
                foreach ($sideDates as $sDate) {
                    $weeklySchedule[$sDate][] = 99;
                }

                $sideTagIds = $sideDistributions->pluck('tag_id')->toArray();

                $tags = $this->collectTagsForAssignment($assignment);

                if ($tags->isEmpty()) {
                    Log::debug('AutoDistribute: No tags found for client '.$assignment->client->company);

                    continue;
                }

                $tags = $tags->filter(function ($tag) use ($weekStart, $sideTagIds) {
                    if (in_array($tag->id, $sideTagIds)) {
                        return false;
                    }

                    if (! $this->isYearlyTagMatchingWeek($tag, $weekStart)) {
                        return false;
                    }

                    return true;
                });

                if ($tags->isEmpty()) {
                    continue;
                }

                $isSecondary = false;
                $priorityTags = $assignment->client->tags ? $assignment->client->tags->filter(fn ($t) => $t->is_active) : collect();
                $tagHistory = $assignment->client_id ? $this->getClientTagUsageHistory($assignment->client_id, $selectedWeek) : [];

                $hasFlexibleVeryHighInSide = $sideDistributions->contains(function ($d) {
                    return $d->tag
                        && ImportanceLevel::fromString($d->tag->importance ?? '') === ImportanceLevel::VeryHigh
                        && ! ($d->tag->is_there_date_for_sending && $d->tag->date_for_sending_yearly);
                });

                $enableVeryHigh = $assignment->client->enable_very_high && ! $hasFlexibleVeryHighInSide;

                $weekNumber = (int) $weekStart->format('W');
                $clientSeed = ($assignment->client_id ?? 0) * 17 + $weekNumber * 31;

                if (! $isSecondary && $assignment->client->importance_weights) {
                    $tagsList = $this->importanceRatioService->selectWeightedTags(
                        $tags,
                        $assignment->client->importance_weights,
                        $enableVeryHigh,
                        $designsCount,
                        $priorityTags,
                        $tagHistory,
                        $clientSeed,
                        $designerBatchTagUsage,
                        $designerBatchGroupUsage
                    );
                } else {
                    $priorityTagIds = $priorityTags->pluck('id')->toArray();
                    $tagsList = $this->sortTagsByHistoryAndDiversity(
                        $tags,
                        $priorityTagIds,
                        $tagHistory,
                        [],
                        $clientSeed,
                        $designerBatchTagUsage,
                        $designerBatchGroupUsage
                    );
                }

                $tagsList = $tagsList->values();
                $tagsCount = $tagsList->count();

                $distributedCount = 0;
                $tagIndex = 0;
                $hasDistributedVeryHigh = $hasFlexibleVeryHighInSide;

                $pendingFlexibleTags = [];

                while ($distributedCount < $designsCount) {
                    if ($tagIndex > max($tagsCount, $designsCount) * 10) {
                        break;
                    }

                    $tag = $tagsList->get($tagIndex % $tagsCount);
                    $isVeryHigh = ImportanceLevel::fromString($tag->importance ?? '') === ImportanceLevel::VeryHigh;
                    $isYearlyVeryHigh = $isVeryHigh && $tag->date_for_sending_yearly;
                    $rank = $this->importanceRatioService->getImportanceRank($tag);

                    // التاقات السنوية العالية جداً لا تخضع لشرط "مرة واحدة"
                    if (! $isSecondary && $isVeryHigh && ! $isYearlyVeryHigh && $hasDistributedVeryHigh) {
                        $tagIndex++;

                        continue;
                    }

                    if (! $isSecondary && $tagIndex >= $tagsCount) {
                        $repeatableValues = ['medium', 'متوسطة', 'متوسط', 'low', 'منخفضة', 'منخفض'];
                        $tagImportance = trim(strtolower($tag->importance ?? ''));
                        if (! in_array($tagImportance, $repeatableValues)) {
                            $tagIndex++;

                            continue;
                        }
                    }

                    $tagIndex++;

                    $targetDate = null;
                    if ($tag->is_there_date_for_sending) {
                        foreach ($days as $day) {
                            $carbonDay = Carbon::parse($day);

                            if (! empty($tag->weekly_day)) {
                                $targetDayNames = is_array($tag->weekly_day) ? $tag->weekly_day : [$tag->weekly_day];
                                foreach ($targetDayNames as $targetName) {
                                    $weeklyDay = is_string($targetName) ? trim($targetName) : '';
                                    if ($weeklyDay === '') {
                                        continue;
                                    }
                                    if (
                                        strcasecmp($carbonDay->format('l'), $weeklyDay) === 0 ||
                                        $carbonDay->locale('ar')->translatedFormat('l') === $weeklyDay
                                    ) {
                                        $targetDate = $day;
                                        break 2;
                                    }
                                }
                            }

                            if ($tag->date_for_sending_yearly) {
                                try {
                                    $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                                    if ($carbonDay->month === $tagDate->month && $carbonDay->day === $tagDate->day) {
                                        $targetDate = $day;
                                        break;
                                    }
                                } catch (\Exception $e) {
                                }
                            }
                        }
                    }

                    if ($targetDate) {
                        $newRecords[] = [
                            'client_designer_id' => $assignmentId,
                            'tag_id' => $tag->id,
                            'distribution_date' => $targetDate,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tag, null, $selectedWeek),
                            'status' => 'pending',
                        ];
                        $dailyLoad[$targetDate]++;
                        $weeklySchedule[$targetDate][] = $rank;
                        $designerBatchTagUsage[$tag->id] = ($designerBatchTagUsage[$tag->id] ?? 0) + 1;
                        $grpId = $tag->tag_group_id ?? 0;
                        $designerBatchGroupUsage[$grpId] = ($designerBatchGroupUsage[$grpId] ?? 0) + 1;
                        $count++;
                        $distributedCount++;
                        if ($isVeryHigh && ! $tag->date_for_sending_yearly) {
                            $hasDistributedVeryHigh = true;
                        }
                    } else {
                        $pendingFlexibleTags[] = [
                            'tag' => $tag,
                            'rank' => $rank,
                        ];
                        $distributedCount++;
                        if ($isVeryHigh && ! $tag->date_for_sending_yearly) {
                            $hasDistributedVeryHigh = true;
                        }
                    }
                }

                usort($pendingFlexibleTags, fn ($a, $b) => $a['rank'] <=> $b['rank']);

                foreach ($pendingFlexibleTags as $pendingTag) {
                    $tagModel = $pendingTag['tag'];
                    if (! $tagModel) {
                        continue;
                    }

                    $currentRank = $pendingTag['rank'];
                    $targetDate = null;
                    $clientUsage = $weeklySchedule;

                    $emptyDays = array_diff($days, array_keys($clientUsage));

                    if (! empty($emptyDays)) {
                        $minLoad = PHP_INT_MAX;
                        $bestDays = [];
                        foreach ($emptyDays as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) {
                                $minLoad = $load;
                                $bestDays = [$day];
                            } elseif ($load === $minLoad) {
                                $bestDays[] = $day;
                            }
                        }
                        $targetDate = $bestDays[array_rand($bestDays)];
                    } else {
                        $preferredDays = [];
                        $acceptableDays = [];

                        foreach ($days as $day) {
                            $existingRanks = $clientUsage[$day] ?? [];

                            $hasVeryHigh = in_array(1, $existingRanks);
                            $hasHigh = in_array(2, $existingRanks);

                            if ($currentRank === 1) {
                                if (! $hasVeryHigh && ! $hasHigh) {
                                    $preferredDays[] = $day;
                                } else {
                                    $acceptableDays[] = $day;
                                }
                            } elseif ($currentRank === 2) {
                                if (! $hasVeryHigh) {
                                    $preferredDays[] = $day;
                                } else {
                                    $acceptableDays[] = $day;
                                }
                            } else {
                                if ($hasVeryHigh) {
                                    $preferredDays[] = $day;
                                } else {
                                    $acceptableDays[] = $day;
                                }
                            }
                        }

                        $finalCandidates = ! empty($preferredDays) ? $preferredDays : $acceptableDays;
                        if (empty($finalCandidates)) {
                            $finalCandidates = $days;
                        }

                        $minLoad = PHP_INT_MAX;
                        $bestDays = [];
                        foreach ($finalCandidates as $day) {
                            $load = $dailyLoad[$day];
                            if ($load < $minLoad) {
                                $minLoad = $load;
                                $bestDays = [$day];
                            } elseif ($load === $minLoad) {
                                $bestDays[] = $day;
                            }
                        }
                        $targetDate = $bestDays[array_rand($bestDays)];
                    }

                    $newRecords[] = [
                        'client_designer_id' => $assignmentId,
                        'tag_id' => $tagModel->id,
                        'distribution_date' => $targetDate,
                        'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagModel, null, $selectedWeek),
                        'status' => 'pending',
                    ];

                    $dailyLoad[$targetDate]++;
                    $weeklySchedule[$targetDate][] = $currentRank;
                    $designerBatchTagUsage[$tagModel->id] = ($designerBatchTagUsage[$tagModel->id] ?? 0) + 1;
                    $grpId = $tagModel->tag_group_id ?? 0;
                    $designerBatchGroupUsage[$grpId] = ($designerBatchGroupUsage[$grpId] ?? 0) + 1;
                    $count++;
                }

                $allNewRecords[$assignmentId] = $newRecords;
                $recordSequence[$assignmentId] = true;
            }

            foreach ($allNewRecords as $assignmentId => $records) {
                if (empty($records)) {
                    continue;
                }

                DB::transaction(function () use ($assignmentId, $records) {
                    ClientTagDistribution::where('client_designer_id', $assignmentId)->delete();
                    $userId = auth()->id();
                    $now = now();
                    $formattedRecords = array_map(function ($rec) use ($userId, $now) {
                        return array_merge([
                            'created_by_user' => $userId,
                            'updated_by_user' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $rec);
                    }, $records);
                    ClientTagDistribution::insert($formattedRecords);
                });
            }
        }

        return $count;
    }

    public function autoAssignIdeas(Collection $assignments, string $selectedWeek): int
    {
        return $this->assignIdeasToAssignments($assignments, $selectedWeek);
    }

    public function distributeSmartForDesigner(Collection $assignments, int $designerId, string $selectedWeek): int
    {
        $assignments = $this->ensureRelationsLoaded($assignments);
        $this->clearTagsCache();

        $clientIds = $assignments->where('designer_id', $designerId)->pluck('client_id')->filter()->unique()->toArray();
        $this->preloadClientTagUsageHistory($clientIds, $selectedWeek);

        $totalCount = 0;

        $totalCount += $this->distributeVeryHighTagsForDesigner($assignments, $designerId, $selectedWeek, true);
        $totalCount += $this->distributeHighTagsForDesigner($assignments, $designerId, $selectedWeek, true);
        $totalCount += $this->distributeMediumLowTagsForDesigner($assignments, $designerId, $selectedWeek, true);
        $totalCount += $this->distributeIdeasForDesigner($assignments, $designerId, $selectedWeek);

        return $totalCount;
    }

    public function distributeVeryHighTagsForDesigner(Collection $assignments, int $designerId, string $selectedWeek, bool $skipPreloading = false): int
    {
        if (! $skipPreloading) {
            $assignments = $this->ensureRelationsLoaded($assignments);
        }
        $weekStart = Carbon::parse($selectedWeek);
        $days = $this->getWeekDays($selectedWeek);
        $count = 0;
        $designerAssignments = $assignments->where('designer_id', $designerId);

        if (! $skipPreloading) {
            $clientIds = $designerAssignments->pluck('client_id')->filter()->unique()->toArray();
            $this->preloadClientTagUsageHistory($clientIds, $selectedWeek);
        }

        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = [];

        $existingDistributions = ClientTagDistribution::with('tag')->whereIn(
            'client_designer_id',
            $designerAssignments->pluck('id')
        )->get();

        foreach ($designerAssignments as $assignment) {
            foreach ($existingDistributions->where('client_designer_id', $assignment->id) as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;

                    $rank = $this->importanceRatioService->getImportanceRank($dist->tag);
                    $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                }
            }
        }

        $allClientIds = $designerAssignments->where('is_side', false)->pluck('client_id')->filter()->unique()->toArray();
        $allClientDistributionsInWeek = ClientTagDistribution::with(['tag', 'clientDesigner'])
            ->whereHas('clientDesigner', function ($q) use ($allClientIds, $selectedWeek) {
                $q->whereIn('client_id', $allClientIds)
                    ->where('week_start_date', $selectedWeek);
            })
            ->whereNotIn('status', ['cancelled'])
            ->get();

        DB::transaction(function () use ($designerAssignments, $existingDistributions, $allClientDistributionsInWeek, $weekStart, $days, &$dailyLoad, &$assignmentUsage, $selectedWeek, &$count) {
            $recordsToInsert = [];
            $batchTagUsage = [];
            $batchGroupUsage = [];
            $userId = auth()->id();
            $now = now();

            foreach ($designerAssignments as $assignment) {
                if ($assignment->is_side) {
                    continue;
                }

                $designsCount = $assignment->contract->weekly_designs_count ?? 0;
                $clientDistributions = $allClientDistributionsInWeek->filter(
                    fn ($d) => $d->clientDesigner?->client_id == $assignment->client_id
                );
                $currentCount = $clientDistributions->count();

                if ($currentCount >= $designsCount) {
                    continue;
                }

                if (! $assignment->client->enable_very_high) {
                    continue;
                }

                $tags = $this->collectTagsForAssignment($assignment);

                if ($tags->isEmpty()) {
                    continue;
                }

                $veryHighTags = $tags->filter(function ($tag) use ($weekStart) {
                    $level = ImportanceLevel::fromString($tag->importance ?? '');

                    if ($level !== ImportanceLevel::VeryHigh) {
                        return false;
                    }

                    if (! $this->isYearlyTagMatchingWeek($tag, $weekStart)) {
                        return false;
                    }

                    return true;
                });

                if ($veryHighTags->isEmpty()) {
                    continue;
                }

                $existingTagIdsForAssignment = array_unique(array_merge(
                    $clientDistributions->pluck('tag_id')->toArray(),
                    $existingDistributions->where('client_designer_id', $assignment->id)->pluck('tag_id')->toArray()
                ));

                // تقسيم التاقات إلى سنوية (لها date_for_sending_yearly) ومرنة (بدون)
                $yearlyTags = $veryHighTags->filter(fn ($t) => $t->is_there_date_for_sending && $t->date_for_sending_yearly);
                $flexibleTags = $veryHighTags->filter(fn ($t) => ! ($t->is_there_date_for_sending && $t->date_for_sending_yearly));

                // الحلقة 1: توزيع جميع التاقات السنوية العالية جداً
                foreach ($yearlyTags as $tagToDistribute) {
                    if ($currentCount >= $designsCount) {
                        break;
                    }

                    if (in_array($tagToDistribute->id, $existingTagIdsForAssignment)) {
                        continue;
                    }

                    $excludedDates = array_unique(array_merge(
                        $clientDistributions
                            ->pluck('distribution_date')
                            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                            ->toArray(),
                        array_keys($assignmentUsage[$assignment->id] ?? [])
                    ));

                    $targetDate = $this->findBestDistributionDate(
                        $tagToDistribute,
                        $weekStart,
                        1,
                        6,
                        $dailyLoad,
                        $days,
                        $excludedDates
                    );

                    // إذا لم يجد findBestDistributionDate تاريخاً مناسباً (النافذة خارج أيام العمل)،
                    // نحاول وضع التاق في تاريخه السنوي نفسه إذا كان ضمن أيام العمل
                    if (! $targetDate && $tagToDistribute->date_for_sending_yearly) {
                        try {
                            $yearlyCarbon = Carbon::parse($tagToDistribute->date_for_sending_yearly);
                            // تطبيق السنة الحالية للحصول على تاريخ كامل
                            $yearlyDate = $weekStart->copy()->month($yearlyCarbon->month)->day($yearlyCarbon->day);
                            $yearlyDateStr = $yearlyDate->format('Y-m-d');

                            if (in_array($yearlyDateStr, $days) && ! in_array($yearlyDateStr, $excludedDates)) {
                                $targetDate = $yearlyDateStr;
                            }
                        } catch (\Exception $e) {
                            // تجاهل الخطأ
                        }
                    }

                    if (! $targetDate) {
                        continue;
                    }

                    $recordsToInsert[] = [
                        'client_designer_id' => $assignment->id,
                        'tag_id' => $tagToDistribute->id,
                        'distribution_date' => $targetDate,
                        'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute, null, $selectedWeek),
                        'status' => 'pending',
                        'created_by_user' => $userId,
                        'updated_by_user' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $dailyLoad[$targetDate]++;
                    $assignmentUsage[$assignment->id][$targetDate][] = 1;
                    $batchTagUsage[$tagToDistribute->id] = ($batchTagUsage[$tagToDistribute->id] ?? 0) + 1;
                    $batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] = ($batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] ?? 0) + 1;
                    $count++;
                    $currentCount++;
                    $existingTagIdsForAssignment[] = $tagToDistribute->id;
                }

                // الحلقة 2: توزيع تاق مرن واحد فقط إذا بقيت سعة
                if ($currentCount >= $designsCount) {
                    continue;
                }

                // التحقق مما إذا كان العميل يمتلك بالفعل وسماً مرناً عالي الأهمية جداً في هذا الأسبوع عبر جميع التعيينات
                $alreadyHasFlexibleVeryHigh = $clientDistributions->contains(function ($d) {
                    return $d->tag
                        && ImportanceLevel::fromString($d->tag->importance ?? '') === ImportanceLevel::VeryHigh
                        && ! ($d->tag->is_there_date_for_sending && $d->tag->date_for_sending_yearly);
                });

                if ($alreadyHasFlexibleVeryHigh) {
                    continue;
                }

                $availableFlexible = $flexibleTags->whereNotIn('id', $existingTagIdsForAssignment);

                if ($availableFlexible->isEmpty()) {
                    continue;
                }

                $priorityTagIds = $assignment->client->tags ? $assignment->client->tags->pluck('id')->toArray() : [];
                $tagHistory = $assignment->client_id ? $this->getClientTagUsageHistory($assignment->client_id, $selectedWeek) : [];
                $currentAssignedGroupCounts = $this->getAssignedGroupCounts($existingDistributions->where('client_designer_id', $assignment->id));

                $weekNumber = (int) $weekStart->format('W');
                $clientSeed = ($assignment->client_id ?? 0) * 17 + $weekNumber * 31;

                $sortedFlexible = $this->sortTagsByHistoryAndDiversity(
                    $availableFlexible,
                    $priorityTagIds,
                    $tagHistory,
                    $currentAssignedGroupCounts,
                    $clientSeed,
                    $batchTagUsage,
                    $batchGroupUsage
                );
                $tagToDistribute = $sortedFlexible->first();

                $excludedDates = array_unique(array_merge(
                    $clientDistributions
                        ->pluck('distribution_date')
                        ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                        ->toArray(),
                    $existingDistributions
                        ->where('client_designer_id', $assignment->id)
                        ->pluck('distribution_date')
                        ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                        ->toArray(),
                    array_keys($assignmentUsage[$assignment->id] ?? [])
                ));

                $rank = 1;
                $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                $targetDate = $this->allocateBestDateForTag(
                    $tagToDistribute,
                    $rank,
                    $days,
                    $dailyLoad,
                    $clientUsage,
                    $weekStart,
                    $excludedDates
                );

                if ($targetDate) {
                    $recordsToInsert[] = [
                        'client_designer_id' => $assignment->id,
                        'tag_id' => $tagToDistribute->id,
                        'distribution_date' => $targetDate,
                        'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute, null, $selectedWeek),
                        'status' => 'pending',
                        'created_by_user' => $userId,
                        'updated_by_user' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $dailyLoad[$targetDate]++;
                    $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                    $batchTagUsage[$tagToDistribute->id] = ($batchTagUsage[$tagToDistribute->id] ?? 0) + 1;
                    $batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] = ($batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] ?? 0) + 1;
                    $count++;
                    $currentCount++;
                    $existingTagIdsForAssignment[] = $tagToDistribute->id;
                }
            }

            if (! empty($recordsToInsert)) {
                ClientTagDistribution::insert($recordsToInsert);
            }
        });

        return $count;
    }

    public function distributeHighTagsForDesigner(Collection $assignments, int $designerId, string $selectedWeek, bool $skipPreloading = false): int
    {
        if (! $skipPreloading) {
            $assignments = $this->ensureRelationsLoaded($assignments);
        }
        $weekStart = Carbon::parse($selectedWeek);
        $days = $this->getWeekDays($selectedWeek);
        $count = 0;
        $designerAssignments = $assignments->where('designer_id', $designerId);

        if (! $skipPreloading) {
            $clientIds = $designerAssignments->pluck('client_id')->filter()->unique()->toArray();
            $this->preloadClientTagUsageHistory($clientIds, $selectedWeek);
        }

        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = [];

        $existingDistributions = ClientTagDistribution::with('tag')->whereIn(
            'client_designer_id',
            $designerAssignments->pluck('id')
        )->get();

        foreach ($designerAssignments as $assignment) {
            foreach ($existingDistributions->where('client_designer_id', $assignment->id) as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;

                    $rank = $this->importanceRatioService->getImportanceRank($dist->tag);
                    $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                }
            }
        }

        $allClientIds = $designerAssignments->where('is_side', false)->pluck('client_id')->filter()->unique()->toArray();
        $allClientDistributionsInWeek = ClientTagDistribution::with(['tag', 'clientDesigner'])
            ->whereHas('clientDesigner', function ($q) use ($allClientIds, $selectedWeek) {
                $q->whereIn('client_id', $allClientIds)
                    ->where('week_start_date', $selectedWeek);
            })
            ->whereNotIn('status', ['cancelled'])
            ->get();

        DB::transaction(function () use ($designerAssignments, $existingDistributions, $allClientDistributionsInWeek, $weekStart, $days, &$dailyLoad, &$assignmentUsage, $selectedWeek, &$count) {
            $recordsToInsert = [];
            $batchTagUsage = [];
            $batchGroupUsage = [];
            $userId = auth()->id();
            $now = now();

            foreach ($designerAssignments as $assignment) {
                if ($assignment->is_side) {
                    continue;
                }

                $designsCount = $assignment->contract->weekly_designs_count ?? 0;
                $clientDistributions = $allClientDistributionsInWeek->filter(
                    fn ($d) => $d->clientDesigner?->client_id == $assignment->client_id
                );
                $currentDistributionsCount = $clientDistributions->count();

                if ($currentDistributionsCount >= $designsCount) {
                    continue;
                }

                $tags = $this->collectTagsForAssignment($assignment);

                if ($tags->isEmpty()) {
                    continue;
                }

                $highTags = $tags->filter(function ($tag) use ($weekStart) {
                    $level = ImportanceLevel::fromString($tag->importance ?? '');

                    if ($level !== ImportanceLevel::High) {
                        return false;
                    }

                    if (! $this->isYearlyTagMatchingWeek($tag, $weekStart)) {
                        return false;
                    }

                    return true;
                });

                if ($highTags->isEmpty()) {
                    continue;
                }

                $isSecondary = false;
                $targetHighCount = 1;
                if (! $isSecondary && $assignment->client->importance_weights) {
                    $quota = $this->importanceRatioService->calculateLevelQuota(
                        $assignment->client->importance_weights,
                        (bool) $assignment->client->enable_very_high,
                        $designsCount
                    );
                    $targetHighCount = $quota['high'] ?? 0;
                }

                $existingHighTagIds = array_unique(array_merge(
                    $clientDistributions->pluck('tag_id')->toArray(),
                    $existingDistributions->where('client_designer_id', $assignment->id)->pluck('tag_id')->toArray()
                ));

                $priorityTagIds = $assignment->client->tags ? $assignment->client->tags->pluck('id')->toArray() : [];
                $tagHistory = $assignment->client_id ? $this->getClientTagUsageHistory($assignment->client_id, $selectedWeek) : [];
                $currentAssignedGroupCounts = $this->getAssignedGroupCounts($existingDistributions->where('client_designer_id', $assignment->id));

                $weekNumber = (int) $weekStart->format('W');
                $clientSeed = ($assignment->client_id ?? 0) * 17 + $weekNumber * 31;

                for ($hi = 0; $hi < $targetHighCount; $hi++) {
                    if ($currentDistributionsCount >= $designsCount) {
                        break;
                    }

                    $availableHighTags = $highTags->whereNotIn('id', $existingHighTagIds);

                    if ($availableHighTags->isEmpty()) {
                        break;
                    }

                    $sortedHigh = $this->sortTagsByHistoryAndDiversity(
                        $availableHighTags,
                        $priorityTagIds,
                        $tagHistory,
                        $currentAssignedGroupCounts,
                        $clientSeed,
                        $batchTagUsage,
                        $batchGroupUsage
                    );
                    $tagToDistribute = $sortedHigh->first();

                    $excludedDates = array_unique(array_merge(
                        $clientDistributions
                            ->pluck('distribution_date')
                            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                            ->toArray(),
                        $existingDistributions
                            ->where('client_designer_id', $assignment->id)
                            ->pluck('distribution_date')
                            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                            ->toArray(),
                        array_keys($assignmentUsage[$assignment->id] ?? [])
                    ));

                    $rank = 2;
                    $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                    $targetDate = $this->allocateBestDateForTag(
                        $tagToDistribute,
                        $rank,
                        $days,
                        $dailyLoad,
                        $clientUsage,
                        $weekStart,
                        $excludedDates
                    );

                    if ($targetDate) {
                        $recordsToInsert[] = [
                            'client_designer_id' => $assignment->id,
                            'tag_id' => $tagToDistribute->id,
                            'distribution_date' => $targetDate,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute, null, $selectedWeek),
                            'status' => 'pending',
                            'created_by_user' => $userId,
                            'updated_by_user' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $dailyLoad[$targetDate]++;
                        $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                        $batchTagUsage[$tagToDistribute->id] = ($batchTagUsage[$tagToDistribute->id] ?? 0) + 1;
                        $batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] = ($batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] ?? 0) + 1;
                        $count++;
                        $currentDistributionsCount++;
                        $existingHighTagIds[] = $tagToDistribute->id;
                    }
                }
            }

            if (! empty($recordsToInsert)) {
                ClientTagDistribution::insert($recordsToInsert);
            }
        });

        return $count;
    }

    public function distributeMediumLowTagsForDesigner(Collection $assignments, int $designerId, string $selectedWeek, bool $skipPreloading = false): int
    {
        if (! $skipPreloading) {
            $assignments = $this->ensureRelationsLoaded($assignments);
        }
        $weekStart = Carbon::parse($selectedWeek);
        $days = $this->getWeekDays($selectedWeek);
        $count = 0;
        $designerAssignments = $assignments->where('designer_id', $designerId);

        if (! $skipPreloading) {
            $clientIds = $designerAssignments->pluck('client_id')->filter()->unique()->toArray();
            $this->preloadClientTagUsageHistory($clientIds, $selectedWeek);
        }

        $dailyLoad = array_fill_keys($days, 0);
        $assignmentUsage = [];

        $existingDistributions = ClientTagDistribution::with('tag')->whereIn(
            'client_designer_id',
            $designerAssignments->pluck('id')
        )->get();

        foreach ($designerAssignments as $assignment) {
            foreach ($existingDistributions->where('client_designer_id', $assignment->id) as $dist) {
                if (in_array($dist->distribution_date, $days)) {
                    $dailyLoad[$dist->distribution_date]++;

                    $rank = $this->importanceRatioService->getImportanceRank($dist->tag);
                    $assignmentUsage[$assignment->id][$dist->distribution_date][] = $rank;
                }
            }
        }

        $allClientIds = $designerAssignments->where('is_side', false)->pluck('client_id')->filter()->unique()->toArray();
        $allClientDistributionsInWeek = ClientTagDistribution::with(['tag', 'clientDesigner'])
            ->whereHas('clientDesigner', function ($q) use ($allClientIds, $selectedWeek) {
                $q->whereIn('client_id', $allClientIds)
                    ->where('week_start_date', $selectedWeek);
            })
            ->whereNotIn('status', ['cancelled'])
            ->get();

        DB::transaction(function () use ($designerAssignments, $existingDistributions, $allClientDistributionsInWeek, $weekStart, $days, &$dailyLoad, &$assignmentUsage, $selectedWeek, &$count) {
            $recordsToInsert = [];
            $userId = auth()->id();
            $now = now();
            $batchTagUsage = [];
            $batchGroupUsage = [];

            foreach ($designerAssignments as $assignment) {
                if ($assignment->is_side) {
                    continue;
                }

                $designsCount = $assignment->contract->weekly_designs_count ?? 0;
                $clientDistributions = $allClientDistributionsInWeek->filter(
                    fn ($d) => $d->clientDesigner?->client_id == $assignment->client_id
                );
                $currentDistributionsCount = $clientDistributions->count();

                if ($currentDistributionsCount >= $designsCount) {
                    continue;
                }

                $tags = $this->collectTagsForAssignment($assignment);

                if ($tags->isEmpty()) {
                    continue;
                }

                $mediumLowTags = $tags->filter(function ($tag) use ($weekStart) {
                    $level = ImportanceLevel::fromString($tag->importance ?? '');
                    $isMedium = $level === ImportanceLevel::Medium;
                    $isLow = $level === ImportanceLevel::Low;

                    if (! $isMedium && ! $isLow) {
                        return false;
                    }

                    if (! $this->isYearlyTagMatchingWeek($tag, $weekStart)) {
                        return false;
                    }

                    return true;
                });

                if ($mediumLowTags->isEmpty()) {
                    continue;
                }

                $mediumTags = $mediumLowTags->filter(function ($tag) {
                    return ImportanceLevel::fromString($tag->importance ?? '') === ImportanceLevel::Medium;
                });

                $lowTags = $mediumLowTags->filter(function ($tag) {
                    return ImportanceLevel::fromString($tag->importance ?? '') === ImportanceLevel::Low;
                });

                $isSecondary = false;
                $targetMedium = -1;
                $targetLow = -1;
                if (! $isSecondary && $assignment->client->importance_weights) {
                    $quota = $this->importanceRatioService->calculateLevelQuota(
                        $assignment->client->importance_weights,
                        (bool) $assignment->client->enable_very_high,
                        $designsCount
                    );
                    $targetMedium = $quota['medium'] ?? 0;
                    $targetLow = $quota['low'] ?? 0;
                }

                $distributedMedium = 0;
                $distributedLow = 0;

                $existingTagIdsForAssignment = array_unique(array_merge(
                    $clientDistributions->pluck('tag_id')->toArray(),
                    $existingDistributions->where('client_designer_id', $assignment->id)->pluck('tag_id')->toArray()
                ));

                $priorityTagIds = $assignment->client->tags ? $assignment->client->tags->pluck('id')->toArray() : [];
                $tagHistory = $assignment->client_id ? $this->getClientTagUsageHistory($assignment->client_id, $selectedWeek) : [];
                $currentAssignedGroupCounts = $this->getAssignedGroupCounts($existingDistributions->where('client_designer_id', $assignment->id));

                $weekNumber = (int) $weekStart->format('W');
                $clientSeed = ($assignment->client_id ?? 0) * 17 + $weekNumber * 31;

                $maxIterations = $designsCount * 3;
                $iterations = 0;
                while (true) {
                    if (++$iterations > $maxIterations) {
                        Log::warning("distributeMediumLowTagsForDesigner: max iterations reached for assignment {$assignment->id}");
                        break;
                    }

                    if ($currentDistributionsCount >= $designsCount) {
                        break;
                    }

                    $pickFromPool = null;
                    $rank = 3;

                    if ($targetMedium < 0 || $distributedMedium < $targetMedium) {
                        $candidates = $mediumTags->whereNotIn('id', $existingTagIdsForAssignment);
                        if ($candidates->isNotEmpty()) {
                            $pickFromPool = $this->sortTagsByHistoryAndDiversity(
                                $candidates,
                                $priorityTagIds,
                                $tagHistory,
                                $currentAssignedGroupCounts,
                                $clientSeed,
                                $batchTagUsage,
                                $batchGroupUsage
                            );
                        }
                    }

                    if (! $pickFromPool && ($targetLow < 0 || $distributedLow < $targetLow)) {
                        $candidates = $lowTags->whereNotIn('id', $existingTagIdsForAssignment);
                        if ($candidates->isNotEmpty()) {
                            $pickFromPool = $this->sortTagsByHistoryAndDiversity(
                                $candidates,
                                $priorityTagIds,
                                $tagHistory,
                                $currentAssignedGroupCounts,
                                $clientSeed,
                                $batchTagUsage,
                                $batchGroupUsage
                            );
                            $rank = 4;
                        }
                    }

                    if (! $pickFromPool) {
                        $candidates = $mediumLowTags->whereNotIn('id', $existingTagIdsForAssignment);
                        if ($candidates->isEmpty()) {
                            $candidates = $mediumLowTags;
                        }
                        if ($candidates->isNotEmpty()) {
                            $pickFromPool = $this->sortTagsByHistoryAndDiversity(
                                $candidates,
                                $priorityTagIds,
                                $tagHistory,
                                $currentAssignedGroupCounts,
                                $clientSeed,
                                $batchTagUsage,
                                $batchGroupUsage
                            );
                            $level = ImportanceLevel::fromString($candidates->first()->importance ?? '');
                            $rank = $level === ImportanceLevel::Medium ? 3 : 4;
                        }
                    }

                    if (! $pickFromPool || $pickFromPool->isEmpty()) {
                        break;
                    }

                    $tagToDistribute = $pickFromPool->first();

                    if ($rank === 3) {
                        $distributedMedium++;
                    } elseif ($rank === 4) {
                        $distributedLow++;
                    }

                    $excludedDates = array_unique(array_merge(
                        $clientDistributions
                            ->pluck('distribution_date')
                            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                            ->toArray(),
                        $existingDistributions
                            ->where('client_designer_id', $assignment->id)
                            ->pluck('distribution_date')
                            ->map(fn ($d) => $d instanceof \Carbon\CarbonInterface ? $d->format('Y-m-d') : \Carbon\Carbon::parse($d)->format('Y-m-d'))
                            ->toArray(),
                        array_keys($assignmentUsage[$assignment->id] ?? [])
                    ));

                    $clientUsage = $assignmentUsage[$assignment->id] ?? [];
                    $targetDate = $this->allocateBestDateForTag(
                        $tagToDistribute,
                        $rank,
                        $days,
                        $dailyLoad,
                        $clientUsage,
                        $weekStart,
                        $excludedDates
                    );

                    if ($targetDate) {
                        $recordsToInsert[] = [
                            'client_designer_id' => $assignment->id,
                            'tag_id' => $tagToDistribute->id,
                            'distribution_date' => $targetDate,
                            'scheduled_sending_at' => $this->calculateScheduledSendingAt($targetDate, $tagToDistribute, null, $selectedWeek),
                            'status' => 'pending',
                            'created_by_user' => $userId,
                            'updated_by_user' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $dailyLoad[$targetDate]++;
                        $assignmentUsage[$assignment->id][$targetDate][] = $rank;
                        $batchTagUsage[$tagToDistribute->id] = ($batchTagUsage[$tagToDistribute->id] ?? 0) + 1;
                        $batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] = ($batchGroupUsage[$tagToDistribute->tag_group_id ?? 0] ?? 0) + 1;
                        $count++;
                        $currentDistributionsCount++;
                        $existingTagIdsForAssignment[] = $tagToDistribute->id;
                    } else {
                        break;
                    }
                }
            }

            if (! empty($recordsToInsert)) {
                ClientTagDistribution::insert($recordsToInsert);
            }
        });

        return $count;
    }

    public function assignIdeasToAssignments(Collection $assignments, string $selectedWeek): int
    {
        if ($assignments->isEmpty()) {
            return 0;
        }

        $assignedCount = 0;
        $weekStartDate = Carbon::parse($selectedWeek)->format('Y-m-d');
        $weekMaxDate = Carbon::parse($selectedWeek)->addDays(6)->format('Y-m-d');
        $clientIds = $assignments->pluck('client_id')->filter()->unique()->toArray();
        $assignmentIds = $assignments->pluck('id')->toArray();

        // 1. Fetch candidate ideas in a single query
        $candidateIdeas = Idea::with(['tags:id', 'blockedClients:id', 'clients:id', 'locations:id'])
            ->where('is_visible_in_generator', true)
            ->where(function ($query) use ($weekStartDate, $weekMaxDate) {
                $query->whereNull('scheduled_at')
                    ->orWhere(function ($sub) use ($weekStartDate, $weekMaxDate) {
                        $sub->whereDate('scheduled_at', '>=', $weekStartDate)
                            ->whereDate('scheduled_at', '<=', $weekMaxDate);
                    });
            })
            ->get();

        // 2. Fetch existing used idea IDs for these clients in this week
        $usedIdeasRows = ClientTagDistribution::join('client_designer', 'client_designer.id', '=', 'client_tag_distributions.client_designer_id')
            ->whereIn('client_designer.client_id', $clientIds)
            ->where('client_designer.week_start_date', $selectedWeek)
            ->whereNotNull('client_tag_distributions.idea_id')
            ->select('client_designer.client_id', 'client_tag_distributions.idea_id')
            ->get();

        $usedIdeaIdsByClient = [];
        foreach ($usedIdeasRows as $row) {
            $usedIdeaIdsByClient[$row->client_id][] = (int) $row->idea_id;
        }

        // 3. Fetch distributions needing ideas
        $distributions = ClientTagDistribution::with('tag')
            ->whereIn('client_designer_id', $assignmentIds)
            ->whereNull('idea_id')
            ->whereNotIn('status', ['sending', 'completed'])
            ->orderBy('distribution_date', 'desc')
            ->get();

        $assignmentClientMap = $assignments->pluck('client_id', 'id')->toArray();
        $assignmentLocationMap = $assignments->mapWithKeys(function ($a) {
            return [$a->id => $a->client?->location_id];
        })->toArray();
        $distributionsToUpdate = [];

        foreach ($distributions as $distribution) {
            $clientId = $assignmentClientMap[$distribution->client_designer_id] ?? null;
            if (! $clientId) {
                continue;
            }

            $tagId = $distribution->tag_id;
            $distDate = $distribution->distribution_date instanceof \Carbon\CarbonInterface
                ? $distribution->distribution_date->format('Y-m-d')
                : (string) $distribution->distribution_date;

            $usedForClient = $usedIdeaIdsByClient[$clientId] ?? [];
            $clientLocationId = $assignmentLocationMap[$distribution->client_designer_id] ?? null;

            // In-memory filter candidate ideas
            $validIdeas = $candidateIdeas->filter(function ($idea) use ($tagId, $clientId, $clientLocationId, $distDate, $weekMaxDate, $usedForClient) {
                if (in_array($idea->id, $usedForClient)) {
                    return false;
                }

                if (! $idea->tags->contains('id', $tagId)) {
                    return false;
                }

                if ($idea->blockedClients->contains('id', $clientId)) {
                    return false;
                }

                // إذا كانت الفكرة مخصصة لعملاء محددين، يجب ألا تُسند إلا لهم
                if ($idea->clients->isNotEmpty() && ! $idea->clients->contains('id', $clientId)) {
                    return false;
                }

                // إذا كانت الفكرة مخصصة لمواقع محددة، يجب ألا تُسند إلا لعميل يقع في ذلك الموقع
                if ($idea->locations->isNotEmpty() && $clientLocationId && ! $idea->locations->contains('id', $clientLocationId)) {
                    return false;
                }

                if ($idea->scheduled_at) {
                    $schedDate = Carbon::parse($idea->scheduled_at)->format('Y-m-d');
                    if ($schedDate < $distDate || $schedDate > $weekMaxDate) {
                        return false;
                    }
                }

                return true;
            });

            if ($validIdeas->isNotEmpty()) {
                $preferredScheduled = $validIdeas->whereNotNull('scheduled_at')
                    ->filter(fn ($i) => Carbon::parse($i->scheduled_at)->format('Y-m-d') > $distDate)
                    ->sortBy('scheduled_at');

                $fallbackScheduled = $validIdeas->whereNotNull('scheduled_at')
                    ->filter(fn ($i) => Carbon::parse($i->scheduled_at)->format('Y-m-d') === $distDate);

                $flexible = $validIdeas->whereNull('scheduled_at');

                if ($preferredScheduled->isNotEmpty()) {
                    $selectedIdea = $preferredScheduled->first();
                } elseif ($fallbackScheduled->isNotEmpty()) {
                    $selectedIdea = $fallbackScheduled->first();
                } else {
                    $selectedIdea = $flexible->random();
                }

                $distributionsToUpdate[] = [
                    'id' => $distribution->id,
                    'idea_id' => $selectedIdea->id,
                    'scheduled_sending_at' => $this->calculateScheduledSendingAt($distDate, $distribution->tag ?? $distribution->tag_id, $selectedIdea, $selectedWeek),
                ];

                $usedIdeaIdsByClient[$clientId][] = $selectedIdea->id;
                $assignedCount++;
            }
        }

        if (! empty($distributionsToUpdate)) {
            DB::transaction(function () use ($distributionsToUpdate) {
                foreach ($distributionsToUpdate as $item) {
                    ClientTagDistribution::where('id', $item['id'])->update([
                        'idea_id' => $item['idea_id'],
                        'scheduled_sending_at' => $item['scheduled_sending_at'],
                    ]);
                }
            });
        }

        return $assignedCount;
    }

    public function distributeIdeasForDesigner(Collection $assignments, int $designerId, string $selectedWeek): int
    {
        $assignments = $this->ensureRelationsLoaded($assignments);
        $designerAssignments = $assignments->where('designer_id', $designerId);

        return $this->assignIdeasToAssignments($designerAssignments, $selectedWeek);
    }

    public function clearTags(Collection $assignments): int
    {
        $count = 0;
        $weekStartDate = null;

        foreach ($assignments as $assignment) {
            $weekStartDate = $assignment->week_start_date ?? $weekStartDate;
            $deleted = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotIn('status', ['sending', 'completed'])
                ->delete();
            $count += $deleted;
        }

        if ($weekStartDate) {
            $this->cleanupEmptyDuplicateAssignments($weekStartDate);
        }

        return $count;
    }

    public function clearClientTags(int $assignmentId): void
    {
        $assignment = ClientDesigner::find($assignmentId);
        $weekStartDate = $assignment?->week_start_date;

        ClientTagDistribution::where('client_designer_id', $assignmentId)
            ->whereNotIn('status', ['sending', 'completed'])
            ->delete();

        if ($weekStartDate) {
            $this->cleanupEmptyDuplicateAssignments($weekStartDate);
        }
    }

    public function clearIdeas(Collection $assignments): int
    {
        $count = 0;
        foreach ($assignments as $assignment) {
            $updated = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotIn('status', ['sending', 'completed'])
                ->whereNotNull('idea_id')
                ->update(['idea_id' => null]);
            $count += $updated;
        }

        return $count;
    }

    public function clearTagsForDesigner(Collection $assignments, int $designerId): int
    {
        $designerAssignments = $assignments->where('designer_id', $designerId);
        $count = 0;
        $weekStartDate = null;

        foreach ($designerAssignments as $assignment) {
            $weekStartDate = $assignment->week_start_date ?? $weekStartDate;
            $deleted = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotIn('status', ['sending', 'completed'])
                ->delete();
            $count += $deleted;
        }

        if ($weekStartDate) {
            $this->cleanupEmptyDuplicateAssignments($weekStartDate);
        }

        return $count;
    }

    /**
     * ينظف التعيينات الجانبية (is_side = true) التي أصبحت فارغة بعد حذف التاقات منها أو نقلها لمصمم آخر.
     * التعيينات الأساسية (is_side = false) محمية تماماً ولا تُحذف أبداً.
     */
    public function cleanupEmptyDuplicateAssignments(string $weekStartDate): int
    {
        return ClientDesigner::where('week_start_date', $weekStartDate)
            ->where('is_side', true)
            ->whereDoesntHave('distributions')
            ->delete();
    }

    public function clearIdeasForDesigner(Collection $assignments, int $designerId): int
    {
        $designerAssignments = $assignments->where('designer_id', $designerId);
        $count = 0;

        foreach ($designerAssignments as $assignment) {
            $updated = ClientTagDistribution::where('client_designer_id', $assignment->id)
                ->whereNotIn('status', ['sending', 'completed'])
                ->whereNotNull('idea_id')
                ->update(['idea_id' => null]);
            $count += $updated;
        }

        return $count;
    }

    public function allocateBestDateForTag(
        Tag $tag,
        int $rank,
        array $days,
        array $dailyLoad,
        array $clientUsage,
        Carbon $weekStart,
        array $excludedDates = []
    ): ?string {
        $targetDate = null;

        if ($tag->is_there_date_for_sending) {
            $targetDate = $this->findBestDistributionDate(
                $tag,
                $weekStart,
                1,
                6,
                $dailyLoad,
                $days,
                $excludedDates
            );
        }

        if (! $targetDate) {
            $availableDays = array_diff($days, $excludedDates);
            $emptyDays = array_diff($availableDays, array_keys($clientUsage));

            if (! empty($emptyDays)) {
                $minLoad = PHP_INT_MAX;
                $bestDays = [];
                foreach ($emptyDays as $day) {
                    $load = $dailyLoad[$day] ?? 0;
                    if ($load < $minLoad) {
                        $minLoad = $load;
                        $bestDays = [$day];
                    } elseif ($load === $minLoad) {
                        $bestDays[] = $day;
                    }
                }
                $targetDate = ! empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
            } else {
                $minClientLoad = PHP_INT_MAX;
                $leastLoadedDaysForClient = [];

                foreach ($days as $day) {
                    $countOnDay = count($clientUsage[$day] ?? []);
                    if ($countOnDay < $minClientLoad) {
                        $minClientLoad = $countOnDay;
                        $leastLoadedDaysForClient = [$day];
                    } elseif ($countOnDay === $minClientLoad) {
                        $leastLoadedDaysForClient[] = $day;
                    }
                }

                $candidateDays = ! empty($leastLoadedDaysForClient) ? $leastLoadedDaysForClient : $days;
                $preferredDays = [];
                $acceptableDays = [];

                foreach ($candidateDays as $day) {
                    $existingRanks = $clientUsage[$day] ?? [];
                    $hasVeryHigh = in_array(1, $existingRanks);
                    $hasHigh = in_array(2, $existingRanks);

                    if ($rank === 1) {
                        if (! $hasVeryHigh && ! $hasHigh) {
                            $preferredDays[] = $day;
                        } else {
                            $acceptableDays[] = $day;
                        }
                    } elseif ($rank === 2) {
                        if (! $hasVeryHigh) {
                            $preferredDays[] = $day;
                        } else {
                            $acceptableDays[] = $day;
                        }
                    } else {
                        if ($hasVeryHigh) {
                            $preferredDays[] = $day;
                        } else {
                            $acceptableDays[] = $day;
                        }
                    }
                }

                $finalCandidates = ! empty($preferredDays) ? $preferredDays : $acceptableDays;
                if (empty($finalCandidates)) {
                    $finalCandidates = $candidateDays;
                }

                $minLoad = PHP_INT_MAX;
                $bestDays = [];
                foreach ($finalCandidates as $day) {
                    $load = $dailyLoad[$day] ?? 0;
                    if ($load < $minLoad) {
                        $minLoad = $load;
                        $bestDays = [$day];
                    } elseif ($load === $minLoad) {
                        $bestDays[] = $day;
                    }
                }
                $targetDate = ! empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
            }
        }

        return $targetDate;
    }

    public function isYearlyTagMatchingWeek($tag, Carbon $weekStart): bool
    {
        if (! $tag->is_there_date_for_sending || ! $tag->date_for_sending_yearly) {
            return true;
        }

        try {
            $tagDate = Carbon::parse($tag->date_for_sending_yearly);
            $tagDateInWeekYear = $weekStart->copy()->month($tagDate->month)->day($tagDate->day);
            $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

            return $tagDateInWeekYear->betweenIncluded($weekStart->startOfDay(), $weekEnd);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function findBestDistributionDate($tag, Carbon $weekStart, int $minLead, int $maxLead, array $dailyLoad, array $workingDays, array $excludedDates = []): ?string
    {
        static $arMap = [
            'Sunday' => 'الأحد',
            'Monday' => 'الإثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
        ];

        $candidateSendingDates = [];
        $checkStart = $weekStart->copy();

        for ($i = 0; $i < 30; $i++) {
            $potentialDate = $checkStart->copy()->addDays($i);
            $isMatch = false;

            if (! empty($tag->weekly_day)) {
                $daysArray = is_array($tag->weekly_day) ? $tag->weekly_day : [$tag->weekly_day];

                $potentialDayName = $potentialDate->format('l');
                $potentialDayNameAr = $arMap[$potentialDayName] ?? '';

                foreach ($daysArray as $d) {
                    $d = trim($d);
                    if (strcasecmp($potentialDayName, $d) === 0 || $potentialDayNameAr === $d) {
                        $isMatch = true;
                        break;
                    }
                }
            }

            if ($tag->date_for_sending_yearly) {
                try {
                    $tagDate = Carbon::parse($tag->date_for_sending_yearly);
                    if ($potentialDate->month === $tagDate->month && $potentialDate->day === $tagDate->day) {
                        $isMatch = true;
                    }
                } catch (\Exception $e) {
                }
            }

            if ($isMatch) {
                $candidateSendingDates[] = $potentialDate;
            }
        }

        $possibleWindowsEncoded = [];

        foreach ($candidateSendingDates as $sDate) {
            $minWindow = $sDate->copy()->subDays($maxLead);
            $maxWindow = $sDate->copy()->subDays($minLead);

            foreach ($workingDays as $wdStr) {
                $wd = Carbon::parse($wdStr);
                if ($wd->betweenIncluded($minWindow, $maxWindow)) {
                    $possibleWindowsEncoded[] = $wdStr;
                }
            }
        }

        $validDates = array_unique($possibleWindowsEncoded);

        $validDates = array_diff($validDates, $excludedDates);

        if (empty($validDates)) {
            return null;
        }

        $minLoadVal = PHP_INT_MAX;
        $bestDays = [];

        foreach ($validDates as $day) {
            $load = $dailyLoad[$day] ?? 0;
            if ($load < $minLoadVal) {
                $minLoadVal = $load;
                $bestDays = [$day];
            } elseif ($load === $minLoadVal) {
                $bestDays[] = $day;
            }
        }

        return ! empty($bestDays) ? $bestDays[array_rand($bestDays)] : null;
    }

    public function calculateScheduledSendingAt($distributionDate, $tagOrId, $ideaOrId, $selectedWeek): ?string
    {
        $idea = $ideaOrId instanceof Idea ? $ideaOrId : ($ideaOrId ? Idea::find($ideaOrId) : null);
        if ($idea && $idea->scheduled_at) {
            return $idea->scheduled_at instanceof \Carbon\CarbonInterface
                ? $idea->scheduled_at->format('Y-m-d H:i:s')
                : (string) $idea->scheduled_at;
        }

        $tag = $tagOrId instanceof Tag ? $tagOrId : ($tagOrId ? Tag::find($tagOrId) : null);
        if (! $tag) {
            return null;
        }

        // 1. التاقات السنوية ذات التاريخ السنوي الثابت
        if ($tag->is_there_date_for_sending && $tag->date_for_sending_yearly) {
            try {
                $weekStart = Carbon::parse($selectedWeek);
                $yearlyCarbon = Carbon::parse($tag->date_for_sending_yearly);
                $yearlyDateInWeekYear = $weekStart->copy()->month($yearlyCarbon->month)->day($yearlyCarbon->day);
                $time = $tag->weekly_time ? Carbon::parse($tag->weekly_time)->format('H:i:s') : '09:00:00';

                return $yearlyDateInWeekYear->format('Y-m-d').' '.$time;
            } catch (\Exception $e) {
                return null;
            }
        }

        // 2. التاقات ذات الأوقات/الأيام الأسبوعية المحددة
        if ($tag->weekly_time) {
            try {
                $baseDate = Carbon::parse($distributionDate);
                $isVeryHigh = ImportanceLevel::fromString($tag->importance ?? '') === ImportanceLevel::VeryHigh;

                $targetDate = null;

                if ($isVeryHigh && ! empty($tag->weekly_day)) {
                    $weekStart = Carbon::parse($selectedWeek);
                    $targetDayNames = is_array($tag->weekly_day) ? $tag->weekly_day : [$tag->weekly_day];

                    for ($i = 0; $i < 7; $i++) {
                        $potentialDate = $weekStart->copy()->addDays($i);
                        $potentialDayName = $potentialDate->translatedFormat('l');
                        $potentialDayNameEn = $potentialDate->format('l');

                        foreach ($targetDayNames as $targetName) {
                            if (strcasecmp($targetName, $potentialDayName) === 0 || strcasecmp($targetName, $potentialDayNameEn) === 0) {
                                $targetDate = $potentialDate;
                                break 2;
                            }
                        }
                    }
                }

                if (! $targetDate) {
                    $targetDate = $baseDate->addDay();
                }

                return $targetDate->format('Y-m-d').' '.Carbon::parse($tag->weekly_time)->format('H:i:s');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public function hasDistributedVeryHigh(Collection $assignments, int $designerId): bool
    {
        $designerAssignments = $assignments->where('designer_id', $designerId);

        foreach ($designerAssignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if ($dist->tag && ImportanceLevel::fromString($dist->tag->importance ?? '') === ImportanceLevel::VeryHigh) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasDistributedHigh(Collection $assignments, int $designerId): bool
    {
        $designerAssignments = $assignments->where('designer_id', $designerId);

        foreach ($designerAssignments as $assignment) {
            foreach ($assignment->distributions as $dist) {
                if ($dist->tag && ImportanceLevel::fromString($dist->tag->importance ?? '') === ImportanceLevel::High) {
                    return true;
                }
            }
        }

        return false;
    }

    public function loadAvailableIdeasForTag(?int $newTagId, ?int $clientId, string $selectedWeek, ?int $newIdeaId): array
    {
        if (! $newTagId) {
            return [];
        }

        $query = Idea::whereHas('tags', function ($q) use ($newTagId) {
            $q->where('tags.id', $newTagId);
        });

        if ($clientId) {
            $client = \App\Models\Client::find($clientId);

            $query->whereDoesntHave('blockedClients', function ($q) use ($clientId) {
                $q->where('client_id', $clientId);
            });

            // الفكرة إما عامة (ليس لها عملاء مخصصون) أو مخصصة لهذا العميل تحديداً
            $query->where(function ($q) use ($clientId) {
                $q->doesntHave('clients')
                    ->orWhereHas('clients', function ($sub) use ($clientId) {
                        $sub->where('clients.id', $clientId);
                    });
            });

            // إذا كان للعميل موقع، الفكرة إما عامة لجميع المواقع أو مخصصة لموقع هذا العميل
            if ($client?->location_id) {
                $locationId = $client->location_id;
                $query->where(function ($q) use ($locationId) {
                    $q->doesntHave('locations')
                        ->orWhereHas('locations', function ($sub) use ($locationId) {
                            $sub->where('locations.id', $locationId);
                        });
                });
            }

            $usedIdeaIds = ClientTagDistribution::whereHas('clientDesigner', function ($q) use ($clientId, $selectedWeek) {
                $q->where('client_id', $clientId)
                    ->where('week_start_date', $selectedWeek);
            })->whereNotNull('idea_id')->pluck('idea_id')->toArray();

            if ($newIdeaId && in_array($newIdeaId, $usedIdeaIds)) {
                $usedIdeaIds = array_diff($usedIdeaIds, [$newIdeaId]);
            }

            if (! empty($usedIdeaIds)) {
                $query->whereNotIn('id', $usedIdeaIds);
            }
        }

        return $query->pluck('name', 'id')->toArray();
    }
}
