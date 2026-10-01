<?php

namespace App\Filament\Resources\TagResource\Widgets;

use App\Models\Tag;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * ودجة المؤشرات السريعة التفاعلية لجدول الوسوم (Tags Quick Stats Header).
 *
 * توفر نظرة شمولية وحية لحالة مكتبة الوسوم:
 * 1. إجمالي الوسوم النشطة ونسبة النمو.
 * 2. وسوم بدون أفكار (تنبيه حرج للمعالجة الفورية).
 * 3. وسوم مجدولة أسبوعياً وسنوياً.
 * 4. وسوم مخصصة لعملاء محددين.
 *
 * تدعم النقر التفاعلي لفلترة الجدول مباشرة مع تحديث لحظي.
 */
class TagsQuickStatsWidget extends Widget
{
    protected static string $view = 'filament.resources.tag-resource.widgets.tags-quick-stats-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $activeFilter = null;

    #[On('tag-filter-synced')]
    public function syncActiveFilter(?string $filter): void
    {
        $this->activeFilter = $filter;
    }

    #[On('idea-added')]
    #[On('idea-detached')]
    public function refreshOnIdeaChanged(): void
    {
        // Triggers Livewire re-render to update idea counts and health status
    }

    public function filterBy(string $type): void
    {
        if ($this->activeFilter === $type) {
            $this->activeFilter = null;
            $this->dispatch('filter-tags', filter: 'all');
        } else {
            $this->activeFilter = $type;
            $this->dispatch('filter-tags', filter: $type);
        }
    }

    public function resetFilter(): void
    {
        $this->activeFilter = null;
        $this->dispatch('filter-tags', filter: 'all');
    }

    public function getStatsData(): array
    {
        $totalTags = Tag::count();
        $activeTags = Tag::where('is_active', true)->count();
        $inactiveTags = $totalTags - $activeTags;

        // Tags created this month
        $thisMonthTags = Tag::where('is_active', true)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // Growth or active ratio
        $activePercentage = $totalTags > 0 ? (int) round(($activeTags / $totalTags) * 100) : 0;

        // Tags without ideas (Critical warning)
        $tagsWithoutIdeas = Tag::whereDoesntHave('ideas')->count();
        $activeTagsWithoutIdeas = Tag::where('is_active', true)->whereDoesntHave('ideas')->count();
        $tagsWithIdeas = Tag::whereHas('ideas')->count();
        $ideasCoveragePercentage = $totalTags > 0 ? (int) round(($tagsWithIdeas / $totalTags) * 100) : 0;

        // Scheduled tags
        $scheduledTags = Tag::where('is_there_date_for_sending', true)->count();
        $weeklyScheduled = Tag::where('is_there_date_for_sending', true)->whereNotNull('weekly_day')->count();
        $yearlyScheduled = Tag::where('is_there_date_for_sending', true)->whereNotNull('date_for_sending_yearly')->count();
        $unscheduledTags = $totalTags - $scheduledTags;

        // Client-specific tags
        $customClientTags = Tag::where('is_auto_assigned', false)->count();
        $autoAssignedTags = Tag::where('is_auto_assigned', true)->count();
        $uniqueClientsCount = DB::table('client_tag')->distinct()->count('client_id');

        return [
            'total_tags' => $totalTags,
            'active_tags' => $activeTags,
            'inactive_tags' => $inactiveTags,
            'this_month_tags' => $thisMonthTags,
            'active_percentage' => $activePercentage,
            'tags_without_ideas' => $tagsWithoutIdeas,
            'active_tags_without_ideas' => $activeTagsWithoutIdeas,
            'tags_with_ideas' => $tagsWithIdeas,
            'ideas_coverage_percentage' => $ideasCoveragePercentage,
            'scheduled_tags' => $scheduledTags,
            'weekly_scheduled' => $weeklyScheduled,
            'yearly_scheduled' => $yearlyScheduled,
            'unscheduled_tags' => $unscheduledTags,
            'custom_client_tags' => $customClientTags,
            'auto_assigned_tags' => $autoAssignedTags,
            'unique_clients_count' => $uniqueClientsCount,
        ];
    }
}
