<?php

namespace App\Filament\Resources\DesignTaskResource\Pages;

use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Filament\Resources\DesignTaskResource;
use App\Models\Client;
use App\Models\ClientTemplate;
use App\Models\DesignTask;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ListDesignTasks extends ListRecords
{
    protected static string $resource = DesignTaskResource::class;

    protected static string $view = 'filament.resources.design-task-resource.pages.list-design-tasks';

    public ?array $taskData = [];

    public function mount(): void
    {
        $this->loadDefaultActiveTab();

        $this->createTaskForm->fill([
            'priority' => DesignTaskPriority::Medium->value,
        ]);
    }

    protected function getForms(): array
    {
        return array_merge(parent::getForms(), [
            'createTaskForm',
        ]);
    }

    public function createTaskForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(12)->schema([
                    Forms\Components\TextInput::make('company')
                        ->label('اسم العميل')
                        ->required()
                        ->live(debounce: 400)
                        ->datalist(fn () => Client::query()->whereNotNull('company')->where('company', '!=', '')->distinct()->pluck('company')->toArray())
                        ->placeholder('ابحث عن اسم العميل أو أدخل اسماً جديداً...')
                        ->helperText(function (Forms\Get $get) {
                            $company = $get('company');
                            if (blank($company)) {
                                return null;
                            }

                            $client = Client::where('company', $company)->first();
                            if (! $client) {
                                return new HtmlString('
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 mt-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        <span>عميل غير مسجل (مهمة فردية)</span>
                                    </span>
                                ');
                            }

                            $contract = \App\Models\Contract::where('client_id', $client->id)->latest('id')->first();
                            $contractStatus = $contract?->status;

                            $badgeClass = match ($contractStatus) {
                                'active' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                                'suspended', 'موقف يدوياً', 'موقوف' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                                'expired' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
                                default => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
                            };

                            $statusLabel = match ($contractStatus) {
                                'active' => 'عميل نشط',
                                'suspended', 'موقف يدوياً', 'موقوف' => 'عميل موقوف',
                                'expired' => 'اشتراك منتهي',
                                default => $contractStatus ?? 'بدون اشتراك',
                            };

                            return new HtmlString('
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold border mt-1 '.$badgeClass.'">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    <span>'.e($statusLabel).'</span>
                                </span>
                            ');
                        })
                        ->columnSpan(['default' => 12, 'md' => 7]),

                    Forms\Components\Select::make('priority')
                        ->label('الأهمية')
                        ->options(DesignTaskPriority::class)
                        ->default(DesignTaskPriority::Medium->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->columnSpan(['default' => 12, 'md' => 5]),

                    Forms\Components\ViewField::make('designer_id')
                        ->label('المصمم الموكل')
                        ->view('filament.resources.design-task-resource.components.designer-picker')
                        ->inlineLabel(false)
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\Textarea::make('description')
                        ->label('وصف المهمة')
                        ->rows(2)
                        ->placeholder('اكتب تفاصيل ومتطلبات المهمة...')
                        ->required()
                        ->columnSpan(['default' => 12, 'md' => 7]),

                    Forms\Components\FileUpload::make('reference_files')
                        ->label('مرفقات مرجعية (صغير)')
                        ->multiple()
                        ->maxFiles(5)
                        ->maxSize(config('filesystems.max_file_size', 10240))
                        ->disk('public')
                        ->directory('clients/temp/design-tasks/references')
                        ->imagePreviewHeight('40')
                        ->panelLayout('compact')
                        ->columnSpan(['default' => 12, 'md' => 5]),
                ]),
            ])
            ->statePath('taskData');
    }

    public function createTask(): void
    {
        if (! auth()->user()?->can('create', DesignTask::class)) {
            Notification::make()
                ->title('غير مصرح لك بإنشاء مهام جانبية')
                ->danger()
                ->send();

            return;
        }

        $data = $this->createTaskForm->getState();

        $company = trim($data['company']);
        $client = Client::where('company', $company)->first();

        $task = DesignTask::create([
            'designer_id' => $data['designer_id'],
            'assigner_id' => auth()->id(),
            'client_id' => $client?->id,
            'client_name' => $company,
            'is_subscribed_client' => (bool) $client,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? DesignTaskPriority::Medium->value,
            'reference_files' => ! empty($data['reference_files']) ? array_values($data['reference_files']) : null,
            'status' => DesignTaskStatus::Pending->value,
        ]);

        $this->createTaskForm->fill([
            'priority' => DesignTaskPriority::Medium->value,
            'company' => null,
            'designer_id' => null,
            'description' => null,
            'reference_files' => [],
        ]);

        Notification::make()
            ->title('تم إسناد المهمة بنجاح 🚀')
            ->body("تم إسناد المهمة للمصمم: {$task->designer?->user?->name}")
            ->success()
            ->send();

        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->hidden(),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    /**
     * تعريف التبويبات الذكية لتصنيف المهام حسب الحالة مع العدادات الحية.
     */
    public function getTabs(): array
    {
        $counts = $this->getTaskCountsByStatus();

        return [
            'pending' => Tab::make('قيد التنفيذ لدى المصمم')
                ->icon('heroicon-m-clock')
                ->badge($counts['pending'])
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::Pending->value)),
            'in_review' => Tab::make('بانتظار المراجعة')
                ->icon('heroicon-m-bell')
                ->badge($counts['in_review'])
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::InReview->value)),
            'needs_revision' => Tab::make('قيد التعديل')
                ->icon('heroicon-m-arrow-path')
                ->badge($counts['needs_revision'])
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::NeedsRevision->value)),
            'all' => Tab::make('جميع المهام')
                ->icon('heroicon-m-list-bullet')
                ->badge($counts['all']),
        ];
    }

    /**
     * حساب إحصائيات المهام لكل حالة باستعلام مجمّع واحد.
     */
    protected function getTaskCountsByStatus(): array
    {
        $user = auth()->user();
        $query = DesignTask::query();

        if ($user && ! $user->hasRole(['admin', 'super_admin'])) {
            $query->where('assigner_id', $user->id);
        }

        $counts = $query->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->toArray();

        $total = array_sum($counts);

        return [
            'all' => $total,
            'in_review' => (int) ($counts[DesignTaskStatus::InReview->value] ?? 0),
            'needs_revision' => (int) ($counts[DesignTaskStatus::NeedsRevision->value] ?? 0),
            'pending' => (int) ($counts[DesignTaskStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[DesignTaskStatus::Approved->value] ?? 0),
        ];
    }

    /**
     * اعتماد مهمة تصميم من داخل المودال.
     */
    public function approveTask(int $taskId): void
    {
        $user = auth()->user();
        $query = DesignTask::query();

        if ($user && ! $user->hasRole(['admin', 'super_admin'])) {
            $query->where('assigner_id', $user->id);
        }

        $task = $query->findOrFail($taskId);

        if ($task->is_template_update && $task->client_id && $task->template_type) {
            $template = ClientTemplate::firstOrNew([
                'client_id' => $task->client_id,
                'type' => $task->template_type,
            ]);

            if ($task->design_files) {
                $sourceFile = $task->design_files[0] ?? null;
                if ($sourceFile) {
                    if ($template->file && $template->file !== $sourceFile && Storage::disk('public')->exists($template->file)) {
                        Storage::disk('public')->delete($template->file);
                    }
                    $template->file = $sourceFile;
                }
            }

            if ($task->local_path) {
                $template->local_path = $task->local_path;
            }

            $template->updated_at = now();
            $template->save();
        } else {
            if ($task->is_subscribed_client && $task->client) {
                $task->client->increment('cliche_counter');
            }
        }

        $task->update([
            'status' => DesignTaskStatus::Approved,
        ]);

        Notification::make()
            ->title('تم اعتماد التصميم بنجاح ✅')
            ->success()
            ->send();

        $this->dispatch('close-modal', id: "{$this->getId()}-table-action");
    }

    /**
     * طلب تعديل على مهمة تصميم من داخل المودال أو الدرج التفاعلي.
     *
     * @param  array<int, string>  $files  مصفوفة المرفقات (روابط ملفات أو صور Base64 ملصوقة من الحافظة)
     */
    public function requestTaskRevision(int $taskId, string $notes, array $files = []): void
    {
        $user = auth()->user();
        $query = DesignTask::query();

        if ($user && ! $user->hasRole(['admin', 'super_admin'])) {
            $query->where('assigner_id', $user->id);
        }

        $task = $query->findOrFail($taskId);

        $savedFiles = [];
        if (! empty($files)) {
            $clientId = $task->client_id ?? 'unknown';
            $dir = "clients/{$clientId}/design-tasks/{$task->id}/revisions";

            foreach ($files as $fileData) {
                if (is_string($fileData) && str_starts_with($fileData, 'data:image')) {
                    if (preg_match('/^data:image\/(\w+);base64,/', $fileData, $type)) {
                        $raw = substr($fileData, strpos($fileData, ',') + 1);
                        $ext = strtolower($type[1]);
                        if (in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
                            $decoded = base64_decode($raw);
                            if ($decoded !== false) {
                                $fileName = 'paste_'.now()->timestamp.'_'.uniqid().'.'.$ext;
                                $filePath = "{$dir}/{$fileName}";
                                Storage::disk('public')->put($filePath, $decoded);
                                $savedFiles[] = $filePath;
                            }
                        }
                    }
                } elseif (is_string($fileData) && ! empty($fileData)) {
                    $savedFiles[] = $fileData;
                }
            }
        }

        $task->update([
            'status' => DesignTaskStatus::NeedsRevision,
            'revision_notes' => $notes,
            'revision_files' => ! empty($savedFiles) ? $savedFiles : null,
            'design_files' => null,
        ]);

        Notification::make()
            ->title('تم إرسال طلب التعديل بنجاح 🔄')
            ->warning()
            ->send();

        $this->dispatch('close-modal', id: "{$this->getId()}-table-action");
    }
}
