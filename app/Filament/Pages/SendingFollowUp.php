<?php

namespace App\Filament\Pages;

use App\Models\ClientTagDistribution;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
                        @set_time_limit(0);
                        $records->loadMissing(['clientDesigner.client', 'idea']);

                        $zipFileName = 'designs-'.now()->timestamp.'.zip';
                        $zipPath = \Illuminate\Support\Facades\Storage::disk('public')->path($zipFileName);

                        $zip = new ZipArchive;
                        $addedCount = 0;
                        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                            foreach ($records as $record) {
                                if ($record->attachment_path) {
                                    $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);
                                    if (is_file($filePath)) {
                                        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                                        $safeClient = Str::slug($record->clientDesigner->client->company ?? 'client', '_');
                                        $safeIdea = Str::slug(Str::limit($record->idea->name ?? 'idea', 20), '_');
                                        $fileNameInZip = "{$safeClient}_{$safeIdea}_{$record->id}.{$extension}";

                                        $zip->addFile($filePath, $fileNameInZip);
                                        $zip->setCompressionName($fileNameInZip, ZipArchive::CM_STORE);
                                        $addedCount++;
                                    }
                                }
                            }
                            $zip->close();
                        }

                        if ($addedCount > 0 && file_exists($zipPath)) {
                            return response()->download($zipPath)->deleteFileAfterSend();
                        }

                        if (file_exists($zipPath)) {
                            @unlink($zipPath);
                        }

                        Notification::make()
                            ->title('لا توجد ملفات مرفقة متاحة للتحميل')
                            ->body('العناصر المحددة لا تحتوي على ملفات مرفقة متوفرة على الخادم.')
                            ->warning()
                            ->send();

                        return null;
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->striped()
            ->poll('30s');
    }
}
