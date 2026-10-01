<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'المالية';

    protected static ?string $navigationLabel = 'إدارة الاشتراكات';

    protected static ?string $pluralLabel = 'الاشتراكات';

    protected static ?string $modelLabel = 'اشتراك';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_contract');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view_contract');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_contract');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update_contract');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete_contract');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete_contract');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('بيانات التعاقد والمدة الزمنية')
                            ->description('تحديد العميل ونوع الدفع ودورة الفوترة وتواريخ السريان')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('العميل')
                                    ->relationship('client', 'company')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('payment_type')
                                    ->label('نوع الدفع')
                                    ->options([
                                        'advance' => 'مقدم',
                                        'deferred' => 'مؤخر',
                                    ])
                                    ->default('advance')
                                    ->required(),
                                Forms\Components\Select::make('billing_cycle')
                                    ->label('دورة الفوترة')
                                    ->options([
                                        'weekly' => 'أسبوعي',
                                        'monthly' => 'شهري',
                                        'yearly' => 'سنوي',
                                    ])
                                    ->default('monthly')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        $startDate = $get('start_date');
                                        if ($startDate) {
                                            $set('end_date', \App\Models\Contract::calculateEndDate($startDate, $state)->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('تاريخ البدء')
                                    ->default(now())
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($state) {
                                            $billingCycle = $get('billing_cycle') ?? 'monthly';
                                            $set('end_date', \App\Models\Contract::calculateEndDate($state, $billingCycle)->format('Y-m-d'));
                                        }
                                    }),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('تاريخ الانتهاء')
                                    ->default(now()->addMonth())
                                    ->required(),
                            ]),

                        Forms\Components\Section::make('الخطة المالية وحصص التصاميم')
                            ->description('القيمة المالية للاشتراك وعدد التصاميم المسموح بها')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('المبلغ الإجمالي للمشروع')
                                    ->numeric()
                                    ->required()
                                    ->suffix(fn ($get) => \App\Models\Currency::find($get('currency_id'))?->currency ?? 'YER'),
                                Forms\Components\Select::make('currency_id')
                                    ->label('العملة')
                                    ->options(fn () => \App\Models\Currency::pluck('currency_name', 'id'))
                                    ->default(fn () => \App\Models\Currency::first()?->id)
                                    ->required()
                                    ->live(),
                                Forms\Components\TextInput::make('marketing_amount')
                                    ->label('مبلغ التسويق المخصص')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('weekly_designs_count')
                                    ->label('عدد التصاميم الأسبوعية')
                                    ->numeric()
                                    ->default(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set, $get) {
                                        if ($get('billing_cycle') === 'monthly') {
                                            $set('monthly_designs_count', \App\Models\Contract::calculateMonthlyDesignsCount((int) $state));
                                        }
                                    }),
                                Forms\Components\TextInput::make('monthly_designs_count')
                                    ->label('عدد التصاميم الشهرية')
                                    ->numeric()
                                    ->readonly()
                                    ->default(4),
                            ]),

                        Forms\Components\Section::make('الخدمات والطلبات الإضافية')
                            ->description('تحديد أسعار وشروط الخدمات خارج باقة الاشتراك')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Fieldset::make('التصاميم الإضافية')
                                    ->schema([
                                        Forms\Components\Toggle::make('additional_designs_enabled')
                                            ->label('تمكين التصاميم الإضافية')
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\TextInput::make('additional_design_price')
                                            ->label('سعر التصميم الإضافي')
                                            ->numeric()
                                            ->visible(fn ($get) => (bool) $get('additional_designs_enabled')),
                                    ])
                                    ->columns(2),
                                Forms\Components\Fieldset::make('الطلبات البسيطة')
                                    ->schema([
                                        Forms\Components\Toggle::make('simple_requests_enabled')
                                            ->label('تمكين الطلبات البسيطة')
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\TextInput::make('simple_request_price')
                                            ->label('سعر الطلب البسيط الواحد')
                                            ->numeric()
                                            ->visible(fn ($get) => (bool) $get('simple_requests_enabled')),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('حالة الاشتراك والتجديد')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('حالة الاشتراك')
                                    ->options([
                                        'active' => 'نشط',
                                        'suspended' => 'موقف يدوياً',
                                        'expired' => 'منتهي',
                                        'renewed' => 'مجدد',
                                    ])
                                    ->default('active')
                                    ->required(),
                                Forms\Components\Toggle::make('auto_renewal')
                                    ->label('تجديد تلقائي عند الانتهاء')
                                    ->default(true),
                            ]),

                        Forms\Components\Section::make('المتابعة والإشعار')
                            ->description('خيارات التوقيف التلقائي والمتابعة القانونية')
                            ->schema([
                                Forms\Components\Toggle::make('auto_suspension_enabled')
                                    ->label('تفعيل التوقيف التلقائي')
                                    ->default(false)
                                    ->live(),
                                Forms\Components\TextInput::make('suspension_period_days')
                                    ->label('مدة التوقيف بعد الاستحقاق (أيام)')
                                    ->numeric()
                                    ->default(3)
                                    ->visible(fn ($get) => (bool) $get('auto_suspension_enabled')),
                                Forms\Components\Toggle::make('is_under_lawsuit')
                                    ->label('تم إشعاره')
                                    ->default(false),
                                Forms\Components\Textarea::make('legal_notes')
                                    ->label('ملاحظات كيفية مقاضاته')
                                    ->rows(3),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('حالة الاشتراك')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'expired' => 'danger',
                        'renewed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'suspended' => 'موقف يدوياً',
                        'expired' => 'منتهي',
                        'renewed' => 'مجدد',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('نوع الدفع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'advance' => 'مقدم',
                        'deferred' => 'مؤخر',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label('دورة الفوترة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
                        'yearly' => 'سنوي',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('تاريخ البدء')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('تاريخ الانتهاء')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('قيمة الاشتراك')
                    ->formatStateUsing(fn ($state, Contract $record) => number_format($state).' '.($record->currency?->currency ?? 'YER'))
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_renewal')
                    ->label('تجديد تلقائي')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_under_lawsuit')
                    ->label('تم إشعاره')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('حالة الاشتراك')
                    ->options([
                        'active' => 'نشط',
                        'suspended' => 'موقف يدوياً',
                        'expired' => 'منتهي',
                        'renewed' => 'مجدد',
                    ]),
                Tables\Filters\SelectFilter::make('payment_type')
                    ->label('نوع الدفع')
                    ->options([
                        'advance' => 'مقدم',
                        'deferred' => 'مؤخر',
                    ]),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label('دورة الفوترة')
                    ->options([
                        'weekly' => 'أسبوعي',
                        'monthly' => 'شهري',
                        'yearly' => 'سنوي',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
