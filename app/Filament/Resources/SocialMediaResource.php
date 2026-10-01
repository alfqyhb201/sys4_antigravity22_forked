<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\SocialMediaResource\Pages;
use App\Models\SocialMedia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * مورد Filament لإدارة وسائل التواصل الاجتماعي (Social Media).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف وسائل التواصل الاجتماعي.
 */
class SocialMediaResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = SocialMedia::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'وسائل التواصل';

    protected static ?string $pluralModelLabel = 'وسائل التواصل';

    protected static ?string $modelLabel = 'وسيلة تواصل';

    protected static ?string $slug = 'social-media';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الوسيلة')
                    ->description('أدخل اسم وسيلة التواصل الاجتماعي (مثل: فيسبوك، انستغرام).')
                    ->icon('heroicon-o-hashtag')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الوسيلة')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-share')
                            ->placeholder('مثال: تيك توك، واتساب...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الوسيلة')
                    ->sortable()
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('أضيف بواسطة')
                    ->icon('heroicon-m-user')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل')
                    ->icon('heroicon-m-pencil-square')
                    ->description(fn ($record) => $record->updated_at?->diffForHumans())
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListSocialMedia::route('/'),
            'create' => Pages\CreateSocialMedia::route('/create'),
            'edit' => Pages\EditSocialMedia::route('/{record}/edit'),
        ];
    }
}
