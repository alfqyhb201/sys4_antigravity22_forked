<?php

namespace App\Filament\Pages\Archive;

use App\Filament\Pages\SendingFollowUp;
use App\Filament\Pages\SupervisorDashboard;
use App\Models\ClientTagDistribution;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use ZipArchive;

class RecentlySentArchive extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'المرسلة حديثاً';

    protected static ?string $title = ' ';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.archive.recently-sent-archive';

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            SupervisorDashboard::getUrl() => 'واجهة المشرف',
            SendingFollowUp::getUrl() => 'واجهة الإرسال',
            static::getUrl() => 'المرسلة حديثاً',
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
            || $user->can('view_archive');
    }

    /**
     * إحصائيات سريعة للمرسلة حديثاً.
     */
    public function getRecentStats(): array
    {
        $last24h = now()->subDay();
        $last7d = now()->subDays(7);

        $stats = ClientTagDistribution::query()
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(CASE WHEN completed_at >= ? THEN 1 END) as last_24h,
                COUNT(CASE WHEN completed_at >= ? THEN 1 END) as last_7d
            ', [$last24h, $last7d])
            ->first();

        return [
            'last_24h' => (int) ($stats->last_24h ?? 0),
            'last_7d' => (int) ($stats->last_7d ?? 0),
        ];
    }

    protected function getViewData(): array
    {
        return $this->getRecentStats();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return ClientTagDistribution::query()
                    ->where('status', 'completed')
                    ->whereNotNull('completed_at')
                    ->where('completed_at', '>=', now()->subDays(7))
                    ->with([
                        'clientDesigner.client',
                        'clientDesigner.designer.user',
                        'tag',
                        'idea',
                        'sender',
                    ])
                    ->orderBy('completed_at', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('clientDesigner.client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('tag.name')
                    ->label('التاق والمحتوى')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->description(function (ClientTagDistribution $record) {
                        $ideaName = $record->idea?->name ?? 'محتوى مخصص';
                        $designerName = $record->clientDesigner?->designer?->user?->name;

                        return $designerName ? "{$ideaName} • المصمم: {$designerName}" : $ideaName;
                    }),

                Tables\Columns\ImageColumn::make('attachment_path')
                    ->label('التصميم')
                    ->disk('public')
                    ->square()
                    ->width(56)
                    ->height(56)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm',
                        'loading' => 'lazy',
                    ])
                    ->action(
                        Tables\Actions\Action::make('view_image')
                            ->modalHeading('معاينة التصميم')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalWidth('3xl')
                            ->modalContent(fn ($record) => new HtmlString(
                                '<div class="flex flex-col items-center gap-3 p-2">
                                    <img src="'.asset('storage/'.$record->attachment_path).'" class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-lg ring-1 ring-gray-200 dark:ring-gray-800" alt="التصميم" />
                                </div>'
                            ))
                    ),

                Tables\Columns\TextColumn::make('sender.name')
                    ->label('المُرسِل')
                    ->icon('heroicon-m-user')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('completed_at')
                    ->label('وقت الإرسال')
                    ->dateTime('d/m/Y - h:i A')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-check-circle')
                    ->description(fn ($record) => $record->completed_at?->diffForHumans()),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('last_24h')
                    ->label('آخر 24 ساعة')
                    ->placeholder('الكل (7 أيام)')
                    ->trueLabel('آخر 24 ساعة فقط')
                    ->falseLabel('أقدم من 24 ساعة')
                    ->default(true)
                    ->queries(
                        true: fn ($query) => $query->where('completed_at', '>=', now()->subDay()),
                        false: fn ($query) => $query->where('completed_at', '<', now()->subDay()),
                        blank: fn ($query) => $query,
                    ),

                Tables\Filters\SelectFilter::make('client')
                    ->label('تصفية بالعميل')
                    ->relationship('clientDesigner.client', 'company')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('sender')
                    ->label('تصفية بالمُرسِل')
                    ->relationship('sender', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('undoSend')
                    ->label('تراجع')
                    ->tooltip('إرجاع المهمة إلى واجهة الإرسال')
                    ->icon('heroicon-m-arrow-uturn-right')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('إرجاع المهمة إلى واجهة الإرسال')
                    ->modalDescription(fn (ClientTagDistribution $record) => 'سيتم إرجاع تصميم "'.($record->clientDesigner?->client?->company ?? 'العميل').'" إلى واجهة الإرسال وإلغاء حالة الإتمام.')
                    ->modalSubmitActionLabel('نعم، أرجع المهمة')
                    ->modalIcon('heroicon-o-arrow-uturn-right')
                    ->modalIconColor('warning')
                    ->action(function (ClientTagDistribution $record) {
                        // Decrement cliche_counter because the send is being undone
                        $client = $record->clientDesigner?->client;
                        if ($client && $client->cliche_counter > 0) {
                            $client->decrement('cliche_counter');
                        }

                        $record->update([
                            'status' => 'sending',
                            'sender_id' => null,
                            'completed_at' => null,
                        ]);

                        Notification::make()
                            ->title('تم إرجاع المهمة بنجاح')
                            ->body('تم إرجاع التصميم إلى واجهة الإرسال.')
                            ->success()
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
                Tables\Actions\BulkAction::make('undoSelectedSend')
                    ->label('تراجع عن المحددين')
                    ->icon('heroicon-m-arrow-uturn-right')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('إرجاع المهام المحددة')
                    ->modalDescription(fn (Collection $records) => 'سيتم إرجاع ('.$records->count().') تصميم إلى واجهة الإرسال. هل أنت متأكد؟')
                    ->modalSubmitActionLabel('نعم، أرجع الكل')
                    ->modalIcon('heroicon-o-arrow-uturn-right')
                    ->modalIconColor('warning')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            $client = $record->clientDesigner?->client;
                            if ($client && $client->cliche_counter > 0) {
                                $client->decrement('cliche_counter');
                            }

                            $record->update([
                                'status' => 'sending',
                                'sender_id' => null,
                                'completed_at' => null,
                            ]);
                            $count++;
                        }

                        Notification::make()
                            ->title('تم إرجاع المهام بنجاح')
                            ->body("تم إرجاع ({$count}) تصميم إلى واجهة الإرسال.")
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

                        $zipFileName = 'recently-sent-designs-'.now()->timestamp.'.zip';
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

                        \Filament\Notifications\Notification::make()
                            ->title('لا توجد ملفات مرفقة متاحة للتحميل')
                            ->body('العناصر المحددة لا تحتوي على ملفات مرفقة متوفرة على الخادم.')
                            ->warning()
                            ->send();

                        return null;
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->striped()
            ->emptyStateHeading('لا توجد مهام مرسلة حديثاً')
            ->emptyStateDescription('ستظهر هنا المهام التي تم تأكيد إرسالها خلال آخر 7 أيام.')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
