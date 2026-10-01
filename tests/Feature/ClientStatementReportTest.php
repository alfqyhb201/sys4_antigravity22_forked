<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientStatementReportTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $company = 'شركة المحيط للتجارة'): Client
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
            'client_code' => 'CL-'.rand(1000, 9999),
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777000111',
        ]);
    }

    private function createContract(Client $client, Currency $currency): Contract
    {
        return Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonth(),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient();

        $response = $this->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $client = $this->createClient();

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_client_statement_report(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient('مؤسسة الصفا للتسويق');

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertSuccessful();
        $response->assertSee('كشف حساب مالي تفصيلي');
        $response->assertSee('مؤسسة الصفا للتسويق');
        $response->assertSee('إجمالي المسحوبات (مدين +)');
        $response->assertSee('إجمالي المقبوضات (دائن -)');
    }

    public function test_draft_and_cancelled_invoices_are_excluded_from_statement(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient('مجموعة الهلال التجارية');
        $contract = $this->createContract($client, $currency);

        // 1. فاتورة مسودة (Draft) بمبلغ 5000 — يجب ألا تظهر
        $draft = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-DRAFT-999',
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(5),
            'total_amount' => 5000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 5000,
            'status' => 'draft',
            'notes' => 'فاتورة مسودة لا يجب ظهورها بالكشف',
        ]);

        // 2. فاتورة ملغاة (Cancelled) بمبلغ 3000 — يجب ألا تظهر
        $cancelled = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-CANCEL-888',
            'issue_date' => now()->subDays(8),
            'due_date' => now()->subDays(3),
            'total_amount' => 3000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 3000,
            'status' => 'cancelled',
            'notes' => 'فاتورة ملغاة لا يجب ظهورها',
        ]);

        // 3. فاتورة مرحلة (Posted) بمبلغ 1500 — يجب أن تظهر
        $posted = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'invoice_number' => 'INV-POSTED-101',
            'issue_date' => now()->subDays(5),
            'due_date' => now()->subDays(1),
            'total_amount' => 1500,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 1500,
            'status' => 'posted',
            'notes' => 'فاتورة اشتراك معتمدة',
        ]);

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertSuccessful();

        // التأكد من ظهور الفاتورة المرحومة
        $response->assertSee($posted->invoice_number);
        $response->assertSee('1,500.00');

        // التأكد من عدم ظهور الفواتير المسودة والملغاة
        $response->assertDontSee($draft->invoice_number);
        $response->assertDontSee($cancelled->invoice_number);
        $response->assertDontSee('5,000.00');
    }

    public function test_statement_correctly_calculates_debit_credit_and_running_balance(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient('شركة الرائد الدولية');
        $contract = $this->createContract($client, $currency);

        // فاتورة 1: 2000 ر.س (Posted)
        $inv1 = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(10),
            'total_amount' => 2000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 2000,
            'status' => 'posted',
            'notes' => 'فاتورة شهر 1',
        ]);

        // سند قبض 1: 1200 ر.س
        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $inv1->id,
            'amount' => 1200,
            'original_amount' => 1200,
            'base_currency_amount' => 1200,
            'exchange_rate' => 1.0,
            'paid_currency_id' => $currency->id,
            'receipt_date' => now()->subDays(12),
            'payment_method' => 'cash',
            'reference_number' => 'REC-5001',
            'notes' => 'سداد جزئي للفاتورة 1',
        ]);

        // فاتورة 2: 3000 ر.س (Posted)
        Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'issue_date' => now()->subDays(8),
            'due_date' => now()->subDays(2),
            'total_amount' => 3000,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 3000,
            'status' => 'posted',
            'notes' => 'فاتورة شهر 2',
        ]);

        // الحساب المتوقع:
        // إجمالي المدين: 2000 + 3000 = 5000
        // إجمالي الدائن: 1200
        // صافي الرصيد: 3800

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertSuccessful();
        $response->assertSee('5,000.00');
        $response->assertSee('1,200.00');
        $response->assertSee('3,800.00');
    }

    public function test_statement_print_and_pdf_endpoint(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo('view_financial_reports');

        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient('مؤسسة الصدارة');

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id, 'print' => 1]));
        $response->assertSuccessful();
        $response->assertSee('window.print()', false);
    }

    public function test_statement_service_excludes_draft_invoices(): void
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'SAR'],
            ['currency' => 'SAR', 'currency_name' => 'ريال سعودي', 'value' => 1, 'symbol' => 'ر.س', 'is_base' => true]
        );
        $client = $this->createClient('شركة البركة');
        $contract = $this->createContract($client, $currency);

        // Draft
        $draft = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->subDays(1),
            'total_amount' => 9999,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 9999,
            'status' => 'draft',
        ]);

        // Posted
        $posted = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'currency_id' => $currency->id,
            'issue_date' => now()->subDays(3),
            'due_date' => now()->subDays(1),
            'total_amount' => 2500,
            'exchange_rate' => 1.0,
            'base_currency_amount' => 2500,
            'status' => 'posted',
        ]);

        $service = app(\App\Services\FinancialDashboardService::class);
        $data = $service->getClientStatementData($client);

        $references = $data['items']->pluck('reference_number')->all();

        $this->assertContains($posted->invoice_number, $references);
        $this->assertNotContains($draft->invoice_number, $references);
        $this->assertEquals(2500.0, $data['totalDebit']);
        $this->assertEquals(2500.0, $data['finalBalance']);
    }
}
