<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Enums\ComplaintStatus;
use App\Filament\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * مورد Filament لإدارة شكاوي العملاء.
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء وعرض وتعديل وحذف الشكاوي،
 * مع إمكانية تتبع حالة كل شكوى وربطها بالعميل المعني.
 */
class ComplaintResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Complaint::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     */
    protected static ?string $navigationGroup = 'CRM';

    /**
     * اسم المورد في قائمة التنقل.
     */
    protected static ?string $navigationLabel = 'الشكاوي';

    /**
     * اسم النموذج بصيغة الجمع.
     */
    protected static ?string $pluralModelLabel = 'الشكاوي';

    /**
     * اسم النموذج بصيغة المفرد.
     */
    protected static ?string $modelLabel = 'شكوى';

    /**
     * الرابط الثابت (slug) للمورد.
     */
    protected static ?string $slug = 'complaints';

    /**
     * عدد شارة التنقل - يعرض عدد الشكاوي الجديدة.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', ComplaintStatus::New->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * لون شارة التنقل.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل الشكاوي.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات الشكوى')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        Forms\Components\Select::make('client_id')
                            ->label('العميل')
                            ->relationship('client', 'company')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->company.($record->client_name ? ' - '.$record->client_name : ''))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options(ComplaintStatus::class)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->hidden(),

                        Forms\Components\RichEditor::make('description')
                            ->label('تفاصيل الشكوى')
                            ->required()
                            ->disableToolbarButtons([
                                'attachFiles',
                                'link',
                            ])
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض الشكاوي.
     *
     * @param  \Filament\Tables\Table  $table  جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'createdBy', 'resolvedBy']))
            ->columns([
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-building-office'),

                Tables\Columns\TextColumn::make('description')
                    ->label('تفاصيل الشكوى')
                    ->html()
                    ->limit(80)
                    ->tooltip(fn ($record) => strip_tags($record->description))
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (ComplaintStatus $state): string => match ($state) {
                        ComplaintStatus::New => 'danger',
                        ComplaintStatus::Resolved => 'success',
                    })
                    ->icon(fn (ComplaintStatus $state): string => match ($state) {
                        ComplaintStatus::New => 'heroicon-o-clock',
                        ComplaintStatus::Resolved => 'heroicon-o-check-circle',
                    }),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('أضيف بواسطة')
                    ->icon('heroicon-o-user')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الشكوى')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Tables\Filters\SelectFilter::make('status')
                //     ->label('الحالة')
                //     ->options(ComplaintStatus::class),

                // Tables\Filters\SelectFilter::make('client_id')
                //     ->label('العميل')
                //     ->relationship('client', 'company')
                //     ->searchable()
                //     ->preload(),

                // Tables\Filters\Filter::make('new_only')
                //     ->label('الشكاوي الجديدة فقط')
                //     ->query(fn (Builder $query): Builder => $query->where('status', ComplaintStatus::New->value))
                //     ->toggle(),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->slideOver(),
                    Tables\Actions\Action::make('resolve')
                        ->label('تم الحل')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تأكيد حل الشكوى')
                        ->modalDescription('هل أنت متأكد من أن هذه الشكوى تم حلها؟')
                        ->visible(fn (Complaint $record): bool => $record->status === ComplaintStatus::New && (auth()->user()?->can('update', $record) ?? false))
                        ->action(function (Complaint $record): void {
                            $record->update([
                                'status' => ComplaintStatus::Resolved,
                                'updated_by_user' => auth()->id(),
                                'resolved_by_user' => auth()->id(),
                            ]);
                        }),
                    Tables\Actions\Action::make('reopen')
                        ->label('إعادة فتح')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('إعادة فتح الشكوى')
                        ->modalDescription('هل تريد إعادة فتح هذه الشكوى؟')
                        ->visible(fn (Complaint $record): bool => $record->status === ComplaintStatus::Resolved && (auth()->user()?->can('update', $record) ?? false))
                        ->action(function (Complaint $record): void {
                            $record->update([
                                'status' => ComplaintStatus::New,
                                'updated_by_user' => auth()->id(),
                                'resolved_by_user' => null,
                            ]);
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل الشكوى.
     *
     * @param  \Filament\Infolists\Infolist  $infolist  قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('تفاصيل الشكوى')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.company')
                            ->label('اسم الشركة')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->icon('heroicon-o-building-office'),

                        TextEntry::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->color(fn (ComplaintStatus $state): string => match ($state) {
                                ComplaintStatus::New => 'danger',
                                ComplaintStatus::Resolved => 'success',
                            }),

                        TextEntry::make('description')
                            ->label('الشكوى')
                            ->html()
                            ->prose()
                            ->extraAttributes([
                                'style' => 'border-top-width: 1px; border-top-color: var(--brand-purple);',
                            ])
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('معلومات إضافية')
                    ->icon('heroicon-o-information-circle')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label('أضيف بواسطة')
                            ->icon('heroicon-o-user-plus'),
                        TextEntry::make('resolvedBy.name')
                            ->label('تم حلها بواسطة')
                            ->icon('heroicon-o-check-circle')
                            ->placeholder('لم تُحل بعد'),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإضافة')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                    ]),

                UserTrackingSection::make(),

                Tabs::make('Tabs')
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
     * يقوم بإرجاع صفحات (Pages) هذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'create' => Pages\CreateComplaint::route('/create'),
            'edit' => Pages\EditComplaint::route('/{record}/edit'),
            'view' => Pages\ViewComplaint::route('/{record}'),
        ];
    }
}
