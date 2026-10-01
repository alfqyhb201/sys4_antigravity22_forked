<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Designer;
use App\Models\Idea;
use App\Models\Tag;
use App\Services\TagDistributionExportService;
use App\Services\TagDistributionService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TagDistribution extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static string $view = 'filament.pages.tag-distribution';

    protected static ?string $title = 'توزيع التاقات';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $selectedWeek = null;

    public ?string $activeTab = null;

    public string $searchClient = '';

    public $assignments = [];

    public $editingDistributionId = null;

    public $newDate = null;

    public $newDesignerId = null;

    public $designers = [];

    public $newTagId = null;

    public $newIdeaId = null;

    public $newCustomIdea = null;

    public $newScheduledSendingAt = null;

    public $availableTags = [];

    public $availableIdeas = [];

    public $distributionStatus = [];

    public bool $isSmartDistributionRunning = false;

    // Direct Upload Properties for Supervisor/Admin
    public ?int $uploadDistributionId = null;

    public ?string $uploadClientName = null;

    public ?string $uploadTagName = null;

    public ?string $uploadDate = null;

    public $uploadFile = null;

    public ?string $uploadNotes = null;

    public string $uploadTargetStatus = 'sending';

    public ?string $uploadScheduledSendingAt = null;

    // Quick Add Properties
    public ?int $quickAddAssignmentId = null;

    public ?string $quickAddDate = null;

    public ?int $quickAddTagId = null;

    public ?int $quickAddIdeaId = null;

    public ?string $quickAddCustomIdea = null;

    public ?string $quickAddScheduledSendingAt = null;

    public array $quickAddAvailableTags = [];

    public array $quickAddAvailableIdeas = [];

    public ?string $quickAddClientName = null;

    public ?string $quickAddDesignerName = null;

    protected ?array $cachedWeekDays = null;

    protected TagDistributionService $tagDistributionService;

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_tag_distribution');
    }

    public function boot(TagDistributionService $tagDistributionService): void
    {
        $this->tagDistributionService = $tagDistributionService;
    }

    public function openTransferDesignerModal(int $id): void
    {
        $this->editingDistributionId = $id;
        $distribution = ClientTagDistribution::find($id);

        $this->designers = Designer::with('user')->get()->pluck('user.name', 'id')->toArray();
        $this->newDesignerId = $distribution?->clientDesigner?->designer_id;

        $this->dispatch('open-modal', id: 'transfer-designer-modal');
    }

    public function openEditDetailsModal($id): void
    {
        $this->editingDistributionId = $id;
        $distribution = ClientTagDistribution::find($id);

        if (! $distribution) {
            return;
        }

        $this->newTagId = (int) $distribution->tag_id;
        $this->newIdeaId = $distribution->idea_id;
        $this->newCustomIdea = $distribution->custom_idea;

        $assignment = ClientDesigner::with([
            'client.category',
            'client.tags',
            'client.tagGroups',
            'client.clientNeeds.tags',
            'client.location',
            'contract',
        ])->find($distribution->client_designer_id);
        $this->availableTags = [];

        if ($assignment) {
            $this->availableTags = $this->tagDistributionService->collectTagsForAssignment($assignment)
                ->pluck('name', 'id')
                ->toArray();
        }

        if (empty($this->availableTags)) {
            $this->availableTags = Tag::pluck('name', 'id')->toArray();
        }

        if ($this->newTagId && ! array_key_exists($this->newTagId, $this->availableTags)) {
            $tag = Tag::find($this->newTagId);
            if ($tag) {
                $this->availableTags[$tag->id] = $tag->name;
            }
        }

        $this->loadAvailableIdeas($distribution->client_designer->client_id ?? null);

        $this->dispatch('open-modal', id: 'edit-details-modal');
    }

    public function updatedNewTagId(): void
    {
        $distribution = ClientTagDistribution::find($this->editingDistributionId);
        $clientId = $distribution?->client_designer?->client_id;
        $this->loadAvailableIdeas($clientId);
        $this->newIdeaId = null;
    }

    protected function loadAvailableIdeas(?int $clientId): void
    {
        $this->availableIdeas = $this->tagDistributionService->loadAvailableIdeasForTag(
            $this->newTagId,
            $clientId,
            $this->selectedWeek,
            $this->newIdeaId
        );
    }

    public function isAdmin(): bool
    {
        $user = auth()->user();

        return $user && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }

    public function transferDesigner(): void
    {
        $this->guardAgainstPastWeek();

        $this->validate([
            'newDesignerId' => 'required|exists:designers,id',
        ]);

        $distribution = ClientTagDistribution::find($this->editingDistributionId);

        if ($distribution) {
            if (in_array($distribution->status, ['sending', 'reviewing', 'completed']) && ! $this->isAdmin()) {
                $statusMap = [
                    'sending' => 'قيد الإرسال',
                    'reviewing' => 'قيد المراجعة',
                    'completed' => 'تم إرساله',
                ];
                $statusLabel = $statusMap[$distribution->status] ?? $distribution->status;

                Notification::make()
                    ->title('لا يمكن نقل هذا التاق')
                    ->body("لا يمكن نقل تاق حالته {$statusLabel}.")
                    ->danger()
                    ->send();
                $this->reset(['editingDistributionId', 'newDesignerId', 'designers']);
                $this->dispatch('close-modal', id: 'transfer-designer-modal');
                $this->loadAssignments();

                return;
            }

            $currentClientDesigner = $distribution->clientDesigner;

            if ($currentClientDesigner->designer_id != $this->newDesignerId) {
                $primaryAssignment = ClientDesigner::where('client_id', $currentClientDesigner->client_id)
                    ->where('week_start_date', $currentClientDesigner->week_start_date)
                    ->where('is_side', false)
                    ->first();

                $isSide = $primaryAssignment && ($primaryAssignment->designer_id != $this->newDesignerId);

                $newAssignment = ClientDesigner::firstOrCreate(
                    [
                        'client_id' => $currentClientDesigner->client_id,
                        'designer_id' => $this->newDesignerId,
                        'week_start_date' => $currentClientDesigner->week_start_date,
                    ],
                    [
                        'contract_id' => $currentClientDesigner->contract_id,
                        'is_side' => $isSide,
                    ]
                );

                $distribution->client_designer_id = $newAssignment->id;
                $distribution->save();

                // تنظيف التعيين القديم إذا أصبح فارغاً وكان تعييناً جانبياً
                $this->tagDistributionService->cleanupEmptyDuplicateAssignments($this->selectedWeek);
            }

            Notification::make()
                ->title('تم نقل التاق بنجاح')
                ->success()
                ->send();
        }

        $this->reset(['editingDistributionId', 'newDesignerId', 'designers']);
        $this->dispatch('close-modal', id: 'transfer-designer-modal');
        $this->loadAssignments();
    }

    public function deleteDistribution(int $id): void
    {
        $this->guardAgainstPastWeek();

        $distribution = ClientTagDistribution::find($id);
        if (! $distribution) {
            return;
        }

        if (in_array($distribution->status, ['sending', 'reviewing', 'completed']) && ! $this->isAdmin()) {
            $statusMap = [
                'sending' => 'قيد الإرسال',
                'reviewing' => 'قيد المراجعة',
                'completed' => 'تم إرساله',
            ];
            $statusLabel = $statusMap[$distribution->status] ?? $distribution->status;

            Notification::make()
                ->title('لا يمكن حذف هذا التاق')
                ->body("لا يمكن حذف تاق حالته {$statusLabel}.")
                ->danger()
                ->send();

            return;
        }

        $distribution->delete();

        // تنظيف أي سجل client_designer أصبح فارغاً وزائداً
        $this->tagDistributionService->cleanupEmptyDuplicateAssignments($this->selectedWeek);

        Notification::make()
            ->title('تم حذف التاق بنجاح')
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function openQuickAddModal(int $assignmentId, string $date): void
    {
        $this->guardAgainstPastWeek();

        $assignment = ClientDesigner::with([
            'client.category',
            'client.tags',
            'client.tagGroups',
            'client.clientNeeds.tags',
            'client.location',
            'contract',
            'designer.user',
        ])->find($assignmentId);

        if (! $assignment) {
            return;
        }

        $this->quickAddAssignmentId = $assignmentId;
        $this->quickAddDate = $date;
        $this->quickAddClientName = $assignment->client?->company ?: ($assignment->client?->client_name ?: "عميل #{$assignment->client_id}");
        $this->quickAddDesignerName = $assignment->designer?->user?->name ?: 'غير محدد';
        $this->quickAddTagId = null;
        $this->quickAddIdeaId = null;
        $this->quickAddCustomIdea = null;
        $this->quickAddScheduledSendingAt = null;

        $tags = $this->tagDistributionService->collectTagsForAssignment($assignment);
        if ($tags->isNotEmpty()) {
            $this->quickAddAvailableTags = $tags->pluck('name', 'id')->toArray();
        } else {
            $this->quickAddAvailableTags = Tag::where('is_active', true)->pluck('name', 'id')->toArray();
        }

        $this->quickAddAvailableIdeas = [];

        $this->dispatch('open-modal', id: 'quick-add-modal');
    }

    public function updatedQuickAddTagId(): void
    {
        $assignment = ClientDesigner::find($this->quickAddAssignmentId);
        $clientId = $assignment?->client_id;

        if ($this->quickAddTagId) {
            $this->quickAddAvailableIdeas = $this->tagDistributionService->loadAvailableIdeasForTag(
                $this->quickAddTagId,
                $clientId,
                $this->selectedWeek,
                null
            );
        } else {
            $this->quickAddAvailableIdeas = [];
        }

        $this->quickAddIdeaId = null;
    }

    public function saveQuickAdd(): void
    {
        $this->guardAgainstPastWeek();

        $this->validate([
            'quickAddAssignmentId' => 'required|exists:client_designer,id',
            'quickAddTagId' => 'required|exists:tags,id',
            'quickAddDate' => 'required|date',
        ]);

        if (Carbon::parse($this->quickAddDate)->startOfDay()->lt(Carbon::today())) {
            Notification::make()
                ->title('تاريخ في الماضي')
                ->body('لا يمكن إضافة تاق في تاريخ سابق لليوم.')
                ->danger()
                ->send();

            return;
        }

        $assignment = ClientDesigner::find($this->quickAddAssignmentId);
        if (! $assignment) {
            return;
        }

        $ideaId = ! empty($this->quickAddIdeaId) ? (int) $this->quickAddIdeaId : null;
        $customIdea = ! empty($this->quickAddCustomIdea) ? $this->quickAddCustomIdea : null;

        $scheduledSendingAt = null;
        if (! empty($this->quickAddScheduledSendingAt) && auth()->user()->can('edit_sending_time')) {
            $scheduledSendingAt = $this->quickAddScheduledSendingAt;
        } else {
            $scheduledSendingAt = $this->tagDistributionService->calculateScheduledSendingAt(
                $this->quickAddDate,
                $this->quickAddTagId,
                $ideaId,
                $this->selectedWeek
            );
        }

        ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $this->quickAddTagId,
            'distribution_date' => $this->quickAddDate,
            'idea_id' => $ideaId,
            'custom_idea' => $customIdea,
            'status' => 'pending',
            'scheduled_sending_at' => $scheduledSendingAt,
            'created_by_user' => auth()->id(),
            'updated_by_user' => auth()->id(),
        ]);

        $this->reset([
            'quickAddAssignmentId',
            'quickAddDate',
            'quickAddTagId',
            'quickAddIdeaId',
            'quickAddCustomIdea',
            'quickAddScheduledSendingAt',
            'quickAddAvailableTags',
            'quickAddAvailableIdeas',
            'quickAddClientName',
            'quickAddDesignerName',
        ]);

        $this->dispatch('close-modal', id: 'quick-add-modal');

        Notification::make()
            ->title('تم إضافة التاق بنجاح')
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function updateDetails(): void
    {
        $this->guardAgainstPastWeek();

        $this->validate([
            'newTagId' => 'required|exists:tags,id',
        ]);

        $distribution = ClientTagDistribution::find($this->editingDistributionId);

        if ($distribution) {
            $ideaId = ! empty($this->newIdeaId) ? (int) $this->newIdeaId : null;
            $customIdea = ! empty($this->newCustomIdea) ? $this->newCustomIdea : null;

            // إعادة حساب وقت الإرسال إذا تغير التاق أو الفكرة
            if ((int) $distribution->tag_id !== (int) $this->newTagId || $distribution->idea_id !== $ideaId) {
                $scheduledSendingAt = $this->tagDistributionService->calculateScheduledSendingAt(
                    $distribution->distribution_date,
                    $this->newTagId,
                    $ideaId,
                    $this->selectedWeek
                );
            } else {
                $scheduledSendingAt = $distribution->scheduled_sending_at;
            }

            $distribution->update([
                'tag_id' => $this->newTagId,
                'idea_id' => $ideaId,
                'custom_idea' => $customIdea,
                'scheduled_sending_at' => $scheduledSendingAt,
            ]);

            Notification::make()
                ->title('تم تعديل التاق بنجاح')
                ->success()
                ->send();
        }

        $this->reset(['editingDistributionId', 'newTagId', 'newIdeaId', 'newCustomIdea', 'availableTags', 'availableIdeas']);
        $this->dispatch('close-modal', id: 'edit-details-modal');
        $this->loadAssignments();
    }

    public function openChangeDateModal(int $id): void
    {
        $this->editingDistributionId = $id;
        $distribution = ClientTagDistribution::find($id);

        $this->newDate = $distribution?->distribution_date;
        $this->newScheduledSendingAt = $distribution?->scheduled_sending_at?->format('Y-m-d\TH:i');

        $this->dispatch('open-modal', id: 'change-date-modal');
    }

    public function changeDate(): void
    {
        $this->guardAgainstPastWeek();

        $distribution = ClientTagDistribution::find($this->editingDistributionId);

        if (! $distribution) {
            $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
            $this->dispatch('close-modal', id: 'change-date-modal');
            $this->loadAssignments();

            return;
        }

        // رفض تعيين تاريخ توزيع في الماضي فقط إذا كان المستخدم يغير تاريخ التوزيع إلى يوم سابق ولم يكن أدمن
        $isChangingDistributionDate = $distribution->distribution_date !== $this->newDate;
        if ($isChangingDistributionDate && ! $this->isAdmin() && Carbon::parse($this->newDate)->startOfDay()->lt(Carbon::today())) {
            Notification::make()
                ->title('تاريخ في الماضي')
                ->body('لا يمكن تغيير تاريخ التاق إلى تاريخ في الماضي.')
                ->danger()
                ->send();
            $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
            $this->dispatch('close-modal', id: 'change-date-modal');
            $this->loadAssignments();

            return;
        }

        // التحقق من أن وقت الإرسال المدخل ليس في الماضي
        if ($this->newScheduledSendingAt && ! $this->isAdmin() && Carbon::parse($this->newScheduledSendingAt)->isPast()) {
            Notification::make()
                ->title('وقت الإرسال في الماضي')
                ->body('لا يمكن تعيين وقت إرسال في الماضي.')
                ->danger()
                ->send();
            $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
            $this->dispatch('close-modal', id: 'change-date-modal');
            $this->loadAssignments();

            return;
        }

        if ($distribution) {
            if (in_array($distribution->status, ['sending', 'reviewing', 'completed']) && ! $this->isAdmin()) {
                $statusMap = [
                    'sending' => 'قيد الإرسال',
                    'reviewing' => 'قيد المراجعة',
                    'completed' => 'تم إرساله',
                ];
                $statusLabel = $statusMap[$distribution->status] ?? $distribution->status;

                Notification::make()
                    ->title('لا يمكن تغيير تاريخ هذا التاق')
                    ->body("لا يمكن تغيير تاريخ تاق حالته {$statusLabel}.")
                    ->danger()
                    ->send();
                $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
                $this->dispatch('close-modal', id: 'change-date-modal');
                $this->loadAssignments();

                return;
            }

            $newDateCarbon = Carbon::parse($this->newDate);

            // التحقق من أن تاريخ التوزيع الجديد ليس بعد وقت الإرسال (غير منطقي)
            $sendingTimeToCheck = $this->newScheduledSendingAt
                ? Carbon::parse($this->newScheduledSendingAt)
                : $distribution->scheduled_sending_at;

            if ($sendingTimeToCheck && $newDateCarbon->gt($sendingTimeToCheck->startOfDay())) {
                Notification::make()
                    ->title('لا يمكن تغيير التاريخ')
                    ->body('تاريخ التوزيع الجديد بعد وقت الإرسال المحدد، لا يمكن نقل التاق إلى يوم بعد وقت إرساله.')
                    ->danger()
                    ->send();
                $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
                $this->dispatch('close-modal', id: 'change-date-modal');
                $this->loadAssignments();

                return;
            }

            // استخدام وقت الإرسال المدخل يدوياً إذا وُجد ولدى المستخدم صلاحية
            if ($this->newScheduledSendingAt && auth()->user()->can('edit_sending_time')) {
                $distribution->scheduled_sending_at = Carbon::parse($this->newScheduledSendingAt);
            } elseif ($distribution->scheduled_sending_at) {
                // الحفاظ على وقت الإرسال الموجود مسبقاً (تحديث التاريخ فقط)
                $existingDateTime = Carbon::parse($distribution->scheduled_sending_at);
                $distribution->scheduled_sending_at = $newDateCarbon->format('Y-m-d').' '.$existingDateTime->format('H:i:s');
            } else {
                $distribution->scheduled_sending_at = $this->tagDistributionService->calculateScheduledSendingAt(
                    $this->newDate,
                    $distribution->tag_id,
                    $distribution->idea_id,
                    $this->selectedWeek
                );
            }

            $distribution->distribution_date = $this->newDate;
            $distribution->save();

            Notification::make()
                ->title('تم تغيير تاريخ التاق بنجاح')
                ->success()
                ->send();
        }

        $this->reset(['editingDistributionId', 'newDate', 'newScheduledSendingAt']);
        $this->dispatch('close-modal', id: 'change-date-modal');
        $this->loadAssignments();
    }

    public function openUploadDesignModal(int $id): void
    {
        $this->guardAgainstPastWeek();

        $distribution = ClientTagDistribution::with(['clientDesigner.client', 'tag'])->find($id);

        if (! $distribution) {
            return;
        }

        $this->uploadDistributionId = $id;
        $this->uploadClientName = $distribution->clientDesigner?->client?->company ?: ($distribution->clientDesigner?->client?->client_name ?: 'العميل');
        $this->uploadTagName = $distribution->tag?->name ?? 'تاق';
        $this->uploadDate = $distribution->distribution_date;
        $this->uploadTargetStatus = 'sending';
        $this->uploadNotes = $distribution->designer_notes;
        $this->uploadScheduledSendingAt = $distribution->scheduled_sending_at?->format('Y-m-d\TH:i');
        $this->uploadFile = null;

        $this->dispatch('open-modal', id: 'upload-design-modal');
    }

    public function saveUploadedDesign(): void
    {
        $this->guardAgainstPastWeek();

        $this->validate([
            'uploadFile' => 'required|image|max:10240',
        ], [
            'uploadFile.required' => 'يرجى اختيار أو سحب صورة التصميم المنجز.',
            'uploadFile.image' => 'يجب أن يكون الملف صورة صالحة (PNG, JPG, JPEG, WEBP).',
            'uploadFile.max' => 'الحد الأقصى لحجم الصورة هو 10 ميجابايت.',
        ]);

        if (! $this->uploadDistributionId) {
            return;
        }

        $distribution = ClientTagDistribution::with(['clientDesigner.client', 'tag'])->find($this->uploadDistributionId);

        if (! $distribution) {
            Notification::make()
                ->title('سجل التوزيع غير موجود')
                ->danger()
                ->send();

            return;
        }

        $clientId = $distribution->clientDesigner?->client_id ?? 'unknown';
        $path = $this->uploadFile->store("clients/{$clientId}/submissions/{$distribution->id}", 'public');

        $targetStatus = in_array($this->uploadTargetStatus, ['sending', 'reviewing'], true)
            ? $this->uploadTargetStatus
            : 'sending';

        $updateData = [
            'attachment_path' => $path,
            'status' => $targetStatus,
        ];

        if ($this->uploadNotes !== null) {
            $updateData['designer_notes'] = $this->uploadNotes;
        }

        if ($targetStatus === 'sending') {
            $updateData['reviewer_id'] = auth()->id();

            if (! empty($this->uploadScheduledSendingAt)) {
                $updateData['scheduled_sending_at'] = Carbon::parse($this->uploadScheduledSendingAt);
            } elseif (! $distribution->scheduled_sending_at) {
                $sendDate = Carbon::now()->hour < 6 ? Carbon::today() : Carbon::tomorrow();
                $weeklyTime = $distribution->tag?->weekly_time ? Carbon::parse($distribution->tag->weekly_time) : null;

                if ($weeklyTime) {
                    $updateData['scheduled_sending_at'] = $sendDate->setTime($weeklyTime->hour, $weeklyTime->minute, $weeklyTime->second);
                } else {
                    $updateData['scheduled_sending_at'] = $sendDate->setTime(12, 0, 0);
                }
            }
        }

        $distribution->update($updateData);

        $this->dispatch('close-modal', id: 'upload-design-modal');
        $this->reset(['uploadFile', 'uploadNotes', 'uploadDistributionId', 'uploadScheduledSendingAt', 'uploadTargetStatus']);

        $statusMsg = $targetStatus === 'sending' ? 'وجاهز للإرسال والنشر 🚀' : 'وتم تحويله للمراجعة 🔍';

        Notification::make()
            ->title("تم رفع واعتماد التصميم بنجاح لـ {$this->uploadClientName} {$statusMsg}")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function getWeekDays(): array
    {
        if ($this->cachedWeekDays === null) {
            $this->cachedWeekDays = $this->tagDistributionService->getWeekDays($this->selectedWeek);
        }

        return $this->cachedWeekDays;
    }

    public function getWeekDateRange(): string
    {
        $start = Carbon::parse($this->selectedWeek);
        $end = $start->copy()->addDays(6);

        return sprintf(
            'الأسبوع من %s إلى %s',
            $start->translatedFormat('j F Y'),
            $end->translatedFormat('j F Y')
        );
    }

    public function isPastWeek(): bool
    {
        return Carbon::parse($this->selectedWeek)
            ->startOfWeek()
            ->lt(Carbon::now()->startOfWeek());
    }

    protected function guardAgainstPastWeek(): void
    {
        if ($this->isPastWeek()) {
            abort(403, 'لا يمكن تعديل التوزيع في الأسابيع السابقة');
        }
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

    public function navigateToWeek(string $week): void
    {
        $this->selectedWeek = $week;
        $this->cachedWeekDays = null;
        $this->distributionStatus = [];
        $this->loadAssignments();

        if ($this->assignments->isNotEmpty()) {
            $hasCurrentTab = $this->assignments->contains(fn ($a) => $a->designer?->user?->name === $this->activeTab);
            if (! $hasCurrentTab) {
                $this->activeTab = $this->assignments->first()->designer?->user?->name;
            }
        } else {
            $this->activeTab = null;
        }

        $this->dispatch('$refresh');
    }

    public function mount(): void
    {
        $this->selectedWeek = $this->selectedWeek ?? request()->query('week', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $this->loadAssignments();

        if (! $this->activeTab && $this->assignments->isNotEmpty()) {
            $this->activeTab = $this->assignments->first()->designer?->user?->name;
        }
    }

    public function loadAssignments(): void
    {
        $this->assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])
            ->where('week_start_date', $this->selectedWeek)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('تصدير إكسل')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('exportExcel'),

            Action::make('addSideTask')
                ->label('إضافة مهمة جانبية')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->modalHeading('إضافة مهمة جانبية جديدة')
                ->modalWidth('2xl')
                ->form([
                    Select::make('designer_id')
                        ->label('المصمم')
                        ->options(function () {
                            return Designer::with('user')
                                ->whereNotNull('max_capacity')
                                ->where('max_capacity', '>', 0)
                                ->get()
                                ->pluck('user.name', 'id');
                        })
                        ->default(function () {
                            if ($this->activeTab) {
                                $designer = Designer::whereHas('user', fn ($q) => $q->where('name', $this->activeTab))->first();
                                if ($designer) {
                                    return $designer->id;
                                }
                            }

                            return $this->assignments->first()?->designer_id ?? Designer::first()?->id;
                        })
                        ->searchable()
                        ->required()
                        ->reactive(),

                    Select::make('client_id')
                        ->label('العميل')
                        ->options(function () {
                            return Client::with('category')
                                ->orderBy('company')
                                ->get()
                                ->mapWithKeys(function ($client) {
                                    $name = $client->company ?: ($client->client_name ?: "عميل #{$client->id}");
                                    $category = $client->category?->name ? " ({$client->category->name})" : '';

                                    return [$client->id => "{$name}{$category}"];
                                });
                        })
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (callable $set) {
                            $set('tag_id', null);
                            $set('idea_id', null);
                        }),

                    Select::make('tag_id')
                        ->label('نوع المهمة (التاق)')
                        ->options(function (callable $get) {
                            $clientId = $get('client_id');
                            if (! $clientId) {
                                return Tag::where('is_active', true)->pluck('name', 'id');
                            }

                            $client = Client::with(['category', 'tags', 'tagGroups'])->find($clientId);
                            if (! $client) {
                                return Tag::where('is_active', true)->pluck('name', 'id');
                            }

                            $categoryId = $client->category_id;
                            $tagsQuery = Tag::where('is_active', true);

                            if ($categoryId) {
                                $tagsQuery->where(function ($q) use ($categoryId, $client) {
                                    $q->whereHas('categories', fn ($catQuery) => $catQuery->where('categories.id', $categoryId))
                                        ->orWhere('assign_all_categories', true)
                                        ->orWhereHas('clients', fn ($cliQuery) => $cliQuery->where('clients.id', $client->id));

                                    if ($client->tagGroups && $client->tagGroups->isNotEmpty()) {
                                        $q->orWhereIn('tag_group_id', $client->tagGroups->pluck('id'));
                                    }
                                });
                            }

                            $tags = $tagsQuery->pluck('name', 'id');

                            return $tags->isNotEmpty() ? $tags : Tag::where('is_active', true)->pluck('name', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) => $set('idea_id', null))
                        ->helperText(function (callable $get) {
                            $clientId = $get('client_id');
                            if (! $clientId) {
                                return 'اختر العميل أولاً لفلترة التاقات حسب تصنيفه';
                            }

                            $client = Client::with('category')->find($clientId);
                            if ($client?->category) {
                                return "يتم عرض التاقات المرتبطة بتصنيف ({$client->category->name})";
                            }

                            return null;
                        }),

                    DatePicker::make('distribution_date')
                        ->label('تاريخ الإسناد')
                        ->required()
                        ->default(now()->format('Y-m-d'))
                        ->rules(['required', 'date', 'after_or_equal:today']),

                    Select::make('idea_id')
                        ->label('الفكرة المقترحة (اختياري)')
                        ->options(function (callable $get) {
                            $tagId = $get('tag_id');
                            $clientId = $get('client_id');
                            if (! $tagId) {
                                return [];
                            }

                            $query = Idea::whereHas('tags', function ($q) use ($tagId) {
                                $q->where('tags.id', $tagId);
                            });

                            if ($clientId) {
                                $client = Client::find($clientId);

                                $query->whereDoesntHave('blockedClients', function ($q) use ($clientId) {
                                    $q->where('client_id', $clientId);
                                })->where(function ($q) use ($clientId) {
                                    $q->doesntHave('clients')
                                        ->orWhereHas('clients', function ($sub) use ($clientId) {
                                            $sub->where('clients.id', $clientId);
                                        });
                                });

                                if ($client?->location_id) {
                                    $locationId = $client->location_id;
                                    $query->where(function ($q) use ($locationId) {
                                        $q->doesntHave('locations')
                                            ->orWhereHas('locations', function ($sub) use ($locationId) {
                                                $sub->where('locations.id', $locationId);
                                            });
                                    });
                                }
                            }

                            return $query->pluck('name', 'id');
                        })
                        ->searchable()
                        ->placeholder('اختر فكرة أو اتركه فارغاً'),

                    Textarea::make('custom_idea')
                        ->label('توجيهات مخصصة (اختياري)')
                        ->rows(3)
                        ->placeholder('أضف تفاصيل أو تعليمات إضافية للمصمم...'),

                    \Filament\Forms\Components\DateTimePicker::make('scheduled_sending_at')
                        ->label('وقت الإرسال (اختياري)')
                        ->visible(fn () => auth()->user()->can('edit_sending_time')),
                ])
                ->action(function (array $data): void {
                    $this->guardAgainstPastWeek();
                    $ideaId = ! empty($data['idea_id']) ? (int) $data['idea_id'] : null;
                    $customIdea = ! empty($data['custom_idea']) ? $data['custom_idea'] : null;

                    // استخدام وقت الإرسال المدخل يدوياً إذا وُجد
                    $scheduledSendingAt = null;
                    if (! empty($data['scheduled_sending_at']) && auth()->user()->can('edit_sending_time')) {
                        $scheduledSendingAt = $data['scheduled_sending_at'];
                    } else {
                        $scheduledSendingAt = $this->tagDistributionService->calculateScheduledSendingAt(
                            $data['distribution_date'],
                            $data['tag_id'],
                            $ideaId,
                            $this->selectedWeek
                        );
                    }

                    // التحقق مما إذا كان هذا المصمم هو المصمم الأساسي للعميل أم مصمم جانبي
                    $primaryAssignment = ClientDesigner::where('client_id', $data['client_id'])
                        ->where('week_start_date', $this->selectedWeek)
                        ->where('is_side', false)
                        ->first();

                    $isSide = $primaryAssignment && ($primaryAssignment->designer_id != $data['designer_id']);

                    $contract = Contract::where('client_id', $data['client_id'])
                        ->where('status', 'active')
                        ->latest()
                        ->first();

                    $clientDesigner = ClientDesigner::firstOrCreate(
                        [
                            'client_id' => $data['client_id'],
                            'designer_id' => $data['designer_id'],
                            'week_start_date' => $this->selectedWeek,
                        ],
                        [
                            'contract_id' => $contract?->id,
                            'is_side' => $isSide,
                        ]
                    );

                    ClientTagDistribution::create([
                        'client_designer_id' => $clientDesigner->id,
                        'tag_id' => $data['tag_id'],
                        'distribution_date' => $data['distribution_date'],
                        'idea_id' => $ideaId,
                        'custom_idea' => $customIdea,
                        'status' => 'pending',
                        'scheduled_sending_at' => $scheduledSendingAt,
                    ]);

                    $this->loadAssignments();

                    Notification::make()
                        ->title('تم إضافة المهمة الجانبية بنجاح')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function autoDistribute(): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->autoDistribute($this->assignments, $this->selectedWeek);

        Notification::make()
            ->title('تم توزيع التاقات بنجاح')
            ->body("تم توزيع {$count} تاق على العملاء.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearTags(): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->clearTags($this->assignments);

        Notification::make()
            ->title('تم حذف توزيع التاقات')
            ->body("تم حذف {$count} تاق.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearClientTags(int $assignmentId): void
    {
        $this->guardAgainstPastWeek();

        $assignment = ClientDesigner::find($assignmentId);

        if (! $assignment || $assignment->week_start_date !== $this->selectedWeek) {
            abort(403, 'لا يمكن تعديل هذا التعيين');
        }

        $this->tagDistributionService->clearClientTags($assignmentId);

        Notification::make()
            ->title('تم حذف تاقات العميل')
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function autoAssignIdeas(): void
    {
        $this->guardAgainstPastWeek();

        $assignedCount = $this->tagDistributionService->autoAssignIdeas($this->assignments, $this->selectedWeek);

        Notification::make()
            ->title('تم توزيع الأفكار بنجاح')
            ->body("تم إسناد {$assignedCount} فكرة.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearIdeas(): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->clearIdeas($this->assignments);

        Notification::make()
            ->title('تم حذف الأفكار')
            ->body("تم حذف {$count} فكرة من التوزيع.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearTagsForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->clearTagsForDesigner($this->assignments, $designerId);

        unset($this->distributionStatus[$designerId]);

        Notification::make()
            ->title('تم حذف تاقات المصمم')
            ->body("تم حذف {$count} تاق.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function clearIdeasForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->clearIdeasForDesigner($this->assignments, $designerId);

        Notification::make()
            ->title('تم حذف أفكار المصمم')
            ->body("تم حذف {$count} فكرة.")
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function distributeIdeasForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $assignedCount = $this->tagDistributionService->distributeIdeasForDesigner($this->assignments, $designerId, $this->selectedWeek);

        if (! $this->isSmartDistributionRunning) {
            Notification::make()
                ->title('تم توزيع أفكار المصمم')
                ->body("تم إسناد {$assignedCount} فكرة.")
                ->success()
                ->send();
        }

        $this->loadAssignments();
    }

    public function distributeSmartForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $this->isSmartDistributionRunning = true;

        try {
            $this->tagDistributionService->distributeSmartForDesigner($this->assignments, $designerId, $this->selectedWeek);
        } finally {
            $this->isSmartDistributionRunning = false;
        }

        Notification::make()
            ->title('تم التوزيع الذكي بنجاح')
            ->body('تم تنفيذ التوزيع المتدرج: العالية جداً ← العالية ← البقية ← الأفكار.')
            ->success()
            ->send();

        $this->loadAssignments();
    }

    public function distributeVeryHighTagsForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->distributeVeryHighTagsForDesigner($this->assignments, $designerId, $this->selectedWeek);

        if ($count > 0) {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('تم توزيع التاقات الهامة')
                    ->body("تم توزيع {$count} تاق عالي الأهمية للمصمم.")
                    ->success()
                    ->send();
            }

            $this->loadAssignments();
        } else {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('لا توجد تاقات متاحة')
                    ->body('لم يتم العثور على تاقات يمكن توزيعها (طابق القواعد أو الحصة).')
                    ->info()
                    ->send();
            }
        }

        $this->distributionStatus[$designerId]['very_high_processed'] = true;
    }

    public function distributeHighTagsForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->distributeHighTagsForDesigner($this->assignments, $designerId, $this->selectedWeek);

        if ($count > 0) {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('تم توزيع التاقات العالية')
                    ->body("تم توزيع {$count} تاق عالي الأهمية للمصمم.")
                    ->success()
                    ->send();
            }

            $this->loadAssignments();
        } else {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('لا توجد تاقات متاحة')
                    ->body('لم يتم العثور على تاقات عالية يمكن توزيعها وفق القواعد (مثلاً: قبل موعد الإرسال بـ 3-5 أيام).')
                    ->info()
                    ->send();
            }
        }

        $this->distributionStatus[$designerId]['high_processed'] = true;
    }

    public function distributeMediumLowTagsForDesigner($designerId): void
    {
        $this->guardAgainstPastWeek();

        $count = $this->tagDistributionService->distributeMediumLowTagsForDesigner($this->assignments, $designerId, $this->selectedWeek);

        if ($count > 0) {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('تم توزيع التاقات المتوسطة/المنخفضة')
                    ->body("تم توزيع {$count} تاق.")
                    ->success()
                    ->send();
            }

            $this->loadAssignments();
        } else {
            if (! $this->isSmartDistributionRunning) {
                Notification::make()
                    ->title('لا توجد تاقات متاحة')
                    ->body('لم يتم العثور على تاقات يمكن توزيعها.')
                    ->info()
                    ->send();
            }
        }
    }

    public function hasDistributedVeryHigh($designerId): bool
    {
        if ($this->distributionStatus[$designerId]['very_high_processed'] ?? false) {
            return true;
        }

        if ($this->tagDistributionService->hasDistributedVeryHigh($this->assignments, $designerId)) {
            return true;
        }

        // إذا لم يكن لدى أي من عملاء هذا المصمم تاقات عالية جداً مفعلة، نعتبر الشرط متجاوزاً
        $designerAssignments = collect($this->assignments)->where('designer_id', $designerId);
        $hasAnyVeryHighClient = $designerAssignments->contains(function ($assignment) {
            return (bool) ($assignment->client?->enable_very_high ?? false);
        });

        if (! $hasAnyVeryHighClient) {
            return true;
        }

        return false;
    }

    public function hasDistributedHigh($designerId): bool
    {
        if ($this->distributionStatus[$designerId]['high_processed'] ?? false) {
            return true;
        }

        if ($this->tagDistributionService->hasDistributedHigh($this->assignments, $designerId)) {
            return true;
        }

        return false;
    }

    public function exportExcel(): BinaryFileResponse
    {
        abort_unless(static::canAccess(), 403);

        $exportService = app(TagDistributionExportService::class);
        $filePath = $exportService->export($this->assignments, $this->selectedWeek);
        $fileName = $exportService->generateFileName($this->selectedWeek);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
