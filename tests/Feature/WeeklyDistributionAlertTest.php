<?php

namespace Tests\Feature;

use App\Filament\Widgets\WeeklyDistributionAlertWidget;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Tag;
use App\Models\User;
use App\Services\WeeklyDistributionAuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeeklyDistributionAlertTest extends TestCase
{
    use RefreshDatabase;

    protected WeeklyDistributionAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WeeklyDistributionAuditService::class);
    }

    private function createDesigner(array $attributes = []): Designer
    {
        $user = User::factory()->create();

        return Designer::create(array_merge([
            'user_id' => $user->id,
            'min_capacity' => 5,
            'max_capacity' => 20,
            'rate' => 8,
        ], $attributes));
    }

    private function createClientWithContract(int $weeklyDesignsCount = 5, string $contractStatus = 'active', int $clientStatus = 1): array
    {
        $category = Category::factory()->create();
        $currency = Currency::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'status' => $clientStatus,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => $contractStatus,
            'payment_type' => 'advance',
            'weekly_designs_count' => $weeklyDesignsCount,
            'monthly_designs_count' => $weeklyDesignsCount * 4,
            'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'billing_cycle' => 'monthly',
            'total_amount' => 1000,
        ]);

        return ['client' => $client, 'contract' => $contract, 'category' => $category];
    }

    #[Test]
    public function it_reports_fully_distributed_when_no_active_contracts_exist()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $summary = $this->service->getAuditSummary($weekStart);

        $this->assertTrue($summary['is_fully_distributed']);
        $this->assertEquals(0, $summary['unassigned_count']);
        $this->assertEquals(0, $summary['missing_tags_count']);
    }

    #[Test]
    public function it_detects_unassigned_active_contracts_for_the_week()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');

        // عميل نشط مع عقد يحتاج تصاميم
        $data = $this->createClientWithContract(5, 'active');

        $summary = $this->service->getAuditSummary($weekStart);
        $unassigned = $this->service->getUnassignedContracts($weekStart);

        $this->assertFalse($summary['is_fully_distributed']);
        $this->assertEquals(1, $summary['unassigned_count']);
        $this->assertCount(1, $unassigned);
        $this->assertEquals($data['contract']->id, $unassigned->first()['contract_id']);
    }

    #[Test]
    public function it_detects_clients_with_missing_tags_when_assigned_to_designer()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(7, 'active');

        // تعيين العميل للمصمم في هذا الأسبوع
        ClientDesigner::create([
            'client_id' => $data['client']->id,
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        $summary = $this->service->getAuditSummary($weekStart);
        $missing = $this->service->getClientsWithMissingTags($weekStart);

        // لم يعد غير معين لمصمم
        $this->assertEquals(0, $summary['unassigned_count']);
        // لكنه يظهر في التاقات الناقصة لأن التاقات الموزعة 0 من أصل 7
        $this->assertEquals(1, $summary['missing_tags_count']);
        $this->assertCount(1, $missing);
        $this->assertEquals(7, $missing->first()['missing_count']);
    }

    #[Test]
    public function it_can_assign_contract_to_designer_directly()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(4, 'active');

        $this->service->assignContractToDesigner($data['contract']->id, $designer->id, $weekStart);

        $this->assertDatabaseHas('client_designer', [
            'client_id' => $data['client']->id,
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        $unassigned = $this->service->getUnassignedContracts($weekStart);
        $this->assertCount(0, $unassigned);
    }

    #[Test]
    public function it_can_distribute_tags_for_client_and_handles_array_weekly_day()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(2, 'active');

        // إنشاء تاق مع weekly_day كمصفوفة
        $tag = Tag::factory()->create([
            'name' => 'تاق أسبوعي تجريبي',
            'importance' => 'high',
            'is_active' => true,
            'is_auto_assigned' => true,
            'is_there_date_for_sending' => true,
            'weekly_day' => ['sunday', 'monday'],
        ]);
        $tag->categories()->attach($data['category']->id);

        $assignment = ClientDesigner::create([
            'client_id' => $data['client']->id,
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        $count = $this->service->distributeTagsForAssignment($assignment->id, $weekStart);

        $this->assertGreaterThan(0, $count);
        $this->assertDatabaseHas('client_tag_distributions', [
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
        ]);
    }

    #[Test]
    public function it_can_distribute_tags_for_all_missing_clients()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(2, 'active');

        $tag = Tag::factory()->create([
            'name' => 'تاق تلقائي للجميع',
            'importance' => 'medium',
            'is_active' => true,
            'is_auto_assigned' => true,
            'assign_all_categories' => true,
        ]);
        $tag->categories()->syncWithoutDetaching([$data['category']->id]);

        ClientDesigner::create([
            'client_id' => $data['client']->id,
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        $count = $this->service->distributeTagsForAllMissing($weekStart);

        $this->assertGreaterThan(0, $count);
    }

    #[Test]
    public function widget_can_mount_and_execute_assignment_action()
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(6, 'active');

        Livewire::test(WeeklyDistributionAlertWidget::class)
            ->assertSee('تنبيه: التوزيع الأسبوعي بحاجة لمتابعة')
            ->assertSee($data['client']->company)
            ->set("designerSelections.{$data['contract']->id}", $designer->id)
            ->call('assignContract', $data['contract']->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_designer', [
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);
    }

    #[Test]
    public function it_does_not_flag_client_as_unassigned_when_contract_was_renewed_after_assignment()
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $designer = $this->createDesigner();
        $data = $this->createClientWithContract(6, 'active');

        // 1. العميل تم توزيعه في البداية مع العقد القديم
        $assignment = ClientDesigner::create([
            'client_id' => $data['client']->id,
            'contract_id' => $data['contract']->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        // 2. تجديد العقد وإنشاء عقد نشط جديد
        $newContract = $data['contract']->createRenewalContract();

        // 3. التحقق من أن التدقيق لا يعتبر العميل غير موزع
        $summary = $this->service->getAuditSummary($weekStart);
        $unassigned = $this->service->getUnassignedContracts($weekStart);

        $this->assertEquals(0, $summary['unassigned_count']);
        $this->assertCount(0, $unassigned);

        // 4. التحقق من تحديث contract_id في client_designer تلقائياً
        $assignment->refresh();
        $this->assertEquals($newContract->id, $assignment->contract_id);
    }
}
