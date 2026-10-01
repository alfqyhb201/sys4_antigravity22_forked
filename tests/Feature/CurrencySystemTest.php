<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\CurrencySetting;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $yer;

    protected Currency $usd;

    protected Currency $sar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->yer = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 530.00,
            'is_base' => true,
            'symbol' => '﷼',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $this->usd = Currency::create([
            'currency' => 'USD',
            'currency_name' => 'دولار أمريكي',
            'value' => 1.00,
            'is_base' => false,
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ]);

        $this->sar = Currency::create([
            'currency' => 'SAR',
            'currency_name' => 'ريال سعودي',
            'value' => 3.75,
            'is_base' => false,
            'symbol' => 'ر.س',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_single_base_currency_constraint(): void
    {
        $this->assertTrue($this->yer->is_base);
        $this->assertFalse($this->usd->is_base);

        $this->usd->update(['is_base' => true]);

        $this->yer->refresh();
        $this->usd->refresh();

        $this->assertFalse($this->yer->is_base);
        $this->assertTrue($this->usd->is_base);
        $this->assertEquals($this->usd->id, Currency::getBase()->id);
    }

    /** @test */
    public function test_exchange_rate_model_rates_calculation(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 530.00,
            'effective_date' => now()->toDateString(),
        ]);

        $directRate = ExchangeRate::getLatestRate($this->usd->id, $this->yer->id);
        $this->assertEquals(530.00, $directRate);

        $inverseRate = ExchangeRate::getLatestRate($this->yer->id, $this->usd->id);
        $this->assertEquals(round(1 / 530, 6), $inverseRate);
    }

    /** @test */
    public function test_currency_service_conversions(): void
    {
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 530.00,
            'effective_date' => now()->toDateString(),
        ]);

        $service = app(CurrencyService::class);

        $converted = $service->convert(100, $this->usd->id, $this->yer->id);
        $this->assertEquals(53000.00, $converted);

        $formatted = $service->formatAmount(100, $this->usd);
        $this->assertStringContainsString('100.00', $formatted);
        $this->assertStringContainsString('$', $formatted);
    }

    /** @test */
    public function test_invoice_inherits_currency_from_contract(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار',
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'postpaid',
            'billing_cycle' => 'monthly',
            'start_date' => now(),
            'end_date' => now()->addYear(),
            'weekly_designs_count' => 2,
            'monthly_designs_count' => 8,
            'total_amount' => 1000,
            'currency_id' => $this->usd->id,
        ]);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
            'total_amount' => 1000,
            'exchange_rate' => 530.00,
        ]);

        $this->assertEquals($this->usd->id, $invoice->currency_id);
        $this->assertEquals(530000.00, (float) $invoice->base_currency_amount);
    }

    /** @test */
    public function test_receipt_paid_currency_and_base_amount(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار 2',
            'client_name' => 'عميل 2',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        $receipt = Receipt::create([
            'client_id' => $client->id,
            'paid_currency_id' => $this->usd->id,
            'amount' => 500,
            'original_amount' => 500,
            'exchange_rate' => 530.00,
            'receipt_date' => now(),
            'payment_method' => 'cash',
        ]);

        $this->assertEquals($this->usd->id, $receipt->paid_currency_id);
        $this->assertEquals(265000.00, (float) $receipt->base_currency_amount);
    }

    /** @test */
    public function test_currency_setting_get_and_set(): void
    {
        CurrencySetting::set('test_key', 'test_value');
        $this->assertEquals('test_value', CurrencySetting::get('test_key'));

        $this->assertEquals('default_fallback', CurrencySetting::get('non_existent', 'default_fallback'));
    }

    /** @test */
    public function test_payment_service_cross_currency_payment(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار 3',
            'client_name' => 'عميل 3',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        // الفاتورة بالدولار: 100$
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'currency_id' => $this->usd->id,
            'total_amount' => 100,
            'exchange_rate' => 530.00,
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
            'status' => 'posted',
        ]);

        // السداد بالريال السعودي: 375 ر.س (يعادل 100$)
        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->usd->id,
            'rate' => 1 / 3.75,
            'effective_date' => now()->toDateString(),
        ]);

        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 141.333,
            'effective_date' => now()->toDateString(),
        ]);

        $service = app(\App\Services\PaymentService::class);
        $receipt = $service->payInvoice($invoice, [
            'amount' => 100,
            'original_amount' => 375,
            'paid_currency_id' => $this->sar->id,
            'payment_method' => 'cash',
            'receipt_date' => now()->toDateString(),
        ]);

        $this->assertEquals($this->sar->id, $receipt->paid_currency_id);
        $this->assertEquals(375, (float) $receipt->original_amount);
        $this->assertEquals(100, (float) $receipt->amount);
        $this->assertEquals('paid', $invoice->fresh()->status);
    }

    /** @test */
    public function test_recalculate_base_amounts_artisan_command(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار 4',
            'client_name' => 'عميل 4',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        // إنشاء فاتورة بسعر صرف قديم (500)
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'currency_id' => $this->usd->id,
            'total_amount' => 100,
            'exchange_rate' => 500.00,
            'base_currency_amount' => 50000.00,
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
        ]);

        // إضافة سعر صرف جديد لـ USD -> YER (530)
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 530.00,
            'effective_date' => now()->toDateString(),
        ]);

        // تشغيل أمر إعادة الحساب مع خيار تحديث الأسعار
        $this->artisan('currency:recalculate-base-amounts --update-rates')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertEquals(530.00, (float) $invoice->exchange_rate);
        $this->assertEquals(53000.00, (float) $invoice->base_currency_amount);
    }

    /** @test */
    public function test_currency_settings_page_forbidden_without_permission(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $this->actingAs($this->user)
            ->get('/admin/currency-settings-page')
            ->assertForbidden();
    }

    /** @test */
    public function test_currency_settings_page_accessible_with_manage_settings_permission(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage_settings']);
        $this->user->givePermissionTo('manage_settings');

        $this->actingAs($this->user)
            ->get('/admin/currency-settings-page')
            ->assertOk();
    }

    /** @test */
    public function test_recalculate_base_amounts_preserves_locked_exchange_rates_by_default(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع 5', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف 5']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار 5',
            'client_name' => 'عميل 5',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        $customLockedRate = 3.80;
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'currency_id' => $this->usd->id,
            'total_amount' => 100,
            'exchange_rate' => $customLockedRate,
            'base_currency_amount' => 380.00,
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
        ]);

        // إضافة سعر عام جديد في جدول أسعار الصرف
        ExchangeRate::create([
            'from_currency_id' => $this->usd->id,
            'to_currency_id' => $this->yer->id,
            'rate' => 530.00,
            'effective_date' => now()->toDateString(),
        ]);

        // تشغيل أمر إعادة الحساب بدون --update-rates
        $this->artisan('currency:recalculate-base-amounts')
            ->assertExitCode(0);

        $invoice->refresh();
        // التأكد من الحفاظ على سعر الصرف المجمّد للعميل
        $this->assertEquals(3.80, (float) $invoice->exchange_rate);
    }

    /** @test */
    public function test_dynamic_reporting_currency_conversion_in_financial_dashboard(): void
    {
        $location = \App\Models\Location::create(['name' => 'موقع 6', 'added_by_user' => $this->user->id]);
        $category = \App\Models\Category::create(['name' => 'تصنيف 6']);

        $client = \App\Models\Client::create([
            'company' => 'شركة اختبار 6',
            'client_name' => 'عميل 6',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        // إنشاء فاتورة بـ 375 ريال سعودي
        Invoice::create([
            'client_id' => $client->id,
            'currency_id' => $this->sar->id,
            'total_amount' => 375,
            'status' => 'posted',
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
        ]);

        // إضافة سعر صرف بين SAR و USD (1 USD = 3.75 SAR => 1 SAR = 0.266667 USD)
        ExchangeRate::create([
            'from_currency_id' => $this->sar->id,
            'to_currency_id' => $this->usd->id,
            'rate' => 1 / 3.75,
            'effective_date' => now()->toDateString(),
        ]);

        $service = app(\App\Services\FinancialDashboardService::class);

        // عند العرض بالعملة الأساسية SAR (375 SAR)
        $snapshotSar = $service->getAccountingSnapshot('all', $this->sar->id);
        $this->assertEquals(375.00, $snapshotSar['totalInvoiced']);

        // عند التحويل الديناميكي للعرض بالدولار USD ($100.00)
        $snapshotUsd = $service->getAccountingSnapshot('all', $this->usd->id);
        $this->assertEquals(100.00, $snapshotUsd['totalInvoiced']);
    }
}
