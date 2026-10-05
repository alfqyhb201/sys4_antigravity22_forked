<?php

namespace App\Filament\Resources;

use App\Filament\Enums\ClientTemplateType;
use App\Filament\Resources\ClientTemplateResource\Pages;
use App\Models\Client;
use App\Models\ClientTemplate;
use App\Models\Designer;
use App\Models\DesignTask;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientTemplateResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'قوالب العملاء';

    protected static ?string $pluralModelLabel = 'قوالب العملاء';

    protected static ?string $modelLabel = 'قوالب العملاء';

    protected static ?string $slug = 'client-templates';

    // protected static ?string $recordTitleAttribute = 'company';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasRole(['admin', 'super_admin']) ||
            $user->can('view_any_client_template')
        );
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return Client::query()->with('templates');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('اسم الشركة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('templates_status')
                    ->label('حالة القوالب')
                    ->html()
                    ->getStateUsing(function (Client $record) {
                        $total = count(ClientTemplateType::cases());
                        $count = $record->templates->filter(fn ($t) => ! empty($t->file))->count();
                        if ($count === $total) {
                            return '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">✓ مكتمل ('.$count.'/'.$total.')</span>';
                        }
                        if ($count === 0) {
                            return '<span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">بدون قوالب (0/'.$total.')</span>';
                        }

                        return '<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">جزئي ('.$count.'/'.$total.')</span>';
                    })
                    ->alignCenter(),
                ...collect(ClientTemplateType::cases())->map(function (ClientTemplateType $type) {
                    return Tables\Columns\TextColumn::make($type->value)
                        ->label($type->getLabel())
                        ->html()
                        ->alignCenter()
                        ->getStateUsing(function (Client $record) use ($type) {
                            $template = $record->templates->firstWhere('type', $type->value);

                            if (! $template || ! $template->file) {
                                $canUpload = auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('update_client_template') || auth()->user()?->can('create_client_template');
                                if ($canUpload) {
                                    return '<span class="inline-flex items-center gap-0.5 rounded-lg border border-dashed border-gray-300 px-2 py-1 text-[11px] font-semibold text-gray-400 hover:border-primary-500 hover:text-primary-600 dark:border-gray-700 dark:text-gray-500 dark:hover:border-primary-400 dark:hover:text-primary-300 transition cursor-pointer" title="انقر لرفع '.$type->getLabel().'">+ رفع</span>';
                                }

                                return '<span class="text-gray-400 text-xs">-</span>';
                            }

                            $imgUrl = $template->thumbnail_url;

                            return '<div class="group/thumb inline-flex items-center gap-1.5 cursor-pointer" title="انقر للمعاينة والتفاصيل">'
                                .'<div class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-50 shadow-sm transition-all group-hover/thumb:border-primary-500 group-hover/thumb:shadow-md dark:border-gray-700 dark:bg-gray-800">'
                                .'<img src="'.$imgUrl.'" loading="lazy" decoding="async" class="h-12 w-12 object-cover transition-transform duration-200 group-hover/thumb:scale-110" />'
                                .'</div>';
                        })
                        ->action(
                            Tables\Actions\Action::make('manage_col_'.$type->value)
                                ->disabled(function (Client $record) use ($type) {
                                    $template = $record->templates->firstWhere('type', $type->value);
                                    $user = auth()->user();
                                    if (! $user) {
                                        return true;
                                    }
                                    if ($user->hasRole(['admin', 'super_admin'])) {
                                        return false;
                                    }
                                    if ($template && $template->file) {
                                        return ! ($user->can('view_client_template') || $user->can('view_any_client_template'));
                                    }

                                    return ! ($user->can('update_client_template') || $user->can('create_client_template'));
                                })
                                ->modalHeading(fn (Client $record) => $record->templates->firstWhere('type', $type->value)?->file ? 'معاينة: '.$type->getLabel().' - '.$record->company : 'رفع '.$type->getLabel().' - '.$record->company)
                                ->modalWidth(fn (Client $record) => $record->templates->firstWhere('type', $type->value)?->file ? '3xl' : 'lg')
                                ->modalSubmitAction(fn (Client $record) => $record->templates->firstWhere('type', $type->value)?->file ? false : null)
                                ->extraModalFooterActions(function (Client $record) use ($type): array {
                                    $template = $record->templates->firstWhere('type', $type->value);
                                    if (! $template || ! $template->file) {
                                        return [];
                                    }

                                    $canDelete = auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('delete_client_template');
                                    if (! $canDelete) {
                                        return [];
                                    }

                                    return [
                                        Tables\Actions\Action::make('delete_single_template_'.$type->value)
                                            ->label('حذف هذا القالب')
                                            ->icon('heroicon-m-trash')
                                            ->color('danger')
                                            ->requiresConfirmation()
                                            ->modalHeading('تأكيد حذف القالب')
                                            ->modalDescription('هل أنت متأكد من حذف '.$type->getLabel().' لهذا العميل نهائياً؟')
                                            ->action(function () use ($template, $type) {
                                                $template->delete();
                                                Notification::make()
                                                    ->title('تم حذف '.$type->getLabel().' بنجاح')
                                                    ->success()
                                                    ->send();
                                            }),
                                    ];
                                })
                                ->form(function (Client $record) use ($type) {
                                    $template = $record->templates->firstWhere('type', $type->value);
                                    if ($template && $template->file) {
                                        return [];
                                    }

                                    return [
                                        FileUpload::make('file')
                                            ->label('ملف '.$type->getLabel())
                                            ->disk('public')
                                            ->directory('client-templates')
                                            ->image()
                                            ->imageEditor()
                                            ->required()
                                            ->helperText('اختر ملف الصورة المراد رفعها لهذا النموذج'),
                                        TextInput::make('local_path')
                                            ->label('المسار المحلي للملف المصدري (اختياري)')
                                            ->placeholder('مثال: D:\Designs\Clients\Template.psd'),
                                    ];
                                })
                                ->modalContent(function (Client $record) use ($type) {
                                    $template = $record->templates->firstWhere('type', $type->value);
                                    if ($template && $template->file) {
                                        return view('filament.modals.client-template-preview-modal', [
                                            'client' => $record,
                                            'template' => $template,
                                            'typeLabel' => $type->getLabel(),
                                        ]);
                                    }

                                    return null;
                                })
                                ->action(function (Client $record, array $data) use ($type): void {
                                    if (! empty($data['file'])) {
                                        $template = ClientTemplate::firstOrNew([
                                            'client_id' => $record->id,
                                            'type' => $type->value,
                                        ]);
                                        $template->file = $data['file'];
                                        $template->local_path = $data['local_path'] ?? null;
                                        $template->updated_at = now();
                                        $template->save();

                                        Notification::make()
                                            ->title('تم رفع '.$type->getLabel().' بنجاح')
                                            ->success()
                                            ->send();
                                    }
                                })
                        );
                })->toArray(),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_template')
                    ->label('فلترة حسب القوالب')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('نوع القالب')
                            ->options(ClientTemplateType::class)
                            ->placeholder('اختر نوع القالب'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['type'], function (Builder $query, $type) {
                            return $query->whereHas('templates', function (Builder $q) use ($type) {
                                $q->where('type', $type);
                            });
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('uploadTemplates')
                    ->label('رفع القوالب')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('update_client_template') || auth()->user()?->can('create_client_template'))
                    ->modalHeading(fn (Client $record) => 'إدارة ورفع قوالب: '.$record->company)
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('حفظ جميع القوالب')
                    ->fillForm(function (Client $record): array {
                        $data = [];
                        foreach (ClientTemplateType::cases() as $type) {
                            $template = $record->templates->firstWhere('type', $type->value);
                            $data[$type->value.'_file'] = $template?->file;
                            $data[$type->value.'_local_path'] = $template?->local_path;
                        }

                        return $data;
                    })
                    ->form([
                        Tabs::make('ClientTemplates')
                            ->tabs(
                                collect(ClientTemplateType::cases())->map(function (ClientTemplateType $type) {
                                    return Tab::make($type->getLabel())
                                        ->icon('heroicon-m-swatch')
                                        ->badge(function (Client $record) use ($type) {
                                            return $record->templates->firstWhere('type', $type->value)?->file ? 'متوفر' : 'فارغ';
                                        })
                                        ->badgeColor(function (Client $record) use ($type) {
                                            return $record->templates->firstWhere('type', $type->value)?->file ? 'success' : 'gray';
                                        })
                                        ->schema([
                                            FileUpload::make($type->value.'_file')
                                                ->label('ملف '.$type->getLabel())
                                                ->disk('public')
                                                ->directory('client-templates')
                                                ->image()
                                                ->imageEditor()
                                                ->downloadable()
                                                ->openable()
                                                ->helperText('ارفع صورة نموذج '.$type->getLabel().' (PNG, JPG, WEBP, إلخ)'),
                                            TextInput::make($type->value.'_local_path')
                                                ->label('المسار المحلي للملف المصدري (اختياري)')
                                                ->placeholder('مثال: D:\Designs\Clients\Template.psd')
                                                ->helperText('مسار ملف العمل على جهاز المصمم (مثل ملفات PSD أو AI)'),
                                        ]);
                                })->toArray()
                            ),
                    ])
                    ->action(function (Client $record, array $data): void {
                        $savedCount = 0;
                        foreach (ClientTemplateType::cases() as $type) {
                            $file = $data[$type->value.'_file'] ?? null;
                            $localPath = $data[$type->value.'_local_path'] ?? null;
                            $existing = $record->templates->firstWhere('type', $type->value);

                            if ($file) {
                                $template = ClientTemplate::firstOrNew([
                                    'client_id' => $record->id,
                                    'type' => $type->value,
                                ]);
                                $template->file = $file;
                                $template->local_path = $localPath;
                                $template->updated_at = now();
                                $template->save();
                                $savedCount++;
                            } elseif ($existing && empty($file)) {
                                $canDelete = auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('delete_client_template');
                                if ($canDelete) {
                                    $existing->delete();
                                }
                            }
                        }

                        Notification::make()
                            ->title('تم حفظ قوالب العميل بنجاح')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('viewGallery')
                    ->label(' ')
                    ->icon('heroicon-m-photo')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('5xl')
                    ->modalHeading(fn (Client $record) => 'معرض قوالب: '.$record->company)
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin']) || auth()->user()?->can('view_client_template') || auth()->user()?->can('view_any_client_template'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Client $record) => view('filament.modals.client-templates-gallery-drawer', [
                        'record' => $record,
                    ])),
                Tables\Actions\Action::make('sendUpdateTask')
                    ->label('إرسال مهمة تحديث')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->hasRole(['admin', 'super_admin', 'supervisor']) || auth()->user()?->can('create_design_task'))
                    ->form([
                        Forms\Components\Select::make('designer_id')
                            ->label('المصمم المُكلّف')
                            ->options(Designer::with('user')->get()->pluck('user.name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('template_types')
                            ->label('النماذج المطلوب تحديثها')
                            ->options(ClientTemplateType::class)
                            ->multiple()
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات إضافية')
                            ->rows(3),
                    ])
                    ->action(function (Client $record, array $data) {
                        foreach ($data['template_types'] as $type) {
                            $templateLabel = ClientTemplateType::from($type)?->getLabel() ?? $type;
                            $description = "تحديث نموذج {$templateLabel} للعميل {$record->company}";

                            if (! empty($data['notes'])) {
                                $description .= "\n\nملاحظات:\n".$data['notes'];
                            }

                            DesignTask::create([
                                'designer_id' => $data['designer_id'],
                                'assigner_id' => auth()->id(),
                                'client_id' => $record->id,
                                'is_subscribed_client' => true,
                                'is_template_update' => true,
                                'template_type' => $type,
                                'priority' => 'medium',
                                'status' => 'pending',
                                'description' => $description,
                            ]);
                        }

                        $count = count($data['template_types']);
                        Notification::make()
                            ->title("تم إرسال {$count} مهمة بنجاح")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientTemplates::route('/'),
        ];
    }
}
