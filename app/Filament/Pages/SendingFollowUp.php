<?php

namespace App\Filament\Pages;

use App\Models\ClientTagDistribution;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ZipArchive;

class SendingFollowUp extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'واجهة الإرسال';

    protected static ?string $title = ' ';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false; // Hidden from sidebar, accessed via Supervisor Dashboard

    protected static string $view = 'filament.pages.sending-follow-up';

    public ?string $activeTab = 'today';

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public ?string $zipToken = null;

    public int $zipPercent = 0;

    public string $zipStatus = 'idle';

    /** @var array<int, int> */
    public array $pendingZipIds = [];

    public int $totalZipCount = 0;

    public int $addedZipFiles = 0;

    /**
     * @param  array<int, int>  $recordIds
     */
    public function startZipBuild(array $recordIds): void
    {
        if (empty($recordIds)) {
            return;
        }

        $this->pendingZipIds = array_values($recordIds);
        $this->totalZipCount = count($this->pendingZipIds);
        $this->addedZipFiles = 0;
        $this->zipToken = Str::random(32);
        $this->zipPercent = 0;
        $this->zipStatus = 'running';

        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('tmp-zips');

        $this->dispatch('trigger-next-zip-chunk');
    }

    public function processNextZipChunk()
    {
        if (! $this->zipToken || empty($this->pendingZipIds)) {
            return $this->finalizeZipBuild();
        }

        $chunkIds = array_splice($this->pendingZipIds, 0, 5);
        $zipPath = \Illuminate\Support\Facades\Storage::disk('local')->path("tmp-zips/designs-{$this->zipToken}.zip");
        $publicDisk = \Illuminate\Support\Facades\Storage::disk('public');

        $records = ClientTagDistribution::query()
            ->with(['clientDesigner.client', 'idea'])
            ->whereIn('id', $chunkIds)
            ->get();

        $zip = new ZipArchive;
        $openMode = file_exists($zipPath) ? 0 : (ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($zip->open($zipPath, $openMode) === true) {
            foreach ($records as $record) {
                if ($record->attachment_path) {
                    $filePath = $publicDisk->path($record->attachment_path);
                    if (is_file($filePath)) {
                        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                        $safeClient = Str::slug($record->clientDesigner?->client?->company ?? 'client', '_');
                        $safeIdea = Str::slug(Str::limit($record->idea?->name ?? 'idea', 20), '_');
                        $nameInZip = "{$safeClient}_{$safeIdea}_{$record->id}.{$extension}";

                        $zip->addFile($filePath, $nameInZip);
                        $zip->setCompressionName($nameInZip, ZipArchive::CM_STORE);
                        $this->addedZipFiles++;
                    }
                }
            }
            $zip->close();
        }

        $processedSoFar = $this->totalZipCount - count($this->pendingZipIds);
        $this->zipPercent = (int) min(99, round(($processedSoFar / max($this->totalZipCount, 1)) * 100));

        if (empty($this->pendingZipIds)) {
            return $this->finalizeZipBuild();
        }

        $this->dispatch('trigger-next-zip-chunk');

        return null;
    }

    protected function finalizeZipBuild()
    {
        $zipPath = \Illuminate\Support\Facades\Storage::disk('local')->path("tmp-zips/designs-{$this->zipToken}.zip");

        if ($this->addedZipFiles === 0) {
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
            $this->dismissZip();
            Notification::make()
                ->title('لا توجد ملفات مرفقة متاحة للتحميل')
                ->body('العناصر المحددة لا تحتوي على ملفات مرفقة متوفرة على الخادم.')
                ->warning()
                ->send();

            return null;
        }

        $this->zipPercent = 100;
        $fileName = 'designs-'.now()->format('Y-m-d_H-i-s').'.zip';
        Cache::put("sending_zip:{$this->zipToken}", [
            'user_id' => auth()->id(),
            'file' => "tmp-zips/designs-{$this->zipToken}.zip",
            'name' => $fileName,
        ], now()->addMinutes(10));
        $downloadUrl = route('sending-follow-up-zip.download', ['token' => $this->zipToken]);
        $this->dismissZip();

        Notification::make()->title('تم تجهيز الملف بنجاح، يبدأ التنزيل الآن')->success()->send();
        $this->dispatch('download-sending-zip', url: $downloadUrl);

        return null;
    }

    public function dismissZip(): void
    {
        $this->zipToken = null;
        $this->zipPercent = 0;
        $this->zipStatus = 'idle';
        $this->pendingZipIds = [];
        $this->totalZipCount = 0;
        $this->addedZipFiles = 0;
    }

    /**
     * تجميع إحصائيات المواعيد في استعلام واحد فائق السرعة.
     */
    public function getFollowUpStats(): array
    {
        $baseQuery = ClientTagDistribution::query()->where('status', 'sending');

        $nowStr = now()->toDateTimeString();
        $todayStr = today()->toDateString();
        $startOfDayStr = now()->startOfDay()->toDateTimeString();

        $stats = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as all_count,
                COUNT(CASE WHEN scheduled_sending_at <= ? THEN 1 END) as due_now_count,
                COUNT(CASE WHEN DATE(scheduled_sending_at) = ? THEN 1 END) as today_count,
                COUNT(CASE WHEN scheduled_sending_at < ? THEN 1 END) as overdue_count,
                COUNT(CASE WHEN scheduled_sending_at > ? THEN 1 END) as upcoming_count
            ', [
                $nowStr,
                $todayStr,
                $startOfDayStr,
                $nowStr,
            ])
            ->first();

        return [
            'all_count' => (int) ($stats->all_count ?? 0),
            'due_now_count' => (int) ($stats->due_now_count ?? 0),
            'today_count' => (int) ($stats->today_count ?? 0),
            'overdue_count' => (int) ($stats->overdue_count ?? 0),
            'upcoming_count' => (int) ($stats->upcoming_count ?? 0),
        ];
    }

    public function getTabs(): array
    {
        $stats = $this->getFollowUpStats();

        return [
            'today' => [
                'label' => 'مواعيد اليوم',
                'badge' => $stats['today_count'],
                'badgeColor' => 'primary',
                'icon' => 'heroicon-o-calendar',
            ],
            'due_now' => [
                'label' => 'المستحقة الآن / المتأخرة',
                'badge' => $stats['due_now_count'],
                'badgeColor' => $stats['due_now_count'] > 0 ? 'danger' : 'gray',
                'icon' => 'heroicon-o-fire',
            ],
            'overdue' => [
                'label' => 'المتأخرة فقط',
                'badge' => $stats['overdue_count'],
                'badgeColor' => $stats['overdue_count'] > 0 ? 'danger' : 'gray',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'upcoming' => [
                'label' => 'القادمة لاحقاً',
                'badge' => $stats['upcoming_count'],
                'badgeColor' => 'info',
                'icon' => 'heroicon-o-clock',
            ],
            'all' => [
                'label' => 'جميع المواعيد',
                'badge' => $stats['all_count'],
                'badgeColor' => 'gray',
                'icon' => 'heroicon-o-list-bullet',
            ],
        ];
    }

    protected function getViewData(): array
    {
        $stats = $this->getFollowUpStats();

        return [
            'sendingCount' => $stats['all_count'],
            'todayCount' => $stats['today_count'],
            'dueNowCount' => $stats['due_now_count'],
            'overdueCount' => $stats['overdue_count'],
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('supervisor')
            || $user->hasRole('admin')
            || $user->can('view_supervisor_dashboard')
            || $user->can('view_sending_follow_up');
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            SupervisorDashboard::getUrl() => 'واجهة المشرف',
            static::getUrl() => 'واجهة الإرسال والمتابعة',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = ClientTagDistribution::query()
                    ->where('status', 'sending')
                    ->with([
                        'clientDesigner.client',
                        'clientDesigner.designer.user',
                        'tag',
                        'idea',
                        'reviewer',
                    ])
                    ->orderBy('scheduled_sending_at', 'asc');

                if ($this->activeTab === 'today') {
                    $query->whereDate('scheduled_sending_at', \Carbon\Carbon::today());
                } elseif ($this->activeTab === 'due_now') {
                    $query->where('scheduled_sending_at', '<=', now());
                } elseif ($this->activeTab === 'overdue') {
                    $query->where('scheduled_sending_at', '<', \Carbon\Carbon::now()->startOfDay());
                } elseif ($this->activeTab === 'upcoming') {
                    $query->where('scheduled_sending_at', '>', now());
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('clientDesigner.client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tag.name')
                    ->label('التاق والمحتوى')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->description(function (ClientTagDistribution $record) {
                        $ideaName = $record->idea?->name ?? 'محتوى مخصص';
                        $designerName = $record->clientDesigner?->designer?->user?->name;

                        return $designerName ? "{$ideaName} • المصمم: {$designerName}" : $ideaName;
                    })
                    ->toggleable(),

                Tables\Columns\ImageColumn::make('attachment_path')
                    ->label('التصميم النهائي')
                    ->disk('public')
                    ->square()
                    ->width(64)
                    ->height(64)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm transition hover:scale-105 cursor-pointer',
                        'loading' => 'lazy',
                    ])
                    ->action(
                        Tables\Actions\Action::make('view_image')
                            ->modalHeading('معاينة التصميم النهائي')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalWidth('3xl')
                            ->modalContent(fn ($record) => new HtmlString(
                                '<div class="flex flex-col items-center gap-3 p-2">
                                    <img src="'.asset('storage/'.$record->attachment_path).'" class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-lg ring-1 ring-gray-200 dark:ring-gray-800" alt="التصميم النهائي" />
                                    <div class="flex items-center gap-3 mt-2">
                                        <a href="'.asset('storage/'.$record->attachment_path).'" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 hover:bg-primary-100 transition">
                                            فتح بالحجم الكامل ↗
                                        </a>
                                    </div>
                                </div>'
                            ))
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('المراجع والاعتماد')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->badge()
                    ->color('success')
                    ->placeholder('غير محدد')
                    ->searchable()
                    ->sortable()
                    ->description(function (ClientTagDistribution $record) {
                        if (! $record->updated_at) {
                            return null;
                        }

                        return 'معتمد: '.$record->updated_at->diffForHumans().' ('.$record->updated_at->format('d/m h:i A').')';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('scheduled_sending_at')
                    ->label('موعد الإرسال')
                    ->dateTime('l, d M - h:i A')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state < now() ? 'danger' : 'success')
                    ->icon(fn ($state) => $state < now() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-calendar-days')
                    ->description(fn ($record) => $record->scheduled_sending_at?->diffForHumans())
                    ->toggleable(),
            ])
            ->filtersFormWidth(MaxWidth::Medium)
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->label('تصفية بالعميل')
                    ->relationship('clientDesigner.client', 'company')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('designer')
                    ->label('تصفية بالمصمم')
                    ->options(fn () => \App\Models\Designer::with('user')->get()->pluck('user.name', 'id')->filter()->toArray())
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (filled($value)) {
                            $query->whereHas('clientDesigner', fn ($q) => $q->where('designer_id', $value));
                        }
                    }),

                Tables\Filters\SelectFilter::make('reviewer')
                    ->label('تصفية بالمراجع')
                    ->relationship('reviewer', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('tag_filter')
                    ->form([
                        Forms\Components\Select::make('importance')
                            ->label('أهمية الوسم')
                            ->placeholder('جميع مستويات الأهمية')
                            ->options([
                                'veryhigh' => 'عالية جداً 🔥',
                                'high' => 'عالية ⚡',
                                'medium' => 'متوسطة',
                                'low' => 'منخفضة',
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('tag_ids', [])),

                        Forms\Components\Select::make('tag_ids')
                            ->label('الوسوم')
                            ->placeholder(fn (Forms\Get $get) => filled($get('importance'))
                                ? 'اختر من وسوم هذه الأهمية...'
                                : 'اختر الوسوم...'
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function (Forms\Get $get) {
                                $importance = $get('importance');

                                return \App\Models\Tag::query()
                                    ->when(filled($importance), function (Builder $q) use ($importance) {
                                        if ($importance === 'veryhigh') {
                                            $q->whereIn('importance', ['veryhigh', 'very_high']);
                                        } else {
                                            $q->where('importance', $importance);
                                        }
                                    })
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            }),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['importance'] ?? null) && empty($data['tag_ids'] ?? []),
                                function (Builder $q) use ($data) {
                                    $importance = $data['importance'];
                                    $q->whereHas('tag', function (Builder $tagQuery) use ($importance) {
                                        if ($importance === 'veryhigh') {
                                            $tagQuery->whereIn('importance', ['veryhigh', 'very_high']);
                                        } else {
                                            $tagQuery->where('importance', $importance);
                                        }
                                    });
                                }
                            )
                            ->when(
                                ! empty($data['tag_ids'] ?? []),
                                fn (Builder $q) => $q->whereIn('tag_id', $data['tag_ids'])
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['importance'] ?? null)) {
                            $labels = [
                                'veryhigh' => 'أهمية الوسم: عالية جداً 🔥',
                                'high' => 'أهمية الوسم: عالية ⚡',
                                'medium' => 'أهمية الوسم: متوسطة',
                                'low' => 'أهمية الوسم: منخفضة',
                            ];
                            $indicators[] = $labels[$data['importance']] ?? ('أهمية الوسم: '.$data['importance']);
                        }

                        if (! empty($data['tag_ids'] ?? [])) {
                            $count = count($data['tag_ids']);
                            if ($count <= 2) {
                                $names = \App\Models\Tag::whereIn('id', $data['tag_ids'])->pluck('name')->implode('، ');
                                $indicators[] = 'الوسوم: '.$names;
                            } else {
                                $indicators[] = "الوسوم: ({$count}) محددة";
                            }
                        }

                        return $indicators;
                    }),

                Tables\Filters\Filter::make('date_range')
                    ->label('فترة تاريخ مخصصة')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('من تاريخ'),
                        Forms\Components\DatePicker::make('to_date')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_sending_at', '>=', $date))
                            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_sending_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('markAsCompleted')
                    ->label('تم الإرسال ')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد إتمام الإرسال')
                    ->modalDescription(fn (ClientTagDistribution $record) => 'هل أنت متأكد من تأكيد إرسال تصميم "'.($record->clientDesigner?->client?->company ?? 'العميل').'"؟ سيتم نقله إلى الأرشيف.')
                    ->modalSubmitActionLabel('نعم، تأكيد الإرسال')
                    ->modalIcon('heroicon-o-check-badge')
                    ->modalIconColor('success')
                    ->action(function (ClientTagDistribution $record) {
                        $record->update([
                            'status' => 'completed',
                            'sender_id' => auth()->id(),
                            'completed_at' => now(),
                        ]);

                        // Increment the cliché counter for the client
                        $client = $record->clientDesigner?->client;
                        if ($client) {
                            $client->increment('cliche_counter');
                        }

                        Notification::make()
                            ->title('تم إتمام المهمة بنجاح')
                            ->body('تم نقل التصميم إلى الأرشيف المكتمل. يمكنك استعادته من صفحة "المرسلة حديثاً".')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('requestChanges')
                    ->label('طلب تعديل')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->button()
                    ->modalHeading('طلب تعديلات على التصميم')
                    ->modalWidth('lg')
                    ->form(function (ClientTagDistribution $record) {
                        $clientId = $record->clientDesigner?->client_id ?? 'unknown';
                        $dir = "clients/{$clientId}/distributions/{$record->id}/revisions";

                        return [
                            Forms\Components\ViewField::make('quick_templates')
                                ->view('filament.components.quick-revision-templates'),
                            Forms\Components\Textarea::make('reviewer_feedback')
                                ->label('ملاحظات التعديل')
                                ->id('reviewer-feedback-input')
                                ->required()
                                ->rows(4)
                                ->default($record->reviewer_feedback)
                                ->placeholder('اكتب ملاحظاتك بالتفصيل هنا أو اضغط على أحد القوالب السريعة أعلاه...'),
                            Forms\Components\FileUpload::make('reviewer_attachments')
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
                    ->action(function (ClientTagDistribution $record, array $data) {
                        $feedback = $data['reviewer_feedback'] ?? '';
                        $attachments = $data['reviewer_attachments'] ?? null;

                        $record->load(['clientDesigner.client', 'clientDesigner.designer.user', 'tag']);

                        $record->update([
                            'status' => 'changes_requested',
                            'reviewer_feedback' => $feedback,
                            'reviewer_attachments' => $attachments,
                            'reviewer_id' => auth()->id(),
                        ]);

                        $designerUser = $record->clientDesigner?->designer?->user;
                        if ($designerUser) {
                            $clientName = $record->clientDesigner?->client?->company
                                ?: ($record->clientDesigner?->client?->client_name ?: 'العميل');
                            $tagName = $record->tag?->name;
                            $tagText = $tagName ? " (وسم: {$tagName})" : '';
                            $attachmentNotice = (! empty($attachments)) ? ' [مع صور ومرفقات]' : '';

                            Notification::make()
                                ->title('طلب تعديل على التصميم 📝')
                                ->body("تم طلب تعديل على تصميم {$clientName}{$tagText}{$attachmentNotice} - ملاحظات: {$feedback}")
                                ->icon('heroicon-o-arrow-path')
                                ->iconColor('warning')
                                ->warning()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('view')
                                        ->label('عرض لوحة المصمم')
                                        ->url('/admin/designer-dashboard'),
                                ])
                                ->sendToDatabase($designerUser, isEventDispatched: true);
                        }

                        Notification::make()
                            ->title('تم إرجاع التصميم للتعديل')
                            ->body('تم تغيير حالة التصميم إلى "قيد التعديل" وإشعار المصمم.')
                            ->warning()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('download')
                    ->label('')
                    ->tooltip('تحميل التصميم')
                    ->icon('heroicon-m-cloud-arrow-down')
                    ->color('gray')
                    ->iconButton()
                    ->action(function (ClientTagDistribution $record) {
                        if (! $record->attachment_path) {
                            Notification::make()
                                ->title('لا يوجد ملف للتحميل')
                                ->danger()
                                ->send();

                            return;
                        }

                        $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);

                        if (! file_exists($filePath)) {
                            Notification::make()
                                ->title('الملف غير موجود في الخادم')
                                ->danger()
                                ->send();

                            return;
                        }

                        return response()->download($filePath);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markSelectedAsCompleted')
                    ->label('تأكيد الإرسال للمحددين')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ تأكيد الإرسال الجماعي')
                    ->modalDescription(fn (Collection $records) => '⚡ أنت على وشك تأكيد إرسال ('.$records->count().') تصميم. ستُنقل هذه المهام إلى الأرشيف وتختفي من هذه الواجهة. هل أنت متأكد؟')
                    ->modalSubmitActionLabel('نعم، تأكيد الإرسال')
                    ->modalCancelActionLabel('إلغاء')
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('warning')
                    ->action(function (Collection $records) {
                        $now = now();
                        $userId = auth()->id();
                        $count = 0;

                        foreach ($records as $record) {
                            $record->update([
                                'status' => 'completed',
                                'sender_id' => $userId,
                                'completed_at' => $now,
                            ]);

                            $client = $record->clientDesigner?->client;
                            if ($client) {
                                $client->increment('cliche_counter');
                            }
                            $count++;
                        }

                        Notification::make()
                            ->title('تم تأكيد الإرسال بنجاح')
                            ->body("تم إتمام وإرسال ({$count}) تصميم. يمكنك استعادتهم من صفحة \"المرسلة حديثاً\".")
                            ->success()
                            ->send();

                        $this->resetTable();
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('downloadSelected')
                    ->label('تنزيل المحدد (ZIP)')
                    ->icon('heroicon-m-archive-box-arrow-down')
                    ->color('gray')
                    ->action(function (Collection $records) {
                        $this->startZipBuild($records->pluck('id')->all());
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->striped()
            ->poll('30s');
    }
}
