<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Enums\DesignTaskStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class AdditionalDesignTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'additionalDesignTasks';

    protected static ?string $title = 'التصاميم الإضافية';

    protected static ?string $label = 'تصميم إضافي';

    protected static ?string $pluralLabel = 'التصاميم الإضافية';

    protected static ?string $icon = 'heroicon-o-swatch';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('designer.user.name')
                    ->label('المصمم')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user'),
                Tables\Columns\TextColumn::make('display_client_name')
                    ->label('العميل')
                    ->icon('heroicon-m-building-office'),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->wrap()
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (DesignTaskStatus $state): string => match ($state) {
                        DesignTaskStatus::Approved => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('YER')
                    ->placeholder('-'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
