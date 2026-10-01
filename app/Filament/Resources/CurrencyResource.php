<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers\ExchangeRatesRelationManager;
use App\Models\Currency;
use App\Models\CurrencySetting;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * مورد Filament لإدارة العملات (Currencies).
 */
class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'الاعدادات';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'العملات';

    protected static ?string $recordTitleAttribute = 'currency_name';

    protected static ?string $slug = 'currencies';

    protected static ?string $modelLabel = 'عملة';

    protected static ?string $pluralModelLabel = 'عملات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات العملة')
                    ->description('أدخل بيانات العملة التي تريد إضافتها للنظام')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('currency')
                                ->label('رمز العملة')
                                ->placeholder('مثال: USD، YER، SAR')
                                ->required()
                                ->maxLength(10)
                                ->helperText('رمز العملة الدولي المؤلف من 3 أحرف')
                                ->prefix('🏷️')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase; font-weight: bold; letter-spacing: 2px;']),

                            TextInput::make('currency_name')
                                ->label('اسم العملة')
                                ->placeholder('مثال: دولار أمريكي')
                                ->required()
                                ->maxLength(100)
                                ->helperText('الاسم الكامل للعملة بالعربية')
                                ->prefix('🌐'),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('symbol')
                                ->label('الرمز المحلي/الشعار')
                                ->placeholder('مثال: $ ، ﷼')
                                ->maxLength(10),

                            TextInput::make('decimal_places')
                                ->label('عدد المنازل العشرية')
                                ->numeric()
                                ->default(2)
                                ->minValue(0)
                                ->maxValue(6)
                                ->required(),

                            TextInput::make('value')
                                ->label('القيمة مقابل الدولار الأمريكي')
                                ->placeholder('مثال: 530 أو 3.75')
                                ->numeric()
                                ->required()
                                ->step(0.0001)
                                ->minValue(0.0001)
                                ->prefix('1 USD =')
                                ->helperText('كم وحدة من هذه العملة تساوي دولاراً واحداً؟ (قيمة مرجعية احتياطية)'),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('is_base')
                                ->label('العملة الأساسية للنظام')
                                ->helperText('عند تفعيل هذا، ستصبح هذه العملة هي العملة الأساسية لجميع التقارير')
                                ->default(false),

                            Toggle::make('is_active')
                                ->label('مفعلة')
                                ->default(true),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('currency')
                    ->label('رمز العملة')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->weight(\Filament\Support\Enums\FontWeight::ExtraBold)
                    ->color('secondary'),

                Tables\Columns\TextColumn::make('currency_name')
                    ->label('اسم العملة')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Medium)
                    ->icon('heroicon-m-globe-alt'),

                Tables\Columns\TextColumn::make('symbol')
                    ->label('الرمز')
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_base')
                    ->label('العملة الأساسية')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('value')
                    ->label('يعادل مقابل الدولار')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 4, '.', ','), '0'), '.'))
                    ->sortable()
                    ->icon('heroicon-m-arrow-path')
                    ->color(fn ($record) => $record->value == 1 ? 'success' : 'warning')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->defaultSort('value', 'asc')
            ->striped()
            ->filters([
                Tables\Filters\TernaryFilter::make('is_base')
                    ->label('العملة الأساسية'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->actions([
                Tables\Actions\Action::make('setAsBase')
                    ->label('تعيين كـ أساسية')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (Currency $record): bool => ! $record->is_base && (auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('manage_settings')))
                    ->requiresConfirmation()
                    ->action(function (Currency $record) {
                        $record->update(['is_base' => true]);
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Currency $record, Tables\Actions\DeleteAction $action) {
                        if ($record->is_base) {
                            \Filament\Notifications\Notification::make()
                                ->title('لا يمكن حذف العملة الأساسية')
                                ->danger()
                                ->send();
                            $action->cancel();

                            return;
                        }
                        if (CurrencySetting::get('allow_delete_with_transactions', '0') !== '1') {
                            $hasTransactions = \App\Models\Invoice::where('currency_id', $record->id)->exists()
                                || \App\Models\Receipt::where('paid_currency_id', $record->id)->exists()
                                || \App\Models\Contract::where('currency_id', $record->id)->exists();
                            if ($hasTransactions) {
                                \Filament\Notifications\Notification::make()
                                    ->title('لا يمكن حذف هذه العملة')
                                    ->body('هذه العملة مرتبطة بفواتير أو سندات أو عقود. يمكنك تفعيل السماح بالحذف من إعدادات العملات.')
                                    ->danger()
                                    ->send();
                                $action->cancel();
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function (\Illuminate\Database\Eloquent\Collection $records, Tables\Actions\DeleteBulkAction $action) {
                            if (CurrencySetting::get('allow_delete_with_transactions', '0') !== '1') {
                                foreach ($records as $record) {
                                    if ($record->is_base) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('لا يمكن حذف العملة الأساسية: '.$record->currency_name)
                                            ->danger()
                                            ->send();
                                        $action->cancel();

                                        return;
                                    }
                                    $hasTransactions = \App\Models\Invoice::where('currency_id', $record->id)->exists()
                                        || \App\Models\Receipt::where('paid_currency_id', $record->id)->exists();
                                    if ($hasTransactions) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('لا يمكن حذف عملة مرتبطة بمعاملات: '.$record->currency_name)
                                            ->danger()
                                            ->send();
                                        $action->cancel();

                                        return;
                                    }
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ExchangeRatesRelationManager::class,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                UserTrackingSection::make(),
                \Filament\Infolists\Components\Tabs::make('Tabs')
                    ->tabs([
                        ActivityLogInfolistTab::make(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
