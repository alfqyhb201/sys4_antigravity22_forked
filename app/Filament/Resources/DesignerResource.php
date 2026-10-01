<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\DesignerResource\Pages;
use App\Models\Designer;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة المصممين (Designers).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف بيانات المصممين،
 * بما في ذلك معلوماتهم الأساسية، محددات الطاقة الأسبوعية، والتصنيفات المرتبطة بهم.
 */
class DesignerResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Designer::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationGroup = 'المستخدمون';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'المصممين';

    protected static ?string $slug = 'designers';

    protected static ?string $modelLabel = 'مصمم';

    protected static ?string $pluralModelLabel = 'مصممين';

    protected static ?string $recordTitleAttribute = 'user.name';

    public static function getGlobalSearchResultTitle($record): string|Htmlable
    {
        return $record->user->name;
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'التقييم' => $record->rate,
            'الحد الأقصى' => $record->max_capacity,
        ];
    }

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل المصممين.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('بيانات الحساب والوظيفة')
                    ->description('ربط المصمم بحساب مستخدم وتحديد تخصصاته وساعات عمله')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name', modifyQueryUsing: function (Builder $query, $record) {
                                $query->where('status', 1)
                                    ->whereNotIn('id', Designer::query()
                                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->pluck('user_id')
                                        ->toArray());
                            })
                            ->searchable()
                            ->label('المستخدم')
                            ->required(),

                        Select::make('categories')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->preload()
                            ->label('التصنيفات')
                            ->required(),

                        TextInput::make('shift_hours')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->label('ساعات الدوام اليومية')
                            ->suffix(' ساعات')
                            ->default(8),
                    ])->columns(3),

                Section::make('محددات الطاقة والتقييم')
                    ->description('تحديد السعات الأسبوعية ومقاييس الأداء المعتمدة في خوارزمية التوزيع')
                    ->schema([
                        TextInput::make('min_capacity')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->label('الحد الأدنى لعدد التصاميم'),

                        TextInput::make('max_capacity')
                            ->numeric()
                            ->label('الحد الأعلى لعدد التصاميم')
                            ->default(1)
                            ->minValue(1)
                            ->gte('min_capacity')
                            ->validationMessages([
                                'gte' => 'يجب أن يكون حقل :attribute أكبر من أو يساوي :value.',
                            ]),

                        TextInput::make('rate')
                            ->numeric()
                            ->formatStateUsing(fn ($state) => $state !== null ? (int) $state : null)
                            ->minValue(1)
                            ->maxValue(10)
                            ->label('التقييم (من 10) ⭐')
                            ->helperText('مقياس جودة عمل المصمم ويؤثر في ترشيحه للعملاء'),

                        TextInput::make('amount_of_designs')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->disabled()
                            ->label('(عدد التصاميم)'),

                        TextInput::make('discipline_score')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(1)
                            ->maxValue(10)
                            ->disabled()
                            ->label('درجة الانضباط (من 10)'),
                    ])->columns(2),

                Section::make('الأجهزة والبيانات الإدارية')
                    ->description('بيانات العهدة والأجهزة وحسابات الأدوات الرقمية')
                    ->schema([
                        TextInput::make('freepik_account')
                            ->label('حساب Freepik')
                            ->placeholder('اسم المستخدم أو البريد المسجل'),

                        TextInput::make('pc_number')
                            ->label('رقم الجهاز')
                            ->placeholder('مثال: PC-01'),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض المصممين.
     *
     * @param  \Filament\Tables\Table  $table  جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('اسم المصمم')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user-circle'),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('التصنيفات')
                    ->badge()
                    ->separator(', ')
                    ->limitList(2)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('min_capacity')
                    ->label('الحد الأدنى')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('max_capacity')
                    ->label('الحد الأقصى')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('rate')
                    ->label('التقييم ⭐')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->rate >= 7 => 'success',
                        $record->rate >= 5 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('amount_of_designs')
                    ->label('رصيد الخبرة السابق')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('discipline_score')
                    ->label('الانضباط')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('shift_hours')
                    ->label('ساعات الدوام')
                    ->suffix(' ساعة')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('freepik_account')
                    ->label('حساب Freepik')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-link')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pc_number')
                    ->label('رقم الجهاز')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('categories')
                    ->label('بحسب التصنيف')
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('rating_level')
                    ->label('مستوى التقييم')
                    ->options([
                        'high' => '⭐ متميز (7 فما فوق)',
                        'medium' => '⭐ متوسط (5 إلى 6.9)',
                        'low' => '⭐ منخفض (أقل من 5)',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'high' => $query->where('rate', '>=', 7),
                        'medium' => $query->where('rate', '>=', 5)->where('rate', '<', 7),
                        'low' => $query->where('rate', '<', 5),
                        default => $query,
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('بيانات المصمم')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('اسم المصمم')
                            ->icon('heroicon-m-user-circle')
                            ->weight(FontWeight::Bold),

                        TextEntry::make('shift_hours')
                            ->label('ساعات الدوام اليومية')
                            ->suffix(' ساعات')
                            ->default('—'),
                    ])->columns(2),

                InfolistSection::make('التخصصات ومحددات الطاقة')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        TextEntry::make('categories.name')
                            ->label('التصنيفات')
                            ->badge()
                            ->color('primary')
                            ->separator(', ')
                            ->columnSpanFull(),

                        TextEntry::make('min_capacity')
                            ->label('الحد الأدنى لعدد التصاميم')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('max_capacity')
                            ->label('الحد الأعلى لعدد التصاميم')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('rate')
                            ->label('التقييم ⭐')
                            ->badge()
                            ->color(fn ($record) => match (true) {
                                $record->rate >= 7 => 'success',
                                $record->rate >= 5 => 'warning',
                                default => 'danger',
                            }),

                        TextEntry::make('amount_of_designs')
                            ->label('رصيد الخبرة السابق (عدد التصاميم)')
                            ->default('0'),

                        TextEntry::make('discipline_score')
                            ->label('درجة الانضباط')
                            ->placeholder('—'),
                    ])->columns(3),

                InfolistSection::make('الأجهزة والبيانات الإدارية')
                    ->icon('heroicon-o-computer-desktop')
                    ->schema([
                        TextEntry::make('freepik_account')
                            ->label('حساب Freepik')
                            ->icon('heroicon-m-link')
                            ->copyable()
                            ->placeholder('لا يوجد'),

                        TextEntry::make('pc_number')
                            ->label('رقم الجهاز')
                            ->placeholder('لا يوجد'),
                    ])->columns(2),

                InfolistSection::make('سجل المتابعة والتعديلات')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        UserTrackingSection::make(),
                        \Filament\Infolists\Components\Tabs::make('Tabs')
                            ->tabs([
                                ActivityLogInfolistTab::make(),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDesigners::route('/'),
            'create' => Pages\CreateDesigner::route('/create'),
            'edit' => Pages\EditDesigner::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'categories']);
    }
}
