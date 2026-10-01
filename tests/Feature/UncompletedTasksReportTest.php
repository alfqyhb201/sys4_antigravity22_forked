<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UncompletedTasksReportTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $regularUser;

    private Designer $designer;

    private Client $client;

    private ClientDesigner $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_supervisor_dashboard']);
        $this->supervisor->givePermissionTo('view_supervisor_dashboard');

        $this->regularUser = User::factory()->create();

        // Create designer
        $designerUser = User::factory()->create(['name' => 'المصمم الأول']);
        $this->designer = Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $category = Category::factory()->create();
        $currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->supervisor->id,
        ]);
        $this->client = Client::factory()->create([
            'company' => 'شركة النجاح',
            'category_id' => $category->id,
            'importance_weights' => ['high' => 100],
            'enable_very_high' => true,
        ]);
        Contract::create([
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

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->assignment = ClientDesigner::create([
            'designer_id' => $this->designer->id,
            'client_id' => $this->client->id,
            'week_start_date' => $weekStart,
        ]);
    }

    public function test_guest_cannot_access_report(): void
    {
        $response = $this->get(route('reports.uncompleted-tasks'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_receives_forbidden(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('reports.uncompleted-tasks'));
        $response->assertStatus(403);
    }

    public function test_supervisor_can_view_report_with_uncompleted_tasks(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $tag = Tag::factory()->high()->create();

        // Pending task for today
        ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->supervisor)->get(route('reports.uncompleted-tasks', ['date' => $today]));

        $response->assertStatus(200);
        $response->assertSee('كشف جرد التصاميم بتاريخ');
        $response->assertSee('الموافق يوم');
        $response->assertSee('اسم المصمم');
        $response->assertSee('عدد المهام غير المنجزة');
        $response->assertSee('ملاحظة');
        $response->assertSee('المصمم الأول');
        $response->assertDontSee('المتأخرات');
        $response->assertDontSee('الإجمالي العام');
    }

    public function test_report_shows_empty_notice_when_all_tasks_are_completed(): void
    {
        $today = Carbon::now()->format('Y-m-d');
        $tag = Tag::factory()->high()->create();

        // Completed task for today
        ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $today,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->supervisor)->get(route('reports.uncompleted-tasks', ['date' => $today]));

        $response->assertStatus(200);
        $response->assertSee('جميع المصممين أنجزوا كامل مهامهم لهذا اليوم');
    }
}
