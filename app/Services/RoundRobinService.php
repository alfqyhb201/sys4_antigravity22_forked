<?php

namespace App\Services;

use App\Models\Designer;

class RoundRobinService
{
    /**
     * اختيار المصمم التالي للتوزيع التلقائي بناءً على معايير العدالة:
     * 1. الأقل في عدد الطلبات النشطة حالياً (pending, in_progress).
     * 2. الأقل في إجمالي عدد الطلبات المسندة في اليوم نفسه.
     * 3. المصمم الذي انتظر لأطول فترة منذ آخر طلب أُسند إليه (أو لم يستلم أي طلب سابقاً).
     */
    public function assignNextDesigner(?array $designerIds = null): ?Designer
    {
        $today = now()->toDateString();

        $query = Designer::query()
            ->with('user')
            ->withCount([
                'orders as active_orders_count' => function ($query) {
                    $query->whereIn('status', ['pending', 'in_progress']);
                },
                'orders as today_orders_count' => function ($query) use ($today) {
                    $query->whereDate('created_at', $today);
                },
            ])
            ->withMax('orders as latest_order_at', 'created_at');

        if (! empty($designerIds)) {
            $query->whereIn('id', $designerIds);
        }

        $designers = $query->get();

        if ($designers->isEmpty()) {
            return null;
        }

        $sorted = $designers->sort(function (Designer $a, Designer $b): int {
            // 1. الأولوية للمصمم الأقل في الطلبات النشطة حالياً
            if ($a->active_orders_count !== $b->active_orders_count) {
                return $a->active_orders_count <=> $b->active_orders_count;
            }

            // 2. الأولوية للمصمم صاحب أقل إجمالي طلبات في اليوم نفسه
            if ($a->today_orders_count !== $b->today_orders_count) {
                return $a->today_orders_count <=> $b->today_orders_count;
            }

            // 3. الأولوية للمصمم الذي لم يستلم أي طلب سابقاً، ثم للأقدم في استلام آخر طلب
            $aLast = $a->latest_order_at;
            $bLast = $b->latest_order_at;

            if ($aLast === null && $bLast !== null) {
                return -1;
            }
            if ($aLast !== null && $bLast === null) {
                return 1;
            }
            if ($aLast !== null && $bLast !== null && $aLast !== $bLast) {
                return $aLast <=> $bLast;
            }

            return $a->id <=> $b->id;
        });

        return $sorted->first();
    }
}
