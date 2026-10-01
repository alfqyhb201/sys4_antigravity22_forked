<?php

namespace App\Listeners;

use App\Events\OrderRevisionRequested;

class SendOrderRevisionNotification
{
    public function handle(OrderRevisionRequested $event): void
    {
        $designer = \App\Models\Designer::with('user')->find($event->designerId);

        if ($designer && $designer->user) {
            $notification = \Filament\Notifications\Notification::make()
                ->title('طلب تعديل على الطلب 📋')
                ->body("تم طلب تعديل على طلب العميل: {$event->clientName}")
                ->icon('heroicon-o-arrow-path')
                ->iconColor('warning')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('عرض لوحة التحكم')
                        ->url('/admin/designer-dashboard'),
                ]);

            $notification->sendToDatabase($designer->user, isEventDispatched: true);
        }
    }
}
