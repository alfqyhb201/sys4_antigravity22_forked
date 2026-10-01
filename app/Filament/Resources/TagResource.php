<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Exports\TagExporter;
use App\Filament\Imports\TagImporter;
use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * مورد Filament لإدارة الوسوم (Tags).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الوسوم،
 * مع خيارات متقدمة لتحديد الأهمية، التصنيفات، المواقع،
 * مجموعات الوسوم، والتكرار والجدولة.
 */
class TagResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Tag::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'الوسوم';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'tags';

    protected static ?string $modelLabel = 'وسم';

    protected static ?string $pluralModelLabel = 'وسوم';

    protected static ?string $modelLabelPlural = 'الوسوم';

    protected static ?string $modelLabelSingular = 'وسم';

    protected static ?string $modelLabelSingularPlural = 'وسم';

    protected static ?string $navigationBadge = 'جديد';

    protected static ?string $navigationBadgeColor = 'success';

    protected static ?string $navigationSearch = 'true';

    protected static ?string $navigationSearchPlaceholder = 'ابحث عن وسم...';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الوسوم.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        // Main column (span 2)
                        Forms\Components\Group::make([
                            Forms\Components\Section::make('البيانات الأساسية')
                                ->icon('heroicon-o-tag')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('اسم الوسم')
                                        ->required()
                                        ->maxLength(100)
                                        ->prefixIcon('heroicon-m-tag'),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Select::make('importance')
                                                ->label('درجة الأهمية')
                                                ->options([
                                                    'veryhigh' => 'عالية جداً',
                                                    'high' => 'عالية',
                                                    'medium' => 'متوسطة',
                                                    'low' => 'منخفضة',
                                                ])
                                                ->default('medium')
                                                ->required()
                                                ->columnSpan(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager ? 2 : 1)
                                                ->native(false)
                                                ->prefixIcon('heroicon-m-exclamation-circle'),

                                            Forms\Components\Select::make('tag_group_id')
                                                ->label('مجموعة الوسوم')
                                                ->relationship('tagGroup', 'name')
                                                ->preload()
                                                ->required(fn ($livewire) => ! ($livewire instanceof \Filament\Resources\RelationManagers\RelationManager))
                                                ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager)
                                                ->native(false)
                                                ->searchable()
                                                ->prefixIcon('heroicon-m-rectangle-group'),
                                        ]),

                                    Forms\Components\Grid::make(2)
                                        ->schema([
                                            Forms\Components\Group::make([
                                                Forms\Components\Toggle::make('assign_all_categories')
                                                    ->label('تحديد جميع التصنيفات')
                                                    ->default(true)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        if (! $state) {
                                                            $set('categories', \App\Models\Category::pluck('id')->toArray());
                                                        } else {
                                                            $set('categories', []);
                                                        }
                                                    }),

                                                Forms\Components\Placeholder::make('categories_all_info')
                                                    ->label('')
                                                    ->content('✅ جميع التصنيفات محددة — أي تصنيف جديد سيُسنَد تلقائياً')
                                                    ->visible(fn (Forms\Get $get) => $get('assign_all_categories')),

                                                Forms\Components\Section::make('التصنيفات')
                                                    ->icon('heroicon-o-tag')
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->compact()
                                                    ->visible(fn (Forms\Get $get) => ! $get('assign_all_categories'))
                                                    ->schema([
                                                        Forms\Components\CheckboxList::make('categories')
                                                            ->relationship('categories', 'name')
                                                            ->searchable()
                                                            ->columns(2)
                                                            ->bulkToggleable()
                                                            ->label(''),
                                                    ]),
                                            ]),

                                            Forms\Components\Group::make([
                                                Forms\Components\Toggle::make('assign_all_locations')
                                                    ->label('تحديد جميع المواقع')
                                                    ->default(true)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        if (! $state) {
                                                            $set('locations', \App\Models\Location::pluck('id')->toArray());
                                                        } else {
                                                            $set('locations', []);
                                                        }
                                                    }),

                                                Forms\Components\Placeholder::make('locations_all_info')
                                                    ->label('')
                                                    ->content('✅ جميع المواقع محددة — أي موقع جديد سيُسنَد تلقائياً')
                                                    ->visible(fn (Forms\Get $get) => $get('assign_all_locations')),

                                                Forms\Components\Section::make('المواقع')
                                                    ->icon('heroicon-o-map-pin')
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->compact()
                                                    ->visible(fn (Forms\Get $get) => ! $get('assign_all_locations'))
                                                    ->schema([
                                                        Forms\Components\CheckboxList::make('locations')
                                                            ->relationship('locations', 'name')
                                                            ->searchable()
                                                            ->columns(2)
                                                            ->bulkToggleable()
                                                            ->label(''),
                                                    ]),
                                            ]),
                                        ]),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    /*
                                    Section::make('التكرار والجدولة')
                                        ->icon('heroicon-o-arrow-path')
                                        ->schema([
                                            Toggle::make('is_repetition')
                                                ->label('تمكين التكرار')
                                                ->live(),

                                            Radio::make('repetition')
                                                ->label('نوع التكرار')
                                                ->options([
                                                    'weekly' => 'أسبوعي',
                                                    'yearly' => 'سنوي',
                                                ])
                                                ->inline()
                                                ->nullable()
                                                ->live()
                                                ->visible(fn (Get $get): bool => $get('is_repetition')),

                                            TextInput::make('weekly_times')->numeric()
                                                ->label('مرات التكرار (أسبوعي)')
                                                ->disabled()
                                                ->dehydrated()
                                                ->visible(fn (Get $get): bool => $get('is_repetition') && $get('repetition') === 'weekly'),

                                            TextInput::make('yearly_times')->numeric()
                                                ->label('مرات التكرار (سنوي)')
                                                ->visible(fn (Get $get): bool => $get('is_repetition') && $get('repetition') === 'yearly'),
                                        ]),
                                    */

                                    Section::make('بيانات الإرسال')
                                        ->icon('heroicon-o-paper-airplane')
                                        ->schema([
                                            Toggle::make('is_there_date_for_sending')
                                                ->label('جدولة الإرسال')
                                                ->live()
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    if (! $state) {
                                                        $set('sending_type', 'weekly');
                                                        $set('date_for_sending_yearly', null);
                                                        $set('weekly_day', null);
                                                        $set('weekly_time', null);
                                                        $set('weekly_time_sm', null);
                                                        $set('weekly_times', null);
                                                    }
                                                }),

                                            Radio::make('sending_type')
                                                ->label('نوع الجدولة')
                                                ->options([
                                                    'weekly' => 'أسبوعي',
                                                    'yearly' => 'سنوي',
                                                ])
                                                ->default('weekly')
                                                ->inline()
                                                ->live()
                                                ->dehydrated(false)
                                                ->afterStateHydrated(function (Forms\Set $set, ?Tag $record) {
                                                    if ($record?->date_for_sending_yearly) {
                                                        $set('sending_type', 'yearly');
                                                    } else {
                                                        $set('sending_type', 'weekly');
                                                    }
                                                })
                                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                    if ($state === 'weekly') {
                                                        $set('date_for_sending_yearly', null);
                                                    } elseif ($state === 'yearly') {
                                                        $set('weekly_day', null);
                                                        $set('weekly_times', null);
                                                    }
                                                })
                                                ->visible(fn (Get $get): bool => $get('is_there_date_for_sending')),

                                            DatePicker::make('date_for_sending_yearly')
                                                ->label('التاريخ السنوي')
                                                ->native(true)
                                                ->visible(fn (Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'yearly'),

                                            Select::make('weekly_day')
                                                ->label('أيام الأسبوع')
                                                ->options([
                                                    'Saturday' => 'السبت',
                                                    'Sunday' => 'الأحد',
                                                    'Monday' => 'الاثنين',
                                                    'Tuesday' => 'الثلاثاء',
                                                    'Wednesday' => 'الأربعاء',
                                                    'Thursday' => 'الخميس',
                                                    'Friday' => 'الجمعة',
                                                ])
                                                ->multiple()
                                                ->preload()
                                                ->live()
                                                ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('weekly_times', count($state ?? [])))
                                                ->visible(fn (Get $get): bool => $get('is_there_date_for_sending') && $get('sending_type') === 'weekly'),

                                            Forms\Components\Grid::make(2)
                                                ->schema([
                                                    TimePicker::make('weekly_time')
                                                        ->label('وقت الإرسال')
                                                        ->visible(fn (Get $get): bool => $get('is_there_date_for_sending')),

                                                    TimePicker::make('weekly_time_sm')
                                                        ->label('وقت السوشيال ميديا')
                                                        ->visible(fn (Get $get): bool => $get('is_there_date_for_sending')),
                                                ])
                                                ->visible(fn (Get $get): bool => $get('is_there_date_for_sending')),
                                        ]),
                                ]),
                        ])
                            ->columnSpan([
                                'lg' => 2,
                            ]),

                        // Sidebar column (span 1)
                        Forms\Components\Group::make([
                            Forms\Components\Section::make('الحالة والتعيين')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Forms\Components\Toggle::make('is_active')
                                        ->label('نشط')
                                        ->default(true)
                                        ->onIcon('heroicon-m-check')
                                        ->offIcon('heroicon-m-x-mark')
                                        ->onColor('success')
                                        ->offColor('danger'),

                                    Forms\Components\Toggle::make('is_auto_assigned')
                                        ->label('تعيين تلقائي')
                                        ->default(true)
                                        ->live()
                                        ->helperText('تعيين الوسم تلقائياً للعملاء المطابقين.'),
                                ]),

                            Forms\Components\Section::make('الارتباط بالعملاء')
                                ->icon('heroicon-o-users')
                                ->schema([
                                    Select::make('clients')
                                        ->label('تحديد العملاء')
                                        ->relationship('clients', 'company')
                                        ->multiple()
                                        ->preload()
                                        ->searchable()
                                        ->required(fn (Get $get): bool => ! $get('is_auto_assigned'))
                                        ->columnSpanFull(),
                                ])
                                ->visible(fn (Get $get): bool => ! $get('is_auto_assigned')),
                        ])
                            ->columnSpan([
                                'lg' => 1,
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الوسم')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                TextColumn::make('importance')
                    ->label('الأهمية')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'veryhigh' => 'danger',
                        'high' => 'warning',
                        'medium' => 'success',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'veryhigh' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                        default => $state,
                    }),

                TextColumn::make('tagGroup.name')
                    ->label('المجموعة')
                    ->icon('heroicon-m-rectangle-group')
                    ->color('gray'),

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

                Tables\Columns\IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('ideas_count')
                    ->counts('ideas')
                    ->label('الأفكار المتاحة')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->icon(fn (int $state): string => $state > 0 ? 'heroicon-m-light-bulb' : 'heroicon-m-exclamation-triangle')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} أفكار" : '0 أفكار ⚠️')
                    ->sortable()
                    ->action(static::getQuickIdeasAction())
                    ->tooltip('انقر للمعاينة السريعة وإدارة الأفكار المباشرة')
                    ->toggleable(),

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

                Tables\Filters\TernaryFilter::make('has_ideas')
                    ->label('توفر الأفكار')
                    ->placeholder('الكل')
                    ->trueLabel('يحتوي على أفكار')
                    ->falseLabel('بدون أفكار ⚠️')
                    ->queries(
                        true: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->has('ideas'),
                        false: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->doesntHave('ideas'),
                        blank: fn (\Illuminate\Database\Eloquent\Builder $query) => $query,
                    ),

                Tables\Filters\SelectFilter::make('scheduling')
                    ->label('نوع الجدولة')
                    ->options([
                        'scheduled' => 'جميع المجدولة',
                        'weekly' => 'مجدول أسبوعياً',
                        'yearly' => 'مجدول سنوياً',
                        'unscheduled' => 'غير مجدول',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return match ($data['value'] ?? null) {
                            'scheduled' => $query->where('is_there_date_for_sending', true),
                            'weekly' => $query->where('is_there_date_for_sending', true)->whereNotNull('weekly_day'),
                            'yearly' => $query->where('is_there_date_for_sending', true)->whereNotNull('date_for_sending_yearly'),
                            'unscheduled' => $query->where(function ($q) {
                                $q->where('is_there_date_for_sending', false)
                                    ->orWhereNull('is_there_date_for_sending');
                            }),
                            default => $query,
                        };
                    }),

                Tables\Filters\TernaryFilter::make('is_auto_assigned')
                    ->label('نوع التعيين')
                    ->placeholder('الكل')
                    ->trueLabel('تعيين تلقائي')
                    ->falseLabel('مخصص لعملاء محددين'),

                Tables\Filters\SelectFilter::make('tag_group_id')
                    ->relationship('tagGroup', 'name')
                    ->label('المجموعة')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('importance')
                    ->label('الأهمية')
                    ->options([
                        'veryhigh' => 'عالية جداً',
                        'high' => 'عالية',
                        'medium' => 'متوسطة',
                        'low' => 'منخفضة',
                    ]),
            ])
            ->actions([
                static::getQuickIdeasAction()
                    ->iconButton()
                    ->tooltip('معاينة وإدارة الأفكار المباشرة'),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(TagImporter::class)
                    ->options([
                        'authUserId' => Auth::id(),
                    ]),
                ExportAction::make()
                    ->exporter(TagExporter::class),
                \Filament\Tables\Actions\Action::make('custom_import')
                    ->label('استيراد متقدم')
                    ->icon('heroicon-o-arrow-up-on-square-stack')
                    ->url(\App\Filament\Pages\CustomTagImport::getUrl()),
            ]);
    }

    /**
     * الإجراء السريع لمعاينة وإدارة الأفكار عبر درج جانبي Slide-over Drawer.
     */
    public static function getQuickIdeasAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('quickPeekIdeas')
            ->label('الأفكار المتاحة')
            ->icon('heroicon-o-light-bulb')
            ->color(fn (Tag $record): string => $record->ideas()->exists() ? 'primary' : 'warning')
            ->modalHeading(fn (Tag $record): string => 'الأفكار المرتبطة بالوسم: '.$record->name)
            ->modalDescription('معاينة سريعة لكافة أفكار الوسم وإضافة فكرة جديدة وربطها فوراً دون مغادرة الصفحة.')
            ->modalIcon('heroicon-o-light-bulb')
            ->modalIconColor('primary')
            ->slideOver()
            ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalContent(function (Tag $record) {
                $tag = Tag::with(['ideas' => fn ($q) => $q->latest()])->find($record->id);

                return view('filament.modals.tag-quick-ideas-drawer', [
                    'tag' => $tag ?? $record,
                ]);
            });
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
                UserTrackingSection::make(),
                \Filament\Infolists\Components\Tabs::make('Tabs')
                    ->tabs([
                        ActivityLogInfolistTab::make(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
