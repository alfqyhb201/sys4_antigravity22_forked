<?php

namespace App\Filament\Resources\DesignTaskResource\Pages;

use App\Filament\Enums\DesignTaskStatus;
use App\Filament\Resources\DesignTaskResource;
use App\Models\ClientTemplate;
use App\Models\DesignTask;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListDesignTasks extends ListRecords
{
    protected static string $resource = DesignTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * تعريف التبويبات الذكية لتصنيف المهام حسب الحالة مع العدادات الحية.
     */
    public function getTabs(): array
    {
        $counts = $this->getTaskCountsByStatus();

        return [
            'all' => Tab::make('جميع المهام')
                ->icon('heroicon-m-list-bullet')
                ->badge($counts['all']),
            'in_review' => Tab::make('تنتظر مراجعتك')
                ->icon('heroicon-m-bell')
                ->badge($counts['in_review'])
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::InReview->value)),
            'needs_revision' => Tab::make('قيد التعديل')
                ->icon('heroicon-m-arrow-path')
                ->badge($counts['needs_revision'])
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::NeedsRevision->value)),
            'pending' => Tab::make('قيد الانتظار')
                ->icon('heroicon-m-clock')
                ->badge($counts['pending'])
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::Pending->value)),
            'approved' => Tab::make('المكتملة والمعتمدة')
                ->icon('heroicon-m-check-circle')
                ->badge($counts['approved'])
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DesignTaskStatus::Approved->value)),
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
