<?php

namespace App\Filament\Pages;

use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Designer;
use App\Services\DesignerDistributionService;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DesignerDistribution extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'التخطيط';

    protected static ?string $navigationLabel = 'توزيع المصممين';

    protected static ?string $title = 'توزيع المصممين على العملاء';

    protected static string $view = 'filament.pages.designer-distribution';

    public ?string $selectedWeek = null;

    public ?array $distributionStats = null;

    public ?string $activeTab = 'all';

    private ?array $cachedTabs = null;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_designer_distribution');
    }

    public function mount(): void
    {
        $this->selectedWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->loadDistributionStats();
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function isPastWeek(): bool
    {
        return Carbon::parse($this->selectedWeek)
            ->startOfWeek()
            ->lt(Carbon::now()->startOfWeek());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('client.company')
                    ->label('اسم الشركة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('contract.billing_cycle')
                    ->label('دورة الفوترة')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'weekly' => 'أسبوعي',
                        'yearly' => 'سنوي',
                        default => 'شهري',
                    }),

                TextColumn::make('client.category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('client.customer_rating_value')
                    ->label('التقييم')
                    ->badge()
                    ->color('danger'),

                TextColumn::make('contract.weekly_designs_count')
                    ->label('التصاميم الأسبوعية')
                    ->badge()
                    ->color('success')
                    ->default('0'),

                TextColumn::make('designer.rate')
                    ->label('تقييم المصمم')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 8 => 'success',
                        $state >= 6 => 'warning',
                        default => 'danger',
                    }),

                TextColumn::make('week_start_date')
                    ->label('تاريخ الأسبوع')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->actions([
                Action::make('changeDesigner')
                    ->label('نقل الى مصمم اخر')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Select::make('designer_id')
                            ->label('اختر مصمم')
                            ->options(function () {
                                return Designer::with('user')
                                    ->get()
                                    ->pluck('user.name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->visible(fn () => ! $this->isPastWeek())
                    ->action(function (ClientDesigner $record, array $data) {
                        $record->update([
                            'designer_id' => $data['designer_id'],
                        ]);

                        Notification::make()
                            ->title('تم تغيير المصمم بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('swapAssignment')
                    ->label('تبديل مع عميل آخر')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->form([
                        Select::make('target_assignment_id')
                            ->label('اختر العميل للتبديل معه')
                            ->options(function (ClientDesigner $record) {
                                return ClientDesigner::with(['client', 'designer.user', 'contract'])
                                    ->where('week_start_date', $record->week_start_date)
                                    ->where('id', '!=', $record->id)
                                    ->get()
                                    ->mapWithKeys(function ($item) {
                                        $clientName = $item->client->company ?? $item->client->client_name;
                                        $designerName = $item->designer->user->name ?? 'غير معروف';
                                        $cycle = $item->contract ? match ($item->contract->billing_cycle) {
                                            'weekly' => 'أسبوعي',
                                            'yearly' => 'سنوي',
                                            default => 'شهري',
                                        } : '-';

                                        return [$item->id => "{$clientName} (اشتراك {$cycle}) - مع المصمم: {$designerName}"];
                                    });
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (ClientDesigner $record, array $data) {
                        $targetRecord = ClientDesigner::find($data['target_assignment_id']);

                        if (! $targetRecord) {
                            Notification::make()
                                ->title('حدث خطأ')
                                ->body('التعيين المستهدف لم يعد موجوداً')
                                ->danger()
                                ->send();

                            return;
                        }

                        $currentDesignerId = $record->designer_id;
                        $targetDesignerId = $targetRecord->designer_id;

                        $record->update(['designer_id' => $targetDesignerId]);
                        $targetRecord->update(['designer_id' => $currentDesignerId]);

                        Notification::make()
                            ->title('تم تبديل العملاء بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('removeAssignment')
                    ->label('إلغاء التعيين')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => ! $this->isPastWeek())
                    ->action(function (ClientDesigner $record) {
                        $record->delete();

                        Notification::make()
                            ->title('تم إلغاء التعيين بنجاح')
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                    }),

                Action::make('togglePin')
                    ->label(fn (ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'إلغاء التثبيت' : 'تثبيت العميل')
                    ->icon(fn (ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'heroicon-s-lock-closed' : 'heroicon-o-lock-open')
                    ->color(fn (ClientDesigner $record) => $record->client->fixed_designer_id === $record->designer_id ? 'success' : 'gray')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->action(function (ClientDesigner $record) {
                        $client = $record->client;

                        if ($client->fixed_designer_id === $record->designer_id) {
                            $client->update(['fixed_designer_id' => null]);
                            Notification::make()
                                ->title('تم إلغاء تثبيت العميل')
                                ->success()
                                ->send();
                        } else {
                            $client->update(['fixed_designer_id' => $record->designer_id]);
                            Notification::make()
                                ->title('تم تثبيت العميل عند هذا المصمم')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('stats_distributed')
                    ->label(fn () => 'العملاء الموزعين: '.($this->distributionStats['total_assignments'] ?? 0))
                    ->color('success')
                    ->badge()
                    ->disabled(),

                Action::make('stats_pending')
                    ->label(fn () => 'العملاء قيد الانتظار: '.($this->distributionStats['pending_count'] ?? 0))
                    ->color('danger')
                    ->badge()
                    ->disabled(),

                Action::make('addAssignment')
                    ->label('إضافة تعيين يدوي')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->modalHeading('إضافة تعيين جديد')
                    ->modalDescription('املأ النموذج لإضافة تعيين يدوي لعميل أو أكثر على مصمم.')
                    ->form([
                        Select::make('designer_id')
                            ->label('المصمم')
                            ->options(function () {
                                $weekStart = Carbon::parse($this->selectedWeek)->format('Y-m-d');

                                $designers = Designer::with('user')->get();
                                $assignments = ClientDesigner::where('week_start_date', $weekStart)
                                    ->with('contract')
                                    ->get()
                                    ->groupBy('designer_id');

                                return $designers->mapWithKeys(function ($designer) use ($assignments) {
                                    $designerAssignments = $assignments->get($designer->id) ?? collect();
                                    $currentLoad = $designerAssignments->sum(fn ($a) => $a->contract->weekly_designs_count ?? 0);
                                    $maxCapacity = $designer->max_capacity ?? 0;
                                    $available = max(0, $maxCapacity - $currentLoad);

                                    $label = "{$designer->user->name} (متاح: {$available})";

                                    return [$designer->id => $label];
                                });
                            })
                            ->searchable()
                            ->required(),
                        Select::make('contract_ids')
                            ->label('العملاء / الاشتراكات')
                            ->placeholder('اختر عميل أو أكثر...')
                            ->multiple()
                            ->options(function () {
                                $weekStart = Carbon::parse($this->selectedWeek);
                                $weekEnd = $weekStart->copy()->addDays(6)->format('Y-m-d');

                                $assignedIds = ClientDesigner::where('week_start_date', $this->selectedWeek)
                                    ->whereNotNull('contract_id')
                                    ->pluck('contract_id')
                                    ->toArray();

                                $assignedClientIds = ClientDesigner::where('week_start_date', $this->selectedWeek)
                                    ->pluck('client_id')
                                    ->toArray();

                                return Contract::with('client')
                                    ->whereHas('client', function ($query) {
                                        $query->where('status', 1);
                                    })
                                    ->where('status', 'active')
                                    ->where('weekly_designs_count', '>', 0)
                                    ->whereNotIn('id', $assignedIds)
                                    ->whereNotIn('client_id', $assignedClientIds)
                                    ->where('start_date', '<=', $weekEnd)
                                    ->get()
                                    ->mapWithKeys(function ($contract) {
                                        $clientName = $contract->client ? ($contract->client->company ?? $contract->client->client_name) : 'بدون عميل';
                                        $cycle = match ($contract->billing_cycle) {
                                            'weekly' => 'أسبوعي',
                                            'yearly' => 'سنوي',
                                            default => 'شهري',
                                        };
                                        $designsCount = $contract->weekly_designs_count ?? 0;

                                        return [$contract->id => "{$clientName} - اشتراك {$cycle} - تصاميم أسبوعية: {$designsCount}"];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $contractIds = (array) ($data['contract_ids'] ?? []);
                        $contracts = Contract::whereIn('id', $contractIds)->get();

                        foreach ($contracts as $contract) {
                            ClientDesigner::updateOrCreate(
                                [
                                    'contract_id' => $contract->id,
                                    'week_start_date' => $this->selectedWeek,
                                ],
                                [
                                    'client_id' => $contract->client_id,
                                    'designer_id' => $data['designer_id'],
                                ]
                            );
                        }

                        $count = $contracts->count();

                        Notification::make()
                            ->title("تم إضافة {$count} تعيين بنجاح")
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                        $this->cachedTabs = null;
                    }),

                Action::make('distributeTags')
                    ->label('توزيع التاقات')
                    ->icon('heroicon-o-tag')
                    ->color('info')
                    ->url(fn () => TagDistribution::getUrl(['week' => $this->selectedWeek])),

                Action::make('autoDistribute')
                    ->label('توزيع تلقائي')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد التوزيع التلقائي')
                    ->modalDescription('سيتم توزيع العملاء تلقائياً حسب الخوارزمية الذكية. هل تريد المتابعة؟')
                    ->action(function () {
                        $this->performAutoDistribution();
                    }),

                Action::make('resolveDeficitEqually')
                    ->label(function () {
                        $pending = $this->distributionStats['pending_count'] ?? 0;

                        return $pending > 0
                            ? "معالجة عجز السعة ({$pending} معلق)"
                            : 'معالجة عجز السعة';
                    })
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->visible(function () {
                        if ($this->isPastWeek()) {
                            return false;
                        }

                        $totalAssignments = $this->distributionStats['total_assignments'] ?? 0;
                        $pending = $this->distributionStats['pending_count'] ?? 0;

                        // يظهر فقط إذا تم توزيع بعض العقود بالفعل ولا يزال هناك عقود معلقة بسبب العجز
                        return $totalAssignments > 0 && $pending > 0;
                    })
                    ->modalHeading('⚠️ معالجة عجز السعة بالتساوي (Overload مؤقت لهذا الأسبوع)')
                    ->modalDescription(null)
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('تطبيق وإكمال التوزيع')
                    ->modalCancelActionLabel('إلغاء')
                    ->modalContent(function () {
                        $service = new DesignerDistributionService;
                        $analysis = $service->getDeficitAnalysis($this->selectedWeek);

                        return view('filament.pages.partials.deficit-equalizer-modal', [
                            'analysis' => $analysis,
                        ]);
                    })
                    ->action(function () {
                        $this->performDeficitEqualization();
                    }),

                Action::make('clearDistribution')
                    ->label('مسح التوزيع')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد مسح التوزيع')
                    ->modalDescription('سيتم حذف جميع التعيينات لهذا الأسبوع. هل أنت متأكد؟')
                    ->action(function () {
                        $this->clearDistribution();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulkChangeDesigner')
                    ->label('نقل إلى مصمم آخر')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->form([
                        Select::make('designer_id')
                            ->label('اختر المصمم الجديد')
                            ->options(function () {
                                return Designer::with('user')
                                    ->get()
                                    ->pluck('user.name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $count = $records->count();

                        foreach ($records as $record) {
                            $record->update([
                                'designer_id' => $data['designer_id'],
                            ]);
                        }

                        Notification::make()
                            ->title("تم نقل {$count} اشتراك/اشتراكات إلى المصمم بنجاح")
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                        $this->cachedTabs = null;
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('bulkRemoveAssignment')
                    ->label('إلغاء التعيين للمحددين')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إلغاء التعيين')
                    ->modalDescription('هل أنت متأكد من إلغاء تعيين الاشتراكات المحددة؟')
                    ->visible(fn () => ! $this->isPastWeek())
                    ->action(function (Collection $records) {
                        $count = $records->count();
                        $records->each->delete();

                        Notification::make()
                            ->title("تم إلغاء تعيين {$count} اشتراك/اشتراكات بنجاح")
                            ->success()
                            ->send();

                        $this->loadDistributionStats();
                        $this->cachedTabs = null;
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading(fn () => $this->isPastWeek() ? 'لا يوجد توزيع لهذا الأسبوع السابق' : 'لا يوجد توزيع لهذا الأسبوع')
            ->emptyStateDescription(fn () => $this->isPastWeek() ? 'لم يتم توزيع العملاء في هذا الأسبوع.' : 'استخدم زر "توزيع تلقائي" لتوزيع العملاء على المصممين')
            ->emptyStateIcon('heroicon-o-inbox')
            ->paginated([10, 25, 50, 100, 'all']);
    }

    /** @return Builder<ClientDesigner> */
    protected function getTableQuery(): Builder
    {
        /** @var Builder<ClientDesigner> $query */
        $query = ClientDesigner::query()
            ->with(['client.category', 'contract', 'designer.user'])
            ->whereNotNull('contract_id')
            ->when($this->selectedWeek, function ($query) {
                $query->where('week_start_date', $this->selectedWeek);
            })
            ->when($this->activeTab !== 'all', function ($query) {
                $query->where('designer_id', $this->activeTab);
            });

        return $query->orderBy('designer_id')
            ->orderBy('created_at', 'desc')
            ->orderBy('id');
    }

    protected function guardAgainstPastWeek(): void
    {
        if ($this->isPastWeek()) {
            abort(403, 'لا يمكن تعديل التوزيع في الأسابيع السابقة');
        }
    }

    protected function performAutoDistribution(): void
    {
        $this->guardAgainstPastWeek();

        try {
            $service = new DesignerDistributionService;
            $result = $service->autoDistribute($this->selectedWeek);

            $this->loadDistributionStats();
            $this->cachedTabs = null;

            if ($result['success']) {
                $failed = $result['failed'] ?? 0;

                if ($failed === 0) {
                    Notification::make()
                        ->title('نجح التوزيع التلقائي!')
                        ->body($result['message'])
                        ->success()
                        ->duration(5000)
                        ->send();
                } else {
                    Notification::make()
                        ->title('توزيع جزئي — تم اكتشاف عجز في السعة')
                        ->body($result['message'].' — سيتم فتح نافذة معالجة العجز بالتساوي.')
                        ->warning()
                        ->duration(6000)
                        ->send();

                    // فتح نافذة معالجة العجز تلقائياً فوراً
                    $this->replaceMountedTableAction('resolveDeficitEqually');
                }
            } else {
                Notification::make()
                    ->title('فشل التوزيع التلقائي')
                    ->body($result['message'])
                    ->danger()
                    ->duration(5000)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء التوزيع: '.$e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    public function performDeficitEqualization(): void
    {
        $this->guardAgainstPastWeek();

        try {
            $service = new DesignerDistributionService;
            $result = $service->distributeWithEqualOverload($this->selectedWeek);

            $this->loadDistributionStats();
            $this->cachedTabs = null;

            if ($result['success']) {
                $failed = $result['failed'] ?? 0;

                if ($failed === 0) {
                    Notification::make()
                        ->title('تم إكمال التوزيع بنجاح!')
                        ->body($result['message'])
                        ->success()
                        ->duration(5000)
                        ->send();
                } else {
                    Notification::make()
                        ->title('تنبيه: متبقي بعض الاشتراكات')
                        ->body($result['message'])
                        ->warning()
                        ->duration(6000)
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('تنبيه')
                    ->body($result['message'])
                    ->warning()
                    ->duration(5000)
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء توزيع العجز: '.$e->getMessage())
                ->danger()
                ->duration(5000)
                ->send();
        }
    }

    protected function clearDistribution(): void
    {
        $this->guardAgainstPastWeek();

        try {
            $deleted = ClientDesigner::where('week_start_date', $this->selectedWeek)->delete();

            Notification::make()
                ->title('تم مسح التوزيع')
                ->body("تم حذف {$deleted} تعيين بنجاح")
                ->success()
                ->send();

            $this->distributionStats = null;
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body('حدث خطأ أثناء مسح التوزيع: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function loadDistributionStats(): void
    {
        $service = new DesignerDistributionService;
        $report = $service->getDistributionReport($this->selectedWeek);
        $this->distributionStats = $report;
    }

    public function getTabs(): array
    {
        if ($this->cachedTabs !== null) {
            return $this->cachedTabs;
        }

        $tabs = [
            'all' => 'الكل',
        ];

        $assignments = ClientDesigner::where('week_start_date', $this->selectedWeek)
            ->with(['designer.user', 'contract'])
            ->get();

        $designerStats = $assignments->groupBy('designer_id')->map(function ($designerAssignments) {
            $designer = $designerAssignments->first()->designer;
            $totalDesigns = $designerAssignments->sum(function ($assignment) {
                return $assignment->contract->weekly_designs_count ?? 0;
            });

            return [
                'name' => $designer->user->name ?? 'غير معروف',
                'count' => $totalDesigns,
            ];
        });

        $designerStats = $designerStats->sortBy('name');

        foreach ($designerStats as $designerId => $stats) {
            $tabs[$designerId] = "{$stats['name']} ({$stats['count']})";
        }

        $this->cachedTabs = $tabs;

        return $tabs;
    }

    public function goToPreviousWeek(): void
    {
        $this->navigateToWeek(
            Carbon::parse($this->selectedWeek)->subWeek()->startOfWeek()->format('Y-m-d')
        );
    }

    public function goToNextWeek(): void
    {
        $this->navigateToWeek(
            Carbon::parse($this->selectedWeek)->addWeek()->startOfWeek()->format('Y-m-d')
        );
    }

    public function goToCurrentWeek(): void
    {
        $this->navigateToWeek(Carbon::now()->startOfWeek()->format('Y-m-d'));
    }

    private function navigateToWeek(string $week): void
    {
        $this->selectedWeek = $week;
        $this->distributionStats = null;
        $this->cachedTabs = null;
        $this->loadDistributionStats();
        $this->resetTable();
        $this->dispatch('$refresh');
    }
}
