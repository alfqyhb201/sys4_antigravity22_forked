<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTagDistribution;
use Illuminate\Support\Facades\DB;

class ClientLifecycleService
{
    /**
     * إيقاف العميل وتجميد مهام التوزيع غير المكتملة.
     */
    public function suspend(Client $client): void
    {
        DB::transaction(function () use ($client) {
            // 1. تحديث حالة العميل
            $client->updateQuietly([
                'status' => false,
                'suspended_at' => now(),
            ]);

            // 2. تحديث حالة العقد الحالي إذا كان مختلفاً
            $currentContract = $client->currentContract;
            if ($currentContract && $currentContract->status !== 'suspended') {
                $currentContract->updateQuietly(['status' => 'suspended']);
            }

            // 3. تجميد المهام غير المكتملة في جدول التوزيع
            ClientTagDistribution::query()
                ->whereHas('clientDesigner', function ($q) use ($client) {
                    $q->where('client_id', $client->id);
                })
                ->whereIn('status', ['pending', 'in_progress'])
                ->update(['status' => 'frozen']);
        });
    }

    /**
     * استئناف العميل وفك تجميد المهام مع ترحيل تاريخ المهام الفائتة لليوم الحالي.
     */
    public function resume(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $today = now()->toDateString();

            // 1. تحديث حالة العميل
            $client->updateQuietly([
                'status' => true,
                'suspended_at' => null,
            ]);

            // 2. تحديث حالة العقد الحالي إذا كان مختلفاً
            $currentContract = $client->currentContract;
            if ($currentContract && $currentContract->status !== 'active') {
                $currentContract->updateQuietly(['status' => 'active']);
            }

            // 3. فك تجميد المهام وتحديث التاريخ إذا كان قد مضى
            $frozenDistributions = ClientTagDistribution::query()
                ->whereHas('clientDesigner', function ($q) use ($client) {
                    $q->where('client_id', $client->id);
                })
                ->where('status', 'frozen')
                ->get();

            $currentWeekStart = now()->startOfWeek()->toDateString();

            foreach ($frozenDistributions as $dist) {
                $updates = ['status' => 'pending'];

                // إذا كان تاريخ التوزيع السابق في الماضي، نعدله لتاريخ اليوم لمنع احتساب متأخرات زائفة
                if ($dist->distribution_date < $today) {
                    $updates['distribution_date'] = $today;

                    // إذا كان أسبوع المهمة السابق يختلف عن أسبوع اليوم، ننقل ارتباطها لأسبوع اليوم
                    if ($dist->clientDesigner) {
                        $parentWeekStart = $dist->clientDesigner->week_start_date instanceof \Carbon\Carbon
                            ? $dist->clientDesigner->week_start_date->toDateString()
                            : (string) $dist->clientDesigner->week_start_date;

                        if ($parentWeekStart !== $currentWeekStart) {
                            $currentAssignment = \App\Models\ClientDesigner::firstOrCreate([
                                'client_id' => $dist->clientDesigner->client_id,
                                'designer_id' => $dist->clientDesigner->designer_id,
                                'week_start_date' => $currentWeekStart,
                            ], [
                                'contract_id' => $dist->clientDesigner->contract_id,
                            ]);
                            $updates['client_designer_id'] = $currentAssignment->id;
                        }
                    }
                }

                $dist->updateQuietly($updates);
            }
        });
    }
}
