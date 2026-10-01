<?php

namespace App\Filament\Pages\Archive;

use App\Models\Client;
use App\Models\ClientTagDistribution;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ArchivedClientDesigns extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.archive.archived-client-designs';

    public ?string $client_id = null;

    public ?Client $client = null;

    public function mount()
    {
        $this->client_id = request()->query('client_id');
        if (! $this->client_id) {
            abort(404);
        }

        $this->client = Client::findOrFail($this->client_id);
    }

    public function getTitle(): string
    {
        return 'تصاميم العميل: '.($this->client ? $this->client->company : '');
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            ArchivedClients::getUrl() => 'أرشيف العملاء',
            static::getUrl(['client_id' => $this->client_id]) => $this->getTitle(),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user?->can('view_archive') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => ClientTagDistribution::query()
                    ->where('status', 'completed')
                    ->whereHas('clientDesigner', function (Builder $query) {
                        $query->where('client_id', $this->client_id);
                    })
                    ->with(['clientDesigner.designer.user', 'tag', 'idea', 'sender'])
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('attachment_path')
                    ->label('التصميم النهائي')
                    ->disk('public')
                    ->width(70)
                    ->height(70)
                    ->circular()
                    ->action(
                        Tables\Actions\Action::make('view_image')
                            ->modalHeading('التصميم النهائي')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString('<div class="flex justify-center"><img src="'.asset('storage/'.$record->attachment_path).'" style="max-width: 60%; height: auto; border-radius: 8px;" alt="التصميم النهائي" /></div>'))
                    ),

                Tables\Columns\TextColumn::make('clientDesigner.designer.user.name')
                    ->label('المصمم')
                    ->searchable()
                    ->sortable(),

                // Tables\Columns\TextColumn::make('tag.name')
                //     ->label('التاق والمحتوى')
                //     ->badge()
                //     ->color('info')
                //     ->searchable()
                //     ->description(fn($record) => $record->idea?->name ?? 'محتوى مخصص'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime('l, d M Y - h:i A')
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-calendar-days'),

                Tables\Columns\TextColumn::make('sender.name')
                    ->label('المُرسِل')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('')
                    ->tooltip('تحميل التصميم')
                    ->icon('heroicon-m-cloud-arrow-down')
                    ->color('gray')
                    ->iconButton()
                    ->action(function (ClientTagDistribution $record) {
                        if (! $record->attachment_path) {
                            \Filament\Notifications\Notification::make()
                                ->title('لا يوجد ملف للتحميل')
                                ->danger()
                                ->send();

                            return;
                        }

                        $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);

                        if (! file_exists($filePath)) {
                            \Filament\Notifications\Notification::make()
                                ->title('الملف غير موجود في الخادم')
                                ->danger()
                                ->send();

                            return;
                        }

                        return response()->download($filePath);
                    }),
                Tables\Actions\Action::make('requestRevision')
                    ->label('طلب تعديل')
                    ->tooltip('إعادة التصميم للمصمم للتعديل')
                    ->icon('heroicon-m-exclamation-circle')
                    ->form(function (ClientTagDistribution $record) {
                        $clientId = $record->clientDesigner?->client_id ?? 'unknown';
                        $dir = "clients/{$clientId}/distributions/{$record->id}/revisions";

                        return [
                            \Filament\Forms\Components\Textarea::make('reviewer_feedback')
                                ->label('ملاحظات التعديل')
                                ->required()
                                ->rows(3),
                            \Filament\Forms\Components\FileUpload::make('reviewer_attachments')
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
                        $record->update([
                            'status' => 'changes_requested',
                            'reviewer_feedback' => $feedback,
                            'reviewer_attachments' => $attachments,
                            'distribution_date' => now()->format('Y-m-d'),
                        ]);

                        // Decrement cliche_counter because the design is returned
                        $client = $record->clientDesigner?->client;
                        if ($client && $client->cliche_counter > 0) {
                            $client->decrement('cliche_counter');
                        }

                        $designerUser = $record->clientDesigner?->designer?->user;
                        if ($designerUser) {
                            $clientName = $client?->company ?: ($client?->client_name ?: 'العميل');
                            $tagName = $record->tag?->name;
                            $tagText = $tagName ? " (وسم: {$tagName})" : '';
                            $attachmentNotice = (! empty($attachments)) ? ' [مع صور ومرفقات]' : '';

                            \Filament\Notifications\Notification::make()
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

                        \Filament\Notifications\Notification::make()
                            ->title('تم طلب التعديل')
                            ->body('تمت إعادة التصميم إلى لوحة المصمم.')
                            ->success()
                            ->send();
                    }),
            ])

            ->bulkActions([
                Tables\Actions\BulkAction::make('downloadSelected')
                    ->label('تنزيل المحدد (ZIP)')
                    ->icon('heroicon-m-archive-box-arrow-down')
                    ->color('gray')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $zipFileName = 'archived-designs-'.now()->timestamp.'.zip';
                        $zipPath = \Illuminate\Support\Facades\Storage::disk('public')->path($zipFileName);

                        $zip = new \ZipArchive;
                        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
                            foreach ($records as $record) {
                                if ($record->attachment_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($record->attachment_path)) {
                                    $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);
                                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                                    $safeIdea = \Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($record->idea->name ?? 'idea', 20), '_');
                                    $fileNameInZip = "{$safeIdea}_{$record->id}.{$extension}";

                                    $zip->addFile($filePath, $fileNameInZip);
                                }
                            }
                            $zip->close();
                        }

                        return response()->download($zipPath)->deleteFileAfterSend();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->striped();
    }
}
