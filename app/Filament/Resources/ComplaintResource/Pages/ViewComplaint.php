<?php

namespace App\Filament\Resources\ComplaintResource\Pages;

use App\Filament\Resources\ComplaintResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewComplaint extends ViewRecord
{
    protected static string $resource = ComplaintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resolve')
                ->label('تم الحل')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                // ->requiresConfirmation()
                // ->modalHeading('تأكيد حل الشكوى')
                // ->modalDescription('هل أنت متأكد من أن هذه الشكوى تم حلها؟')
                ->visible(fn (\App\Models\Complaint $record): bool => $record->status === \App\Filament\Enums\ComplaintStatus::New && (auth()->user()?->can('update', $record) ?? false))
                ->action(function (\App\Models\Complaint $record): void {
                    $record->update([
                        'status' => \App\Filament\Enums\ComplaintStatus::Resolved,
                        'updated_by_user' => auth()->id(),
                        'resolved_by_user' => auth()->id(),
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('تم حل الشكوى بنجاح')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reopen')
                ->label('إعادة فتح')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (\App\Models\Complaint $record): bool => $record->status === \App\Filament\Enums\ComplaintStatus::Resolved && (auth()->user()?->can('update', $record) ?? false))
                ->action(function (\App\Models\Complaint $record): void {
                    $record->update([
                        'status' => \App\Filament\Enums\ComplaintStatus::New,
                        'updated_by_user' => auth()->id(),
                        'resolved_by_user' => null,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('تم إعادة فتح الشكوى بنجاح')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
