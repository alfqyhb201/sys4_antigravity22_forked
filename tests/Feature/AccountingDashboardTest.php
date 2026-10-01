<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountingDashboard;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\User;
use App\Services\FinancialDashboardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $company = 'شركة تجريبية'): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'موقع رئيسي'],
            ['name' => 'موقع رئيسي']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف رئيسي'],
            ['name' => 'تصنيف رئيسي']
        );

        return Client::create([
            'company' => $company,
            'client_name' => 'عميل '.$company,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777123456',
        ]);
    }

    private function createContract(Client $client, array $overrides = []): Contract
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530]
        );

        return Contract::create(array_merge([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(10),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 2000,
            'currency_id' => $currency->id,
        ], $overrides));
    }

    public function test_user_with_permission_can_access_accounting_dashboard(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_financial_reports']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(AccountingDashboard::class)
            ->assertSuccessful();
    }

    public function test_tab_counts_are_calculated_accurately(): void
    {
        $service = app(FinancialDashboardService::class);

        $clientSoon = $this->createClient('تجديد وشيك');
        $this->createContract($clientSoon, [
            'end_date' => now()->addDays(4),
            'status' => 'active',
        ]);

        $clientOverdue = $this->createClient('متأخرات');
        $contractOverdue = $this->createContract($clientOverdue, [
            'end_date' => now()->addDays(20),
            'status' => 'active',
        ]);
        Invoice::create([
            'client_id' => $clientOverdue->id,
            'contract_id' => $contractOverdue->id,
            'issue_date' => now()->subDays(20),
            'due_date' => now()->subDays(5),
            'total_amount' => 1000,
            'status' => 'posted',
        ]);

        $clientSuspended = $this->createClient('موقوف');
        $this->createContract($clientSuspended, [
            'end_date' => now()->subDays(10),
            'status' => 'suspended',
        ]);

        $counts = $service->getClientFinancialTabCounts();

        $this->assertEquals(3, $counts['all']);
        $this->assertEquals(1, $counts['critical_overdue']);
        $this->assertEquals(1, $counts['expiring_soon']);
        $this->assertEquals(1, $counts['expired_suspended']);
    }

    public function test_daily_trends_returns_seven_days_with_optimized_query(): void
    {
        $service = app(FinancialDashboardService::class);
        $trends = $service->getDailyTrends();

        $this->assertArrayHasKey('invoiced', $trends);
        $this->assertArrayHasKey('paid', $trends);
        $this->assertArrayHasKey('labels', $trends);
        $this->assertCount(7, $trends['invoiced']);
        $this->assertCount(7, $trends['paid']);
        $this->assertCount(7, $trends['labels']);
    }
}
