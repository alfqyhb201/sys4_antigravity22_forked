<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderRevisionRequested
{
    use Dispatchable, SerializesModels;

    public int $orderId;

    public string $clientName;

    public int $designerId;

    public function __construct(Order $order)
    {
        $this->orderId = $order->id;
        $this->clientName = $order->client_name;
        $this->designerId = $order->designer_id;
    }
}
