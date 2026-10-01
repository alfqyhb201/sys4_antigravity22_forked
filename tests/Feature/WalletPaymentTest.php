<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService;
    }

    private function createTestClient(): Client
    {
        $location = Location::create(['name' => 'موقع محفظة']);
        $category = Category::create(['name' => 'تصنيف محفظة']);

        return Client::create([
            'company' => 'شركة تجربة المحفظة',
            'client_name' => 'عميل محفظة',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
        ]);
    }

    /** @test */
    public function test_excess_payment_goes_to_wallet(): void
    {
        $client = $this->createTestClient();
        $currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال',
            'value' => 1,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 600,
            'currency_id' => $currency->id,
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'total_amount' => 600,
            'status' => 'posted',
        ]);

        // Pay $1000 via FIFO
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 1000.0,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
            'notes' => 'سداد 1000 ريال',
        ]);

        $client->refresh();
        $invoice->refresh();

        // Check receipts count
        $this->assertCount(2, $receipts);

        // First receipt should cover the invoice ($600)
        $this->assertEquals(600, $receipts[0]->amount);
        $this->assertEquals($invoice->id, $receipts[0]->invoice_id);

        // Second receipt should be the excess wallet deposit ($400)
        $this->assertEquals(400, $receipts[1]->amount);
        $this->assertNull($receipts[1]->invoice_id);
        $this->assertTrue(
            str_contains($receipts[1]->notes, 'سند دفعة مقدمة') || str_contains($receipts[1]->notes, 'فائض سداد') || str_contains($receipts[1]->notes, 'إيداع الفائض')
        );

        // Wallet balance check
        $this->assertEquals(400.00, (float) $client->wallet_balance);

        // Invoice check (should be paid, remaining is 0)
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.00, $invoice->remaining);
    }

    /** @test */
    public function test_payment_with_no_unpaid_invoices_goes_fully_to_wallet(): void
    {
        $client = $this->createTestClient();

        // General payment of $500 without unpaid invoices
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 500.0,
            'payment_method' => 'bank_transfer',
            'receipt_date' => now()->toDateString(),
        ]);

        $client->refresh();

        $this->assertCount(1, $receipts);
        $this->assertEquals(500.00, $receipts[0]->amount);
        $this->assertNull($receipts[0]->invoice_id);
        $this->assertEquals($client->id, $receipts[0]->client_id);
        $this->assertEquals(500.00, (float) $client->wallet_balance);
    }

    /** @test */
    public function test_wallet_balance_applied_automatically_on_contract_renewal(): void
    {
        $client = $this->createTestClient();
        $client->update(['wallet_balance' => 300.00]);

        $currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال',
            'value' => 1,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->subDay(),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);

        // Renew contract (should create a new contract + invoice for $1000 and auto-apply $300 from wallet)
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $client->refresh();
        $invoice->refresh();

        // Old contract should be 'renewed'
        $contract->refresh();
        $this->assertEquals('renewed', $contract->status);

        // Wallet balance should be 0 now
        $this->assertEquals(0.00, (float) $client->wallet_balance);

        // A receipt for 300 should be linked to the invoice
        $this->assertDatabaseHas('receipts', [
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'amount' => 300.00,
            'payment_method' => 'wallet',
            'notes' => 'سداد تلقائي من محفظة العميل عند تجديد الاشتراك',
        ]);

        // Remaining invoice amount should be $700
        $this->assertEquals(700.00, $invoice->remaining);
        $this->assertEquals('posted', $invoice->status); // Still unpaid partially
    }
}
