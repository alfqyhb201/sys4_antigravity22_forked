<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Enums\ClientTemplateType;
use App\Filament\Enums\DesignTaskStatus;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\DesignTask;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class DesignerDashboard extends Page
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    /**
     * عنوان الصفحة في القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'لوحة المصمم';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_designer_dashboard');
    }

    /**
     * عنوان الصفحة.
     */
    protected static ?string $title = 'لوحة المصمم';

    public ?string $filterDate = null;

    protected function getListeners(): array
    {
        return [
            'order-refresh' => '$refresh',
        ];
    }

    public function mount(): void
    {
        $this->filterDate = Carbon::now()->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $current = $this->filterDate && $this->validateDateString($this->filterDate)
            ? Carbon::parse($this->filterDate)
            : Carbon::today();

        $this->filterDate = $current->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $current = $this->filterDate && $this->validateDateString($this->filterDate)
            ? Carbon::parse($this->filterDate)
            : Carbon::today();

        $this->filterDate = $current->addDay()->format('Y-m-d');
    }

    public function goToToday(): void
    {
        $this->filterDate = Carbon::today()->format('Y-m-d');
    }

    public function goToYesterday(): void
    {
        $this->filterDate = Carbon::yesterday()->format('Y-m-d');
    }

    private function getDesigner(): ?Designer
    {
        return Designer::where('user_id', auth()->id())->first();
    }

    private function validateOwnership(?ClientTagDistribution $record): bool
    {
        if (! $record) {
            return false;
        }

        $designer = $this->getDesigner();

        if (! $designer) {
            return false;
        }

        $assignment = $record->clientDesigner;

        return $assignment && $assignment->designer_id === $designer->id;
    }

    private function validateDateString(?string $date): bool
    {
        if (blank($date)) {
            return true;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    /**
     * اسم العرض الخاص بالصفحة.
     */
    protected static string $view = 'filament.pages.designer-dashboard';

    /**
     * الحصول على البيانات التي يتم تمريرها إلى العرض.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $designer = $this->getDesigner();

        $targetDate = $this->filterDate && $this->validateDateString($this->filterDate) ? Carbon::parse($this->filterDate) : Carbon::now();
        $weekStartDate = $targetDate->copy()->startOfWeek()->format('Y-m-d');

        $changesCount = 0;
        $reviewingCount = 0;
        $pendingOrders = collect();
        $pendingDailyTasks = collect();
        $pendingDesignTasks = collect();
        $pendingTemplateTasks = collect();
        $changesRequestedDaily = collect();
        $needsRevisionDesignTasks = collect();
        $needsRevisionTemplateTasks = collect();
        $reviewingDailyTasks = collect();
        $completedDailyTasks = collect();

        if ($designer) {
            $targetDateStr = $targetDate->format('Y-m-d');
            $assignments = ClientDesigner::query()
                ->where('designer_id', $designer->id)
                ->where(function ($q) use ($weekStartDate, $targetDateStr) {
                    $q->where('week_start_date', $weekStartDate)
                        ->orWhereHas('distributions', function ($dq) use ($targetDateStr) {
                            $dq->where('distribution_date', $targetDateStr)
                                ->where('status', '!=', 'frozen');
                        });
                })
                ->with([
                    'client.templates',
                    'distributions' => fn ($q) => $q->where('status', '!=', 'frozen')->orderBy('distribution_date'),
                    'distributions.tag',
                    'distributions.idea',
                ])
                ->get();

            foreach ($assignments as $assignment) {
                foreach ($assignment->distributions as $dist) {
                    $status = strtolower(trim($dist->status ?? 'pending'));
                    $date = $dist->distribution_date;

                    $formatted = $this->formatDailyTask($dist, $assignment->client);

                    if ($status === 'changes_requested') {
                        $changesRequestedDaily->push($formatted);
                    } elseif ($status === 'reviewing') {
                        $reviewingDailyTasks->push($formatted);
                    }

                    if ($date == $targetDate->format('Y-m-d') && in_array($status, ['pending', 'in_progress', '', 'null'])) {
                        $pendingDailyTasks->push($formatted);
                    }

                    if ($date == $targetDate->format('Y-m-d') && in_array($status, ['completed', 'sending'])) {
                        $completedDailyTasks->push($formatted);
                    }
                }
            }

            // Load design tasks - split by status and type
            $baseTasks = DesignTask::query()
                ->where('designer_id', $designer->id)
                ->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))
                ->with(['client.templates', 'assigner'])
                ->get();

            foreach ($baseTasks as $task) {
                $tStatus = $task->status->value;
                $isTemplate = $task->is_template_update;

                // Pending → active tab
                if ($tStatus === DesignTaskStatus::Pending->value) {
                    $formatted = $this->formatDesignTask($task);
                    if ($isTemplate) {
                        $pendingTemplateTasks->push($formatted);
                    } else {
                        $pendingDesignTasks->push($formatted);
                    }
                }

                // NeedsRevision → changes tab
                if ($tStatus === DesignTaskStatus::NeedsRevision->value) {
                    $formatted = $this->formatDesignTask($task);
                    if ($isTemplate) {
                        $needsRevisionTemplateTasks->push($formatted);
                    } else {
                        $needsRevisionDesignTasks->push($formatted);
                    }
                }
            }

            // Sort pending design tasks by priority
            $pendingDesignTasks = $pendingDesignTasks->sortByDesc('priority_num')->values();
            $pendingTemplateTasks = $pendingTemplateTasks->sortByDesc('priority_num')->values();

            // Sort needs revision by ID (oldest first)
            $needsRevisionDesignTasks = $needsRevisionDesignTasks->sortBy('id')->values();
            $needsRevisionTemplateTasks = $needsRevisionTemplateTasks->sortBy('id')->values();

            // Sort pending daily tasks by priority descending
            $pendingDailyTasks = $pendingDailyTasks->sortByDesc('priority_num')->values();

            // Sort changes requested by distribution_date ascending
            $changesRequestedDaily = $changesRequestedDaily->sortBy('distribution_date')->values();

            // Sort reviewing by distribution_date ascending
            $reviewingDailyTasks = $reviewingDailyTasks->sortBy('distribution_date')->values();

            // Sort completed daily tasks by ID descending
            $completedDailyTasks = $completedDailyTasks->sortByDesc('id')->values();

            // Load pending orders
            $pendingOrders = Order::query()
                ->where('designer_id', $designer->id)
                ->where('status', OrderStatus::Pending)
                ->with('assigner')
                ->latest()
                ->get();

            // Total counts
            $changesCount = $changesRequestedDaily->count() + $needsRevisionDesignTasks->count() + $needsRevisionTemplateTasks->count();
            $reviewingCount = $reviewingDailyTasks->count();
        }

        // ──────────────────────────────────────────────
        // حساب المهام المتأخرة (من الأيام السابقة)
        // ──────────────────────────────────────────────
        $overdueCount = 0;
        $overdueGrouped = [];

        if ($designer) {
            $dayNames = [
                'Sunday' => 'الأحد',
                'Monday' => 'الاثنين',
                'Tuesday' => 'الثلاثاء',
                'Wednesday' => 'الأربعاء',
                'Thursday' => 'الخميس',
                'Friday' => 'الجمعة',
                'Saturday' => 'السبت',
            ];

            // 1. المهام اليومية المتأخرة (ClientTagDistribution)
            $overdueDaily = ClientTagDistribution::query()
                ->whereHas('clientDesigner', fn ($q) => $q->where('designer_id', $designer->id))
                ->where('distribution_date', '<', $targetDate->format('Y-m-d'))
                ->whereIn('status', ['pending', 'in_progress', 'changes_requested'])
                ->with(['clientDesigner.client', 'tag'])
                ->get();

            foreach ($overdueDaily as $dist) {
                $date = $dist->distribution_date;
                $carbonDate = Carbon::parse($date);
                $dayName = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');
                $formattedDate = $carbonDate->locale('ar')->isoFormat('D MMMM YYYY');
                $clientName = $dist->clientDesigner?->client?->company ?? 'عميل غير معروف';
                $tagName = $dist->tag?->name ?? '';

                $overdueGrouped[$date]['date'] = $date;
                $overdueGrouped[$date]['day_name'] = $dayName;
                $overdueGrouped[$date]['formatted_date'] = $dayName.' '.$formattedDate;
                $overdueGrouped[$date]['tasks'][] = [
                    'type' => 'daily',
                    'client_name' => $clientName,
                    'tag_name' => $tagName,
                    'description' => $dist->custom_idea ?: ($dist->idea?->content ?? ''),
                    'task_type_label' => 'مهمة يومية',
                ];
                $overdueCount++;
            }

            // 2. مهام التصميم المتأخرة (DesignTask)
            $overdueDesign = DesignTask::query()
                ->where('designer_id', $designer->id)
                ->whereIn('status', [DesignTaskStatus::Pending->value, DesignTaskStatus::NeedsRevision->value])
                ->where(function ($q) {
                    $q->whereNull('scheduled_at')
                        ->whereDate('created_at', '<', now()->toDateString());
                })
                ->orWhere(function ($q) use ($designer) {
                    $q->where('designer_id', $designer->id)
                        ->whereIn('status', [DesignTaskStatus::Pending->value, DesignTaskStatus::NeedsRevision->value])
                        ->whereNotNull('scheduled_at')
                        ->where('scheduled_at', '<', now());
                })
                ->with(['client', 'assigner'])
                ->get();

            foreach ($overdueDesign as $task) {
                $date = $task->created_at->format('Y-m-d');
                $carbonDate = $task->created_at;
                $dayName = $dayNames[$carbonDate->format('l')] ?? $carbonDate->format('l');
                $formattedDate = $carbonDate->locale('ar')->isoFormat('D MMMM YYYY');
                $clientName = $task->display_client_name ?: $task->client_name ?: ($task->client?->company ?? 'عميل غير معروف');
                $isTemplate = $task->is_template_update;

                $overdueGrouped[$date]['date'] = $date;
                $overdueGrouped[$date]['day_name'] = $dayName;
                $overdueGrouped[$date]['formatted_date'] = $dayName.' '.$formattedDate;
                $overdueGrouped[$date]['tasks'][] = [
                    'type' => $isTemplate ? 'template' : 'design',
                    'client_name' => $clientName,
                    'tag_name' => '',
                    'description' => $task->description ?? '',
                    'task_type_label' => $isTemplate ? 'تحديث قالب' : 'مهمة تصميم',
                ];
                $overdueCount++;
            }

            // ترتيب المجموعات حسب التاريخ (الأقدم أولاً)
            ksort($overdueGrouped);
            $overdueGrouped = array_values($overdueGrouped);
        }

        return [
            'designer' => $designer,
            'user' => $user,
            'filterDate' => $this->filterDate,
            'changesCount' => $changesCount,
            'reviewingCount' => $reviewingCount,
            'pendingOrders' => $pendingOrders,
            'pendingDailyTasks' => $pendingDailyTasks,
            'pendingDesignTasks' => $pendingDesignTasks,
            'pendingTemplateTasks' => $pendingTemplateTasks,
            'changesRequestedDaily' => $changesRequestedDaily,
            'needsRevisionDesignTasks' => $needsRevisionDesignTasks,
            'needsRevisionTemplateTasks' => $needsRevisionTemplateTasks,
            'reviewingDailyTasks' => $reviewingDailyTasks,
            'completedDailyTasks' => $completedDailyTasks,
            'completedCount' => $completedDailyTasks->count(),
            'overdueCount' => $overdueCount,
            'overdueGrouped' => $overdueGrouped,
        ];
    }

    private function formatDailyTask($dist, $client): array
    {
        $tag = $dist->tag;
        $idea = $dist->idea;
        $status = strtolower(trim($dist->status ?? 'pending'));
        $hasCustomIdea = ! empty($dist->custom_idea);

        $priorityMap = [
            'very_high' => 5,
            'veryhigh' => 5,
            'high' => 4,
            'medium' => 3,
            'normal' => 2,
            'low' => 1,
        ];
        $importance = $tag?->importance ?? 'normal';
        $priorityNum = $priorityMap[$importance] ?? 2;

        $ideaFileUrl = $idea?->idea_file ? Storage::url($idea->idea_file) : null;
        $isImage = $idea?->idea_file ? in_array(strtolower(pathinfo($idea->idea_file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) : false;

        $actionWireClick = in_array($status, ['pending', 'changes_requested'])
            ? "mountAction('submitTask', { distribution_id: {$dist->id} })"
            : ($status === 'reviewing' ? "mountAction('editTask', { distribution_id: {$dist->id} })" : null);

        $actionLabel = match ($status) {
            'changes_requested' => 'إرسال التعديلات',
            'pending' => 'رفع التصميم',
            'reviewing' => 'تعديل التسليم',
            default => '',
        };

        $actionTheme = $status === 'changes_requested' ? 'red' : 'primary';
        $actionIcon = $status === 'changes_requested' ? 'heroicon-m-arrow-path' : 'heroicon-m-paper-airplane';
        $actionDisabled = in_array($status, ['sending', 'completed']);

        $clicheTemplate = $client?->templates?->firstWhere('type', ClientTemplateType::Cliche->value);
        $clicheData = ($clicheTemplate && $clicheTemplate->file) ? [
            'client_name' => $client->company ?? 'عميل غير معروف',
            'file_url' => Storage::url($clicheTemplate->file),
            'thumbnail_url' => $clicheTemplate->thumbnail_url,
            'local_path' => $clicheTemplate->local_path,
            'updated_at' => $clicheTemplate->updated_at ? $clicheTemplate->updated_at->format('Y-m-d h:i A') : null,
        ] : null;

        return [
            'id' => $dist->id,
            'wire_key' => 'daily-'.$dist->id,
            'status' => $status,
            'task_type' => 'daily',
            'priority_num' => $priorityNum,
            'priority_label' => $importance,
            'avatar_letter' => mb_substr($client->company ?? 'ع', 0, 1),
            'avatar_url' => $client->logo_path ? Storage::url($client->logo_path) : null,
            'title' => $client->company ?? 'عميل غير معروف',
            'subtitle' => $tag->name ?? 'تاق',
            'description' => $dist->custom_idea ?: ($idea?->content ?? $idea?->name),
            'description_label' => 'تفاصيل الفكرة',
            'action_wire_click' => $actionWireClick,
            'action_label' => $actionLabel,
            'action_theme' => $actionTheme,
            'action_icon' => $actionIcon,
            'action_disabled' => $actionDisabled,
            'distribution_date' => $dist->distribution_date,
            'idea_data' => [
                'name' => $idea?->name ?? 'فكرة مخصصة',
                'content' => $idea?->content,
                'description' => $idea?->description ?? $dist->custom_idea,
                'is_custom' => $hasCustomIdea,
                'client_notes' => $client->notes,
                'idea_file_url' => $ideaFileUrl,
                'is_image' => $isImage,
            ],
            'cliche_data' => $clicheData,
            'attachment_path' => $dist->attachment_path,
            'designer_notes' => $dist->designer_notes,
            'reviewer_feedback' => $dist->reviewer_feedback,
            'revision_notes' => $dist->reviewer_feedback,
            'revision_attachments' => $dist->reviewer_attachments ?? [],
            'tag_name' => $tag->name ?? 'تاق',
            'client_company' => $client->company ?? 'عميل غير معروف',
            'created_at' => $dist->created_at,
            'updated_at' => $dist->updated_at,
        ];
    }

    private function formatDesignTask($task): array
    {
        $priorityMap = ['high' => 4, 'medium' => 3, 'low' => 2];
        $priorityVal = $task->priority?->value ?? 'low';
        $priorityNum = $priorityMap[$priorityVal] ?? 2;

        $taskStatus = $task->status->value;
        $isTemplate = $task->is_template_update;

        $actionWireClick = $taskStatus === DesignTaskStatus::Pending->value
            ? "mountAction('submitDesignTask', { task_id: {$task->id} })"
            : ($taskStatus === DesignTaskStatus::NeedsRevision->value ? "mountAction('submitDesignTask', { task_id: {$task->id} })" : null);

        $actionLabel = $taskStatus === DesignTaskStatus::Pending->value
            ? 'رفع التصميم'
            : ($taskStatus === DesignTaskStatus::NeedsRevision->value ? 'إعادة رفع التصاميم' : 'رفع التصميم');
        $actionTheme = $isTemplate ? 'teal' : ($taskStatus === DesignTaskStatus::NeedsRevision->value ? 'red' : 'primary');
        $actionIcon = $taskStatus === DesignTaskStatus::NeedsRevision->value ? 'heroicon-m-arrow-path' : 'heroicon-m-arrow-up-tray';

        $clientLogo = $task->client?->logo_path ? Storage::url($task->client?->logo_path) : null;

        $client = $task->client;
        $clicheTemplate = $client?->templates?->firstWhere('type', ClientTemplateType::Cliche->value);
        $clicheData = ($clicheTemplate && $clicheTemplate->file) ? [
            'client_name' => $task->display_client_name ?: $task->client_name ?: ($client?->company ?? 'عميل غير معروف'),
            'file_url' => Storage::url($clicheTemplate->file),
            'thumbnail_url' => $clicheTemplate->thumbnail_url,
            'local_path' => $clicheTemplate->local_path,
            'updated_at' => $clicheTemplate->updated_at ? $clicheTemplate->updated_at->format('Y-m-d h:i A') : null,
        ] : null;

        return [
            'id' => $task->id,
            'wire_key' => ($isTemplate ? 'template-' : 'design-').$task->id,
            'status' => $taskStatus,
            'task_type' => $isTemplate ? 'template' : 'design',
            'priority_num' => $priorityNum,
            'priority_label' => $priorityVal,
            'avatar_letter' => mb_substr($task->display_client_name ?: $task->client_name ?? '', 0, 1),
            'avatar_url' => $clientLogo,
            'template_type_label' => $isTemplate && $task->template_type
                ? (ClientTemplateType::tryFrom($task->template_type)?->getLabel() ?? $task->template_type)
                : null,
            'title' => $task->display_client_name,
            'subtitle' => $isTemplate ? "طلب التحديث من: {$task->assigner?->name}" : "بواسطة: {$task->assigner?->name}",
            'description' => $task->description,
            'description_label' => $isTemplate ? 'النماذج المطلوب تحديثها' : 'وصف المهمة',
            'action_wire_click' => $actionWireClick,
            'action_label' => $actionLabel,
            'action_theme' => $actionTheme,
            'action_icon' => $actionIcon,
            'action_disabled' => $taskStatus === DesignTaskStatus::InReview->value,
            'is_extra' => $task->is_extra,
            'extra_amount' => $task->amount,
            'reference_files' => $task->reference_files ?? [],
            'design_files' => $task->design_files ?? [],
            'cliche_data' => $clicheData,
            'revision_notes' => $task->revision_notes,
            'revision_attachments' => $task->revision_files ?? [],
            'created_at' => $task->created_at,
            'submitted_at' => $task->submitted_at,
        ];
    }

    /**
     * تحديد مسار التنقل (Breadcrumbs) للصفحة.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            static::getUrl() => 'لوحة المصمم',
        ];
    }

    /**
     * تحديث حالة المهمة (simple status update without modal).
     */
    public function updateTaskStatus($distributionId, $status)
    {
        $allowedStatuses = ['in_progress', 'pending'];

        if (! in_array($status, $allowedStatuses, true)) {
            return;
        }

        $record = ClientTagDistribution::find($distributionId);

        if (! $record || ! $this->validateOwnership($record)) {
            return;
        }

        $record->update(['status' => $status]);

        Notification::make()
            ->title('تم تحديث حالة المهمة')
            ->success()
            ->send();
    }

    public function submitTaskAction(): Action
    {
        return Action::make('submitTask')
            ->label('تسليم المهمة')
            ->modalHeading('تسليم المهمة للمراجعة')
            ->form(function (array $arguments = []) {
                $distributionId = $arguments['distribution_id'] ?? null;
                $record = $distributionId ? ClientTagDistribution::with('clientDesigner')->find($distributionId) : null;
                $clientId = $record?->clientDesigner?->client_id ?? 'unknown';
                $dir = $distributionId ? "clients/{$clientId}/submissions/{$distributionId}" : 'clients/temp/submissions';

                return [
                    FileUpload::make('attachment_path')
                        ->label('الصورة النهائية (إجباري)')
                        ->image()
                        ->disk('public')
                        ->directory($dir)
                        ->maxSize(config('filesystems.max_file_size', 10240))
                        ->helperText('💡 يمكنك تصفح الملفات، السحب والإفلات، أو نسخ الصورة من جهازك ولصقها مباشرة هنا (Ctrl + V)')
                        ->required(),
                    Textarea::make('designer_notes')
                        ->label('ملاحظات (اختياري)')
                        ->rows(3),
                ];
            })
            ->action(function (array $data, array $arguments = []) {
                $distributionId = $arguments['distribution_id'] ?? $data['distribution_id'] ?? null;
                if (! $distributionId) {
                    return;
                }

                $record = ClientTagDistribution::find($distributionId);
                if (! $record || ! $this->validateOwnership($record)) {
                    return;
                }

                $allowedStatuses = ['pending', 'changes_requested'];
                if (! in_array($record->status, $allowedStatuses, true)) {
                    return;
                }

                $record->update([
                    'status' => 'reviewing',
                    'attachment_path' => $data['attachment_path'],
                    'designer_notes' => $data['designer_notes'],
                ]);

                Notification::make()
                    ->title('تم إرسال المهمة للمراجعة 🚀')
                    ->success()
                    ->send();
            });
    }

    public function editTaskAction(): Action
    {
        return Action::make('editTask')
            ->label('تعديل التسليم')
            ->modalHeading('تعديل التسليم')
            ->fillForm(function (array $arguments = []) {
                $distributionId = $arguments['distribution_id'] ?? null;
                $record = $distributionId ? ClientTagDistribution::find($distributionId) : null;

                return [
                    'attachment_path' => $record?->attachment_path,
                    'designer_notes' => $record?->designer_notes,
                ];
            })
            ->form(function (array $arguments = []) {
                $distributionId = $arguments['distribution_id'] ?? null;
                $record = $distributionId ? ClientTagDistribution::with('clientDesigner')->find($distributionId) : null;
                $clientId = $record?->clientDesigner?->client_id ?? 'unknown';
                $dir = $distributionId ? "clients/{$clientId}/submissions/{$distributionId}" : 'clients/temp/submissions';

                return [
                    FileUpload::make('attachment_path')
                        ->label('استبدال الصورة (اختياري)')
                        ->image()
                        ->disk('public')
                        ->directory($dir)
                        ->maxSize(config('filesystems.max_file_size', 10240))
                        ->helperText('💡 يمكنك تصفح الملفات، السحب والإفلات، أو نسخ الصورة من جهازك ولصقها مباشرة هنا (Ctrl + V)'),
                    Textarea::make('designer_notes')
                        ->label('تعديل الملاحظات')
                        ->rows(3),
                ];
            })
            ->action(function (array $data, array $arguments = []) {
                $distributionId = $arguments['distribution_id'] ?? $data['distribution_id'] ?? null;
                if (! $distributionId) {
                    return;
                }

                $record = ClientTagDistribution::find($distributionId);
                if (! $record || ! $this->validateOwnership($record)) {
                    return;
                }

                if ($record->status !== 'reviewing') {
                    return;
                }

                $updateData = ['designer_notes' => $data['designer_notes']];

                if (! empty($data['attachment_path'])) {
                    $updateData['attachment_path'] = $data['attachment_path'];
                }

                $record->update($updateData);

                Notification::make()
                    ->title('تم تحديث التسليم بنجاح')
                    ->success()
                    ->send();
            });
    }

    /**
     * إجراء رفع تصاميم مهمة التصميم المخصصة.
     */
    public function submitDesignTaskAction(): Action
    {
        return Action::make('submitDesignTask')
            ->label('رفع التصاميم')
            ->modalHeading('رفع التصاميم للمراجعة')
            ->form(function (array $arguments = []) {
                $taskId = $arguments['task_id'] ?? null;
                $task = $taskId ? DesignTask::find($taskId) : null;
                $clientId = $task?->client_id ?? 'unknown';

                if ($task?->is_template_update && $task?->template_type) {
                    $dir = "clients/{$clientId}/templates/{$task->template_type}";
                } else {
                    $dir = $taskId ? "clients/{$clientId}/design-tasks/{$taskId}/output" : 'clients/temp/design-tasks/output';
                }

                return [
                    FileUpload::make('design_files')
                        ->label('الملفات المرفقة')
                        ->multiple()
                        ->directory($dir)
                        ->maxFiles(10)
                        ->maxSize(config('filesystems.max_file_size', 10240))
                        ->helperText('💡 يمكنك تصفح الملفات، السحب والإفلات، أو نسخ الصور من جهازك ولصقها مباشرة هنا (Ctrl + V)')
                        ->required(),
                    \Filament\Forms\Components\TextInput::make('local_path')
                        ->label('مسار التخزين المحلي (اختياري)')
                        ->placeholder('مثال: \\\\Server\\Designs\\Client\\Template.psd')
                        ->helperText('حدد مسار الحفظ على السيرفر المحلي إن أردت'),
                ];
            })
            ->action(function (array $data, array $arguments = []) {
                $taskId = $arguments['task_id'] ?? $data['task_id'] ?? null;
                if (! $taskId) {
                    return;
                }

                $record = DesignTask::find($taskId);
                $designer = $this->getDesigner();
                if (! $record || ! $designer || $record->designer_id !== $designer->id) {
                    return;
                }

                $allowedStatuses = [DesignTaskStatus::Pending->value, DesignTaskStatus::NeedsRevision->value];
                if (! in_array($record->status->value, $allowedStatuses, true)) {
                    return;
                }

                $record->update([
                    'status' => DesignTaskStatus::InReview->value,
                    'design_files' => $data['design_files'],
                    'local_path' => $data['local_path'] ?? $record->local_path,
                    'submitted_at' => now(),
                ]);

                Notification::make()
                    ->title('تم رفع التصاميم بنجاح وإرسالها للمراجعة 🚀')
                    ->success()
                    ->send();
            });
    }

    public function submitOrderForReviewAction(): Action
    {
        return Action::make('submitOrderForReview')
            ->label('إرسال للمراجعة')
            ->action(function (array $arguments) {
                $designer = Designer::where('user_id', auth()->id())->first();
                $record = Order::find($arguments['order_id']);
                if ($record && $designer && $record->designer_id === $designer->id) {
                    $record->update([
                        'status' => OrderStatus::InReview,
                    ]);

                    Notification::make()
                        ->title('تم إرسال الطلب للمراجعة ✅')
                        ->body('بانتظار اعتماد المشرف.')
                        ->success()
                        ->send();

                    $this->dispatch('order-refresh');
                }
            });
    }
}
