<?php

namespace App\Filament\Resources\CurrencyResource\RelationManagers;

use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ExchangeRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'exchangeRatesFrom';

    protected static ?string $title = 'أسعار الصرف التاريخية';

    protected static ?string $modelLabel = 'سعر صرف';

    protected static ?string $pluralModelLabel = 'أسعار الصرف';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('to_currency_id')
                    ->label('إلى العملة')
                    ->options(fn ($livewire) => Currency::where('id', '!=', $livewire->ownerRecord->id)->pluck('currency_name', 'id'))
                    ->required()
                    ->searchable(),

                Forms\Components\TextInput::make('rate')
                    ->label('سعر الصرف')
                    ->numeric()
                    ->required()
                    ->step(0.000001)
                    ->minValue(0.000001)
                    ->helperText('كم تساوي وحدة واحدة من عملة المبدأ بالعملة المستهدفة'),

                Forms\Components\DatePicker::make('effective_date')
                    ->label('تاريخ النفاذ')
                    ->default(now())
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rate')
            ->columns([
                Tables\Columns\TextColumn::make('toCurrency.currency_name')
                    ->label('إلى العملة')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('rate')
                    ->label('سعر الصرف')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label('تاريخ النفاذ')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30),
            ])
            ->defaultSort('effective_date', 'desc')
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
