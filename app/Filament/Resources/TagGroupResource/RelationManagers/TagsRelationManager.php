<?php

namespace App\Filament\Resources\TagGroupResource\RelationManagers;

use App\Filament\Resources\TagResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * مدير علاقة الوسوم التابعة لمجموعة الوسوم.
 *
 * يتيح عرض وإضافة وتعديل وحذف الوسوم المرتبطة بمجموعة معينة
 * باستخدام نفس نموذج وتجربة مورد الوسوم (TagResource).
 */
class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $title = 'الوسوم المرتبطة';

    protected static ?string $modelLabel = 'وسم';

    protected static ?string $pluralModelLabel = 'الوسوم';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return TagResource::form($form);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الوسم')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                TextColumn::make('importance')
                    ->label('الأهمية')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'veryhigh' => 'danger',
                        'high' => 'warning',
                        'medium' => 'success',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'veryhigh' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                        default => $state ?? '—',
                    }),

                TextColumn::make('categories.name')
                    ->label('التصنيفات')
                    ->badge()
                    ->color('info')
                    ->limitList(3)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('locations.name')
                    ->label('المواقع')
                    ->badge()
                    ->color('primary')
                    ->limitList(3)
                    ->toggleable(),

                TextColumn::make('weekly_day')
                    ->label('أيام الإرسال')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),

                TextColumn::make('weekly_time')
                    ->label('الوقت')
                    ->time('h:i A')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط'),

                Tables\Filters\SelectFilter::make('importance')
                    ->label('الأهمية')
                    ->options([
                        'veryhigh' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver(),
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
