<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * مورد Filament لإدارة الفئات (Categories).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف الفئات في لوحة التحكم.
 */
class CategoryResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Category::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'التصنيفات';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'categories';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'تصنيفات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('تفاصيل التصنيف')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-tag')
                            ->placeholder('أدخل اسم التصنيف...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('التصنيف')
                    ->sortable()
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('أضيف بواسطة')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-pencil-square')
                    ->description(fn ($record) => $record->updated_at?->diffForHumans())
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(\App\Filament\Imports\CategoryImporter::class)
                    ->options([
                        'authUserId' => Auth::id(),
                    ]),
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\CategoryExporter::class),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (Category $record) => Client::where('category_id', $record->id)->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(fn () => Client::whereNotNull('category_id')->exists()),
                ]),
            ])
            ->striped();
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
