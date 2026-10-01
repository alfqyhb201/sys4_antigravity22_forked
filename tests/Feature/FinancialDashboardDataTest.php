<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientFinancialDetail;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Services\FinancialDashboardService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialDashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private function createClient(string $company = 'شركة اختبار مالية'): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'موقع اختبار مالي'],
            ['name' => 'موقع اختبار مالي']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف اختبار مالي'],
            ['name' => 'تصنيف اختبار مالي']
        );

        return Client::create([
            'company' => $company,
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777000111',
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
            'end_date' => now()->addMonth(),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1000,
            'marketing_amount' => 200,
            'currency_id' => $currency->id,
            'grace_period_days' => 5,
            'auto_suspension_enabled' => true,
        ], $overrides));
    }

    private function createInvoice(Client $client, Contract $contract, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(2),
            'total_amount' => 1000,
            'status' => 'posted',
        ], $overrides));
    }

    public function test_accounting_snapshot_aggregates_the_current_period(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient();
        $contract = $this->createContract($client);

        $currentInvoice = $this->createInvoice($client, $contract, [
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(7),
            'total_amount' => 1000,
            'status' => 'posted',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $currentInvoice->id,
            'amount' => 400,
            'receipt_date' => now()->startOfMonth(),
            'payment_method' => 'cash',
        ]);

        $oldInvoice = $this->createInvoice($client, $contract, [
            'issue_date' => now()->subMonths(2),
            'due_date' => now()->subMonths(2)->addDays(5),
            'total_amount' => 700,
            'status' => 'paid',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $oldInvoice->id,
            'amount' => 700,
            'receipt_date' => now()->subMonth(),
            'payment_method' => 'cash',
        ]);

        $snapshot = $service->getAccountingSnapshot('month');

        $this->assertSame('هذا الشهر', $snapshot['periodLabel']);
        $this->assertSame(1000.0, $snapshot['totalInvoiced']);
        $this->assertSame(600.0, $snapshot['totalRemaining']);
        $this->assertSame(400.0, $snapshot['totalPaid']);
        $this->assertSame(0.0, $snapshot['totalOverdue']);
        $this->assertSame(0, $snapshot['overdueCount']);
    }

    public function test_top_debtors_query_exposes_financial_summary_fields(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة الملخص المالي');
        $contract = $this->createContract($client, [
            'grace_period_days' => 3,
        ]);

        $invoiceOne = $this->createInvoice($client, $contract, [
            'issue_date' => now()->subDays(8),
            'due_date' => now()->subDays(4),
            'total_amount' => 1200,
            'status' => 'posted',
        ]);

        $invoiceTwo = $this->createInvoice($client, $contract, [
            'issue_date' => now()->subDays(2),
            'due_date' => now()->addDays(3),
            'total_amount' => 800,
            'status' => 'paid',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $invoiceOne->id,
            'amount' => 500,
            'receipt_date' => now()->subDay(),
            'payment_method' => 'cash',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $invoiceTwo->id,
            'amount' => 800,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        $record = $service->topDebtorsQuery()->first();

        $this->assertNotNull($record);
        $this->assertSame(2000.0, (float) $record->total_invoiced);
        $this->assertSame(700.0, (float) $record->outstanding_balance);
        $this->assertSame(2, (int) $record->invoices_count);
        $this->assertNotNull($record->last_payment_date);
        $this->assertSame('شركة الملخص المالي', $record->company);
    }

    public function test_prepare_client_financial_profile_populates_summary_attributes(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة الملف المالي');
        $contract = $this->createContract($client);

        $invoice = $this->createInvoice($client, $contract, [
            'due_date' => now()->subDays(6),
            'total_amount' => 900,
            'status' => 'posted',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
            'receipt_date' => now()->subDay(),
            'payment_method' => 'cash',
        ]);

        $prepared = $service->prepareClientFinancialProfile($client);

        $this->assertSame(1, (int) $prepared->invoices_count);
        $this->assertSame(900.0, (float) $prepared->invoiced_amount);
        $this->assertSame(600.0, (float) $prepared->outstanding_balance);
        $this->assertSame(300.0, (float) $prepared->paid_amount);
        $this->assertSame('شركة الملف المالي', $prepared->company);
    }

    public function test_financial_client_page_exposes_tabbed_sections(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_financial_reports', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_financial_reports']);

        $client = $this->createClient('شركة الصفحة المالية');
        $contract = $this->createContract($client, [
            'grace_period_days' => 4,
        ]);

        $invoice = $this->createInvoice($client, $contract, [
            'total_amount' => 1500,
            'status' => 'posted',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'receipt_date' => now()->subDay(),
            'payment_method' => 'cash',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(ClientFinancialDetail::class, ['client' => $client])
            ->assertSee('نظرة عامة')
            ->assertSee('الفواتير')
            ->assertSee('سندات القبض')
            ->assertSee('تفاصيل الاشتراك الحالي')
            ->assertSee('الحالة الزمنية والتواريخ');
    }

    public function test_top_debtors_query_ignores_soft_deleted_receipts_for_outstanding_balance(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة السندات المحذوفة');
        $contract = $this->createContract($client);

        $invoice = $this->createInvoice($client, $contract, [
            'total_amount' => 1000,
            'status' => 'posted',
        ]);

        $receipt = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        // Soft delete the receipt
        $receipt->delete();

        $record = $service->topDebtorsQuery()->where('clients.id', $client->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(1000.0, (float) $record->outstanding_balance);
    }

    public function test_top_debtors_query_accounts_for_receipt_allocations_in_outstanding_balance(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة التخصيصات');
        $contract = $this->createContract($client);

        $invoice = $this->createInvoice($client, $contract, [
            'total_amount' => 1500,
            'status' => 'posted',
        ]);

        $advanceReceipt = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'amount' => 1000,
            'unallocated_amount' => 0,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        ReceiptAllocation::create([
            'receipt_id' => $advanceReceipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'allocated_currency_id' => $invoice->currency_id,
            'exchange_rate' => 1.0,
        ]);

        $record = $service->topDebtorsQuery()->where('clients.id', $client->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(500.0, (float) $record->outstanding_balance);

        $profile = $service->prepareClientFinancialProfile($client);
        $this->assertSame(500.0, (float) $profile->outstanding_balance);
    }

    public function test_top_debtors_query_ignores_allocations_from_soft_deleted_receipts(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة التخصيص المحذوف');
        $contract = $this->createContract($client);

        $invoice = $this->createInvoice($client, $contract, [
            'total_amount' => 2000,
            'status' => 'posted',
        ]);

        $advanceReceipt = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'amount' => 800,
            'unallocated_amount' => 0,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        ReceiptAllocation::create([
            'receipt_id' => $advanceReceipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 800,
            'allocated_currency_id' => $invoice->currency_id,
            'exchange_rate' => 1.0,
        ]);

        $advanceReceipt->delete();

        $record = $service->topDebtorsQuery()->where('clients.id', $client->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(2000.0, (float) $record->outstanding_balance);
    }

    public function test_top_debtors_query_clamps_overpaid_invoices_to_zero(): void
    {
        $service = app(FinancialDashboardService::class);

        $client = $this->createClient('شركة السداد الزائد');
        $contract = $this->createContract($client);

        $inv1 = $this->createInvoice($client, $contract, [
            'total_amount' => 500,
            'status' => 'posted',
        ]);

        $inv2 = $this->createInvoice($client, $contract, [
            'total_amount' => 800,
            'status' => 'posted',
        ]);

        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => $inv1->id,
            'amount' => 600,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        $record = $service->topDebtorsQuery()->where('clients.id', $client->id)->first();

        $this->assertNotNull($record);
        $this->assertSame(800.0, (float) $record->outstanding_balance);
    }
}
