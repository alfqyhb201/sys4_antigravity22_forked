<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\ClientNeedResource\Pages;
use App\Models\ClientNeed;
use App\Models\TagGroup;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة أنواع العملاء (Client Needs).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف أنواع العملاء،
 * مع ربطها بالوسوم والتصنيفات وتحديد درجة أهميتها.
 */
class ClientNeedResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = ClientNeed::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     */
    protected static ?string $navigationGroup = 'المحتوى';

    /**
     * ترتيب المورد في قائمة التنقل.
     */
    protected static ?int $navigationSort = 3;

    /**
     * اسم المورد في قائمة التنقل.
     */
    protected static ?string $navigationLabel = 'أنواع العملاء';

    /**
     * السمة المستخدمة كعنوان للسجل.
     */
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * الرابط الثابت (slug) للمورد.
     */
    protected static ?string $slug = 'client-needs';

    /**
     * اسم النموذج المفرد.
     */
    protected static ?string $modelLabel = 'نوع العميل';

    /**
     * اسم النموذج بصيغة الجمع.
     */
    protected static ?string $pluralModelLabel = 'أنواع العملاء';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل أنواع العملاء.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // العمود الأيمن: البيانات الأساسية
                        Section::make('البيانات الأساسية')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('اسم نوع العميل')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-document-text')
                                    ->placeholder('مثال: تصميم شعار احترافي'),

                                Forms\Components\CheckboxList::make('importance_level')
                                    ->label('درجات الأهمية المستهدفة')
                                    ->options([
                                        'very_high' => 'عالية جداً',
                                        'high' => 'عالية',
                                        'medium' => 'متوسطة',
                                        'low' => 'منخفضة',
                                    ])
                                    ->columns(2)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($set) => $set('tags', [])),

                                Forms\Components\Select::make('categories')
                                    ->label('التصنيفات')
                                    ->relationship('categories', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon('heroicon-m-folder')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, $set) {
                                        $set('tags', []);
                                    }),
                            ])
                            ->columnSpan(1),

                        // العمود الأيسر: اختيار الوسوم
                        Section::make('اختيار الوسوم')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\Select::make('tag_groups')
                                    ->label('مجموعات التاقات (للفلترة)')
                                    ->options(fn () => TagGroup::query()->pluck('name', 'id'))
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon('heroicon-m-rectangle-group')
                                    ->nullable()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($set) => $set('tags', [])),

                                Forms\Components\Select::make('tags')
                                    ->label('التاقات (الوسوم المطابقة)')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->prefixIcon('heroicon-m-tag')
                                    ->options(function ($get) {
                                        $importanceLevels = $get('importance_level') ?? [];
                                        $categories = $get('categories') ?? [];
                                        $tagGroups = $get('tag_groups') ?? [];

                                        if (empty($importanceLevels) && empty($tagGroups)) {
                                            return collect();
                                        }

                                        $importanceMap = [
                                            'very_high' => 'veryhigh',
                                            'high' => 'high',
                                            'medium' => 'medium',
                                            'low' => 'low',
                                        ];

                                        $query = \App\Models\Tag::query();

                                        if (! empty($tagGroups)) {
                                            // عند اختيار مجموعات: أظهر التاقات التابعة لهذه المجموعات فقط
                                            $query->whereIn('tag_group_id', $tagGroups);
                                        } else {
                                            // بدون مجموعات: استبعد التاقات المرتبطة بمجموعة أو تصنيف أو أهمية
                                            $mappedLevels = array_map(fn ($level) => $importanceMap[$level] ?? $level, $importanceLevels);
                                            $query->whereIn('importance', $mappedLevels)
                                                ->whereNull('tag_group_id')
                                                ->whereDoesntHave('categories');

                                            if (! empty($categories)) {
                                                $query->whereHas('categories', fn ($q) => $q->whereIn('id', $categories));
                                            }
                                        }

                                        return $query->pluck('name', 'id');
                                    }),
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نوع العميل')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('importance_level')
                    ->label('مستويات الأهمية')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'very_high' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'very_high' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                        default => $state,
                    })
                    ->separator(','),

                Tables\Columns\TextColumn::make('categories.name')
                    ->label('التصنيفات')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tags.name')
                    ->label('التاقات المرتبطة')
                    ->badge()
                    ->color('primary')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('بواسطة')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->icon('heroicon-m-user')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل')
                    ->icon('heroicon-m-pencil-square')
                    ->description(fn ($record) => $record->updated_at?->diffForHumans())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('importance_level')
                    ->label('درجة الأهمية')
                    ->options([
                        'very_high' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                    ])
                    ->query(
                        fn (Builder $query, array $data) => ! empty($data['value'])
                            ? $query->whereJsonContains('importance_level', $data['value'])
                            : $query
                    ),
                Tables\Filters\SelectFilter::make('categories')
                    ->label('التصنيفات')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
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

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientNeeds::route('/'),
            'create' => Pages\CreateClientNeed::route('/create'),
            'edit' => Pages\EditClientNeed::route('/{record}/edit'),
        ];
    }
}
