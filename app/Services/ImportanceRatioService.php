<?php

namespace App\Services;

use App\Enums\ImportanceLevel;
use App\Models\Tag;
use Illuminate\Support\Collection;

class ImportanceRatioService
{
    public function getImportanceRank(Tag $tag): int
    {
        return ImportanceLevel::fromString($tag->importance ?? '')->rank();
    }

    public function calculateLevelQuota(array $weights, bool $enableVeryHigh, int $designsCount): array
    {
        $quota = ['very_high' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        if ($enableVeryHigh) {
            $quota['very_high'] = min(1, $designsCount);
        }

        $remaining = $designsCount - $quota['very_high'];

        if ($remaining <= 0) {
            return $quota;
        }

        $levels = ['high', 'medium', 'low'];
        $activeWeights = [];

        foreach ($levels as $level) {
            $w = (int) ($weights[$level] ?? 0);
            if ($w > 0) {
                $activeWeights[$level] = $w;
            }
        }

        $totalWeight = array_sum($activeWeights);

        if ($totalWeight <= 0) {
            $perLevel = (int) floor($remaining / count($levels));
            $remainder = $remaining - ($perLevel * count($levels));
            foreach ($levels as $i => $level) {
                $quota[$level] = $perLevel + ($i < $remainder ? 1 : 0);
            }

            return $quota;
        }

        $distributed = 0;
        foreach ($activeWeights as $level => $weight) {
            $count = (int) floor($remaining * $weight / $totalWeight);
            $quota[$level] = $count;
            $distributed += $count;
        }

        $remainder = $remaining - $distributed;
        if ($remainder > 0) {
            arsort($activeWeights);
            $i = 0;
            foreach ($activeWeights as $level => $w) {
                if ($i >= $remainder) {
                    break;
                }
                $quota[$level]++;
                $i++;
            }
        }

        return $quota;
    }

    public function selectWeightedTags(
        Collection $tags,
        array $weights,
        bool $enableVeryHigh,
        int $designsCount,
        ?Collection $priorityTags = null,
        array $tagHistory = [],
        int $clientSeed = 0,
        array $batchTagUsage = [],
        array $batchGroupUsage = []
    ): Collection {
        return $this->selectBalancedWeightedTags(
            $tags,
            $weights,
            $enableVeryHigh,
            $designsCount,
            $priorityTags,
            $tagHistory,
            $clientSeed,
            $batchTagUsage,
            $batchGroupUsage
        );
    }

    public function selectBalancedWeightedTags(
        Collection $tags,
        array $weights,
        bool $enableVeryHigh,
        int $designsCount,
        ?Collection $priorityTags = null,
        array $tagHistory = [],
        int $clientSeed = 0,
        array $batchTagUsage = [],
        array $batchGroupUsage = []
    ): Collection {
        $quota = $this->calculateLevelQuota($weights, $enableVeryHigh, $designsCount);
        $priorityIds = $priorityTags ? $priorityTags->pluck('id')->toArray() : [];

        $byLevel = [
            'very_high' => collect(),
            'high' => collect(),
            'medium' => collect(),
            'low' => collect(),
            'other' => collect(),
        ];

        foreach ($tags as $tag) {
            $level = match ($this->getImportanceRank($tag)) {
                1 => 'very_high',
                2 => 'high',
                3 => 'medium',
                4 => 'low',
                default => 'other',
            };
            $byLevel[$level]->push($tag);
        }

        $selected = collect();
        $groupUsageCounts = [];

        foreach (['very_high', 'high', 'medium', 'low'] as $level) {
            $needed = $quota[$level] ?? 0;
            if ($needed <= 0) {
                $byLevel['other'] = $byLevel['other']->merge($byLevel[$level]);

                continue;
            }

            $pool = $byLevel[$level];
            if ($pool->isEmpty()) {
                continue;
            }

            $poolByGroup = $pool->groupBy(fn ($t) => $t->tag_group_id ?? 0);

            $sortedGroups = [];
            foreach ($poolByGroup as $groupId => $groupTags) {
                $sorted = $groupTags->sort(function ($a, $b) use ($priorityIds, $tagHistory, $batchTagUsage, $clientSeed) {
                    $isPriA = in_array($a->id, $priorityIds) ? 0 : 1;
                    $isPriB = in_array($b->id, $priorityIds) ? 0 : 1;
                    if ($isPriA !== $isPriB) {
                        return $isPriA <=> $isPriB;
                    }

                    // Prefer tags with lower batch usage across the designer's assignments this run
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

                $sortedGroups[$groupId] = $sorted;
            }

            $levelSelected = collect();
            $takeCount = min($needed, $pool->count());

            // 1. First Pass: Select priority tags in this level first (up to takeCount)
            foreach ($sortedGroups as $groupId => $groupTags) {
                if ($levelSelected->count() >= $takeCount) {
                    break;
                }

                while ($groupTags->isNotEmpty()) {
                    if ($levelSelected->count() >= $takeCount) {
                        break;
                    }

                    $firstTag = $groupTags->first();
                    if (in_array($firstTag->id, $priorityIds)) {
                        $tag = $groupTags->shift();
                        $levelSelected->push($tag);
                        $groupUsageCounts[$groupId] = ($groupUsageCounts[$groupId] ?? 0) + 1;
                    } else {
                        break;
                    }
                }
            }

            // 2. Second Pass: Round-robin across remaining groups for non-priority slots
            $groupIds = array_keys($sortedGroups);
            if ($clientSeed > 0 && count($groupIds) > 1) {
                $offset = abs($clientSeed) % count($groupIds);
                $groupIds = array_merge(array_slice($groupIds, $offset), array_slice($groupIds, 0, $offset));
            }

            $exhaustedGroups = [];

            while ($levelSelected->count() < $takeCount && count($exhaustedGroups) < count($groupIds)) {
                usort($groupIds, function ($gA, $gB) use ($groupUsageCounts, $batchGroupUsage, $clientSeed) {
                    $countA = ($groupUsageCounts[$gA] ?? 0) * 100 + ($batchGroupUsage[$gA] ?? 0);
                    $countB = ($groupUsageCounts[$gB] ?? 0) * 100 + ($batchGroupUsage[$gB] ?? 0);

                    if ($countA !== $countB) {
                        return $countA <=> $countB;
                    }

                    if ($clientSeed > 0) {
                        $hashA = (int) sprintf('%u', crc32($gA.'_'.$clientSeed));
                        $hashB = (int) sprintf('%u', crc32($gB.'_'.$clientSeed));
                        if ($hashA !== $hashB) {
                            return $hashA <=> $hashB;
                        }
                    }

                    return $gA <=> $gB;
                });

                $pickedInThisRound = false;
                foreach ($groupIds as $gId) {
                    if ($levelSelected->count() >= $takeCount) {
                        break;
                    }
                    if (in_array($gId, $exhaustedGroups)) {
                        continue;
                    }

                    if ($sortedGroups[$gId]->isNotEmpty()) {
                        $tag = $sortedGroups[$gId]->shift();
                        $levelSelected->push($tag);
                        $groupUsageCounts[$gId] = ($groupUsageCounts[$gId] ?? 0) + 1;
                        $pickedInThisRound = true;
                    } else {
                        $exhaustedGroups[] = $gId;
                    }
                }

                if (! $pickedInThisRound) {
                    break;
                }
            }

            $selected = $selected->merge($levelSelected);

            foreach ($sortedGroups as $remainingInGroup) {
                if ($remainingInGroup->isNotEmpty()) {
                    $byLevel['other'] = $byLevel['other']->merge($remainingInGroup);
                }
            }
        }

        $remaining = $designsCount - $selected->count();
        if ($remaining > 0 && $byLevel['other']->isNotEmpty()) {
            $sortedOther = $byLevel['other']->sort(function ($a, $b) use ($priorityIds, $tagHistory, $batchTagUsage, $clientSeed) {
                $isPriA = in_array($a->id, $priorityIds) ? 0 : 1;
                $isPriB = in_array($b->id, $priorityIds) ? 0 : 1;
                if ($isPriA !== $isPriB) {
                    return $isPriA <=> $isPriB;
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

            $selected = $selected->merge($sortedOther->take($remaining));
        }

        if ($selected->count() < $designsCount) {
            $repeatable = $byLevel['medium']->merge($byLevel['low'])->sort(function ($a, $b) use ($tagHistory, $batchTagUsage, $clientSeed) {
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

                if ($clientSeed > 0) {
                    $hashA = (int) sprintf('%u', crc32($a->id.'_'.$clientSeed));
                    $hashB = (int) sprintf('%u', crc32($b->id.'_'.$clientSeed));
                    if ($hashA !== $hashB) {
                        return $hashA <=> $hashB;
                    }
                }

                return $a->id <=> $b->id;
            })->values();

            while ($selected->count() < $designsCount && $repeatable->isNotEmpty()) {
                $selected->push($repeatable->shift());
            }
        }

        if ($selected->count() < $designsCount) {
            $any = $tags->sort(function ($a, $b) use ($tagHistory, $batchTagUsage, $clientSeed) {
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

                if ($clientSeed > 0) {
                    $hashA = (int) sprintf('%u', crc32($a->id.'_'.$clientSeed));
                    $hashB = (int) sprintf('%u', crc32($b->id.'_'.$clientSeed));
                    if ($hashA !== $hashB) {
                        return $hashA <=> $hashB;
                    }
                }

                return $a->id <=> $b->id;
            })->values();

            while ($selected->count() < $designsCount && $any->isNotEmpty()) {
                $selected->push($any->shift());
            }
        }

        return $selected;
    }
}
