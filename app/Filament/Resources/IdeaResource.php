<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Exports\IdeaExporter;
use App\Filament\Imports\IdeaImporter;
use App\Filament\Resources\IdeaResource\Pages;
use App\Models\Idea;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة الأفكار (Ideas).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الأفكار،
 * مع إمكانية ربطها بالعملاء، الوسوم، والمواقع.
 */
class IdeaResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Idea::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?string $navigationLabel = 'الأفكار';

    protected static ?string $pluralLabel = 'الأفكار';

    protected static ?string $label = 'أفكار';

    protected static ?string $slug = 'ideas';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'أفكار';

    protected static ?string $pluralModelLabel = 'الأفكار';

    protected static ?string $modelLabelPlural = 'الأفكار';

    protected static ?string $modelLabelSingular = 'أفكار';

    protected static ?string $modelLabelSingularPlural = 'الأفكار';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الأفكار.
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
                        Forms\Components\Section::make('المحتوى الأساسي')
                            ->description('أدخل تفاصيل الفكرة ومحتواها.')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                TextInput::make('name')
                                    ->label('اسم الفكرة')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-light-bulb')
                                    ->placeholder('مثال: حملة تخفيضات الشتاء'),

                                Textarea::make('description')
                                    ->label('الوصف المختصر')
                                    ->rows(2)
                                    ->placeholder('وصف بسيط للفكرة...'),

                                Textarea::make('content')
                                    ->label('المحتوى التفصيلي')
                                    ->rows(5)
                                    ->placeholder('أدخل محتوى الفكرة بالتفصيل هنا...')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),

                        Forms\Components\Section::make('الإعدادات والجدولة')
                            ->description('التحكم في ظهور الفكرة وجدولتها.')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                DateTimePicker::make('scheduled_at')
                                    ->label('تاريخ الجدولة')
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->nullable()
                                    ->suffixAction(
                                        \Filament\Forms\Components\Actions\Action::make('clear')
                                            ->icon('heroicon-m-x-mark')
                                            ->color('danger')
                                            ->action(fn ($set) => $set('scheduled_at', null))
                                    ),

                                Forms\Components\Group::make([
                                    Toggle::make('repeat_for_clients')
                                        ->label('تكرار للعملاء')
                                        ->onColor('success')
                                        ->helperText('هل سيتم تكرار هذه الفكرة لعملاء صنفوا ضمن نفس الوسوم؟'),

                                    Toggle::make('is_visible_in_generator')
                                        ->label('نشطة')
                                        ->helperText('عند التفعيل، تكون الفكرة مؤهلة للدخول في التوزيع التلقائي للأسبوع.')
                                        ->onColor('success')
                                        ->offColor('danger')
                                        ->default(true),
                                ])->columns(1),

                                FileUpload::make('idea_file')
                                    ->label('ملف الفكرة (مرفق)')
                                    ->directory('ideas')
                                    ->maxSize(config('filesystems.max_file_size', 10240)),
                            ])
                            ->columnSpan(1),

                        Forms\Components\Section::make('الارتباطات والتاقات')
                            ->description('تحديد العملاء والمواقع المرتبطة عبر التاقات.')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Forms\Components\Select::make('tags')
                                    ->relationship('tags', 'name')
                                    ->label('التاقات')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->prefixIcon('heroicon-m-hashtag'),

                                Forms\Components\Select::make('clients')
                                    ->relationship('clients', 'company')
                                    ->label('العملاء المخصصون')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->prefixIcon('heroicon-m-users'),

                                Forms\Components\Select::make('locations')
                                    ->relationship(
                                        'locations',
                                        'name',
                                        fn (Builder $query, Get $get) => $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $get('tags') ?? []))
                                    )
                                    ->label('المواقع (بناءً على التاقات)')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->prefixIcon('heroicon-m-map-pin'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الفكرة')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\IconColumn::make('is_visible_in_generator')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('scheduled_at')
                    ->label('الجدولة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->color('gray')
                    ->icon('heroicon-m-calendar-days'),

                Tables\Columns\IconColumn::make('repeat_for_clients')
                    ->label('تكرار')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('clients_count')
                    ->label('العملاء')
                    ->counts('clients')
                    ->badge()
                    ->color('success'),

                TextColumn::make('tags.name')
                    ->label('التاقات')
                    ->badge()
                    ->color('info')
                    ->limitList(3)
                    ->expandableLimitedList(),

                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('tags')
                    ->label('فلترة بالتاقات')
                    ->relationship('tags', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_visible_in_generator')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشطة فقط')
                    ->falseLabel('متوقفة فقط'),
                Filter::make('scheduled')
                    ->label('مجدولة فقط')
                    ->query(fn (Builder $query) => $query->whereNotNull('scheduled_at')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->slideOver()->iconButton(),
                Tables\Actions\DeleteAction::make()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير')
                    ->exporter(IdeaExporter::class),
                ImportAction::make()
                    ->label('استيراد')
                    ->importer(IdeaImporter::class),
            ])
            ->striped();
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [
            // لاحقاً: يمكن إضافة RelationManagers
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
            'index' => Pages\ListIdeas::route('/'),
            'create' => Pages\CreateIdea::route('/create'),
            // 'edit' => Pages\EditIdea::route('/{record}/edit'),
        ];
    }
}
