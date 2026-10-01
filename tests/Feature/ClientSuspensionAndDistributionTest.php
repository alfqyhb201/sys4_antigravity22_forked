<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Designer;
use App\Models\Tag;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\ClientLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSuspensionAndDistributionTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Designer $designer;

    private Contract $contract;

    private ClientDesigner $clientDesigner;

    private Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);

        $designerUser = User::factory()->create(['name' => 'مصمم تجريبي']);
        $this->designer = Designer::create([
            'user_id' => $designerUser->id,
            'status' => 1,
            'max_load' => 20,
        ]);

        $category = Category::factory()->create();
        $location = \App\Models\Location::create(['name' => 'صنعاء']);

        $this->client = Client::create([
            'company' => 'شركة الأمل للتجارة',
            'status' => true,
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);

        $currency = \App\Models\Currency::factory()->create([
            'is_base' => true,
        ]);

        $this->contract = Contract::create([
            'client_id' => $this->client->id,
            'currency_id' => $currency->id,
            'status' => 'active',
            'start_date' => now()->startOfWeek(),
            'end_date' => now()->endOfWeek(),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 500,
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
        ]);

        $this->clientDesigner = ClientDesigner::create([
            'client_id' => $this->client->id,
            'designer_id' => $this->designer->id,
            'contract_id' => $this->contract->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
        ]);

        $this->tag = Tag::factory()->create();
    }

    public function test_client_suspension_freezes_uncompleted_distributions_and_preserves_completed(): void
    {
        $completedDist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $inProgressDist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'in_progress',
        ]);

        $pendingDist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        app(ClientLifecycleService::class)->suspend($this->client);

        $this->client->refresh();
        $this->contract->refresh();

        $this->assertFalse((bool) $this->client->status);
        $this->assertNotNull($this->client->suspended_at);
        $this->assertEquals('suspended', $this->contract->status);

        $this->assertEquals('completed', $completedDist->fresh()->status);
        $this->assertEquals('frozen', $inProgressDist->fresh()->status);
        $this->assertEquals('frozen', $pendingDist->fresh()->status);
    }

    public function test_contract_status_change_triggers_observer_and_freezes_tasks(): void
    {
        $pendingDist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->contract->update(['status' => 'suspended']);

        $this->assertFalse((bool) $this->client->fresh()->status);
        $this->assertEquals('frozen', $pendingDist->fresh()->status);
    }

    public function test_client_resumption_unfreezes_distributions_and_shifts_past_dates_to_today(): void
    {
        $pastDate = now()->subDays(2)->toDateString();
        $todayDate = now()->toDateString();

        $dist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => $pastDate,
            'status' => 'pending',
        ]);

        // Suspend
        app(ClientLifecycleService::class)->suspend($this->client);
        $this->assertEquals('frozen', $dist->fresh()->status);

        // Resume
        app(ClientLifecycleService::class)->resume($this->client);

        $this->client->refresh();
        $this->contract->refresh();
        $dist->refresh();

        $this->assertTrue((bool) $this->client->status);
        $this->assertNull($this->client->suspended_at);
        $this->assertEquals('active', $this->contract->status);

        // Status unfreezes to pending
        $this->assertEquals('pending', $dist->status);
        // Past distribution date is shifted to today
        $this->assertEquals($todayDate, $dist->distribution_date);

        // Check that it's NOT counted as overdue in admin workflow stats
        $stats = app(AdminDashboardService::class)->getWorkflowStats();
        $this->assertEquals(0, $stats['overdueTasks']);
    }

    public function test_future_distribution_dates_remain_unchanged_on_resumption(): void
    {
        $futureDate = now()->addDays(2)->toDateString();

        $dist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => $futureDate,
            'status' => 'pending',
        ]);

        app(ClientLifecycleService::class)->suspend($this->client);
        $this->assertEquals('frozen', $dist->fresh()->status);

        app(ClientLifecycleService::class)->resume($this->client);
        $dist->refresh();

        $this->assertEquals('pending', $dist->status);
        // Future date is not changed
        $this->assertEquals($futureDate, $dist->distribution_date);
    }

    public function test_frozen_distributions_are_excluded_from_overdue_and_daily_stats(): void
    {
        $pastDate = now()->subDays(2)->toDateString();

        $dist = ClientTagDistribution::create([
            'client_designer_id' => $this->clientDesigner->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => $pastDate,
            'status' => 'pending',
        ]);

        // Before suspension: it's overdue
        $statsBefore = app(AdminDashboardService::class)->getWorkflowStats();
        $this->assertEquals(1, $statsBefore['overdueTasks']);

        // Suspend: it becomes frozen
        app(ClientLifecycleService::class)->suspend($this->client);
        $this->assertEquals('frozen', $dist->fresh()->status);

        // While frozen: it must NOT be counted as overdue
        $statsAfter = app(AdminDashboardService::class)->getWorkflowStats();
        $this->assertEquals(0, $statsAfter['overdueTasks']);
    }
}
