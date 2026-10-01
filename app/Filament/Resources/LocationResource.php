<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\LocationResource\Pages;
use App\Models\Location;
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
 * مورد Filament لإدارة المواقع (Locations).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف المواقع الجغرافية.
 */
class LocationResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Location::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'المواقع';

    protected static ?string $pluralLabel = 'المواقع';

    protected static ?string $label = 'موقع';

    protected static ?string $slug = 'locations';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('تفاصيل الموقع')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الموقع')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-map-pin')
                            ->placeholder('مثال: شارع الاستقلال، المنطقة الصناعية...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الموقع')
                    ->sortable()
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label('العملاء')
                    ->counts('clients')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('بواسطة')
                    ->icon('heroicon-m-user')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل')
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
                    ->importer(\App\Filament\Imports\LocationImporter::class)
                    ->options([
                        'authUserId' => Auth::id(),
                    ]),
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\LocationExporter::class),
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
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }
}
