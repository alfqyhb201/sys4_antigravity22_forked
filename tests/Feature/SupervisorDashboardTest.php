<?php

namespace Tests\Feature;

use App\Filament\Pages\SupervisorDashboard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private \App\Models\Designer $designer;

    private \App\Models\Client $client;

    private \App\Models\ClientDesigner $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_supervisor_dashboard']);
        $this->user->givePermissionTo('view_supervisor_dashboard');

        // Create a designer
        $this->designer = \App\Models\Designer::create([
            'user_id' => $this->user->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $category = \App\Models\Category::factory()->create();
        $currency = \App\Models\Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);
        $this->client = \App\Models\Client::factory()->create([
            'category_id' => $category->id,
            'importance_weights' => ['high' => 100],
            'enable_very_high' => true,
        ]);
        $contract = \App\Models\Contract::create([
            'client_id' => $this->client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);

        // Create assignment for the current week
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->assignment = \App\Models\ClientDesigner::create([
            'designer_id' => $this->designer->id,
            'client_id' => $this->client->id,
            'week_start_date' => $weekStart,
        ]);
    }

    public function test_can_render_page(): void
    {
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200);
    }

    public function test_shows_designer_stats_table(): void
    {
        // Create a pending task for today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => \App\Models\Tag::factory()->high()->create()->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200)
            ->assertSee('التقرير اليومي للمصممين')
            ->assertSee($this->user->name);
    }

    public function test_daily_report_shows_correct_counts(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // 2 pending tasks for today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'in_progress',
        ]);

        // 1 overdue task from yesterday
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterday,
            'status' => 'pending',
        ]);

        // 1 completed today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'sending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200)
            ->assertSee('مهام اليوم')
            ->assertSee('متأخرات')
            ->assertSee('منجز اليوم');
    }

    public function test_designer_without_tasks_not_shown(): void
    {
        // Designer has no distributions at all
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200)
            ->assertDontSee($this->user->name);
    }

    public function test_filter_date_changes_stats(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // Task for today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        // Task for yesterday (should be shown as today_task when filter is yesterday)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterday,
            'status' => 'in_progress',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200);

        // Change filter to yesterday
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->set('filterDate', $yesterday)
            ->assertStatus(200);
    }

    public function test_multiple_designers_shown(): void
    {
        // Create a second designer
        $user2 = User::factory()->create();
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 10,
            'rate' => 7,
            'shift_hours' => 8,
            'discipline_score' => 8,
            'amount_of_designs' => 50,
        ]);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $assignment2 = \App\Models\ClientDesigner::create([
            'designer_id' => $designer2->id,
            'client_id' => $this->client->id,
            'week_start_date' => $weekStart,
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        // Task for designer 1
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        // Task for designer 2
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'in_progress',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertStatus(200)
            ->assertSee($this->user->name)
            ->assertSee($user2->name);
    }

    public function test_table_filter_date_excludes_designers_without_tasks(): void
    {
        // Create a separate designer with a unique name
        $designerUser = User::factory()->create(['name' => 'Unique Designer Name']);
        $designer = \App\Models\Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $assignment = \App\Models\ClientDesigner::create([
            'designer_id' => $designer->id,
            'client_id' => $this->client->id,
            'week_start_date' => $weekStart,
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // Task for today only
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        // On initial load (today), we should see the designer
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertSee('Unique Designer Name');

        // When filtered to yesterday, we should NOT see the designer (since they have no tasks on yesterday)
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->filterTable('filter_date', [
                'date' => $yesterday,
            ])
            ->assertDontSee('Unique Designer Name');
    }

    public function test_can_reassign_today_tasks_to_another_designer(): void
    {
        $user2 = User::factory()->create(['name' => 'المصمم البديل']);
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->callTableAction('reassignTasks', $this->designer, [
                'target_designer_id' => $designer2->id,
                'transfer_scope' => 'today',
            ])
            ->assertNotified();

        $distribution->refresh();
        $this->assertEquals($designer2->id, $distribution->clientDesigner->designer_id);
    }

    public function test_can_reassign_custom_tasks_to_another_designer(): void
    {
        $user2 = User::factory()->create(['name' => 'المصمم البديل الثاني']);
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
        ]);

        $tag1 = \App\Models\Tag::factory()->high()->create();
        $tag2 = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        $dist1 = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag1->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        $dist2 = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag2->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->callTableAction('reassignTasks', $this->designer, [
                'target_designer_id' => $designer2->id,
                'transfer_scope' => 'custom',
                'selected_distribution_ids' => [$dist1->id],
            ])
            ->assertNotified();

        $dist1->refresh();
        $dist2->refresh();

        // dist1 should be reassigned to designer2
        $this->assertEquals($designer2->id, $dist1->clientDesigner->designer_id);
        // dist2 should remain with original designer
        $this->assertEquals($this->designer->id, $dist2->clientDesigner->designer_id);
    }

    public function test_quick_day_navigation_buttons_work(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertSet('filterDate', $today)
            // Go to yesterday
            ->call('setPreviousDay')
            ->assertSet('filterDate', $yesterday)
            // Go to today
            ->call('setToday')
            ->assertSet('filterDate', $today)
            // Go to tomorrow
            ->call('setNextDay')
            ->assertSet('filterDate', $tomorrow);
    }

    public function test_view_tasks_modal_renders_properly(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->callTableAction('viewTasks', $this->designer)
            ->assertHasNoTableActionErrors();
    }

    public function test_can_filter_designers_with_uncompleted_today_tasks(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $tag = \App\Models\Tag::factory()->high()->create();

        // Create a second designer who has completed all tasks today
        $user2 = User::factory()->create(['name' => 'المصمم المنجز']);
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);
        $assignment2 = \App\Models\ClientDesigner::create([
            'designer_id' => $designer2->id,
            'client_id' => $this->client->id,
            'week_start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        // Designer 1 has pending tasks for today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        // Designer 2 has only completed tasks for today
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'completed',
        ]);

        // When filtering by uncompleted_today
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->filterTable('uncompleted_today', true)
            ->assertCanSeeTableRecords([$this->designer])
            ->assertCanNotSeeTableRecords([$designer2]);
    }

    public function test_can_filter_designers_with_overdue_tasks(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $tag = \App\Models\Tag::factory()->high()->create();

        // Create a second designer who has only today's tasks and no overdue tasks
        $user2 = User::factory()->create(['name' => 'مصمم بدون متأخرات']);
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);
        $assignment2 = \App\Models\ClientDesigner::create([
            'designer_id' => $designer2->id,
            'client_id' => $this->client->id,
            'week_start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        // Designer 1 has overdue task from yesterday
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterday,
            'status' => 'pending',
        ]);

        // Designer 2 has task today only
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        // When filtering by has_overdue
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->filterTable('has_overdue', true)
            ->assertCanSeeTableRecords([$this->designer])
            ->assertCanNotSeeTableRecords([$designer2]);
    }

    public function test_can_quick_reassign_task_to_another_designer(): void
    {
        $user2 = User::factory()->create(['name' => 'المصمم البديل']);
        $designer2 = \App\Models\Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->call('quickReassignTask', $distribution->id, $designer2->id)
            ->assertHasNoErrors();

        $distribution->refresh();
        $this->assertEquals($designer2->id, $distribution->clientDesigner->designer_id);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user2->id,
        ]);
    }

    public function test_can_notify_designer_for_task(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->call('notifyDesignerForTask', $distribution->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->user->id,
        ]);
    }

    public function test_quick_reassign_task_handles_same_designer_gracefully(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->call('quickReassignTask', $distribution->id, $this->designer->id)
            ->assertHasNoErrors();

        $distribution->refresh();
        $this->assertEquals($this->designer->id, $distribution->clientDesigner->designer_id);
    }

    public function test_top_stat_cards_reflect_selected_report_date(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // Task for today (completed)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'completed',
        ]);

        // Task for yesterday (completed)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterday,
            'status' => 'completed',
        ]);

        // In date mode for today: completedCount is 1
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->assertViewHas('completedCount', 1)
            ->assertViewHas('statsScope', 'date')
            ->assertViewHas('isCumulative', false);

        // In date mode for yesterday: completedCount is 1
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->set('filterDate', $yesterday)
            ->assertViewHas('completedCount', 1);
    }

    public function test_top_stat_cards_switch_to_cumulative_mode(): void
    {
        $tag = \App\Models\Tag::factory()->high()->create();
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        // Task for today (completed)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'completed',
        ]);

        // Task for yesterday (completed)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterday,
            'status' => 'completed',
        ]);

        // Switch to cumulative mode
        Livewire::actingAs($this->user)
            ->test(SupervisorDashboard::class)
            ->call('setStatsScope', 'cumulative')
            ->assertViewHas('completedCount', 2)
            ->assertViewHas('statsScope', 'cumulative')
            ->assertViewHas('isCumulative', true);
    }
}
