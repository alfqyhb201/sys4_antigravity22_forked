<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Exports\TagGroupExporter;
use App\Filament\Imports\TagGroupImporter;
use App\Filament\Resources\TagGroupResource\Pages;
use App\Filament\Resources\TagGroupResource\RelationManagers;
use App\Models\TagGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * مورد Filament لإدارة مجموعات الوسوم (Tag Groups).
 *
 * يوفر هذا المورد واجهة لإنشاء وعرض وتعديل وحذف مجموعات الوسوم.
 */
class TagGroupResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = TagGroup::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'مجموعات الوسوم';

    protected static ?string $pluralLabel = 'مجموعات الوسوم';

    protected static ?string $label = 'مجموعة وسم';

    protected static ?string $slug = 'tag-groups';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationGroup = 'المحتوى';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات المجموعة')
                    ->icon('heroicon-o-rectangle-group')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم المجموعة')
                            ->required()
                            ->maxLength(100)
                            ->prefixIcon('heroicon-m-rectangle-group')
                            ->placeholder('مثال: وسوم الأعياد، وسوم المناسبات...'),

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
                                ->icon('heroicon-o-folder')
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
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('اسم المجموعة')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('primary'),

                TextColumn::make('tags_count')
                    ->label('عدد الوسوم')
                    ->counts('tags')
                    ->badge()
                    ->color('info'),

                TextColumn::make('categories_count')
                    ->label('التصنيفات')
                    ->counts('categories')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('createdBy.name')
                    ->label('أُضيف بواسطة')
                    ->icon('heroicon-m-user')
                    ->description(fn ($record) => $record->created_at?->diffForHumans())
                    ->toggleable(),

                TextColumn::make('updatedBy.name')
                    ->label('آخر تعديل')
                    ->icon('heroicon-m-pencil-square')
                    ->description(fn ($record) => $record->updated_at?->diffForHumans())
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('categories')
                    ->label('التصنيفات')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->iconButton(),
                Tables\Actions\EditAction::make()->slideOver()->iconButton(),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->hidden(fn (TagGroup $record) => $record->tags()->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Filament\Tables\Actions\BulkAction $action, $records) {
                            $deletedCount = 0;
                            $skippedCount = 0;

                            foreach ($records as $record) {
                                if ($record->tags()->exists()) {
                                    $skippedCount++;
                                } else {
                                    $record->delete();
                                    $deletedCount++;
                                }
                            }

                            if ($skippedCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title("تم حذف {$deletedCount} مجموعة، وتخطي {$skippedCount} مجموعة لوجود وسوم مرتبطة بها")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(TagGroupImporter::class)
                    ->options([
                        'authUserId' => Auth::id(),
                    ]),
                ExportAction::make()
                    ->exporter(TagGroupExporter::class),
            ])
            ->striped();
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [
            RelationManagers\TagsRelationManager::class,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ─── البطاقة التعريفية مع الإحصائيات ──────────
                Section::make()
                    ->extraAttributes([
                        'style' => 'background: linear-gradient(135deg, color-mix(in srgb, var(--surface) 100%, var(--brand-purple) 5%), var(--surface)); border: 1px solid var(--brand-border); border-radius: 16px;',
                    ])
                    ->schema([
                        // رأس البطاقة — أيقونة + اسم المجموعة
                        TextEntry::make('name')
                            ->label('')
                            ->html()
                            ->formatStateUsing(fn ($state): string => '
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div style="width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-purple), #a78bfa); box-shadow: 0 4px 12px color-mix(in srgb, var(--brand-purple) 30%, transparent);">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12l2 2 4-4"/></svg>
                                    </div>
                                    <div>
                                        <div style="font-size: 1.4rem; font-weight: 700; letter-spacing: -0.01em; color: var(--brand-text);">'.e($state).'</div>
                                        <div style="font-size: 0.75rem; color: var(--brand-muted); margin-top: 2px;">مجموعة وسوم</div>
                                    </div>
                                </div>
                            '),

                        // إحصائيات في صف أفقي
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('tags_count')
                                    ->label('')
                                    ->html()
                                    ->state(fn (TagGroup $r) => $r->tags()->count())
                                    ->formatStateUsing(fn ($state): string => '
                                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--surface); border: 1px solid var(--brand-border);">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--brand-orange) 12%, transparent); color: var(--brand-orange);">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                            </div>
                                            <div style="line-height: 1;">
                                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--brand-text);">'.e($state).'</div>
                                                <div style="font-size: 0.65rem; color: var(--brand-muted); font-weight: 500; margin-top: 2px;">وسم</div>
                                            </div>
                                        </div>
                                    '),

                                TextEntry::make('categories_count')
                                    ->label('')
                                    ->html()
                                    ->state(fn (TagGroup $r) => $r->categories()->count())
                                    ->formatStateUsing(fn ($state): string => '
                                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--surface); border: 1px solid var(--brand-border);">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--brand-purple) 12%, transparent); color: var(--brand-purple);">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                            </div>
                                            <div style="line-height: 1;">
                                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--brand-text);">'.e($state).'</div>
                                                <div style="font-size: 0.65rem; color: var(--brand-muted); font-weight: 500; margin-top: 2px;">تصنيف</div>
                                            </div>
                                        </div>
                                    '),

                                TextEntry::make('createdBy.name')
                                    ->label('')
                                    ->html()
                                    ->formatStateUsing(fn ($state, TagGroup $r): string => '
                                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--surface); border: 1px solid var(--brand-border);">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--brand-emerald) 12%, transparent); color: var(--brand-emerald);">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                            </div>
                                            <div style="line-height: 1;">
                                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--brand-text);">'.e($r->createdBy?->name ?? '—').'</div>
                                                <div style="font-size: 0.6rem; color: var(--brand-muted); font-weight: 500; margin-top: 2px;">أضيف بواسطة</div>
                                            </div>
                                        </div>
                                    '),
                            ]),

                        // شريط التصنيفات
                        TextEntry::make('categories_list')
                            ->label('')
                            ->html()
                            ->visible(fn (TagGroup $r): bool => $r->categories()->exists())
                            ->formatStateUsing(function (TagGroup $record): string {
                                $cats = $record->categories;
                                $badges = $cats->map(fn ($c): string => '
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 100px; font-size: 0.72rem; font-weight: 600; background: color-mix(in srgb, var(--brand-purple-light) 15%, transparent); color: var(--brand-purple-light); border: 1px solid color-mix(in srgb, var(--brand-purple-light) 25%, transparent);">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        '.e($c->name).'
                                    </span>
                                ')->implode(' ');

                                return '
                                    <div style="display: flex; align-items: center; gap: 10px; padding-top: 10px; border-top: 1px solid var(--brand-border); margin-top: 6px;">
                                        <span style="font-size: 0.72rem; font-weight: 500; color: var(--brand-muted); white-space: nowrap;">التصنيفات:</span>
                                        <div style="display: flex; flex-wrap: wrap; gap: 5px;">'.$badges.'</div>
                                    </div>
                                ';
                            }),
                    ]),

                // ─── معلومات إضافية ──────────
                Section::make('معلومات إضافية')
                    ->icon('heroicon-o-information-circle')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime()
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('updated_at')
                            ->label('آخر تحديث')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('createdBy.name')
                            ->label('أضيف بواسطة')
                            ->icon('heroicon-o-user-plus')
                            ->placeholder('—'),

                        TextEntry::make('updatedBy.name')
                            ->label('آخر تعديل بواسطة')
                            ->icon('heroicon-o-pencil-square')
                            ->placeholder('—'),
                    ]),

                // ─── بطاقات الوسوم ──────────
                Section::make('الوسوم')
                    ->icon('heroicon-o-tag')
                    ->description('جميع الوسوم المرتبطة بهذه المجموعة')
                    ->schema([
                        RepeatableEntry::make('tags')
                            ->label('')
                            ->contained(false)
                            ->columns(2)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('')
                                            ->html()
                                            ->formatStateUsing(fn ($state): string => '
                                                <div style="font-weight: 600; font-size: 0.9rem; color: var(--brand-text);">'.e($state).'</div>
                                            '),

                                        TextEntry::make('importance')
                                            ->label('')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                'high' => 'danger',
                                                'medium' => 'warning',
                                                'low' => 'success',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'high' => 'عالية',
                                                'medium' => 'متوسطة',
                                                'low' => 'منخفضة',
                                                default => '—',
                                            }),
                                    ])
                                    ->extraAttributes(fn ($record) => [
                                        'style' => 'display: flex; align-items: center; border-radius: 10px; background: var(--surface); border: 1px solid var(--brand-border); padding: 0.6rem 1rem;'.
                                            match ($record?->importance) {
                                                'high' => ' border-inline-start: 3px solid var(--brand-red);',
                                                'medium' => ' border-inline-start: 3px solid var(--brand-amber);',
                                                'low' => ' border-inline-start: 3px solid var(--brand-emerald);',
                                                default => '',
                                            },
                                    ]),
                            ]),
                    ]),

                // ─── سجل التغييرات ──────────
                Tabs::make('ActivityTab')
                    ->columnSpanFull()
                    ->tabs([
                        ActivityLogInfolistTab::make(),
                    ]),
            ]);
    }

    /**
     * يقوم بإرجاع صفحات (Pages) لهذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTagGroups::route('/'),
            'create' => Pages\CreateTagGroup::route('/create'),
            'edit' => Pages\EditTagGroup::route('/{record}/edit'),
        ];
    }
}
