<?php

namespace App\Listeners;

use App\Events\OrderAssigned;

class SendOrderAssignedNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderAssigned $event): void
    {
        $designer = \App\Models\Designer::with('user')->find($event->designerId);

        if ($designer && $designer->user) {
            $notification = \Filament\Notifications\Notification::make()
                ->title('طلب تصميم جديد 📋')
                ->body("تم إسناد طلب جديد لك من العميل: {$event->clientName}")
                ->icon('heroicon-o-shopping-cart')
                ->iconColor('success')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('عرض لوحة التحكم')
                        ->url('/admin/designer-dashboard'),
                ]);

            $notification->sendToDatabase($designer->user, isEventDispatched: true);
        }
    }
}
