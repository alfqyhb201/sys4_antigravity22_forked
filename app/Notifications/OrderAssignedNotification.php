<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderAssignedNotification extends Notification
{
    use Queueable;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'message' => '📋 تم إسناد طلب تصميم جديد من: '.$this->order->client_name,
            'order_id' => $this->order->id,
            'client_name' => $this->order->client_name,
            'url' => '/admin/designer-dashboard',
        ];
    }
}
