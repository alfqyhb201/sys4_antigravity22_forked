<?php

namespace Tests\Feature;

use App\Livewire\ClientImporterComponent;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClientImportService;
use App\Services\ImportResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'clientimportuser',
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_client']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_client']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_client']);

        $this->user->givePermissionTo([
            'create_client',
            'view_client',
            'update_client',
        ]);

        $this->actingAs($this->user);

        $this->category = Category::create(['name' => 'تصنيف اختبار']);
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
        ]);
    }

    /**
     * تنفيذ استيراد من CSV مباشرة عبر الخدمة.
     */
    private function runCsvImport(string $csvContent, ?int $authUserId = null): ImportResult
    {
        $path = tempnam(sys_get_temp_dir(), 'cli_import').'.csv';
        file_put_contents($path, $csvContent);

        // نستخرج العناوين من أول سطر لبناء columnMap (حقل = عنوان)
        $headers = str_getcsv(explode("\n", $csvContent)[0]);
        $columnMap = array_combine($headers, $headers);

        return app(ClientImportService::class)->import($path, $columnMap, $authUserId ?? $this->user->id);
    }

    /** @test */
    public function test_can_import_client_with_contract_and_first_invoice(): void
    {
        $result = $this->runCsvImport(
            "company,category,contact_number,contract_total_amount,contract_payment_type,contract_billing_cycle,contract_start_date\n"
                .'شركة أ,تصنيف اختبار,777123456,5000,advance,monthly,2026-01-01'
        );

        $this->assertSame(1, $result->successful);
        $this->assertSame(0, $result->failed);

        $this->assertDatabaseHas('clients', [
            'company' => 'شركة أ',
            'contact_number' => '777123456',
            'category_id' => $this->category->id,
        ]);

        $client = Client::where('company', 'شركة أ')->first();
        $this->assertNotNull($client);

        // تتبع المستخدم
        $this->assertEquals($this->user->id, $client->added_by_user);
        $this->assertEquals($this->user->id, $client->updated_by_user);

        // الاشتراك
        $contract = $client->contracts()->first();
        $this->assertNotNull($contract);
        $this->assertSame('active', $contract->status);
        $this->assertSame('advance', $contract->payment_type);
        $this->assertSame('monthly', $contract->billing_cycle);
        $this->assertSame(5000.0, (float) $contract->total_amount);
        $this->assertSame(1, $contract->weekly_designs_count);
        $this->assertSame(4, $contract->monthly_designs_count); // حساب تلقائي من الأسبوعية
        $this->assertEquals($this->currency->id, $contract->currency_id); // العملة الافتراضية
        $this->assertEquals('2026-02-01', $contract->end_date->format('Y-m-d')); // حساب تلقائي
        $this->assertEquals($this->user->id, $contract->created_by_user);

        // الفاتورة الأولى
        $invoice = $this->firstInvoice($contract);
        $this->assertNotNull($invoice);
        $this->assertSame('posted', $invoice->status);
        $this->assertSame(5000.0, (float) $invoice->total_amount);

        $this->assertCount(1, $invoice->items);
        $this->assertEquals('خدمات التصميم - 4 تصاميم شهرياً', $invoice->items->first()->description);
    }

    /** @test */
    public function test_skips_duplicate_client_with_warning(): void
    {
        Client::create(['company' => 'شركة مكررة', 'category_id' => $this->category->id]);

        $result = $this->runCsvImport(
            "company,category,contract_total_amount\n"
                .'شركة مكررة,تصنيف اختبار,1000'
        );

        $this->assertSame(0, $result->successful);
        $this->assertSame(0, $result->failed);
        $this->assertNotEmpty($result->warnings);
        $this->assertStringContainsString('موجود مسبقاً', array_values($result->warnings)[0]);

        $this->assertDatabaseCount('clients', 1);
    }

    /** @test */
    public function test_fails_row_when_category_does_not_exist(): void
    {
        $result = $this->runCsvImport(
            "company,category,contract_total_amount\n"
                .'شركة جديدة,تصنيف غير موجود,1000'
        );

        $this->assertSame(1, $result->failed);
        $this->assertSame(0, $result->successful);
        $this->assertStringContainsString('غير موجود', array_values($result->errors)[0]);
        $this->assertDatabaseMissing('clients', ['company' => 'شركة جديدة']);
    }

    /** @test */
    public function test_fails_row_when_contact_number_is_invalid(): void
    {
        $result = $this->runCsvImport(
            "company,category,contact_number,contract_total_amount\n"
                .'شركة هاتف,تصنيف اختبار,12345,1000'
        );

        $this->assertSame(1, $result->failed);
        $this->assertStringContainsString('9 أرقام', array_values($result->errors)[0]);
        $this->assertDatabaseMissing('clients', ['company' => 'شركة هاتف']);
    }

    /** @test */
    public function test_fails_row_when_contract_amount_is_missing(): void
    {
        $result = $this->runCsvImport(
            "company,category,contract_total_amount\n"
                .'شركة بلا اشتراك,تصنيف اختبار,'
        );

        $this->assertSame(1, $result->failed);
        $this->assertStringContainsString('الاشتراك إلزامي', array_values($result->errors)[0]);
        $this->assertDatabaseMissing('clients', ['company' => 'شركة بلا اشتراك']);
    }

    /** @test */
    public function test_grace_period_is_null_when_credit_allowed(): void
    {
        $result = $this->runCsvImport(
            "company,category,is_credit_allowed,contract_total_amount,contract_grace_period_days\n"
                .'شركة ائتمان,تصنيف اختبار,نعم,2000,10'
        );

        $this->assertSame(1, $result->successful);

        $client = Client::where('company', 'شركة ائتمان')->first();
        $this->assertTrue($client->is_credit_allowed);
        $this->assertNull($client->contracts()->first()->grace_period_days);
    }

    /** @test */
    public function test_accepts_arabic_enum_values_and_suspended_contract(): void
    {
        $result = $this->runCsvImport(
            "company,category,contract_status,contract_payment_type,contract_billing_cycle,contract_total_amount\n"
                .'شركة عربية,تصنيف اختبار,موقف,مؤخر,سنوي,8000'
        );

        $this->assertSame(1, $result->successful);

        $contract = Client::where('company', 'شركة عربية')->first()->contracts()->first();
        $this->assertSame('suspended', $contract->status);
        $this->assertSame('deferred', $contract->payment_type);
        $this->assertSame('yearly', $contract->billing_cycle);
        $this->assertSame('draft', $this->firstInvoice($contract)->status); // مؤجل → مسودة
    }

    /** @test */
    public function test_can_import_from_xlsx_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cli_import').'.xlsx';

        $writer = SimpleExcelWriter::create($path);
        $writer->addHeader(['company', 'category', 'contract_total_amount', 'contract_marketing_amount']);
        $writer->addRow(['شركة إكسل', 'تصنيف اختبار', '7000', '500']);
        $writer->close();

        $columnMap = [
            'company' => 'company',
            'category' => 'category',
            'contract_total_amount' => 'contract_total_amount',
            'contract_marketing_amount' => 'contract_marketing_amount',
        ];

        $result = app(ClientImportService::class)->import($path, $columnMap, $this->user->id);

        $this->assertSame(1, $result->successful);
        $this->assertDatabaseHas('clients', ['company' => 'شركة إكسل']);

        $client = Client::where('company', 'شركة إكسل')->first();
        $contract = $client->contracts()->first();
        $invoice = $this->firstInvoice($contract);
        $this->assertSame(7500.0, (float) $invoice->total_amount); // 7000 + 500 تسويق
        $this->assertCount(2, $invoice->items); // تصميم + تسويق
    }

    /** @test */
    public function test_livewire_component_imports_clients_with_auto_column_mapping(): void
    {
        Storage::fake('local');

        $csvContent = "اسم الشركة,التصنيف,المبلغ الإجمالي للاشتراك\n"
            .'شركة حية,تصنيف اختبار,3000';

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent, 'text/csv');

        Livewire::test(ClientImporterComponent::class)
            ->set('uploadedFile', $file)
            ->assertSet('errorMessage', null)
            ->assertSet('step', 2)
            ->assertSet('columnMap.company', 'اسم الشركة')
            ->assertSet('columnMap.category', 'التصنيف')
            ->assertSet('columnMap.contract_total_amount', 'المبلغ الإجمالي للاشتراك')
            ->call('startImport')
            ->assertSet('step', 4)
            ->assertSet('importResultData.successful', 1);

        $this->assertDatabaseHas('clients', ['company' => 'شركة حية']);
        $client = Client::where('company', 'شركة حية')->first();
        $this->assertNotNull($client->contracts()->first());
        $this->assertNotNull($this->firstInvoice($client->contracts()->first()));
    }

    /**
     * الحصول على الفاتورة الأولى لاشتراك معين.
     */
    private function firstInvoice(Contract $contract): ?Invoice
    {
        return Invoice::where('contract_id', $contract->id)->first();
    }

    /** @test */
    public function test_livewire_component_requires_mandatory_fields_mapped(): void
    {
        Storage::fake('local');

        $csvContent = "اسم الشركة,التصنيف\n"
            .'شركة ناقصة,تصنيف اختبار';

        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent, 'text/csv');

        Livewire::test(ClientImporterComponent::class)
            ->set('uploadedFile', $file)
            ->assertSet('step', 2)
            ->call('startImport')
            ->assertSet('step', 2)
            ->assertSet('errorMessage', fn ($message) => is_string($message) && str_contains($message, 'المبلغ الإجمالي للاشتراك'));
    }

    /**
     * التحقق من إمكانية تنزيل نموذج Excel للاستيراد.
     * ملاحظة: المسار عمداً خارج /admin/clients/ حتى لا يلتقطه مسار
     * Filament (admin/clients/{record}) الذي يسجَّل أولاً بلا قيد رقمي.
     */
    public function test_can_download_import_template(): void
    {
        $this->get(route('clients.import.template'))
            ->assertOk()
            ->assertDownload('clients_import_template.xlsx');
    }

    /** @test */
    public function test_rolls_back_database_changes_when_exception_occurs_during_row_import(): void
    {
        Contract::creating(function ($contract) {
            if ($contract->client && $contract->client->company === 'شركة معطوبة') {
                throw new \RuntimeException('خطأ محاكى في الداتا بيز');
            }
        });

        $result = $this->runCsvImport(
            "company,category,contract_total_amount\n"
                .'شركة معطوبة,تصنيف اختبار,5000'
        );

        $this->assertSame(1, $result->failed);
        $this->assertSame(0, $result->successful);
        $this->assertDatabaseMissing('clients', ['company' => 'شركة معطوبة']);
        $this->assertDatabaseCount('contracts', 0);
    }

    /** @test */
    public function test_rolls_back_entire_import_when_one_client_is_duplicate_and_one_is_new(): void
    {
        Client::create(['company' => 'شركة سابقة', 'category_id' => $this->category->id]);

        $result = $this->runCsvImport(
            "company,category,contract_total_amount\n"
                ."شركة جديدة جداً,تصنيف اختبار,3000\n"
                .'شركة سابقة,تصنيف اختبار,4000'
        );

        $this->assertSame(0, $result->successful);
        $this->assertDatabaseMissing('clients', ['company' => 'شركة جديدة جداً']);
        $this->assertDatabaseCount('clients', 1);
    }
}
