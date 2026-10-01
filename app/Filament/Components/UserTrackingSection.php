<?php

namespace App\Filament\Components;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class UserTrackingSection
{
    public static function make(): Section
    {
        return Section::make('معلومات التتبع')
            ->schema([
                TextEntry::make('createdBy.name')
                    ->label('أضيف بواسطة')
                    ->icon('heroicon-o-user-plus'),
                TextEntry::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->icon('heroicon-o-clock'),
                TextEntry::make('updatedBy.name')
                    ->label('آخر تحديث بواسطة')
                    ->icon('heroicon-o-pencil-square')
                    ->placeholder('لا يوجد'),
                TextEntry::make('updated_at')
                    ->label('آخر تحديث')
                    ->since(),
            ])
            ->columns(2)
            ->collapsible();
    }
}
