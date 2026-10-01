<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService;
    }

    /**
     * إنشاء عميل اختبار مع اشتراك نشط.
     */
    private function createTestClient(): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'موقع اختبار سداد'],
            ['name' => 'موقع اختبار سداد']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف اختبار سداد'],
            ['name' => 'تصنيف اختبار سداد']
        );

        return Client::create([
            'company' => 'شركة اختبار السداد',
            'client_name' => 'عميل اختبار السداد',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777888999',
        ]);
    }

    /**
     * إنشاء اشتراك اختبار.
     */
    private function createTestContract(Client $client, array $overrides = []): Contract
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

    /**
     * إنشاء فاتورة اختبار.
     */
    private function createTestInvoice(Client $client, Contract $contract, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(5),
            'total_amount' => 50000,
            'status' => 'posted',
        ], $overrides));
    }

    // ======================================================================
    // اختبارات سداد فاتورة محددة
    // ======================================================================

    public function test_pay_invoice_full_amount_marks_as_paid(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        $receipt = $this->service->payInvoice($invoice, [
            'amount' => 10000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        // التحقق من إنشاء سند القبض
        $this->assertNotNull($receipt);
        $this->assertDatabaseHas('receipts', [
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'payment_method' => 'cash',
        ]);

        // التحقق من تحول حالة الفاتورة إلى مسددة
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_pay_invoice_partial_amount_keeps_posted(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        $receipt = $this->service->payInvoice($invoice, [
            'amount' => 3000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertEquals(3000, (float) $receipt->amount);

        // الفاتورة تبقى posted لأنه سداد جزئي
        $invoice->refresh();
        $this->assertEquals('posted', $invoice->status);
        $this->assertEquals(7000, $invoice->remaining);
    }

    public function test_pay_invoice_rejects_amount_exceeding_remaining(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->payInvoice($invoice, [
            'amount' => 15000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);
    }

    public function test_pay_invoice_respects_previous_partial_payments(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        // سداد جزئي أول
        $this->service->payInvoice($invoice, [
            'amount' => 6000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        // المبلغ المتبقي الآن 4000
        $invoice->refresh();
        $this->assertEquals(4000, $invoice->remaining);

        // محاولة سداد مبلغ أكبر من المتبقي يجب أن تفشل
        $this->expectException(\InvalidArgumentException::class);
        $this->service->payInvoice($invoice, [
            'amount' => 5000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);
    }

    public function test_pay_invoice_stores_reference_number_and_notes(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        $receipt = $this->service->payInvoice($invoice, [
            'amount' => 5000,
            'payment_method' => 'kuraimi',
            'receipt_date' => '2026-06-18',
            'reference_number' => 'TXN-12345',
            'notes' => 'سداد عبر الكريمي',
        ]);

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'payment_method' => 'kuraimi',
            'reference_number' => 'TXN-12345',
            'notes' => 'سداد عبر الكريمي',
        ]);
    }

    // ======================================================================
    // اختبارات FIFO
    // ======================================================================

    public function test_fifo_pays_oldest_invoice_first(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // إنشاء 3 فواتير بتواريخ استحقاق مختلفة
        $oldInvoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 5000,
            'due_date' => now()->subDays(30),
        ]);
        $midInvoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 8000,
            'due_date' => now()->subDays(15),
        ]);
        $newInvoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 3000,
            'due_date' => now()->subDays(5),
        ]);

        // سداد 5000 (يكفي للفاتورة الأقدم فقط)
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 5000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $receipts);
        $this->assertEquals($oldInvoice->id, $receipts->first()->invoice_id);
        $this->assertEquals(5000, (float) $receipts->first()->amount);

        // الفاتورة الأقدم يجب أن تصبح مسددة
        $oldInvoice->refresh();
        $this->assertEquals('paid', $oldInvoice->status);

        // الفواتير الأخرى تبقى posted
        $midInvoice->refresh();
        $newInvoice->refresh();
        $this->assertEquals('posted', $midInvoice->status);
        $this->assertEquals('posted', $newInvoice->status);
    }

    public function test_fifo_distributes_across_multiple_invoices(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        $invoice1 = $this->createTestInvoice($client, $contract, [
            'total_amount' => 3000,
            'due_date' => now()->subDays(20),
        ]);
        $invoice2 = $this->createTestInvoice($client, $contract, [
            'total_amount' => 4000,
            'due_date' => now()->subDays(10),
        ]);
        $invoice3 = $this->createTestInvoice($client, $contract, [
            'total_amount' => 5000,
            'due_date' => now()->subDays(5),
        ]);

        // سداد 10000 (يكفي للفاتورتين الأولى والثانية + جزء من الثالثة)
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 10000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(3, $receipts);

        // التحقق من التوزيع
        $this->assertEquals(3000, (float) $receipts[0]->amount);
        $this->assertEquals($invoice1->id, $receipts[0]->invoice_id);

        $this->assertEquals(4000, (float) $receipts[1]->amount);
        $this->assertEquals($invoice2->id, $receipts[1]->invoice_id);

        $this->assertEquals(3000, (float) $receipts[2]->amount);
        $this->assertEquals($invoice3->id, $receipts[2]->invoice_id);

        // الفاتورتين 1 و 2 مسددة بالكامل
        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertEquals('paid', $invoice1->status);
        $this->assertEquals('paid', $invoice2->status);

        // الفاتورة 3 جزئي (2000 متبقي)
        $invoice3->refresh();
        $this->assertEquals('posted', $invoice3->status);
        $this->assertEquals(2000, $invoice3->remaining);
    }

    public function test_fifo_partial_on_last_invoice(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        $invoice1 = $this->createTestInvoice($client, $contract, [
            'total_amount' => 5000,
            'due_date' => now()->subDays(10),
        ]);
        $invoice2 = $this->createTestInvoice($client, $contract, [
            'total_amount' => 8000,
            'due_date' => now()->subDays(5),
        ]);

        // سداد 7000 (5000 للأولى + 2000 جزئي من الثانية)
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 7000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(2, $receipts);
        $this->assertEquals(5000, (float) $receipts[0]->amount);
        $this->assertEquals(2000, (float) $receipts[1]->amount);

        $invoice1->refresh();
        $invoice2->refresh();
        $this->assertEquals('paid', $invoice1->status);
        $this->assertEquals('posted', $invoice2->status);
        $this->assertEquals(6000, $invoice2->remaining);
    }

    public function test_fifo_deposits_to_wallet_when_no_unpaid_invoices(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // لا توجد فواتير مستحقة
        $receipts = $this->service->payClientFifo($client, [
            'amount' => 5000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $client->refresh();
        $this->assertEquals(5000.00, (float) $client->wallet_balance);
        $this->assertCount(1, $receipts);
        $this->assertEquals(5000.00, $receipts[0]->amount);
        $this->assertNull($receipts[0]->invoice_id);
    }

    public function test_fifo_deposits_excess_to_wallet_when_amount_exceeds_unpaid_total(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $this->createTestInvoice($client, $contract, [
            'total_amount' => 5000,
        ]);

        $receipts = $this->service->payClientFifo($client, [
            'amount' => 6000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $client->refresh();
        $this->assertEquals(1000.00, (float) $client->wallet_balance);
        $this->assertCount(2, $receipts);
        $this->assertEquals(5000.00, $receipts[0]->amount);
        $this->assertEquals(1000.00, $receipts[1]->amount);
        $this->assertNull($receipts[1]->invoice_id);
    }

    public function test_fifo_skips_already_paid_invoices(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // فاتورة مسددة
        $this->createTestInvoice($client, $contract, [
            'total_amount' => 5000,
            'due_date' => now()->subDays(20),
            'status' => 'paid',
        ]);

        // فاتورة مستحقة
        $unpaidInvoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 3000,
            'due_date' => now()->subDays(10),
        ]);

        $receipts = $this->service->payClientFifo($client, [
            'amount' => 3000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $receipts);
        $this->assertEquals($unpaidInvoice->id, $receipts->first()->invoice_id);

        $unpaidInvoice->refresh();
        $this->assertEquals('paid', $unpaidInvoice->status);
    }

    // ======================================================================
    // اختبارات ترو (العمليات الحسابية والخصومات والتصاميم الإضافية)
    // ======================================================================

    public function test_calculate_monthly_amount(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'total_amount' => 45000,
            'marketing_amount' => 15000,
        ]);

        $monthlyAmount = $this->service->calculateMonthlyAmount($contract);
        $this->assertEquals(60000.0, $monthlyAmount);
    }

    public function test_calculate_count_amount(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'total_amount' => 40000,
            'marketing_amount' => 20000,
        ]);

        $amount = $this->service->calculateCountAmount($contract, 15);
        $this->assertEquals(30000.0, $amount);
    }

    public function test_calculate_period_amount(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'total_amount' => 50000,
            'marketing_amount' => 10000,
        ]);

        $amount = $this->service->calculatePeriodAmount($contract, '2026-06-01', '2026-06-10');
        $this->assertEquals(20000.0, $amount);
    }

    public function test_record_payment_with_discount_creates_two_receipts(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 50000,
        ]);

        $receipts = $this->service->recordPaymentWithDiscount($client, [
            'amount' => 40000,
            'discount' => 10000,
            'payment_method' => 'kuraimi',
            'receipt_date' => now()->toDateString(),
            'notes' => 'سداد الاشتراك مع خصم تشجيعي',
        ]);

        $this->assertCount(2, $receipts);

        $cashReceipt = $receipts->firstWhere('payment_method', 'kuraimi');
        $this->assertNotNull($cashReceipt);
        $this->assertEquals(40000.0, (float) $cashReceipt->amount);

        $discountReceipt = $receipts->firstWhere('payment_method', 'discount');
        $this->assertNotNull($discountReceipt);
        $this->assertEquals(10000.0, (float) $discountReceipt->amount);
        $this->assertStringContainsString('خصم ممنوح', $discountReceipt->notes);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.0, $invoice->remaining);
    }

    public function test_record_payment_with_discount_allows_excess_as_advance(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        // دفع 40,000 بينما المديونية 10,000 فقط
        $receipts = $this->service->recordPaymentWithDiscount($client, [
            'amount' => 40000,
            'discount' => 0,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertCount(2, $receipts);
        $this->assertEquals(10000.0, (float) $receipts[0]->amount);
        $this->assertEquals(30000.0, (float) $receipts[1]->unallocated_amount);
        $this->assertEquals(30000.0, (float) $client->fresh()->total_advance_balance);
    }

    public function test_record_payment_with_discount_when_net_amount_equals_or_exceeds_outstanding_balance(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $invoice = $this->createTestInvoice($client, $contract, [
            'total_amount' => 10000,
        ]);

        // العميل عليه 10,000 وتم إدخال مبلغ مقبوض 10,000 وخصم 2,000
        $receipts = $this->service->recordPaymentWithDiscount($client, [
            'amount' => 10000,
            'discount' => 2000,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        // يجب أن ينتج: سند خصم (2000) + سند نقدي للفاتورة (8000) + سند دفعة مقدمة فائض (2000)
        $this->assertCount(3, $receipts);

        $discountReceipt = $receipts->firstWhere('payment_method', 'discount');
        $this->assertNotNull($discountReceipt);
        $this->assertEquals(2000.0, (float) $discountReceipt->amount);
        $this->assertEquals($invoice->id, $discountReceipt->invoice_id);

        $cashInvoiceReceipt = $receipts->first(fn ($r) => $r->payment_method === 'cash' && $r->invoice_id === $invoice->id);
        $this->assertNotNull($cashInvoiceReceipt);
        $this->assertEquals(8000.0, (float) $cashInvoiceReceipt->amount);

        $advanceReceipt = $receipts->first(fn ($r) => $r->payment_method === 'cash' && $r->invoice_id === null);
        $this->assertNotNull($advanceReceipt);
        $this->assertEquals(2000.0, (float) $advanceReceipt->amount);
        $this->assertEquals(2000.0, (float) $advanceReceipt->unallocated_amount);

        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(0.0, $invoice->fresh()->remaining);
        $this->assertEquals(2000.0, (float) $client->fresh()->total_advance_balance);
    }

    public function test_buy_additional_designs_creates_invoice_and_increments_balance(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        $this->assertEquals(0, $client->additional_designs_balance);

        $invoice = $this->service->buyAdditionalDesigns($client, [
            'design_quantity' => 3,
            'per_design_price' => 10000,
            'pay_from_wallet' => false,
        ]);

        $client->refresh();
        $this->assertEquals(3, $client->additional_designs_balance);

        $this->assertNotNull($invoice);
        $this->assertEquals(30000.0, (float) $invoice->total_amount);
        $this->assertEquals('posted', $invoice->status);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('شراء تصاميم إضافية (عدد 3 × 10,000.00)', $invoice->items->first()->description);
    }

    public function test_buy_additional_designs_pays_from_wallet_when_requested(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $client->update(['wallet_balance' => 40000]);

        $invoice = $this->service->buyAdditionalDesigns($client, [
            'design_quantity' => 3,
            'per_design_price' => 10000,
            'pay_from_wallet' => true,
        ]);

        $client->refresh();
        $this->assertEquals(10000.0, (float) $client->wallet_balance);
        $this->assertEquals(3, $client->additional_designs_balance);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0.0, $invoice->remaining);
    }

    public function test_design_task_approval_consumes_prepaid_balance(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => true,
            'additional_design_price' => 10000,
            'additional_designs_count' => 0,
        ]);

        // Buy 2 prepaid designs
        $prepaidInvoice = $this->service->buyAdditionalDesigns($client, [
            'design_quantity' => 2,
            'per_design_price' => 10000,
            'pay_from_wallet' => false,
        ]);

        $client->refresh();
        $this->assertEquals(2, $client->additional_designs_balance);
        $this->assertEquals(0, $contract->additional_designs_count);

        $designer = \App\Models\Designer::factory()->create();
        $assigner = \App\Models\User::factory()->create();

        // Create and approve a design task
        $task = \App\Models\DesignTask::create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'client_id' => $client->id,
            'contract_id' => null,
            'is_template_update' => false,
            'is_subscribed_client' => true,
            'description' => 'مهمة اختبار مسبق الدفع',
            'status' => \App\Filament\Enums\DesignTaskStatus::Pending,
        ]);

        $task->update(['status' => \App\Filament\Enums\DesignTaskStatus::Approved]);

        $client->refresh();
        $contract->refresh();
        $task->refresh();

        // prepaid balance decremented
        $this->assertEquals(1, $client->additional_designs_balance);
        // post-paid contract count remains 0
        $this->assertEquals(0, $contract->additional_designs_count);
        // linked to prepaid invoice
        $this->assertEquals($prepaidInvoice->id, $task->additional_designs_invoice_id);

        // Test refunding when deleted
        $task->delete();
        $client->refresh();
        $this->assertEquals(2, $client->additional_designs_balance);
    }
}
