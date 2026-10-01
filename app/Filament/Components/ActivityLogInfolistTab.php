<?php

namespace App\Filament\Components;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;

class ActivityLogInfolistTab
{
    public static function make(): Tab
    {
        return Tab::make('سجل التغييرات')
            ->icon('heroicon-o-clock')
            ->schema([
                RepeatableEntry::make('activities')
                    ->label('')
                    ->schema([
                        TextEntry::make('causer.name')
                            ->label('المستخدم')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('description')
                            ->label('الحدث')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'created' => 'إنشاء',
                                'updated' => 'تعديل',
                                'deleted' => 'حذف',
                                default => $state ?? '—',
                            }),
                        TextEntry::make('created_at')
                            ->label('التاريخ')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }
}
