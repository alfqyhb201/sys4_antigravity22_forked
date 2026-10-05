<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Pages\ClientFinancialDetail;
use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'تفاصيل العميل';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resetClicheCounter')
                ->label('تصفير عداد الكليشة')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('تصفير عداد الكليشة')
                ->modalDescription('سيتم إعادة عداد التصاميم إلى 0. هل أنت متأكد؟')
                ->action(function (): void {
                    $this->record->update(['cliche_counter' => 0]);

                    Notification::make()
                        ->title('تم تصفير عداد الكليشة بنجاح')
                        ->success()
                        ->send();
                })
                ->visible(fn (): bool => $this->record->cliche_counter > 0 && (auth()->user()?->can('update', $this->record) ?? false)),

            Actions\Action::make('openWhatsApp')
                ->label('مراسلة واتساب')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->url(function (): ?string {
                    $number = preg_replace('/[^0-9]/', '', (string) $this->record->contact_number);
                    if (empty($number)) {
                        return null;
                    }

                    if (strlen($number) === 9 && str_starts_with($number, '7')) {
                        $number = '967'.$number;
                    }

                    return "https://wa.me/{$number}";
                })
                ->openUrlInNewTab()
                ->visible(fn (): bool => ! empty($this->record->contact_number)),

            Actions\Action::make('financialStatement')
                ->label('كشف الحساب المالي')
                ->icon('heroicon-o-credit-card')
                ->color('info')
                ->url(fn (): string => ClientFinancialDetail::getUrl(['client' => $this->record]))
                ->visible(fn (): bool => ClientFinancialDetail::canAccess()),

            Actions\EditAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        $status = $this->record->activity_status;

        return match ($status) {
            'active' => '🟢 العميل نشط وسليم',
            'pending_arrears' => '🟡 متأخرات سداد (بانتظار التحصيل)',
            'auto_suspended' => '🔴 موقّف تلقائياً (تجاوز مهلة السداد)',
            'manually_suspended' => '🔴 موقّف إدارياً',
            'suspended' => '🟡 اشتراك موقّف',
            'expired' => '🔴 اشتراك منتهي',
            default => '⚪ غير محدد',
        };
    }
}
