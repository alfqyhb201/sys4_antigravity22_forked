<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientDesignerLookupTest extends TestCase
{
    use RefreshDatabase;

    protected User $supervisorUser;

    protected User $regularUser;

    protected Designer $designer1;

    protected Designer $designer2;

    protected Category $category;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'supervisor']);
        Role::firstOrCreate(['name' => 'designer']);
        Permission::firstOrCreate(['name' => 'view_any_client']);
        Permission::firstOrCreate(['name' => 'view_client']);
        Permission::firstOrCreate(['name' => 'view_designer_distribution']);

        $this->supervisorUser = User::factory()->create([
            'status' => 1,
            'username' => 'supervisor_user',
        ]);
        $this->supervisorUser->assignRole('supervisor');
        $this->supervisorUser->givePermissionTo(['view_any_client', 'view_client']);

        $this->regularUser = User::factory()->create([
            'status' => 1,
            'username' => 'regular_user',
        ]);
        $this->regularUser->givePermissionTo(['view_any_client', 'view_client']);

        $this->category = Category::factory()->create(['name' => 'تجارة عامة']);
        $this->currency = Currency::factory()->create();

        $user1 = User::factory()->create(['name' => 'سامي المصمم']);
        $this->designer1 = Designer::create([
            'user_id' => $user1->id,
            'rate' => 8,
            'min_capacity' => 5,
            'max_capacity' => 50,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $user2 = User::factory()->create(['name' => 'مروان المساعد']);
        $this->designer2 = Designer::create([
            'user_id' => $user2->id,
            'rate' => 7,
            'min_capacity' => 5,
            'max_capacity' => 50,
            'shift_hours' => 8,
            'discipline_score' => 8,
            'amount_of_designs' => 80,
        ]);
    }

    #[Test]
    public function test_client_current_week_designers_relationship(): void
    {
        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'شركة الأفق المحدودة',
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->addDays(20),
            'weekly_designs_count' => 6,
            'monthly_designs_count' => 24,
            'total_amount' => 1500,
            'currency_id' => $this->currency->id,
        ]);

        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $pastWeek = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');

        // Past week assignment
        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer2->id,
            'contract_id' => $contract->id,
            'week_start_date' => $pastWeek,
            'is_side' => false,
        ]);

        // Current week main assignment
        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $contract->id,
            'week_start_date' => $currentWeek,
            'is_side' => false,
        ]);

        // Current week side assignment
        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer2->id,
            'contract_id' => $contract->id,
            'week_start_date' => $currentWeek,
            'is_side' => true,
        ]);

        $this->assertCount(2, $client->currentWeekClientDesigners);
        $this->assertEquals(2, $client->currentWeekClientDesigners()->count());

        $recent = $client->recentClientDesigners()->get();
        $this->assertCount(3, $recent);
        $this->assertEquals($currentWeek, Carbon::parse($recent->first()->week_start_date)->format('Y-m-d'));
    }

    #[Test]
    public function test_supervisor_sees_current_week_designer_in_clients_table(): void
    {
        $this->actingAs($this->supervisorUser);

        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'شركة النجاح الدولية',
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 4,
            'monthly_designs_count' => 16,
            'total_amount' => 1000,
            'currency_id' => $this->currency->id,
        ]);

        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $contract->id,
            'week_start_date' => $currentWeek,
            'is_side' => false,
        ]);

        Livewire::test(ListClients::class)
            ->assertTableColumnExists('current_week_designers')
            ->assertTableFilterExists('current_week_designer');
    }

    #[Test]
    public function test_regular_user_cannot_see_designer_column_in_clients_table(): void
    {
        $this->actingAs($this->regularUser);

        Livewire::test(ListClients::class)
            ->assertTableColumnHidden('current_week_designers');
    }

    #[Test]
    public function test_supervisor_can_filter_clients_by_current_week_designer(): void
    {
        $this->actingAs($this->supervisorUser);

        $client1 = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'عميل سامي',
        ]);

        $client2 = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'عميل مروان',
        ]);

        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        ClientDesigner::create([
            'client_id' => $client1->id,
            'designer_id' => $this->designer1->id,
            'week_start_date' => $currentWeek,
        ]);

        ClientDesigner::create([
            'client_id' => $client2->id,
            'designer_id' => $this->designer2->id,
            'week_start_date' => $currentWeek,
        ]);

        Livewire::test(ListClients::class)
            ->filterTable('current_week_designer', $this->designer1->id)
            ->assertCanSeeTableRecords([$client1])
            ->assertCanNotSeeTableRecords([$client2]);
    }

    #[Test]
    public function test_infolist_shows_distribution_tab_for_supervisor(): void
    {
        $this->actingAs($this->supervisorUser);

        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'شركة القمة الاستثمارية',
            'fixed_designer_id' => $this->designer1->id,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 4,
            'monthly_designs_count' => 16,
            'total_amount' => 1000,
            'currency_id' => $this->currency->id,
        ]);

        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $contract->id,
            'week_start_date' => $currentWeek,
            'is_side' => false,
        ]);

        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->assertSuccessful()
            ->assertSee('التوزيع')
            ->assertSee('توزيع الأسبوع الحالي')
            ->assertSee('سجل التوزيع (آخر 8 أسابيع)')
            ->assertSee('سامي المصمم')
            ->assertSee('مصمم رئيسي')
            ->assertSee('شهري')
            ->assertSee($currentWeek);
    }

    #[Test]
    public function test_global_search_returns_designer_details_for_supervisor(): void
    {
        $this->actingAs($this->supervisorUser);

        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'مؤسسة الرواد التجارية',
        ]);

        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer1->id,
            'week_start_date' => $currentWeek,
            'is_side' => false,
        ]);

        ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer2->id,
            'week_start_date' => $currentWeek,
            'is_side' => true,
        ]);

        $details = ClientResource::getGlobalSearchResultDetails($client);

        $this->assertArrayHasKey('مصمم الأسبوع', $details);
        $this->assertStringContainsString('سامي المصمم', $details['مصمم الأسبوع']);
        $this->assertStringContainsString('مروان المساعد (جانبي)', $details['مصمم الأسبوع']);
        $this->assertEquals(ClientResource::getUrl('view', ['record' => $client]), ClientResource::getGlobalSearchResultUrl($client));
    }

    #[Test]
    public function test_global_search_omits_designer_details_for_regular_user(): void
    {
        $this->actingAs($this->regularUser);

        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'مؤسسة الشروق',
        ]);

        $details = ClientResource::getGlobalSearchResultDetails($client);

        $this->assertArrayNotHasKey('مصمم الأسبوع', $details);
        $this->assertArrayHasKey('التصنيف', $details);
    }
}
