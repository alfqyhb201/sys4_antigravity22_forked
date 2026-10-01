<?php

namespace App\Filament\Pages;

use App\Models\ClientTagDistribution;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithPagination;

class ReviewerDashboard extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'لوحة المراجع';

    protected static ?string $title = 'لوحة المراجع';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reviewer-dashboard';

    public ?string $search = '';

    public ?int $selectedClient = null;

    public string $activeTab = 'reviewing';

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedClient' => ['except' => null],
        'activeTab' => ['except' => 'reviewing'],
    ];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_reviewer_dashboard');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('todayPage');
        $this->resetPage('previousPage');
        $this->resetPage('revisionPage');
    }

    public function updatedSelectedClient(): void
    {
        $this->resetPage('todayPage');
        $this->resetPage('previousPage');
        $this->resetPage('revisionPage');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'selectedClient']);
        $this->resetPage('todayPage');
        $this->resetPage('previousPage');
        $this->resetPage('revisionPage');
    }

    public function getViewData(): array
    {
        $todayStr = Carbon::now()->format('Y-m-d');

        // Base query for items under review
        $baseQuery = ClientTagDistribution::query()
            ->where('status', 'reviewing')
            ->with(['clientDesigner.client.templates', 'clientDesigner.client.category', 'clientDesigner.designer.user', 'tag', 'idea'])
            ->orderByDesc('updated_at');

        // Base query for items with changes_requested
        $revisionBaseQuery = ClientTagDistribution::query()
            ->where('status', 'changes_requested')
            ->with(['clientDesigner.client.templates', 'clientDesigner.client.category', 'clientDesigner.designer.user', 'tag', 'idea'])
            ->orderByDesc('updated_at');

        // Fetch available clients that currently have designs in review or changes_requested
        $availableClients = ClientTagDistribution::query()
            ->whereIn('status', ['reviewing', 'changes_requested'])
            ->with('clientDesigner.client.templates')
            ->get()
            ->pluck('clientDesigner.client')
            ->filter()
            ->unique('id')
            ->sortBy('company')
            ->values();

        // Apply search filter (company name, client name, tag, designer name)
        if (! empty($this->search)) {
            $searchTerm = trim($this->search);
            $applySearch = function ($q) use ($searchTerm): void {
                $q->where(function ($q) use ($searchTerm) {
                    $q->whereHas('clientDesigner.client', function ($clientQuery) use ($searchTerm) {
                        $clientQuery->where('company', 'like', "%{$searchTerm}%")
                            ->orWhere('client_name', 'like', "%{$searchTerm}%");
                    })->orWhereHas('tag', function ($tagQuery) use ($searchTerm) {
                        $tagQuery->where('name', 'like', "%{$searchTerm}%");
                    })->orWhereHas('clientDesigner.designer.user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('name', 'like', "%{$searchTerm}%");
                    });
                });
            };
            $applySearch($baseQuery);
            $applySearch($revisionBaseQuery);
        }

        // Apply client dropdown filter
        if (! empty($this->selectedClient)) {
            $baseQuery->whereHas('clientDesigner', function ($q) {
                $q->where('client_id', $this->selectedClient);
            });
            $revisionBaseQuery->whereHas('clientDesigner', function ($q) {
                $q->where('client_id', $this->selectedClient);
            });
        }

        // Paginate Today's and upcoming items
        $todayItems = (clone $baseQuery)
            ->where('distribution_date', '>=', $todayStr)
            ->paginate(10, ['*'], 'todayPage');

        // Paginate Previous items
        $previousItems = (clone $baseQuery)
            ->where('distribution_date', '<', $todayStr)
            ->paginate(10, ['*'], 'previousPage');

        // Paginate revision (changes_requested) items
        $revisionItems = $revisionBaseQuery
            ->paginate(10, ['*'], 'revisionPage');

        return [
            'todayItems' => $todayItems,
            'previousItems' => $previousItems,
            'revisionItems' => $revisionItems,
            'todayCount' => $todayItems->total(),
            'previousCount' => $previousItems->total(),
            'revisionCount' => $revisionItems->total(),
            'availableClients' => $availableClients,
            'hasActiveFilters' => ! empty($this->search) || ! empty($this->selectedClient),
        ];
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('اعتماد')
            ->color('success')
            // ->requiresConfirmation()
            ->action(function (array $data, array $arguments = []) {
                /**
                 * Description: Handles the approval process by updating the record status and logging the reviewer ID.
                 * Inputs/Key Variables: $arguments (array containing 'id' of the record), auth()->id() (current user ID).
                 * Outputs/Effect: Updates ClientTagDistribution record status to 'sending' and sets reviewer_id. Sends success notification.
                 */
                $id = $arguments['id'] ?? $data['id'] ?? null;
                if (! $id) {
                    return;
                }

                $record = ClientTagDistribution::with('tag')->find($id);
                if ($record) {
                    $updateData = [
                        'status' => 'sending',
                        'reviewer_id' => auth()->id(),
                    ];

                    // تحديد موعد الإرسال إذا لم يكن محدداً مسبقاً:
                    // إذا كان الاعتماد في ساعات الفجر الأولى (قبل 6:00 صباحاً)، يُعتبر موعد الإرسال اليوم
                    // أما إذا كان الاعتماد خلال النهار أو المساء، فيُجدول ليوم غد
                    if (! $record->scheduled_sending_at) {
                        $sendDate = Carbon::now()->hour < 6 ? Carbon::today() : Carbon::tomorrow();
                        $weeklyTime = $record->tag?->weekly_time ? Carbon::parse($record->tag->weekly_time) : null;

                        if ($weeklyTime) {
                            $updateData['scheduled_sending_at'] = $sendDate->setTime($weeklyTime->hour, $weeklyTime->minute, $weeklyTime->second);
                        } else {
                            $updateData['scheduled_sending_at'] = $sendDate->setTime(12, 0, 0);
                        }
                    }

                    $record->update($updateData);
                    Notification::make()->title('تم اعتماد التصميم بنجاح')->success()->send();
                }
            });
    }

    public function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('طلب تعديلات')
            ->color('danger')
            ->modalHeading('طلب تعديلات على التصميم')
            ->modalWidth('lg')
            ->form(function (array $arguments = []) {
                $id = $arguments['id'] ?? null;
                $record = $id ? ClientTagDistribution::with('clientDesigner')->find($id) : null;
                $clientId = $record?->clientDesigner?->client_id ?? 'unknown';
                $dir = $id ? "clients/{$clientId}/distributions/{$id}/revisions" : 'clients/temp/revisions';

                return [
                    \Filament\Forms\Components\ViewField::make('quick_templates')
                        ->view('filament.components.quick-revision-templates'),
                    \Filament\Forms\Components\Textarea::make('reviewer_feedback')
                        ->label('ملاحظات التعديل')
                        ->id('reviewer-feedback-input')
                        ->required()
                        ->rows(4)
                        ->placeholder('اكتب ملاحظاتك بالتفصيل هنا أو اضغط على أحد القوالب السريعة أعلاه...'),
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
            ->action(function (array $data, array $arguments = []) {
                $id = $arguments['id'] ?? $data['id'] ?? null;
                if (! $id) {
                    return;
                }

                $record = ClientTagDistribution::with([
                    'clientDesigner.client',
                    'clientDesigner.designer.user',
                    'tag',
                ])->find($id);

                if ($record) {
                    $feedback = $data['reviewer_feedback'] ?? '';
                    $attachments = $data['reviewer_attachments'] ?? null;

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

                    Notification::make()->title('تم طلب التعديلات بنجاح')->success()->send();
                }
            });
    }

    public function updateChangesAction(): Action
    {
        return Action::make('updateChanges')
            ->label('تعديل الملاحظات')
            ->color('warning')
            ->modalHeading('تحديث طلب التعديل')
            ->modalWidth('lg')
            ->fillForm(function (array $arguments = []): array {
                $id = $arguments['id'] ?? null;
                if (! $id) {
                    return [];
                }

                $record = ClientTagDistribution::find($id);

                return [
                    'reviewer_feedback' => $record?->reviewer_feedback ?? '',
                    'reviewer_attachments' => $record?->reviewer_attachments ?? [],
                ];
            })
            ->form(function (array $arguments = []) {
                $id = $arguments['id'] ?? null;
                $record = $id ? ClientTagDistribution::with('clientDesigner')->find($id) : null;
                $clientId = $record?->clientDesigner?->client_id ?? 'unknown';
                $dir = $id ? "clients/{$clientId}/distributions/{$id}/revisions" : 'clients/temp/revisions';

                return [
                    \Filament\Forms\Components\ViewField::make('quick_templates')
                        ->view('filament.components.quick-revision-templates'),
                    \Filament\Forms\Components\Textarea::make('reviewer_feedback')
                        ->label('ملاحظات التعديل المحدّثة')
                        ->id('reviewer-feedback-input')
                        ->required()
                        ->rows(4)
                        ->placeholder('عدّل ملاحظاتك هنا...'),
                    \Filament\Forms\Components\FileUpload::make('reviewer_attachments')
                        ->label('صور ومرفقات التعديل')
                        ->multiple()
                        ->image()
                        ->disk('public')
                        ->directory($dir)
                        ->maxFiles(5)
                        ->maxSize(config('filesystems.max_file_size', 10240))
                        ->helperText('💡 يمكنك إضافة أو حذف الصور — الصور الحالية معبأة أدناه'),
                ];
            })
            ->action(function (array $data, array $arguments = []) {
                $id = $arguments['id'] ?? $data['id'] ?? null;
                if (! $id) {
                    return;
                }

                $record = ClientTagDistribution::with([
                    'clientDesigner.client',
                    'clientDesigner.designer.user',
                    'tag',
                ])->find($id);

                if ($record && $record->status === 'changes_requested') {
                    $feedback = $data['reviewer_feedback'] ?? '';
                    $attachments = $data['reviewer_attachments'] ?? null;

                    $record->update([
                        'reviewer_feedback' => $feedback,
                        'reviewer_attachments' => $attachments,
                    ]);

                    $designerUser = $record->clientDesigner?->designer?->user;
                    if ($designerUser) {
                        $clientName = $record->clientDesigner?->client?->company
                            ?: ($record->clientDesigner?->client?->client_name ?: 'العميل');
                        $tagName = $record->tag?->name;
                        $tagText = $tagName ? " (وسم: {$tagName})" : '';

                        Notification::make()
                            ->title('تم تحديث ملاحظات التعديل 📝')
                            ->body("تم تحديث ملاحظات تعديل تصميم {$clientName}{$tagText}")
                            ->icon('heroicon-o-pencil-square')
                            ->iconColor('warning')
                            ->warning()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view')
                                    ->label('عرض لوحة المصمم')
                                    ->url('/admin/designer-dashboard'),
                            ])
                            ->sendToDatabase($designerUser, isEventDispatched: true);
                    }

                    Notification::make()->title('تم تحديث طلب التعديل بنجاح')->success()->send();
                }
            });
    }
}
