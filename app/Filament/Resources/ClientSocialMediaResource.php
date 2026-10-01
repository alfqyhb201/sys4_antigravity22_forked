<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientSocialMediaResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة منصات التواصل الاجتماعي الخاصة بالعملاء.
 */
class ClientSocialMediaResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'منصات العملاء';

    protected static ?string $pluralModelLabel = 'منصات العملاء';

    protected static ?string $modelLabel = 'منصات العميل';

    protected static ?string $slug = 'client-social-media';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user ? app(\App\Policies\ClientSocialMediaPolicy::class)->viewAny($user) : false;
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return $user ? app(\App\Policies\ClientSocialMediaPolicy::class)->view($user, $record) : false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user ? app(\App\Policies\ClientSocialMediaPolicy::class)->create($user) : false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return $user ? app(\App\Policies\ClientSocialMediaPolicy::class)->update($user, $record) : false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return $user ? app(\App\Policies\ClientSocialMediaPolicy::class)->delete($user, $record) : false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('socialMedia');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات العميل')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Forms\Components\Select::make('id')
                            ->label('العميل')
                            ->options(fn () => Client::pluck('company', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull(),

                        Forms\Components\MarkdownEditor::make('notes')
                            ->label('ملاحظات وتوجيهات النشر العامة')
                            ->placeholder('أوقات النشر المفضلة، الهاشتاقات، أو أي شروط خاصة للعميل...')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'strike',
                                'link',
                                'heading',
                                'blockquote',
                                'bulletList',
                                'orderedList',
                                'table',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('منصات التواصل الاجتماعي وروابط الحسابات')
                    ->description('أضف منصات التواصل للعميل مع رابط الحساب والملاحظات لكل منصة.')
                    ->icon('heroicon-o-share')
                    ->schema([
                        Forms\Components\Repeater::make('clientSocialMedia')
                            ->label('المنصات المرتبطة')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Select::make('social_media_id')
                                            ->label('المنصة')
                                            ->options(fn () => \App\Models\SocialMedia::pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('اسم المنصة')
                                                    ->required(),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                return \App\Models\SocialMedia::create($data)->id;
                                            })
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('account_url')
                                            ->label('رابط الحساب')
                                            ->placeholder('https://instagram.com/username')
                                            ->prefixIcon('heroicon-o-link')
                                            ->columnSpan(1),

                                        Forms\Components\TextInput::make('notes')
                                            ->label('ملاحظات الحساب')
                                            ->placeholder('ملاحظات إضافية على المنصة...')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('إضافة منصة جديدة')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('socialMedia.name')
                    ->label('المنصات المرتبطة')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات النشر')
                    ->limit(35)
                    ->placeholder('—'),
            ])
            ->defaultSort('company', 'asc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->slideOver(),
                Tables\Actions\EditAction::make()
                    ->iconButton(),
            ])
            ->bulkActions([])
            ->striped();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات العميل')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        Infolists\Components\TextEntry::make('company')
                            ->label('اسم العميل / الشركة')
                            ->size(\Filament\Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->icon('heroicon-o-building-office'),
                    ]),

                Infolists\Components\Section::make('المنصات المرتبطة')
                    // ->description('قائمة حسابات ومنصات التواصل الاجتماعي المفعلة لهذا العميل')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('socialMedia')
                            ->label(' ')
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('اسم المنصة')
                                            ->weight(FontWeight::Bold)
                                            ->color('primary')
                                            ->icon('heroicon-o-signal'),

                                        Infolists\Components\TextEntry::make('pivot.account_url')
                                            ->label('رابط الحساب')
                                            ->url(fn ($state): ?string => $state ? (str_starts_with($state, 'http') ? $state : 'https://'.$state) : null, true)
                                            ->icon('heroicon-o-link')
                                            ->color('info')
                                            ->placeholder('لا يوجد رابط مباشر'),

                                        Infolists\Components\TextEntry::make('pivot.notes')
                                            ->label('ملاحظات الحساب')
                                            ->placeholder('—'),
                                    ]),
                            ])
                            ->placeholder('لا توجد منصات تواصل اجتماعي مرتبطة بهذا العميل حتى الآن.')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('ملاحظات وتوجيهات النشر')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label(' ')
                            ->placeholder('لا توجد ملاحظات أو توجيهات نشر مسجلة لهذا العميل.')
                            ->markdown()
                            ->prose()
                            ->columnSpanFull(),
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
            'index' => Pages\ListClientSocialMedia::route('/'),
            'create' => Pages\CreateClientSocialMedia::route('/create'),
            'edit' => Pages\EditClientSocialMedia::route('/{record}/edit'),
        ];
    }
}
