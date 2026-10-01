<?php

namespace Tests\Feature;

use App\Filament\Widgets\TopDebtorsWidget;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TopDebtorsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $company): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'موقع مالي'],
            ['name' => 'موقع مالي']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف مالي'],
            ['name' => 'تصنيف مالي']
        );

        return Client::create([
            'company' => $company,
            'client_name' => 'عميل '.$company,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777'.rand(100000, 999999),
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
            'end_date' => now()->addDays(5),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ], $overrides));
    }

    public function test_top_debtors_widget_renders_with_merged_subscription_period_column(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_financial_reports']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $clientSoon = $this->createClient('عميل ينتهي قريباً');
        $this->createContract($clientSoon, [
            'end_date' => now()->addDays(3),
        ]);

        Livewire::actingAs($user)
            ->test(TopDebtorsWidget::class)
            ->assertSuccessful()
            ->assertTableColumnExists('company')
            ->assertTableColumnExists('subscription_period')
            ->assertTableColumnExists('outstanding_balance_display')
            ->assertTableColumnExists('activity_status')
            ->assertCanSeeTableRecords([$clientSoon]);
    }

    public function test_tabs_filter_records_correctly(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_financial_reports']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // 1. Client expiring soon (within 7 days)
        $clientSoon = $this->createClient('عميل تجديد وشيك');
        $contractSoon = $this->createContract($clientSoon, [
            'end_date' => now()->addDays(3),
            'status' => 'active',
        ]);

        // 2. Client expired
        $clientExpired = $this->createClient('عميل منتهي وموقوف');
        $contractExpired = $this->createContract($clientExpired, [
            'end_date' => now()->subDays(5),
            'status' => 'suspended',
        ]);

        // 3. Client critical overdue
        $clientOverdue = $this->createClient('عميل متأخرات حرجة');
        $contractOverdue = $this->createContract($clientOverdue, [
            'end_date' => now()->addDays(20),
            'status' => 'active',
        ]);
        Invoice::create([
            'client_id' => $clientOverdue->id,
            'contract_id' => $contractOverdue->id,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(5),
            'total_amount' => 500,
            'status' => 'posted',
        ]);

        // 4. Client with OLD expired contract BUT currently active renewed contract
        $clientRenewed = $this->createClient('عميل تم تجديده');
        // Old expired contract
        $this->createContract($clientRenewed, [
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonth(),
            'status' => 'expired',
        ]);
        // Current active contract
        $contractRenewedCurrent = $this->createContract($clientRenewed, [
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(25),
            'status' => 'active',
        ]);

        // Tab: all
        Livewire::actingAs($user)
            ->test(TopDebtorsWidget::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$clientSoon, $clientExpired, $clientOverdue, $clientRenewed]);

        // Tab: critical_overdue
        Livewire::actingAs($user)
            ->test(TopDebtorsWidget::class)
            ->set('activeTab', 'critical_overdue')
            ->assertCanSeeTableRecords([$clientOverdue])
            ->assertCanNotSeeTableRecords([$clientSoon, $clientExpired, $clientRenewed]);

        // Tab: expiring_soon
        Livewire::actingAs($user)
            ->test(TopDebtorsWidget::class)
            ->set('activeTab', 'expiring_soon')
            ->assertCanSeeTableRecords([$clientSoon])
            ->assertCanNotSeeTableRecords([$clientExpired, $clientOverdue, $clientRenewed]);

        // Tab: expired_suspended
        Livewire::actingAs($user)
            ->test(TopDebtorsWidget::class)
            ->set('activeTab', 'expired_suspended')
            ->assertCanSeeTableRecords([$clientExpired])
            ->assertCanNotSeeTableRecords([$clientSoon, $clientOverdue, $clientRenewed]);
    }
}
