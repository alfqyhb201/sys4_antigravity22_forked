<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\CustodyResource\Pages;
use App\Models\Custody;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * مورد Filament لإدارة العهد (Custodies).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف العهد المسلمة للمستخدمين.
 */
class CustodyResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Custody::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'العهدة';

    protected static ?string $pluralLabel = 'العهدة';

    protected static ?string $label = 'عهدة';

    protected static ?string $slug = 'custodies';

    protected static ?string $navigationGroup = 'المستخدمون';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'عهدة';

    protected static ?string $pluralModelLabel = 'عهدات';

    protected static ?string $modelLabelPlural = 'العهدات';

    protected static ?string $pluralModelLabelPlural = 'العهدات';

    protected static ?string $navigationBadge = 'جديد';

    protected static ?string $navigationBadgeColor = 'success';

    protected static ?string $navigationSearch = 'true';

    protected static ?string $navigationSearchPlaceholder = 'ابحث عن عهدة...';

    protected static ?string $searchableAttribute = 'name';

    protected static ?string $searchableAttributePlural = 'name';

    protected static ?string $modelLabelSingular = 'عهدة';

    protected static ?string $modelLabelSingularPlural = 'العهدة';

    protected static ?string $modelLabelPluralSingular = 'العهدة';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل العهد.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات العهدة')
                    ->description('حدد العهدة والمستخدم المسؤول عنها')
                    ->icon('heroicon-o-archive-box')
                    ->schema([
                        TextInput::make('name')
                            ->label('اسم العهدة')
                            ->placeholder('مثال: لابتوب، سيارة، مفاتيح...')
                            ->required()
                            ->maxLength(255)
                            ->helperText('اسم العهدة أو الأصل المسلَّم للمستخدم'),

                        Select::make('user_id')
                            ->label('المستلم')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('الموظف أو المستخدم المسؤول عن هذه العهدة'),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض العهد.
     *
     * @param  \Filament\Tables\Table  $table  جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم العهدة')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->icon('heroicon-m-archive-box'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستلم')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('أضيف بواسطة')
                    ->icon('heroicon-m-user')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل بواسطة')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تعديل')
                    ->since()
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->filters([
                //
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
            ]);
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
            'index' => Pages\ListCustodies::route('/'),
            'create' => Pages\CreateCustody::route('/create'),
            'edit' => Pages\EditCustody::route('/{record}/edit'),
        ];
    }
}
