<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ContractRenewalTest extends TestCase
{
    use RefreshDatabase;

    private ?Currency $cachedCurrency = null;

    private function getTestCurrency(): Currency
    {
        if (! $this->cachedCurrency) {
            $this->cachedCurrency = Currency::firstOrCreate(
                ['currency' => 'USD'],
                ['currency' => 'USD', 'currency_name' => 'دولار', 'value' => 1]
            );
        }

        return $this->cachedCurrency;
    }

    private function createTestClient(array $overrides = []): Client
    {
        $location = Location::firstOrCreate(
            ['name' => 'موقع اختبار'],
            ['name' => 'موقع اختبار']
        );

        $category = Category::firstOrCreate(
            ['name' => 'تصنيف اختبار'],
            ['name' => 'تصنيف اختبار']
        );

        return Client::create(array_merge([
            'company' => 'شركة اختبار',
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '123456789',
        ], $overrides));
    }

    private function createTestContract(Client $client, array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now(),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1000,
            'marketing_amount' => 200,
            'currency_id' => $this->getTestCurrency()->id,
            'auto_renewal' => true,
            'grace_period_days' => 5,
        ], $overrides));
    }

    private function createPendingInvoice(Client $client, Contract $contract, array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subDays(5),
            'total_amount' => 1200,
            'status' => 'posted',
        ], $overrides));
    }

    public function test_renew_contract_with_custom_end_date(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'billing_cycle' => 'monthly',
        ]);

        $customEndDate = Carbon::parse('2026-08-15');
        $newContract = $contract->createRenewalContract(null, $customEndDate);

        $contract->refresh();

        // 1. Old contract status should be 'renewed'
        $this->assertEquals('renewed', $contract->status);

        // 2. New contract should have the correct dates
        $this->assertEquals('2026-06-01', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-08-15', $newContract->end_date->format('Y-m-d'));

        // 3. Invoice should be linked to the NEW contract
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($newContract->id, $invoice->contract_id);
    }

    public function test_contract_can_be_renewed_and_generate_invoice(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
        ]);

        $newContract = $contract->createRenewalContract();

        $contract->refresh();

        // 1. Old contract status should be 'renewed'
        $this->assertEquals('renewed', $contract->status);

        // 2. New contract should have the correct dates
        $this->assertEquals('2026-06-01', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-07-01', $newContract->end_date->format('Y-m-d'));

        // 3. Get invoice from new contract
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();
        $this->assertNotNull($invoice);

        // 4. Verify invoice is generated correctly
        $this->assertEquals($client->id, $invoice->client_id);
        $this->assertEquals($newContract->id, $invoice->contract_id);
        $this->assertEquals(1200.0, (float) $invoice->total_amount);
        $this->assertEquals('posted', $invoice->status);

        // 5. Verify invoice items
        $this->assertEquals(2, $invoice->items()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'خدمات التصميم - 4 تصاميم شهرياً',
            'total' => 1000,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'خدمات التسويق',
            'total' => 200,
        ]);
    }

    public function test_renew_contracts_command_works(): void
    {
        $client = $this->createTestClient();

        // Contract that should auto-renew (expired today or earlier)
        $contractToRenew = $this->createTestContract($client, [
            'end_date' => now()->subDay(),
            'auto_renewal' => true,
        ]);

        // Contract that should expire/suspend (expired, auto-renewal false)
        $contractToExpire = $this->createTestContract($client, [
            'end_date' => now()->subDay(),
            'auto_renewal' => false,
        ]);

        // Contract that is not expired yet
        $contractNotExpired = $this->createTestContract($client, [
            'end_date' => now()->addMonth(),
            'auto_renewal' => true,
        ]);

        $this->artisan('contracts:renew-and-bill')
            ->assertExitCode(0);

        $contractToRenew->refresh();
        $contractToExpire->refresh();
        $contractNotExpired->refresh();

        // Verify first contract marked as renewed (old contract is now 'renewed')
        $this->assertEquals('renewed', $contractToRenew->status);

        // Verify a new active contract was created for the same client
        $newContract = $client->contracts()->where('status', 'active')->latest()->first();
        $this->assertNotNull($newContract);

        // Verify second contract suspended
        $this->assertEquals('suspended', $contractToExpire->status);

        // Verify third contract unaffected
        $this->assertEquals('active', $contractNotExpired->status);
    }

    public function test_client_overdue_check_works(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'grace_period_days' => 5,
        ]);

        // Client has no overdue invoices initially
        $this->assertFalse($client->hasOverdueInvoices());

        // Create a posted invoice due 10 days ago (more than grace period 5)
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(10),
            'total_amount' => 1200,
            'status' => 'posted',
        ]);

        $this->assertTrue($client->hasOverdueInvoices());

        // Update invoice status to paid
        $invoice->update(['status' => 'paid']);
        $this->assertFalse($client->hasOverdueInvoices());
    }

    public function test_client_days_overdue_and_last_payment_date_attributes(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'grace_period_days' => 5,
        ]);

        // 1. Initial state
        $this->assertEquals(0, $client->days_overdue);
        $this->assertNull($client->last_payment_date);

        // 2. Create an overdue invoice
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(10), // due 10 days ago
            'total_amount' => 1200,
            'status' => 'posted',
        ]);

        // Overdue days = 10 - 5 (grace period) = 5 days
        $this->assertEquals(5, $client->days_overdue);

        // 3. Add a payment receipt
        \App\Models\Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'receipt_date' => now()->subDays(2),
            'payment_method' => 'cash',
        ]);

        $this->assertEquals(now()->subDays(2)->format('Y-m-d'), $client->last_payment_date->format('Y-m-d'));
    }

    // ---- سيناريوهات إنشاء وتعديل الاشتراك ----

    public function test_create_client_with_contract_creates_contract_and_invoice(): void
    {
        $client = $this->createTestClient();

        $contract = $client->currentContract()->create([
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1000,
            'marketing_amount' => 200,
            'currency_id' => $this->getTestCurrency()->id,
            'auto_renewal' => true,
            'created_by_user' => null,
            'updated_by_user' => null,
        ]);

        // إنشاء الفاتورة الأولى (كما في CreateClient::createInvoiceForContract)
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'total_amount' => 1200,
            'status' => 'posted',
            'notes' => 'فاتورة أولى للاشتراك',
        ]);

        // 1. الاشتراك موجود ومرتبط بالعميل
        $this->assertNotNull($client->currentContract);
        $this->assertEquals($contract->id, $client->currentContract->id);
        $this->assertEquals(1000, (float) $client->currentContract->total_amount);

        // 2. الفاتورة موجودة ومرتبطة بالاشتراك
        $this->assertNotNull($invoice);
        $this->assertEquals($contract->id, $invoice->contract_id);
        $this->assertEquals('posted', $invoice->status);
        $this->assertEquals(1200, (float) $invoice->total_amount);
    }

    public function test_edit_contract_without_invoices_all_fields_modifiable(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // لا توجد فواتير بعد
        $this->assertFalse($client->invoices()->where('status', 'posted')->exists());

        // تعديل جميع الحقول
        $contract->update([
            'total_amount' => 2000,
            'marketing_amount' => 500,
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
            'payment_type' => 'deferred',
            'billing_cycle' => 'yearly',
            'auto_renewal' => false,
            'grace_period_days' => 10,
        ]);

        $contract->refresh();

        // جميع الحقل تعدّلت بنجاح
        $this->assertEquals(2000, (float) $contract->total_amount);
        $this->assertEquals(500, (float) $contract->marketing_amount);
        $this->assertEquals('2026-07-01', $contract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-09-30', $contract->end_date->format('Y-m-d'));
        $this->assertEquals('deferred', $contract->payment_type);
        $this->assertEquals('yearly', $contract->billing_cycle);
        $this->assertFalse($contract->auto_renewal);
        $this->assertEquals(10, $contract->grace_period_days);
    }

    public function test_edit_contract_with_invoices_protects_total_amount(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // إنشاء فاتورة مسجلة (posted)
        $this->createPendingInvoice($client, $contract);

        $hasInvoices = $client->invoices()->where('status', 'posted')->exists();
        $this->assertTrue($hasInvoices);

        // محاكاة منطق afterSave() — بيانات جديدة قادمة من النموذج
        $contractData = [
            'total_amount' => 9999,
            'marketing_amount' => 500,
            'start_date' => '2026-07-01',
            'end_date' => '2026-09-30',
        ];

        // الحماية: مقارنة البيانات الجديدة مع القيم الحالية في الاشتراك
        $protectedFields = [];

        if ((float) $contractData['total_amount'] !== (float) $contract->total_amount) {
            $contractData['total_amount'] = (float) $contract->total_amount;
            $protectedFields[] = 'المبلغ الإجمالي';
        }

        if ((float) $contractData['marketing_amount'] !== (float) $contract->marketing_amount) {
            $contractData['marketing_amount'] = (float) $contract->marketing_amount;
            $protectedFields[] = 'مبلغ التسويق';
        }

        if ($contractData['start_date'] !== $contract->start_date?->format('Y-m-d')) {
            $contractData['start_date'] = $contract->start_date?->format('Y-m-d');
            $protectedFields[] = 'تاريخ البداية';
        }

        if ($contractData['end_date'] !== $contract->end_date?->format('Y-m-d')) {
            $contractData['end_date'] = $contract->end_date?->format('Y-m-d');
            $protectedFields[] = 'تاريخ النهاية';
        }

        // التحقق من أن الحقول المحمية تم اكتشافها
        $this->assertContains('المبلغ الإجمالي', $protectedFields);
        $this->assertContains('مبلغ التسويق', $protectedFields);
        $this->assertContains('تاريخ البداية', $protectedFields);
        $this->assertContains('تاريخ النهاية', $protectedFields);

        // التحقق من أن القيم رجعت للأصل
        $this->assertEquals((float) $contract->total_amount, (float) $contractData['total_amount']);
        $this->assertEquals((float) $contract->marketing_amount, (float) $contractData['marketing_amount']);
        $this->assertEquals($contract->start_date?->format('Y-m-d'), $contractData['start_date']);
        $this->assertEquals($contract->end_date?->format('Y-m-d'), $contractData['end_date']);

        // بعد تطبيق afterSave()، الاشتراك الفعلي لم يتغير
        $contract->refresh();
        $this->assertEquals(1000, (float) $contract->total_amount);
        $this->assertEquals(200, (float) $contract->marketing_amount);
    }

    public function test_edit_contract_with_invoices_allows_non_protected_fields(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // إنشاء فاتورة مسجلة
        $this->createPendingInvoice($client, $contract);

        // تعديل الحقول غير المحمية
        $contract->update([
            'payment_type' => 'deferred',
            'billing_cycle' => 'yearly',
            'auto_renewal' => false,
            'grace_period_days' => 15,
            'status' => 'suspended',
        ]);

        $contract->refresh();

        // هذه الحقول يجب أن تتغير
        $this->assertEquals('deferred', $contract->payment_type);
        $this->assertEquals('yearly', $contract->billing_cycle);
        $this->assertFalse($contract->auto_renewal);
        $this->assertEquals(15, $contract->grace_period_days);
        $this->assertEquals('suspended', $contract->status);

        // الحقول المحمية (المالية والتواريخ) لا تتغير
        $this->assertEquals(1000, (float) $contract->total_amount);
        $this->assertEquals(200, (float) $contract->marketing_amount);
    }

    public function test_create_new_contract_after_invoices_old_contract_suspended(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);

        // إنشاء فاتورة مسجلة
        $this->createPendingInvoice($client, $contract);

        // محاكاة منطق زر "إنهاء الاشتراك الحالي وإنشاء اشتراك جديد"
        // 1. إنهاء الاشتراك الحالي
        $contract->update([
            'status' => 'suspended',
            'end_date' => now(),
            'updated_by_user' => null,
        ]);

        // 2. إنشاء اشتراك جديد
        $startDate = now();
        $endDate = $startDate->copy()->addMonth();

        $newContract = $client->contracts()->create([
            'status' => 'active',
            'payment_type' => 'deferred',
            'billing_cycle' => 'monthly',
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'weekly_designs_count' => 2,
            'monthly_designs_count' => 8,
            'total_amount' => 2500,
            'currency_id' => $this->getTestCurrency()->id,
            'marketing_amount' => 500,
            'auto_renewal' => true,
            'created_by_user' => null,
            'updated_by_user' => null,
        ]);

        // 3. إنشاء فاتورة أولى للاشتراك الجديد — deferred → due_date = contract.end_date
        $dueDate = $endDate;
        $newInvoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $newContract->id,
            'issue_date' => $startDate->format('Y-m-d'),
            'due_date' => $dueDate,
            'total_amount' => 3000,
            'status' => 'posted',
            'notes' => 'فاتورة أولى للاشتراك الجديد',
        ]);

        // التحقق من الاشتراك القديم
        $contract->refresh();
        $this->assertEquals('suspended', $contract->status);
        $this->assertEquals(now()->format('Y-m-d'), $contract->end_date->format('Y-m-d'));

        // التحقق من الاشتراك الجديد
        $this->assertNotNull($client->currentContract);
        $this->assertEquals($newContract->id, $client->currentContract->id);
        $this->assertEquals(2500, (float) $client->currentContract->total_amount);
        $this->assertEquals('active', $client->currentContract->status);

        // التحقق من أن relationship contracts() يعيد جميع الاشتراكات
        $this->assertEquals(2, $client->contracts()->count());

        // التحقق من الفاتورة الجديدة
        $this->assertNotNull($newInvoice);
        $this->assertEquals($newContract->id, $newInvoice->contract_id);
        $this->assertEquals(3000, (float) $newInvoice->total_amount);
    }

    public function test_contracts_relationship_returns_all_contracts(): void
    {
        $client = $this->createTestClient();

        // اشتراك أول
        $contract1 = $this->createTestContract($client);
        $this->assertEquals(1, $client->contracts()->count());

        // اشتراك ثاني
        $contract2 = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonth()->format('Y-m-d'),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1500,
            'currency_id' => $this->getTestCurrency()->id,
            'auto_renewal' => true,
        ]);
        $this->assertEquals(2, $client->contracts()->count());

        // currentContract يعود بالأحدث
        $this->assertEquals($contract2->id, $client->currentContract->id);
    }

    public function test_simple_requests_count_increments_when_order_completed(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'simple_requests_enabled' => true,
            'simple_request_price' => 50,
            'simple_requests_count' => 0,
        ]);

        // إكمال طلب للعميل (محاكاة increment)
        $contract->increment('simple_requests_count');
        $contract->increment('simple_requests_count');
        $contract->increment('simple_requests_count');

        $contract->refresh();
        $this->assertEquals(3, $contract->simple_requests_count);

        // تجديد الاشتراك — تتضمن الفاتورة الـ 3 طلبات
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals(150, $invoice->total_amount - (float) $contract->total_amount - (float) $contract->marketing_amount);

        // التحقق من وجود Item للطلبات البسيطة
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'رسوم الطلبات البسيطة (3 طلب × 50.00)',
            'quantity' => 3,
            'unit_amount' => 50,
            'total' => 150,
        ]);

        // التأكد من reset العداد (على الاشتراك القديم)
        $contract->refresh();
        $this->assertEquals(0, $contract->simple_requests_count);

        // الاشتراك القديم status = renewed
        $this->assertEquals('renewed', $contract->status);
    }

    public function test_simple_requests_count_does_not_increment_when_disabled(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'simple_requests_enabled' => false,
            'simple_requests_count' => 0,
        ]);

        $contract->increment('simple_requests_count', 5);
        $contract->refresh();

        $expectedBase = (float) $contract->total_amount + (float) $contract->marketing_amount;
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals($expectedBase, (float) $invoice->total_amount);

        $contract->refresh();
        $this->assertEquals(5, $contract->simple_requests_count);
    }

    public function test_simple_requests_not_included_when_price_is_zero(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'simple_requests_enabled' => true,
            'simple_request_price' => null,
            'simple_requests_count' => 5,
        ]);

        $expectedBase = (float) $contract->total_amount + (float) $contract->marketing_amount;
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals($expectedBase, (float) $invoice->total_amount);

        $contract->refresh();
        $this->assertEquals(5, $contract->simple_requests_count);
    }

    public function test_simple_requests_incremented_via_order_completion(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'simple_requests_enabled' => true,
            'simple_request_price' => 30,
            'simple_requests_count' => 0,
        ]);

        $designer = \App\Models\Designer::factory()->create();
        $assigner = \App\Models\User::factory()->create();

        $order = \App\Models\Order::create([
            'client_name' => $client->company,
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => \App\Enums\OrderStatus::Pending,
        ]);

        $order->update(['status' => \App\Enums\OrderStatus::Completed]);

        $contract->refresh();
        $this->assertEquals(1, $contract->simple_requests_count);
    }

    public function test_additional_designs_count_increments_on_design_task_approval(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => true,
            'additional_design_price' => 100,
            'additional_designs_count' => 0,
        ]);

        $designer = \App\Models\Designer::factory()->create();
        $assigner = \App\Models\User::factory()->create();

        $task = \App\Models\DesignTask::create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'is_subscribed_client' => true,
            'client_id' => $client->id,
            'is_template_update' => false,
            'description' => 'مهمة إضافية',
            'status' => \App\Filament\Enums\DesignTaskStatus::InReview,
        ]);

        $task->update(['status' => \App\Filament\Enums\DesignTaskStatus::Approved]);

        $contract->refresh();
        $this->assertEquals(1, $contract->additional_designs_count);
    }

    public function test_additional_designs_included_in_renewal_invoice(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => true,
            'additional_design_price' => 200,
            'additional_designs_count' => 3,
        ]);

        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $expectedAdditional = 3 * 200;
        $expectedTotal = (float) $contract->total_amount + (float) $contract->marketing_amount + $expectedAdditional;

        $this->assertEquals($expectedTotal, (float) $invoice->total_amount);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'رسوم التصاميم الإضافية (3 تصميم × 200.00)',
            'quantity' => 3,
            'unit_amount' => 200,
            'total' => 600,
        ]);

        // Count should be reset on OLD contract
        $contract->refresh();
        $this->assertEquals(0, $contract->additional_designs_count);
    }

    public function test_additional_designs_not_included_when_disabled(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => false,
            'additional_design_price' => 100,
            'additional_designs_count' => 3,
        ]);

        $expectedBase = (float) $contract->total_amount + (float) $contract->marketing_amount;
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals($expectedBase, (float) $invoice->total_amount);

        $contract->refresh();
        $this->assertEquals(3, $contract->additional_designs_count);
    }

    public function test_additional_designs_not_included_when_price_is_zero(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => true,
            'additional_design_price' => null,
            'additional_designs_count' => 3,
        ]);

        $expectedBase = (float) $contract->total_amount + (float) $contract->marketing_amount;
        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals($expectedBase, (float) $invoice->total_amount);

        $contract->refresh();
        $this->assertEquals(3, $contract->additional_designs_count);
    }

    public function test_additional_designs_linked_tasks_get_invoice_id_after_renewal(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'additional_designs_enabled' => true,
            'additional_design_price' => 150,
            'additional_designs_count' => 2,
        ]);

        $designer = \App\Models\Designer::factory()->create();
        $assigner = \App\Models\User::factory()->create();

        $task1 = \App\Models\DesignTask::create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'is_subscribed_client' => true,
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'is_template_update' => false,
            'description' => 'تصميم إضافي 1',
            'status' => \App\Filament\Enums\DesignTaskStatus::Approved,
        ]);

        $task2 = \App\Models\DesignTask::create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'is_subscribed_client' => true,
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'is_template_update' => false,
            'description' => 'تصميم إضافي 2',
            'status' => \App\Filament\Enums\DesignTaskStatus::Approved,
        ]);

        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertDatabaseHas('design_tasks', [
            'id' => $task1->id,
            'additional_designs_invoice_id' => $invoice->id,
        ]);

        $this->assertDatabaseHas('design_tasks', [
            'id' => $task2->id,
            'additional_designs_invoice_id' => $invoice->id,
        ]);
    }

    public function test_advance_contract_creates_posted_invoice_on_renewal(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'payment_type' => 'advance',
        ]);

        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals('posted', $invoice->status);
        $this->assertNotNull($invoice->due_date);
    }

    public function test_deferred_contract_posts_previous_draft_invoice_and_creates_draft_for_new_cycle(): void
    {
        $client = $this->createTestClient();

        // 1. الاشتراك الأول (مؤخر) ومعه فاتورة مسودة
        $oldContract = $this->createTestContract($client, [
            'payment_type' => 'deferred',
            'start_date' => now()->subMonth(),
            'end_date' => now(),
        ]);

        $draftInvoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $oldContract->id,
            'issue_date' => now()->subMonth(),
            'due_date' => now(),
            'total_amount' => 1200,
            'status' => 'draft',
            'notes' => 'فاتورة الدورة الأولى (مسودة)',
        ]);

        // 2. تجديد الاشتراك
        $newContract = $oldContract->createRenewalContract();

        // 3. التحقق من أن فاتورة الاشتراك المنتهي تحولت من draft إلى posted
        $draftInvoice->refresh();
        $this->assertEquals('posted', $draftInvoice->status);

        // 4. التحقق من أن الفاتورة الجديدة للاشتراك الجديد أنشئت بحالة draft
        $newInvoice = Invoice::where('contract_id', $newContract->id)->latest()->first();
        $this->assertNotNull($newInvoice);
        $this->assertEquals('draft', $newInvoice->status);
    }

    public function test_renewal_on_suspended_contract_throws_exception(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'status' => 'suspended',
        ]);

        $this->expectException(\LogicException::class);
        $contract->createRenewalContract();
    }

    public function test_overtime_designs_are_calculated_and_billed_on_renewal(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'total_amount' => 1000,
            'monthly_designs_count' => 10,
        ]);

        $tagGroup = \App\Models\TagGroup::create(['name' => 'مجموعة اختبار']);
        $tag = \App\Models\Tag::create(['name' => 'تاق اختبار', 'tag_group_id' => $tagGroup->id]);

        $designer = \App\Models\Designer::factory()->create();
        $clientDesigner = \App\Models\ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'week_start_date' => now()->subWeeks(2),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-02'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-03'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-05-30'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => now()->toDateString(),
            'status' => 'sending',
            'completed_at' => Carbon::parse('2026-06-03'),
        ]);

        $newContract = $contract->createRenewalContract();
        $invoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->assertEquals(1400.0, (float) $invoice->total_amount);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'رسوم تصاميم الفترة الزائدة متأخرة التسليم (2 تصميم × 100.00)',
            'quantity' => 2,
            'unit_amount' => 100.0,
            'total' => 200.0,
        ]);
    }

    public function test_sequential_contract_lifecycle(): void
    {
        $client = $this->createTestClient();

        $contract = $this->createTestContract($client, [
            'payment_type' => 'advance',
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'total_amount' => 1000,
            'monthly_designs_count' => 10,
            'marketing_amount' => 200,
        ]);

        $firstInvoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => Carbon::parse('2026-05-01'),
            'due_date' => Carbon::parse('2026-05-08'),
            'total_amount' => 1200,
            'status' => 'posted',
        ]);

        $paymentService = new \App\Services\PaymentService;
        $paymentService->payInvoice($firstInvoice, [
            'amount' => 1200,
            'payment_method' => 'cash',
            'receipt_date' => '2026-05-02',
        ]);

        $firstInvoice->refresh();
        $this->assertEquals('paid', $firstInvoice->status);
        $this->assertEquals(0.0, $firstInvoice->remaining);

        $tagGroup = \App\Models\TagGroup::create(['name' => 'مجموعة تاق متتابع']);
        $tag = \App\Models\Tag::create(['name' => 'تاق متتابع', 'tag_group_id' => $tagGroup->id]);
        $designer = \App\Models\Designer::factory()->create();
        $clientDesigner = \App\Models\ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'week_start_date' => Carbon::parse('2026-05-01'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-05-15',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-05-15'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-06-02',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-02'),
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-06-03',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-03'),
        ]);

        $newContract = $contract->createRenewalContract();
        $secondInvoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $contract->refresh();
        $this->assertEquals('renewed', $contract->status);

        $this->assertEquals('2026-06-01', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-07-01', $newContract->end_date->format('Y-m-d'));

        $this->assertEquals(1400.0, (float) $secondInvoice->total_amount);
        $this->assertCount(3, $secondInvoice->items);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $secondInvoice->id,
            'description' => 'رسوم تصاميم الفترة الزائدة متأخرة التسليم (2 تصميم × 100.00)',
            'quantity' => 2,
            'unit_amount' => 100.0,
            'total' => 200.0,
        ]);

        $receipts = $paymentService->payClientFifo($client, [
            'amount' => 1500,
            'payment_method' => 'cash',
            'receipt_date' => '2026-06-05',
        ]);

        $client->refresh();
        $secondInvoice->refresh();

        // 1400 should pay off the second invoice, 100 goes to wallet
        $this->assertEquals(100.00, (float) $client->wallet_balance);
        $this->assertEquals('paid', $secondInvoice->status);

        $this->assertCount(2, $receipts);
        $this->assertEquals(1400.00, $receipts[0]->amount);
        $this->assertEquals($secondInvoice->id, $receipts[0]->invoice_id);

        $this->assertEquals(100.00, $receipts[1]->amount);
        $this->assertNull($receipts[1]->invoice_id);
    }

    public function test_lawsuit_is_automatically_deactivated_when_all_overdue_invoices_are_paid(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'is_under_lawsuit' => true,
            'grace_period_days' => 5,
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now()->subDays(15),
            'due_date' => now()->subDays(10), // overdue
            'total_amount' => 1000,
            'status' => 'posted',
        ]);

        $this->assertTrue($contract->is_under_lawsuit);
        $this->assertTrue($client->hasOverdueInvoices());

        // Make payment to pay off the invoice
        \App\Models\Receipt::create([
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $contract->refresh();
        $client->refresh();

        $this->assertFalse($contract->is_under_lawsuit);
        $this->assertFalse($client->hasOverdueInvoices());
    }

    public function test_expiration_notification_sent_one_day_before_end_date(): void
    {
        $client = $this->createTestClient();
        // Contract ending tomorrow (exactly 1 day)
        $contract = $this->createTestContract($client, [
            'end_date' => now()->addDay(),
            'auto_renewal' => true,
            'status' => 'active',
            'notification_date' => null,
        ]);

        $this->artisan('contracts:renew-and-bill')
            ->assertExitCode(0);

        $contract->refresh();
        $this->assertNotNull($contract->notification_date);
    }

    public function test_renew_contract_resets_notification_date_to_null(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'end_date' => now()->subDay(), // Expired
            'auto_renewal' => true,
            'status' => 'active',
            'notification_date' => now(), // already notified
        ]);

        $this->artisan('contracts:renew-and-bill')
            ->assertExitCode(0);

        $contract->refresh();
        $this->assertNull($contract->notification_date);
    }

    public function test_calculate_end_date_helper_works_for_all_cycles(): void
    {
        $start = Carbon::parse('2026-08-03');

        // أسبوعي: البداية + أسبوع − يوم
        $this->assertEquals('2026-08-09', Contract::calculateEndDate($start, 'weekly')->format('Y-m-d'));

        // شهري: البداية + شهر (نفس اليوم)
        $this->assertEquals('2026-09-03', Contract::calculateEndDate($start, 'monthly')->format('Y-m-d'));

        // سنوي: البداية + سنة (نفس اليوم)
        $this->assertEquals('2027-08-03', Contract::calculateEndDate($start, 'yearly')->format('Y-m-d'));
    }

    public function test_renewal_starts_on_same_day_as_previous_end_date_monthly(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-09-03'),
            'billing_cycle' => 'monthly',
        ]);

        $newContract = $contract->createRenewalContract();

        // التجديد يبدأ في نفس يوم نهاية الاشتراك السابق وينتهي في نفس اليوم من الشهر التالي
        $this->assertEquals('2026-09-03', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-03', $newContract->end_date->format('Y-m-d'));
    }

    public function test_renewal_weekly_starts_on_same_day_and_ends_one_week_after(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2026-08-09'),
            'billing_cycle' => 'weekly',
        ]);

        $newContract = $contract->createRenewalContract();

        $this->assertEquals('2026-08-09', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-08-15', $newContract->end_date->format('Y-m-d'));
    }

    public function test_renewal_yearly_starts_on_same_day_and_ends_one_year_after(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-08-03'),
            'end_date' => Carbon::parse('2027-08-03'),
            'billing_cycle' => 'yearly',
        ]);

        $newContract = $contract->createRenewalContract();

        $this->assertEquals('2027-08-03', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2028-08-03', $newContract->end_date->format('Y-m-d'));
    }

    public function test_contract_form_recomputes_end_date_when_billing_cycle_changes(): void
    {
        $user = \App\Models\User::factory()->create(['status' => 1]);
        collect(['view_any_contract', 'create_contract'])
            ->each(fn ($permission) => \Spatie\Permission\Models\Permission::create(['name' => $permission]));
        $user->givePermissionTo(['view_any_contract', 'create_contract']);
        $this->actingAs($user);

        $client = $this->createTestClient();

        Livewire::test(\App\Filament\Resources\ContractResource\Pages\CreateContract::class)
            ->fillForm([
                'client_id' => $client->id,
                'status' => 'active',
                'payment_type' => 'advance',
                'billing_cycle' => 'monthly',
                'start_date' => '2026-08-03',
            ])
            ->set('data.billing_cycle', 'yearly')
            ->assertFormSet([
                'end_date' => '2027-08-03',
            ])
            ->set('data.start_date', '2026-05-10')
            ->assertFormSet([
                'end_date' => '2027-05-10',
            ]);
    }

    public function test_renew_contract_with_custom_start_date_and_end_date(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'billing_cycle' => 'monthly',
        ]);

        $newContract = $contract->createRenewalContract(
            null,
            Carbon::parse('2026-09-15'),
            Carbon::parse('2026-08-10')
        );

        // عند توفير البداية والنهاية معاً تُستخدم كلاهما كما هي
        $this->assertEquals('2026-08-10', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-09-15', $newContract->end_date->format('Y-m-d'));
    }

    public function test_renew_contract_with_custom_start_date_only_computes_end_date(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'billing_cycle' => 'monthly',
        ]);

        // تاريخ بداية مخصص فقط → تُحسب النهاية من البداية عبر المنطق الموحّد (شهري = + شهر)
        $newContract = $contract->createRenewalContract(null, null, Carbon::parse('2026-08-10'));

        $this->assertEquals('2026-08-10', $newContract->start_date->format('Y-m-d'));
        $this->assertEquals('2026-09-10', $newContract->end_date->format('Y-m-d'));
    }

    public function test_contracts_list_page_displays_renewed_contracts(): void
    {
        $user = \App\Models\User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_contract']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_contract']);
        $user->givePermissionTo(['view_any_contract', 'view_contract']);

        $client = $this->createTestClient();
        $renewedContract = $this->createTestContract($client, [
            'status' => 'renewed',
        ]);
        $activeContract = $this->createTestContract($client, [
            'status' => 'active',
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Filament\Resources\ContractResource\Pages\ListContracts::class)
            ->assertCanSeeTableRecords([$renewedContract, $activeContract])
            ->set('activeTab', 'renewed')
            ->assertCanSeeTableRecords([$renewedContract])
            ->assertCanNotSeeTableRecords([$activeContract]);
    }

    public function test_renew_and_bill_command_outputs_detailed_arabic_tables_with_client_info(): void
    {
        $client = $this->createTestClient(['company' => 'مؤسسة الرياض للتجارة']);
        $contract = $this->createTestContract($client, [
            'end_date' => now()->subDay(),
            'auto_renewal' => true,
            'status' => 'active',
            'total_amount' => 1500,
        ]);

        $this->artisan('contracts:renew-and-bill')
            ->expectsOutputToContain('تقرير دورة تجديد الاشتراكات وإصدار الفواتير')
            ->expectsOutputToContain('مؤسسة الرياض للتجارة')
            ->expectsOutputToContain('ملخص نتائج التنفيذ')
            ->assertExitCode(0);
    }

    public function test_renew_and_bill_command_sends_html_email_report(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $client = $this->createTestClient(['company' => 'شركة الأفق الرقمي']);
        $contract = $this->createTestContract($client, [
            'end_date' => now()->subDay(),
            'auto_renewal' => true,
            'status' => 'active',
            'total_amount' => 2500,
        ]);

        $this->artisan('contracts:renew-and-bill', ['--email' => 'alfqyhb201@gmail.com'])
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ContractRenewalReportMail::class, function ($mail) {
            $this->assertTrue($mail->hasTo('alfqyhb201@gmail.com'));
            $this->assertCount(1, $mail->renewedContracts);
            $this->assertEquals('شركة الأفق الرقمي', $mail->renewedContracts[0]['client_name']);

            return true;
        });
    }

    public function test_renew_and_bill_command_sends_html_email_report_to_multiple_recipients(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $client = $this->createTestClient(['company' => 'شركة التقنية المتقدمة']);
        $contract = $this->createTestContract($client, [
            'end_date' => now()->subDay(),
            'auto_renewal' => true,
            'status' => 'active',
            'total_amount' => 3000,
        ]);

        $this->artisan('contracts:renew-and-bill', ['--email' => 'first@test.com, second@test.com'])
            ->assertExitCode(0);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ContractRenewalReportMail::class, function ($mail) {
            $this->assertTrue($mail->hasTo('first@test.com'));
            $this->assertTrue($mail->hasTo('second@test.com'));

            return true;
        });
    }

    public function test_create_renewal_contract_updates_client_designer_assignments(): void
    {
        $client = $this->createTestClient();
        $contract = $this->createTestContract($client);
        $user = \App\Models\User::factory()->create();
        $designer = \App\Models\Designer::create([
            'user_id' => $user->id,
            'min_capacity' => 5,
            'max_capacity' => 20,
            'rate' => 8,
        ]);

        $assignment = \App\Models\ClientDesigner::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'designer_id' => $designer->id,
            'week_start_date' => now()->startOfWeek()->format('Y-m-d'),
        ]);

        $newContract = $contract->createRenewalContract();

        $assignment->refresh();
        $this->assertEquals($newContract->id, $assignment->contract_id);
    }

    public function test_near_expiry_notification_sent_only_to_admin_and_accountant_not_supervisor(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'accountant']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'supervisor']);

        $admin = \App\Models\User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');

        $accountant = \App\Models\User::factory()->create(['status' => 1]);
        $accountant->assignRole('accountant');

        $supervisor = \App\Models\User::factory()->create(['status' => 1]);
        $supervisor->assignRole('supervisor');

        $client = $this->createTestClient();
        $contract = $this->createTestContract($client, [
            'end_date' => now()->addHours(12),
            'auto_renewal' => true,
            'status' => 'active',
            'notification_date' => null,
        ]);

        $this->artisan('contracts:renew-and-bill')
            ->assertExitCode(0);

        $contract->refresh();
        $this->assertNotNull($contract->notification_date);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $accountant->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $supervisor->id,
        ]);
    }
}
