<?php

namespace Tests\Feature;

use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Filament\Resources\TagResource\Widgets\TagsQuickStatsWidget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Idea;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TagsQuickStatsHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TagGroup $tagGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'test_admin',
        ]);

        Permission::firstOrCreate(['name' => 'view_any_tag']);
        Permission::firstOrCreate(['name' => 'view_tag']);
        Permission::firstOrCreate(['name' => 'create_tag']);
        Permission::firstOrCreate(['name' => 'update_tag']);
        Permission::firstOrCreate(['name' => 'delete_tag']);

        $this->user->givePermissionTo([
            'view_any_tag',
            'view_tag',
            'create_tag',
            'update_tag',
            'delete_tag',
        ]);

        $this->actingAs($this->user);

        $this->tagGroup = TagGroup::create([
            'name' => 'مجموعة رئيسية',
            'added_by_user' => $this->user->id,
        ]);
    }

    public function test_tags_quick_stats_widget_renders_on_list_page(): void
    {
        $this->get(TagResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('إجمالي الوسوم النشطة')
            ->assertSee('وسوم بدون أفكار')
            ->assertSee('وسوم مجدولة')
            ->assertSee('وسوم مخصصة لعملاء');
    }

    public function test_tag_ideas_relationship_works(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم تجريبي',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
        ]);

        $idea = Idea::create([
            'name' => 'فكرة إبداعية',
            'content' => 'محتوى الفكرة',
            'added_by_user' => $this->user->id,
        ]);

        $tag->ideas()->attach($idea->id);

        $this->assertCount(1, $tag->ideas);
        $this->assertTrue($tag->ideas->first()->is($idea));
    }

    public function test_tags_quick_stats_widget_computes_accurate_statistics(): void
    {
        // 1. Tag with idea, active, auto-assigned, scheduled weekly
        $tagWithIdea = Tag::factory()->create([
            'name' => 'وسم به فكرة',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
            'is_auto_assigned' => true,
            'is_there_date_for_sending' => true,
            'weekly_day' => ['Sunday', 'Monday'],
            'weekly_time' => '10:00:00',
        ]);

        $idea = Idea::create([
            'name' => 'فكرة 1',
            'added_by_user' => $this->user->id,
        ]);
        $tagWithIdea->ideas()->attach($idea->id);

        // 2. Tag without idea, active, custom client, scheduled yearly
        $category = Category::factory()->create();
        $client = Client::factory()->create(['category_id' => $category->id]);

        $tagWithoutIdea = Tag::factory()->create([
            'name' => 'وسم بلا أفكار',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
            'is_auto_assigned' => false,
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => '2026-10-15',
        ]);
        $tagWithoutIdea->clients()->attach($client->id);

        // 3. Inactive tag, without idea, unscheduled, auto-assigned
        Tag::factory()->create([
            'name' => 'وسم غير نشط',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => false,
            'is_auto_assigned' => true,
            'is_there_date_for_sending' => false,
        ]);

        $widget = Livewire::test(TagsQuickStatsWidget::class);

        $data = $widget->instance()->getStatsData();

        $this->assertEquals(3, $data['total_tags']);
        $this->assertEquals(2, $data['active_tags']);
        $this->assertEquals(1, $data['inactive_tags']);

        $this->assertEquals(2, $data['tags_without_ideas']); // Tag 2 + Tag 3
        $this->assertEquals(1, $data['active_tags_without_ideas']); // Only Tag 2 is active without idea
        $this->assertEquals(1, $data['tags_with_ideas']); // Tag 1

        $this->assertEquals(2, $data['scheduled_tags']); // Tag 1 (weekly) + Tag 2 (yearly)
        $this->assertEquals(1, $data['weekly_scheduled']);
        $this->assertEquals(1, $data['yearly_scheduled']);

        $this->assertEquals(1, $data['custom_client_tags']); // Tag 2
        $this->assertEquals(2, $data['auto_assigned_tags']); // Tag 1 + Tag 3
        $this->assertEquals(1, $data['unique_clients_count']);
    }

    public function test_filter_by_dispatches_events_and_toggles(): void
    {
        Livewire::test(TagsQuickStatsWidget::class)
            ->call('filterBy', 'without_ideas')
            ->assertDispatched('filter-tags', filter: 'without_ideas')
            ->assertSet('activeFilter', 'without_ideas')
            // Calling again toggles it off
            ->call('filterBy', 'without_ideas')
            ->assertDispatched('filter-tags', filter: 'all')
            ->assertSet('activeFilter', null);
    }

    public function test_reset_filter_clears_active_filter(): void
    {
        Livewire::test(TagsQuickStatsWidget::class)
            ->set('activeFilter', 'without_ideas')
            ->call('resetFilter')
            ->assertDispatched('filter-tags', filter: 'all')
            ->assertSet('activeFilter', null);
    }

    public function test_list_tags_filters_table_on_quick_stat_filter(): void
    {
        $tagWithIdea = Tag::factory()->create([
            'name' => 'وسم به فكرة خاص',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
        ]);

        $idea = Idea::create([
            'name' => 'فكرة مرفقة',
            'added_by_user' => $this->user->id,
        ]);
        $tagWithIdea->ideas()->attach($idea->id);

        $tagWithoutIdea = Tag::factory()->create([
            'name' => 'وسم وحيد بلا فكرة',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
        ]);

        // When filtering for without_ideas
        Livewire::test(ListTags::class)
            ->call('applyTagQuickFilter', 'without_ideas')
            ->assertCanSeeTableRecords([$tagWithoutIdea])
            ->assertCanNotSeeTableRecords([$tagWithIdea]);
    }

    public function test_list_tags_filters_active_and_scheduled_tags(): void
    {
        $activeScheduled = Tag::factory()->create([
            'name' => 'وسم نشط ومجدول',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
            'is_there_date_for_sending' => true,
            'weekly_day' => ['Friday'],
        ]);

        $inactiveUnscheduled = Tag::factory()->create([
            'name' => 'وسم معطل وغير مجدول',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => false,
            'is_there_date_for_sending' => false,
        ]);

        // Filter active only
        Livewire::test(ListTags::class)
            ->call('applyTagQuickFilter', 'active')
            ->assertCanSeeTableRecords([$activeScheduled])
            ->assertCanNotSeeTableRecords([$inactiveUnscheduled]);

        // Filter scheduled only
        Livewire::test(ListTags::class)
            ->call('applyTagQuickFilter', 'scheduled')
            ->assertCanSeeTableRecords([$activeScheduled])
            ->assertCanNotSeeTableRecords([$inactiveUnscheduled]);
    }

    public function test_list_tags_filters_custom_client_tags(): void
    {
        $customTag = Tag::factory()->create([
            'name' => 'وسم مخصص لمطعم معين',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_auto_assigned' => false,
        ]);

        $autoTag = Tag::factory()->create([
            'name' => 'وسم عام تلقائي',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_auto_assigned' => true,
        ]);

        Livewire::test(ListTags::class)
            ->call('applyTagQuickFilter', 'custom_clients')
            ->assertCanSeeTableRecords([$customTag])
            ->assertCanNotSeeTableRecords([$autoTag]);
    }

    public function test_can_render_quick_peek_ideas_drawer(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم تجربة المعاينة',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
        ]);

        $idea = Idea::create([
            'name' => 'فكرة رمضانية',
            'content' => 'محتوى الفكرة الرمضانية للتصميم',
            'added_by_user' => $this->user->id,
        ]);
        $tag->ideas()->attach($idea->id);

        Livewire::test(ListTags::class)
            ->mountTableAction('quickPeekIdeas', $tag)
            ->assertTableActionMounted('quickPeekIdeas')
            ->assertSee('الأفكار المرتبطة بالوسم: وسم تجربة المعاينة')
            ->assertSee('فكرة رمضانية')
            ->assertSee('محتوى الفكرة الرمضانية للتصميم')
            ->assertSee('إضافة فكرة سريعة لهذا الوسم');
    }

    public function test_can_quick_add_idea_to_tag_inline(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم بدون أفكار',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
        ]);

        $this->assertCount(0, $tag->ideas);

        Livewire::test(ListTags::class)
            ->call('quickAddIdeaToTag', $tag->id, 'فكرة فورية جديدة', 'محتوى الفكرة الفورية للتنفيذ', 'وصف إضافي', '2026-11-01 12:00:00', true)
            ->assertDispatched('idea-added', tagId: $tag->id);

        $this->assertDatabaseHas('ideas', [
            'name' => 'فكرة فورية جديدة',
            'content' => 'محتوى الفكرة الفورية للتنفيذ',
            'is_visible_in_generator' => true,
        ]);

        $idea = Idea::where('name', 'فكرة فورية جديدة')->first();
        $this->assertNotNull($idea);

        $this->assertDatabaseHas('tag_idea', [
            'tag_id' => $tag->id,
            'idea_id' => $idea->id,
        ]);

        $this->assertCount(1, $tag->fresh()->ideas);
    }

    public function test_can_detach_idea_from_tag(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم لفصل الفكرة',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
        ]);

        $idea = Idea::create([
            'name' => 'فكرة مراد فك ارتباطها',
            'content' => 'محتوى التجربة',
            'added_by_user' => $this->user->id,
        ]);
        $tag->ideas()->attach($idea->id);

        $this->assertCount(1, $tag->ideas);

        Livewire::test(ListTags::class)
            ->call('detachIdeaFromTag', $tag->id, $idea->id)
            ->assertDispatched('idea-detached', tagId: $tag->id);

        $this->assertDatabaseMissing('tag_idea', [
            'tag_id' => $tag->id,
            'idea_id' => $idea->id,
        ]);

        $this->assertCount(0, $tag->fresh()->ideas);
    }

    public function test_can_toggle_idea_visibility(): void
    {
        $idea = Idea::create([
            'name' => 'فكرة للتبديل',
            'content' => 'محتوى الفكرة',
            'is_visible_in_generator' => true,
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(ListTags::class)
            ->call('toggleIdeaVisibility', $idea->id)
            ->assertDispatched('idea-updated', ideaId: $idea->id);

        $this->assertFalse($idea->fresh()->is_visible_in_generator);

        Livewire::test(ListTags::class)
            ->call('toggleIdeaVisibility', $idea->id)
            ->assertDispatched('idea-updated', ideaId: $idea->id);

        $this->assertTrue($idea->fresh()->is_visible_in_generator);
    }
}
