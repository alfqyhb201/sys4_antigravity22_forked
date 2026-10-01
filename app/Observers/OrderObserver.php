<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderObserver
{
    public function saved(Order $order): void
    {
        // Only trigger if status changed to Completed
        if ($order->wasChanged('status') && $order->status === OrderStatus::Completed) {
            if ($order->client_id) {
                $contract = $order->client->currentContract;
                if ($contract && $contract->simple_requests_enabled) {
                    $contract->increment('simple_requests_count');
                }
            }
        }
    }

    public function deleted(Order $order): void
    {
        if ($order->status === OrderStatus::Completed) {
            if ($order->client_id) {
                $contract = $order->client->currentContract;
                if ($contract && $contract->simple_requests_enabled) {
                    $contract->decrement('simple_requests_count');
                }
            }
        }
    }
}
