<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientDebtorsReportTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $company = 'شركة الأمل للتجارة', ?string $notes = null): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'صنعاء'],
            ['name' => 'صنعاء']
        );

        $category = Category::firstOrCreate(
            ['name' => 'شركات تجارية'],
            ['name' => 'شركات تجارية']
        );

        return Client::create([
            'company' => $company,
            'client_name' => 'مدير '.$company,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777111222',
            'notes' => $notes,
        ]);
    }

    private function createContract(Client $client, Currency $currency): Contract
    {
        return Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(15),
            'weekly_designs_count' => 2,
            'monthly_designs_count' => 8,
            'total_amount' => 50000,
            'currency_id' => $currency->id,
        ]);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/admin/reports/debtors');
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/reports/debtors');
        $response->assertForbidden();
    }

    public function test_authorized_user_can_access_debtors_report(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $response = $this->actingAs($user)->get('/admin/reports/debtors');
        $response->assertSuccessful();
        $response->assertSee('تقرير مديونيات العملاء المالي');
        $response->assertSee('إجمالي مديونية العميل');
        $response->assertSee('مديونية الفاتورة');
        $response->assertSee('تاريخ المديونية');
    }

    public function test_debtor_clients_appear_in_table_with_total_and_invoice_debts(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'symbol' => 'ر.ي', 'is_base' => true]
        );

        $debtor = $this->createClient('مجموعة النور التجارية', 'عميل ذو أولوية');
        $contract = $this->createContract($debtor, $currency);

        Invoice::create([
            'client_id' => $debtor->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-TEST-001',
            'issue_date' => now()->subDays(20),
            'due_date' => now()->subDays(5),
            'total_amount' => 45000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 45000,
            'status' => 'posted',
            'notes' => 'دفعة مستحقة عن شهر أغسطس',
        ]);

        $response = $this->actingAs($user)->get('/admin/reports/debtors');
        $response->assertSuccessful();
        $response->assertSee('مجموعة النور التجارية');
        $response->assertSee('45,000.00 ر.ي');
    }

    public function test_fully_paid_clients_are_not_included(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'symbol' => 'ر.ي', 'is_base' => true]
        );

        $paidClient = $this->createClient('شركة الشروق المسددة');
        $contract = $this->createContract($paidClient, $currency);

        Invoice::create([
            'client_id' => $paidClient->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-PAID-001',
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subDays(10),
            'total_amount' => 30000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 30000,
            'status' => 'paid',
            'notes' => 'فاتورة مسددة بالكامل',
        ]);

        $response = $this->actingAs($user)->get('/admin/reports/debtors');
        $response->assertSuccessful();
        $response->assertDontSee('شركة الشروق المسددة');
    }

    public function test_pdf_download_returns_pdf_stream(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'symbol' => 'ر.ي', 'is_base' => true]
        );

        $debtor = $this->createClient('شركة النورس');
        $contract = $this->createContract($debtor, $currency);

        Invoice::create([
            'client_id' => $debtor->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-PDF-001',
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(2),
            'total_amount' => 12000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 12000,
            'status' => 'posted',
            'notes' => 'ملاحظة تجريبية للـ PDF',
        ]);

        $response = $this->actingAs($user)->get('/admin/reports/debtors?download=pdf');
        $response->assertSuccessful();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
