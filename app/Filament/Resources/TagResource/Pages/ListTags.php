<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Widgets\TagsQuickStatsWidget;
use App\Models\Idea;
use App\Models\Tag;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    #[Url(as: 'quick_filter')]
    public ?string $activeQuickFilter = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TagsQuickStatsWidget::class,
        ];
    }

    public function mount(): void
    {
        parent::mount();

        if ($this->activeQuickFilter) {
            $this->applyTagQuickFilter($this->activeQuickFilter);
        }
    }

    #[On('filter-tags')]
    public function applyTagQuickFilter(string $filter): void
    {
        $this->activeQuickFilter = ($filter === 'all' || empty($filter)) ? null : $filter;

        match ($filter) {
            'active' => $this->tableFilters = [
                'is_active' => ['value' => '1'],
            ],
            'without_ideas' => $this->tableFilters = [
                'has_ideas' => ['value' => '0'],
            ],
            'scheduled' => $this->tableFilters = [
                'scheduling' => ['value' => 'scheduled'],
            ],
            'custom_clients' => $this->tableFilters = [
                'is_auto_assigned' => ['value' => '0'],
            ],
            default => $this->tableFilters = [],
        };

        $this->resetPage();
    }

    public function updatedTableFilters(): void
    {
        $hasIdeas = $this->tableFilters['has_ideas']['value'] ?? null;
        $isActive = $this->tableFilters['is_active']['value'] ?? null;
        $scheduling = $this->tableFilters['scheduling']['value'] ?? null;
        $isAutoAssigned = $this->tableFilters['is_auto_assigned']['value'] ?? null;

        if ($hasIdeas === '0') {
            $this->activeQuickFilter = 'without_ideas';
        } elseif ($isActive === '1' && ! $hasIdeas && ! $scheduling && ! $isAutoAssigned) {
            $this->activeQuickFilter = 'active';
        } elseif ($scheduling === 'scheduled') {
            $this->activeQuickFilter = 'scheduled';
        } elseif ($isAutoAssigned === '0') {
            $this->activeQuickFilter = 'custom_clients';
        } else {
            $this->activeQuickFilter = null;
        }

        $this->dispatch('tag-filter-synced', filter: $this->activeQuickFilter);
    }

    /**
     * إضافة فكرة سريعة وربطها بالوسم مباشرة من النافذة الجانبية.
     */
    public function quickAddIdeaToTag(
        $tagId,
        $name,
        $content,
        $description = null,
        $scheduledAt = null,
        $isVisible = true
    ): void {
        $tagId = (int) $tagId;
        $name = is_string($name) ? trim($name) : '';
        $content = is_string($content) ? trim($content) : '';
        $description = ! empty($description) ? trim((string) $description) : null;
        $scheduledAt = ! empty($scheduledAt) ? trim((string) $scheduledAt) : null;
        $isVisible = filter_var($isVisible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        if (empty($name) || empty($content)) {
            Notification::make()
                ->danger()
                ->title('بيانات غير مكتملة')
                ->body('اسم الفكرة ومحتواها مطلوبان.')
                ->send();

            return;
        }

        $tag = Tag::findOrFail($tagId);

        $parsedDate = null;
        if ($scheduledAt) {
            try {
                $parsedDate = Carbon::parse($scheduledAt);
            } catch (\Exception) {
                $parsedDate = null;
            }
        }

        $idea = Idea::create([
            'name' => $name,
            'content' => $content,
            'description' => $description,
            'scheduled_at' => $parsedDate,
            'is_visible_in_generator' => $isVisible,
            'added_by_user' => Auth::id(),
            'created_by_user' => Auth::id(),
        ]);

        $tag->ideas()->attach($idea->id, [
            'added_by_user' => Auth::id(),
        ]);

        Notification::make()
            ->success()
            ->title('تمت إضافة الفكرة وربطها بنجاح')
            ->body('تم ربط الفكرة "'.$idea->name.'" بالوسم "'.$tag->name.'" فوراً.')
            ->send();

        $this->dispatch('idea-added', tagId: $tagId);
        $this->dispatch('tag-filter-synced', filter: $this->activeQuickFilter);
    }

    /**
     * فك ارتباط فكرة عن الوسم.
     */
    public function detachIdeaFromTag(int $tagId, int $ideaId): void
    {
        $tag = Tag::findOrFail($tagId);
        $tag->ideas()->detach($ideaId);

        Notification::make()
            ->info()
            ->title('تم فك ارتباط الفكرة')
            ->body('تم إلغاء ارتباط الفكرة بالوسم بنجاح.')
            ->send();

        $this->dispatch('idea-detached', tagId: $tagId);
        $this->dispatch('tag-filter-synced', filter: $this->activeQuickFilter);
    }

    /**
     * تبديل حالة الفكرة (مؤهلة للتوزيع أو معطلة).
     */
    public function toggleIdeaVisibility(int $ideaId): void
    {
        $idea = Idea::findOrFail($ideaId);
        $idea->update([
            'is_visible_in_generator' => ! $idea->is_visible_in_generator,
        ]);

        Notification::make()
            ->success()
            ->title($idea->is_visible_in_generator ? 'تم تفعيل الفكرة في المولد' : 'تم تعطيل الفكرة من المولد')
            ->send();

        $this->dispatch('idea-updated', ideaId: $ideaId);
    }
}
