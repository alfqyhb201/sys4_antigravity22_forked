<?php

namespace App\Filament\Resources;

use App\Filament\Components\ActivityLogInfolistTab;
use App\Filament\Components\UserTrackingSection;
use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Filament\Resources\DesignTaskResource\Pages;
use App\Models\ClientTemplate;
use App\Models\DesignTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * مورد Filament لإدارة مهام التصميم.
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء المهام وتعيينها للمصممين
 * ومراجعة التصاميم المسلمة (الموافقة أو طلب تعديل).
 */
class DesignTaskResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = DesignTask::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     */
    protected static ?string $navigationGroup = 'إدارة المهام';

    /**
     * اسم المورد في قائمة التنقل.
     */
    protected static ?string $navigationLabel = 'المهام الجانبية';

    /**
     * اسم النموذج بصيغة الجمع.
     */
    protected static ?string $pluralModelLabel = 'المهام الجانبية';

    /**
     * اسم النموذج بصيغة المفرد.
     */
    protected static ?string $modelLabel = 'مهمة جانبية';

    /**
     * الرابط الثابت (slug) للمورد.
     */
    protected static ?string $slug = 'design-tasks';

    /**
     * عدد شارة التنقل - يعرض عدد المهام قيد المراجعة الخاصة بالمستخدم الحالي.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $query = static::getModel()::where('status', DesignTaskStatus::InReview->value);

        if (! $user->hasRole(['admin', 'super_admin'])) {
            $query->where('assigner_id', $user->id);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * لون شارة التنقل.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل مهام التصميم.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        // العمود الأيمن: تعيين الموظف والأهمية
                        Forms\Components\Section::make('تعيين الموظف والأهمية')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Forms\Components\Select::make('designer_id')
                                    ->label('الموظف الموكل')
                                    ->relationship('designer', 'id')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? 'مصمم #'.$record->id)
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('priority')
                                    ->label('الأهمية')
                                    ->options(DesignTaskPriority::class)
                                    ->default(DesignTaskPriority::Medium->value)
                                    ->selectablePlaceholder(false)
                                    ->required(),

                                Forms\Components\DateTimePicker::make('scheduled_at')
                                    ->label('موعد الجدولة')
                                    ->nullable()
                                    ->native(true)
                                    ->minDate(now()),

                                Forms\Components\Toggle::make('is_extra')
                                    ->label('مهمة بإضافي')
                                    ->default(false)
                                    ->live(),

                                Forms\Components\TextInput::make('amount')
                                    ->label('المبلغ')
                                    ->numeric()
                                    ->prefix('💰')
                                    ->visible(fn (Forms\Get $get): bool => (bool) $get('is_extra')),
                            ])
                            ->columnSpan(1),

                        // العمود الأيسر: معلومات العميل ووصف المهمة
                        Forms\Components\Section::make('العميل ووصف المهمة')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Toggle::make('is_subscribed_client')
                                    ->label('خاصة بعميل مشترك')
                                    ->default(false)
                                    ->live(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('client_id')
                                            ->label('اختر العميل')
                                            ->relationship('client', 'company')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->company.($record->client_name ? ' - '.$record->client_name : ''))
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_subscribed_client'))
                                            ->required(fn (Forms\Get $get): bool => (bool) $get('is_subscribed_client')),

                                        Forms\Components\TextInput::make('client_name')
                                            ->label('اكتب اسم العميل')
                                            ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_subscribed_client'))
                                            ->required(fn (Forms\Get $get): bool => ! (bool) $get('is_subscribed_client'))
                                            ->columnSpan(1),

                                        Forms\Components\Toggle::make('deduct_from_balance')
                                            ->label('تخصم من رصيد العميل المقبل')
                                            ->default(false)
                                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_subscribed_client')),
                                    ]),

                                Forms\Components\Textarea::make('description')
                                    ->label('وصف المهمة')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Forms\Components\FileUpload::make('reference_files')
                                    ->label('إضافة ملفات')
                                    ->multiple()
                                    ->directory(fn (Forms\Get $get, ?DesignTask $record): string => 'clients/'.($get('client_id') ?? $record?->client_id ?? 'unknown').'/design-tasks/'.($record?->id ?? 'new').'/references')
                                    ->maxFiles(10)
                                    ->maxSize(config('filesystems.max_file_size', 10240))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض مهام التصميم.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                if ($user && ! $user->hasRole(['admin', 'super_admin'])) {
                    $query->where('assigner_id', $user->id);
                }
            })
            ->recordAction('viewDetails')
            ->columns([
                Tables\Columns\TextColumn::make('designer.user.name')
                    ->label('المصمم')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user'),

                Tables\Columns\TextColumn::make('display_client_name')
                    ->label('العميل')
                    ->searchable(['client_name'])
                    ->icon('heroicon-m-building-office'),

                Tables\Columns\TextColumn::make('assigner.name')
                    ->label('المُنشئ')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-m-user-circle')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('is_template_update')
                    ->label('النوع')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'primary' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state ? 'قالب' : 'جانبية')
                    ->icon(fn ($state): string => $state ? 'heroicon-o-swatch' : 'heroicon-o-clipboard'),

                Tables\Columns\TextColumn::make('priority')
                    ->label('الأهمية')
                    ->badge()
                    ->color(fn (DesignTaskPriority $state): string => match ($state) {
                        DesignTaskPriority::High => 'danger',
                        DesignTaskPriority::Medium => 'warning',
                        DesignTaskPriority::Low => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (DesignTaskStatus $state): string => match ($state) {
                        DesignTaskStatus::Pending => 'gray',
                        DesignTaskStatus::InReview => 'warning',
                        DesignTaskStatus::NeedsRevision => 'danger',
                        DesignTaskStatus::Approved => 'success',
                    })
                    ->icon(fn (DesignTaskStatus $state): string => match ($state) {
                        DesignTaskStatus::Pending => 'heroicon-o-clock',
                        DesignTaskStatus::InReview => 'heroicon-o-eye',
                        DesignTaskStatus::NeedsRevision => 'heroicon-o-exclamation-circle',
                        DesignTaskStatus::Approved => 'heroicon-o-check-circle',
                    }),

                Tables\Columns\IconColumn::make('is_extra')
                    ->label('إضافي')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('وقت التسليم')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('لم يسلم بعد')
                    ->icon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('موعد الجدولة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->placeholder('فورية')
                    ->icon('heroicon-o-calendar-days')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(DesignTaskStatus::class),

                Tables\Filters\SelectFilter::make('priority')
                    ->label('الأهمية')
                    ->options(DesignTaskPriority::class),

                Tables\Filters\SelectFilter::make('designer_id')
                    ->label('المصمم')
                    ->relationship('designer', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? 'مصمم #'.$record->id)
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('assigner_id')
                    ->label('المُنشئ')
                    ->relationship('assigner', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) ?? false),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    // درج المراجعة التفاعلي السريع (Slide-Over Drawer)
                    Tables\Actions\Action::make('viewDetails')
                        ->label('عرض ومراجعة المهمة')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->slideOver()
                        ->modalHeading(fn (DesignTask $record): string => 'مراجعة مهمة: '.$record->display_client_name)
                        ->modalWidth('6xl')
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                        ->modalContent(function (DesignTask $record) {
                            return view('filament.modals.design-task-details', [
                                'task' => $record->load(['designer.user', 'client.category', 'client.location', 'assigner', 'contract']),
                            ]);
                        }),

                    Tables\Actions\EditAction::make()
                        ->slideOver(),

                    // إجراء الموافقة
                    Tables\Actions\Action::make('approve')
                        ->label('اعتماد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (DesignTask $record): bool => $record->status === DesignTaskStatus::InReview)
                        ->action(function (DesignTask $record): void {
                            if ($record->is_template_update && $record->client_id && $record->template_type) {
                                $template = ClientTemplate::firstOrNew([
                                    'client_id' => $record->client_id,
                                    'type' => $record->template_type,
                                ]);

                                if ($record->design_files) {
                                    $sourceFile = $record->design_files[0] ?? null;
                                    if ($sourceFile) {
                                        if ($template->file && $template->file !== $sourceFile && Storage::disk('public')->exists($template->file)) {
                                            Storage::disk('public')->delete($template->file);
                                        }
                                        $template->file = $sourceFile;
                                    }
                                }

                                if ($record->local_path) {
                                    $template->local_path = $record->local_path;
                                }

                                $template->updated_at = now();
                                $template->save();
                            } else {
                                if ($record->is_subscribed_client && $record->client) {
                                    $record->client->increment('cliche_counter');
                                }
                            }

                            $record->update([
                                'status' => DesignTaskStatus::Approved,
                            ]);

                            Notification::make()
                                ->title('تم اعتماد التصميم بنجاح ✅')
                                ->success()
                                ->send();
                        }),

                    // إجراء طلب التعديل
                    Tables\Actions\Action::make('requestRevision')
                        ->label('طلب تعديل')
                        ->icon('heroicon-o-exclamation-circle')
                        ->color('danger')
                        ->visible(fn (DesignTask $record): bool => $record->status === DesignTaskStatus::InReview)
                        ->form(function (DesignTask $record) {
                            $clientId = $record->client_id ?? 'unknown';
                            $dir = "clients/{$clientId}/design-tasks/{$record->id}/revisions";

                            return [
                                Forms\Components\Textarea::make('revision_notes')
                                    ->label('ملاحظات التعديل')
                                    ->required()
                                    ->rows(4),
                                Forms\Components\FileUpload::make('revision_files')
                                    ->label('صور ومرفقات التعديل (اختياري)')
                                    ->multiple()
                                    ->image()
                                    ->disk('public')
                                    ->directory($dir)
                                    ->maxFiles(5)
                                    ->maxSize(config('filesystems.max_file_size', 10240))
                                    ->helperText('💡 يمكنك إرفاق صور توضيحية أو لقطات شاشة أو نسخها ولصقها مباشرة (Ctrl + V)'),
                            ];
                        })
                        ->action(function (DesignTask $record, array $data): void {
                            $record->update([
                                'status' => DesignTaskStatus::NeedsRevision,
                                'revision_notes' => $data['revision_notes'],
                                'revision_files' => $data['revision_files'] ?? null,
                                'design_files' => null,
                            ]);
                            Notification::make()
                                ->title('تم طلب التعديلات بنجاح')
                                ->success()
                                ->send();
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
        return [];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) هذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDesignTasks::route('/'),
            'create' => Pages\CreateDesignTask::route('/create'),
            // 'edit' => Pages\EditDesignTask::route('/{record}/edit'),
        ];
    }
}
