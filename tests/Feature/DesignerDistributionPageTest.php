<?php

namespace Tests\Feature;

use App\Filament\Pages\DesignerDistribution;
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
use Tests\TestCase;

class DesignerDistributionPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'admin_distribution',
        ]);

        Permission::firstOrCreate(['name' => 'view_designer_distribution']);
        $this->user->givePermissionTo('view_designer_distribution');

        $this->actingAs($this->user);
    }

    #[Test]
    public function test_can_render_designer_distribution_page(): void
    {
        Livewire::test(DesignerDistribution::class)
            ->assertSuccessful();
    }

    #[Test]
    public function test_can_manually_assign_multiple_clients_to_designer(): void
    {
        $category = Category::factory()->create();
        $currency = Currency::factory()->create();

        $designerUser = User::factory()->create(['name' => 'مصمم رئيسي']);
        $designer = Designer::create([
            'user_id' => $designerUser->id,
            'rate' => 9,
            'min_capacity' => 5,
            'max_capacity' => 50,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $client1 = Client::factory()->create(['category_id' => $category->id, 'company' => 'شركة النور']);
        $contract1 = Contract::create([
            'client_id' => $client1->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 5,
            'monthly_designs_count' => 20,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);

        $client2 = Client::factory()->create(['category_id' => $category->id, 'company' => 'شركة الفجر']);
        $contract2 = Contract::create([
            'client_id' => $client2->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 7,
            'monthly_designs_count' => 28,
            'total_amount' => 1200,
            'currency_id' => $currency->id,
        ]);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');

        Livewire::test(DesignerDistribution::class)
            ->callTableAction('addAssignment', null, [
                'designer_id' => $designer->id,
                'contract_ids' => [$contract1->id, $contract2->id],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client1->id,
            'designer_id' => $designer->id,
            'contract_id' => $contract1->id,
            'week_start_date' => $weekStart,
        ]);

        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client2->id,
            'designer_id' => $designer->id,
            'contract_id' => $contract2->id,
            'week_start_date' => $weekStart,
        ]);

        $this->assertEquals(2, ClientDesigner::where('designer_id', $designer->id)->where('week_start_date', $weekStart)->count());
    }
}
