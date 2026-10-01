<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptAllocationTest extends TestCase
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
        $location = Location::firstOrCreate(
            ['name' => 'موقع اختبار التخصيص'],
            ['name' => 'موقع اختبار التخصيص']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف اختبار التخصيص'],
            ['name' => 'تصنيف اختبار التخصيص']
        );

        return Client::create([
            'company' => 'شركة اختبار التخصيص',
            'client_name' => 'عميل اختبار التخصيص',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777123456',
        ]);
    }

    private function createTestContract(Client $client, array $overrides = []): Contract
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'is_base' => true]
        );

        return Contract::create(array_merge([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now(),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 50000,
            'marketing_amount' => 0,
            'currency_id' => $currency->id,
            'auto_renewal' => true,
            'grace_period_days' => 5,
        ], $overrides));
    }

    private function createTestInvoice(Client $client, ?Contract $contract = null, array $overrides = []): Invoice
    {
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'is_base' => true]
        );

        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'contract_id' => $contract?->id,
            'currency_id' => $currency->id,
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(5),
            'total_amount' => 50000,
            'status' => 'posted',
        ], $overrides));
    }

    public function test_fifo_excess_creates_unallocated_advance_receipt(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, ['total_amount' => 30000]);

        $receipts = $this->service->payClientFifo($client, [
            'amount' => 50000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(2, $receipts);

        // السند الأول للفاتورة المستحقة
        $invoiceReceipt = $receipts->first();
        $this->assertEquals($invoice->id, $invoiceReceipt->invoice_id);
        $this->assertEquals(30000, (float) $invoiceReceipt->amount);
        $this->assertEquals(0, (float) $invoiceReceipt->unallocated_amount);

        // السند الثاني كدفعة مقدمة بالفائض
        $advanceReceipt = $receipts->last();
        $this->assertNull($advanceReceipt->invoice_id);
        $this->assertEquals(20000, (float) $advanceReceipt->amount);
        $this->assertEquals(20000, (float) $advanceReceipt->unallocated_amount);

        // التحقق من حالة الفاتورة ورصيد الدفعات المقدمة للعميل
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(20000, $client->fresh()->total_advance_balance);
    }

    public function test_allocate_advance_receipt_to_invoice(): void
    {
        $client = $this->createTestClient();
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'is_base' => true]
        );

        // إنشاء سند دفعة مقدمة بمبلغ 25000
        $advanceReceipt = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'paid_currency_id' => $currency->id,
            'amount' => 25000,
            'unallocated_amount' => 25000,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        $invoice = $this->createTestInvoice($client, null, ['total_amount' => 20000]);

        // تخصيص 20000 على الفاتورة
        $allocation = $this->service->allocateReceiptToInvoice($advanceReceipt, $invoice, 20000);

        $this->assertInstanceOf(ReceiptAllocation::class, $allocation);
        $this->assertEquals(20000, (float) $allocation->amount);

        // المتبقي في السند أصبح 5000
        $this->assertEquals(5000, (float) $advanceReceipt->fresh()->unallocated_amount);

        // الفاتورة سُددت بالكامل
        $this->assertEquals(0, $invoice->fresh()->remaining);
        $this->assertEquals('paid', $invoice->fresh()->status);
    }

    public function test_auto_allocate_advances_across_multiple_receipts(): void
    {
        $client = $this->createTestClient();
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency' => 'YER', 'currency_name' => 'ريال يمني', 'value' => 530, 'is_base' => true]
        );

        // سندين دفعات مقدمة (10000 و 15000)
        $advance1 = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'paid_currency_id' => $currency->id,
            'amount' => 10000,
            'unallocated_amount' => 10000,
            'receipt_date' => now()->subDays(2)->toDateString(),
            'payment_method' => 'cash',
        ]);

        $advance2 = Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'paid_currency_id' => $currency->id,
            'amount' => 15000,
            'unallocated_amount' => 15000,
            'receipt_date' => now()->subDay()->toDateString(),
            'payment_method' => 'cash',
        ]);

        // فاتورة بمبلغ 20000
        $invoice = $this->createTestInvoice($client, null, ['total_amount' => 20000]);

        $allocations = $this->service->autoAllocateAdvances($invoice);

        $this->assertCount(2, $allocations);
        $this->assertEquals(0, (float) $advance1->fresh()->unallocated_amount);
        $this->assertEquals(5000, (float) $advance2->fresh()->unallocated_amount);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $invoice->fresh()->remaining);
    }

    public function test_contract_renewal_auto_allocates_available_advances(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, ['total_amount' => 50000]);
        $currency = $contract->currency;

        // إيداع دفعة مقدمة 50000
        Receipt::create([
            'client_id' => $client->id,
            'invoice_id' => null,
            'paid_currency_id' => $currency->id,
            'amount' => 50000,
            'unallocated_amount' => 50000,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        // تجديد الاشتراك
        $newInvoice = $contract->renewAndGenerateInvoice();

        $this->assertNotNull($newInvoice);
        $this->assertEquals('paid', $newInvoice->status);
        $this->assertEquals(0, $newInvoice->remaining);
        $this->assertEquals(0, $client->fresh()->total_advance_balance);
    }
}
