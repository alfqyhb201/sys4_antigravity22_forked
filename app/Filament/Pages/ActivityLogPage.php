<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'سجل النشاطات';

    protected static ?string $title = 'مراقبة سجل النشاطات والتغييرات';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationGroup = 'الاعدادات';

    public static string $view = 'filament.pages.activity-log';

    public string $statsPeriod = 'today';

    public ?string $activeQuickFilter = null;

    /**
     * قائمة الحقول النظامية المستبعدة من مقارنة وتفاصيل التغييرات في المودال.
     */
    public static array $excludedFields = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'email_verified_at',
    ];

    public static array $modelsMap = [

        'App\Models\Client' => 'عميل',
        'App\Models\Contract' => 'اشتراك',
        'App\Models\Invoice' => 'فاتورة',
        'App\Models\Receipt' => 'سند قبض',
        'App\Models\ReceiptAllocation' => 'تخصيص سند',
        'App\Models\Complaint' => 'شكوى',
        'App\Models\DesignTask' => 'مهمة تصميم',
        'App\Models\Designer' => 'مصمم',
        'App\Models\Order' => 'طلب',
        'App\Models\ClientTagDistribution' => 'توزيع تصميم',
        'App\Models\User' => 'مستخدم',
        'App\Models\Tag' => 'وسم',
        'App\Models\Idea' => 'فكرة',
        'App\Models\Category' => 'فئة',
        'App\Models\Currency' => 'عملة',
        'App\Models\CurrencySetting' => 'إعدادات العملة',
        'App\Models\SystemSetting' => 'الإعدادات العامة',
        'App\Models\ExchangeRate' => 'سعر صرف',
        'App\Models\Location' => 'موقع',
        'App\Models\SocialMedia' => 'تواصل اجتماعي',
        'App\Models\ClientSocialMedia' => 'منصة تواصل عميل',
        'App\Models\ClientNeed' => 'حاجة عميل',
        'App\Models\TagGroup' => 'مجموعة وسوم',
        'App\Models\Custody' => 'عهدة',
        'App\Models\ClientTemplate' => 'قالب عميل',
        'Spatie\Permission\Models\Role' => 'دور / صلاحية',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin'])
            || $user->can('view_activity_log');
    }

    public static function getSubjectRecord(Activity $record): ?\Illuminate\Database\Eloquent\Model
    {
        $subject = $record->subject;

        if (! $subject && $record->subject_type && class_exists($record->subject_type)) {
            try {
                $modelClass = $record->subject_type;
                if (method_exists($modelClass, 'withTrashed')) {
                    $subject = $modelClass::withTrashed()->find($record->subject_id);
                } else {
                    $subject = $modelClass::find($record->subject_id);
                }
            } catch (\Throwable $e) {
                $subject = null;
            }
        }

        return $subject;
    }

    public static function getSubjectUrl(Activity $record): ?string
    {
        $subjectType = $record->subject_type;
        $subjectId = $record->subject_id;

        if (! $subjectType) {
            return null;
        }

        try {
            // الصفحات المخصصة
            if ($subjectType === 'App\Models\SystemSetting') {
                return \App\Filament\Pages\GeneralSettingsPage::getUrl();
            }
            if ($subjectType === 'App\Models\CurrencySetting') {
                return \App\Filament\Pages\CurrencySettingsPage::getUrl();
            }
            if ($subjectType === 'App\Models\Order') {
                return \App\Filament\Pages\CreateOrder::getUrl();
            }
            if ($subjectType === 'App\Models\ClientTagDistribution') {
                $dist = static::getSubjectRecord($record);
                if ($dist instanceof \App\Models\ClientTagDistribution && $dist->clientDesigner?->client_id) {
                    return \App\Filament\Resources\ClientResource::getUrl('view', ['record' => $dist->clientDesigner->client_id]);
                }

                return \App\Filament\Pages\TagDistribution::getUrl();
            }

            // النماذج الوسيطة والمرتبطة (مثل تخصيص السندات)
            if ($subjectType === 'App\Models\ReceiptAllocation') {
                $alloc = static::getSubjectRecord($record);
                if ($alloc instanceof \App\Models\ReceiptAllocation) {
                    if ($alloc->receipt_id && \App\Models\Receipt::where('id', $alloc->receipt_id)->exists()) {
                        return \App\Filament\Resources\ReceiptResource::getUrl('edit', ['record' => $alloc->receipt_id]);
                    }
                    if ($alloc->invoice_id && \App\Models\Invoice::where('id', $alloc->invoice_id)->exists()) {
                        return \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $alloc->invoice_id]);
                    }
                }

                return null;
            }

            // تحديد المورد من لوحة Filament
            $panel = filament()->getCurrentPanel() ?? filament()->getPanel('admin');
            $resource = $panel->getModelResource($subjectType);

            if (! $resource) {
                $customMap = [
                    'App\Models\User' => \App\Filament\Resources\UsersResource::class,
                    'Spatie\Permission\Models\Role' => \App\Filament\Resources\RoleResource::class,
                    'App\Models\ClientTemplate' => \App\Filament\Resources\ClientTemplateResource::class,
                    'App\Models\ClientSocialMedia' => \App\Filament\Resources\ClientSocialMediaResource::class,
                    'App\Models\ExchangeRate' => \App\Filament\Resources\CurrencyResource::class,
                ];
                $resource = $customMap[$subjectType] ?? null;
            }

            if (! $resource || ! class_exists($resource)) {
                return null;
            }

            $subject = static::getSubjectRecord($record);

            // في حال تم حذف العنصر نهائياً من قاعدة البيانات ولا يوجد سجل له
            if ($subjectId && ! $subject) {
                return null;
            }

            if ($subject) {
                if ($resource::hasPage('view')) {
                    return $resource::getUrl('view', ['record' => $subject]);
                }

                if ($resource::hasPage('edit')) {
                    return $resource::getUrl('edit', ['record' => $subject]);
                }
            }

            if ($resource::hasPage('index')) {
                return $resource::getUrl('index');
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    public static function getSubjectName(Activity $record): ?string
    {
        $subject = static::getSubjectRecord($record);

        if ($subject instanceof \App\Models\Client) {
            $company = $subject->company;
            $clientName = $subject->client_name;
            if ($company && $clientName && $company !== $clientName) {
                return "{$company} ({$clientName})";
            }

            return $company ?: ($clientName ?: "عميل #{$record->subject_id}");
        }

        if ($subject instanceof \App\Models\ClientTagDistribution) {
            $tagName = $subject->tag?->name;
            $client = $subject->clientDesigner?->client;
            $clientName = $client?->company ?: $client?->client_name;
            $ideaName = $subject->idea?->name ?: $subject->custom_idea;

            $parts = [];
            if ($tagName) {
                $parts[] = $tagName;
            } elseif ($ideaName) {
                $parts[] = $ideaName;
            } else {
                $parts[] = "تصميم #{$subject->id}";
            }

            if ($clientName) {
                $parts[] = $clientName;
            }

            return implode(' — ', $parts);
        }

        if ($subject instanceof \App\Models\Complaint) {
            $client = $subject->client;
            $clientName = $client?->company ?: $client?->client_name;
            $desc = trim(strip_tags($subject->description ?? ''));
            $descSnippet = $desc ? \Illuminate\Support\Str::limit($desc, 35) : null;
            $prefix = "شكوى #{$subject->id}";
            if ($clientName) {
                return $descSnippet ? "{$prefix} ({$descSnippet}) — {$clientName}" : "{$prefix} — {$clientName}";
            }

            return $descSnippet ? "{$prefix} ({$descSnippet})" : $prefix;
        }

        if ($subject instanceof \App\Models\Designer) {
            return $subject->user?->name ?: "مصمم #{$subject->id}";
        }

        if ($subject instanceof \App\Models\Currency) {
            return $subject->name ?: ($subject->code ?: ($subject->symbol ?: "عملة #{$subject->id}"));
        }

        if ($subject instanceof \App\Models\SystemSetting) {
            return $subject->title ?? $subject->label ?? $subject->key ?? 'الإعدادات العامة';
        }

        if ($subject instanceof \App\Models\CurrencySetting) {
            return $subject->key ?? 'إعدادات العملة';
        }

        if ($subject && method_exists($subject, 'client') && $subject->client) {
            $client = $subject->client;
            $clientName = $client->company ?: $client->client_name;
            $identifier = $subject->reference_number ?? $subject->invoice_number ?? $subject->receipt_number ?? $subject->contract_number ?? $subject->title ?? $subject->name ?? "#{$record->subject_id}";

            return "{$identifier} — {$clientName}";
        }

        if ($subject instanceof \App\Models\ReceiptAllocation) {
            $ref = $subject->receipt?->reference_number ?? ('سند #'.$subject->receipt_id);
            $invRef = $subject->invoice?->reference_number ?? ('فاتورة #'.$subject->invoice_id);

            return "{$ref} ➔ {$invRef}";
        }

        if ($subject) {
            return $subject->name ?? $subject->title ?? $subject->company ?? $subject->client_name ?? $subject->reference_number ?? $subject->key ?? null;
        }

        // Fallback from properties if subject was hard deleted
        $props = $record->properties;
        $attrs = is_array($props) ? ($props['attributes'] ?? $props['old'] ?? []) : ($props ? ($props['attributes'] ?? $props['old'] ?? []) : []);

        if ($record->subject_type === 'App\Models\ClientTagDistribution') {
            $tagId = $attrs['tag_id'] ?? null;
            $tagName = $tagId ? \App\Models\Tag::find($tagId)?->name : null;
            $customIdea = $attrs['custom_idea'] ?? null;
            $clientDesId = $attrs['client_designer_id'] ?? null;
            $clientName = null;
            if ($clientDesId) {
                $cd = \App\Models\ClientDesigner::with('client')->find($clientDesId);
                $clientName = $cd?->client?->company ?: $cd?->client?->client_name;
            }

            $parts = array_filter([$tagName ?: $customIdea ?: ('تصميم #'.$record->subject_id), $clientName]);
            if (! empty($parts)) {
                return implode(' — ', $parts);
            }
        }

        if (! empty($attrs['company']) || ! empty($attrs['client_name'])) {
            $comp = $attrs['company'] ?? '';
            $cname = $attrs['client_name'] ?? '';
            if ($comp && $cname && $comp !== $cname) {
                return "{$comp} ({$cname})";
            }

            return $comp ?: $cname;
        }

        if (! empty($attrs['name'])) {
            return $attrs['name'];
        }

        if (! empty($attrs['title'])) {
            return $attrs['title'];
        }

        if (! empty($attrs['reference_number'])) {
            return $attrs['reference_number'];
        }

        if (! empty($attrs['key'])) {
            return $attrs['key'];
        }

        return null;
    }

    public static function translateModel(string $subjectType): string
    {
        return static::$modelsMap[$subjectType] ?? class_basename($subjectType);
    }

    public static function translateEvent(string $description): string
    {
        return match ($description) {
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'restored' => 'استعادة',
            default => match (true) {
                str_starts_with($description, 'تسجيل سند دفعة مقدمة') => 'سند دفعة مقدمة',
                str_starts_with($description, 'تخصيص مبلغ') => 'تخصيص دفعة',
                default => \Illuminate\Support\Str::limit($description, 22),
            },
        };
    }

    public static function eventColor(string $description): string
    {
        return match ($description) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            default => match (true) {
                str_starts_with($description, 'تسجيل سند دفعة مقدمة') => 'success',
                str_starts_with($description, 'تخصيص مبلغ') => 'info',
                default => 'primary',
            },
        };
    }

    public function setStatsPeriod(string $period): void
    {
        if (in_array($period, ['today', '7days', 'this_month', 'all'])) {
            $this->statsPeriod = $period;
        }
    }

    public function getStatsPeriodLabel(): string
    {
        return match ($this->statsPeriod) {
            'today' => 'اليوم',
            '7days' => 'آخر 7 أيام',
            'this_month' => 'هذا الشهر',
            'all' => 'جميع الفترات',
            default => 'اليوم',
        };
    }

    public function getQuickStats(): array
    {
        $query = Activity::query();

        match ($this->statsPeriod) {
            'today' => $query->whereDate('created_at', now()->toDateString()),
            '7days' => $query->where('created_at', '>=', now()->subDays(7)->startOfDay()),
            'this_month' => $query->where('created_at', '>=', now()->startOfMonth()),
            'all' => null,
        };

        $total = (clone $query)->count();
        $deleted = (clone $query)->where('description', 'deleted')->count();

        $topUserRecord = (clone $query)
            ->whereNotNull('causer_id')
            ->select('causer_id', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('causer_id')
            ->orderByDesc('count')
            ->first();

        $topUser = null;
        if ($topUserRecord) {
            $user = User::find($topUserRecord->causer_id);
            if ($user) {
                $topUser = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'count' => (int) $topUserRecord->count,
                ];
            }
        }

        $topSubjectRecord = (clone $query)
            ->whereNotNull('subject_type')
            ->select('subject_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('subject_type')
            ->orderByDesc('count')
            ->first();

        $topSubject = null;
        if ($topSubjectRecord) {
            $topSubject = [
                'type' => $topSubjectRecord->subject_type,
                'name' => static::translateModel($topSubjectRecord->subject_type),
                'count' => (int) $topSubjectRecord->count,
            ];
        }

        return [
            'total' => $total,
            'deleted' => $deleted,
            'topUser' => $topUser,
            'topSubject' => $topSubject,
            'period' => $this->statsPeriod,
            'periodLabel' => $this->getStatsPeriodLabel(),
        ];
    }

    public function filterByAction(string $action = 'deleted'): void
    {
        $this->tableFilters = ['description' => ['value' => $action]];
        $this->activeQuickFilter = 'نوع الحدث: '.static::translateEvent($action);
        $this->resetPage();
    }

    public function filterByUser(int $userId, ?string $userName = null): void
    {
        $this->tableFilters = ['causer_id' => ['value' => (string) $userId]];
        $this->activeQuickFilter = 'المستخدم: '.($userName ?: User::find($userId)?->name ?: ('#'.$userId));
        $this->resetPage();
    }

    public function filterBySubjectType(string $subjectType, ?string $modelName = null): void
    {
        $this->tableFilters = ['subject_type' => ['value' => $subjectType]];
        $this->activeQuickFilter = 'نوع العنصر: '.($modelName ?: static::translateModel($subjectType));
        $this->resetPage();
    }

    public function applyStatsPeriodToTable(): void
    {
        $dates = match ($this->statsPeriod) {
            'today' => [
                'created_from' => now()->toDateString(),
                'created_until' => now()->toDateString(),
            ],
            '7days' => [
                'created_from' => now()->subDays(7)->toDateString(),
                'created_until' => now()->toDateString(),
            ],
            'this_month' => [
                'created_from' => now()->startOfMonth()->toDateString(),
                'created_until' => now()->toDateString(),
            ],
            default => [
                'created_from' => null,
                'created_until' => null,
            ],
        };

        $this->tableFilters = ['created_at' => $dates];
        $this->activeQuickFilter = 'الفترة: '.$this->getStatsPeriodLabel();
        $this->resetPage();
    }

    public function resetQuickFilter(): void
    {
        $this->tableFilters = [];
        $this->activeQuickFilter = null;
        if (isset($this->tableSearch)) {
            $this->tableSearch = '';
        }
        $this->resetPage();
    }

    public function table(Table $table): Table
    {

        return $table
            ->query(
                Activity::query()
                    ->with(['causer', 'subject'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('المستخدم')
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user')
                    ->placeholder('النظام / تلقائي')
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('نوع الحدث')
                    ->badge()
                    ->color(fn (string $state): string => static::eventColor($state))
                    ->formatStateUsing(fn (string $state): string => static::translateEvent($state))
                    ->tooltip(fn (Activity $record): ?string => in_array($record->description, ['created', 'updated', 'deleted', 'restored']) ? null : $record->description)
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('نوع العنصر')
                    ->formatStateUsing(fn (string $state): string => static::translateModel($state))
                    ->description(fn (Activity $record): ?string => $record->subject_id ? ('#'.$record->subject_id) : null)
                    ->icon('heroicon-m-document')
                    ->color('gray')
                    ->url(fn (Activity $record): ?string => static::getSubjectUrl($record))
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('subject_id', 'like', "%{$search}%")
                            ->orWhere('subject_type', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('subject_name')
                    ->label('اسم العنصر / العميل')
                    ->state(fn (Activity $record): ?string => static::getSubjectName($record))
                    ->weight(FontWeight::Medium)
                    ->icon(fn (Activity $record) => $record->subject_type === 'App\Models\Client' ? 'heroicon-m-building-office-2' : null)
                    ->url(fn (Activity $record): ?string => static::getSubjectUrl($record))
                    ->openUrlInNewTab()
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('properties->attributes->company', 'like', "%{$search}%")
                                ->orWhere('properties->attributes->client_name', 'like', "%{$search}%")
                                ->orWhere('properties->old->company', 'like', "%{$search}%")
                                ->orWhere('properties->old->client_name', 'like', "%{$search}%")
                                ->orWhere('properties->attributes->name', 'like', "%{$search}%")
                                ->orWhere('properties->old->name', 'like', "%{$search}%")
                                ->orWhere('properties->attributes->title', 'like', "%{$search}%")
                                ->orWhere('properties->old->title', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت والتاريخ')
                    ->since()
                    ->description(fn (Activity $record): ?string => $record->created_at?->translatedFormat('Y-m-d h:i A'))
                    ->sortable()
                    ->icon('heroicon-m-clock'),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('نوع الحدث')
                    ->options([
                        'created' => 'إنشاء',
                        'updated' => 'تعديل',
                        'deleted' => 'حذف',
                        'restored' => 'استعادة',
                        'advance_receipt' => 'سند دفعة مقدمة (فائض سداد)',
                        'allocation' => 'تخصيص دفعة مقدمة',
                        'custom' => 'أحداث مخصصة أخرى',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return match ($value) {
                            'created', 'updated', 'deleted', 'restored' => $query->where('description', $value),
                            'advance_receipt' => $query->where('description', 'like', 'تسجيل سند دفعة مقدمة%'),
                            'allocation' => $query->where('description', 'like', 'تخصيص مبلغ%'),
                            'custom' => $query->whereNotIn('description', ['created', 'updated', 'deleted', 'restored'])
                                ->where('description', 'not like', 'تسجيل سند دفعة مقدمة%')
                                ->where('description', 'not like', 'تخصيص مبلغ%'),
                            default => $query->where('description', $value),
                        };
                    }),

                SelectFilter::make('subject_type')
                    ->label('نوع العنصر')
                    ->options(static::$modelsMap),

                SelectFilter::make('causer_id')
                    ->label('المستخدم')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('من تاريخ'),
                        DatePicker::make('created_until')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),

            ])
            ->actions([
                Tables\Actions\Action::make('open_subject')
                    ->label('الانتقال للعنصر')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Activity $record): ?string => static::getSubjectUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (Activity $record): bool => (bool) static::getSubjectUrl($record)),

                Tables\Actions\Action::make('view_details')
                    ->label('عرض التفاصيل')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->modalHeading(function (Activity $record): string {
                        $subjectName = static::getSubjectName($record);
                        $eventName = static::translateEvent($record->description);
                        $modelName = static::translateModel($record->subject_type);

                        return 'تفاصيل النشاط #'.$record->id.' — '.$eventName.' '.$modelName.($subjectName ? ' ('.$subjectName.')' : '');
                    })
                    ->modalContent(fn (Activity $record) => view('filament.pages.activity-log-modal', ['activity' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
            ])
            ->emptyStateHeading('لا توجد نشاطات مطابقة')
            ->emptyStateDescription('لم يتم العثور على أي سجلات نشاط مطابقة لمعايير البحث أو الفلاتر المحددة.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateActions([
                Tables\Actions\Action::make('clear_filters')
                    ->label('إزالة الفلاتر وعرض الكل')
                    ->icon('heroicon-m-funnel')
                    ->color('primary')
                    ->button()
                    ->action(fn () => $this->resetQuickFilter()),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}
