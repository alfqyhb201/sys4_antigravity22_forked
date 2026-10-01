<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomReceiptImport;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ReceiptImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReceiptImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Client $client;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'تصنيف أساسي']);

        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);

        collect([
            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
        ])->each(fn ($p) => Permission::create(['name' => $p]));

        $this->user->givePermissionTo(['view_any_receipt', 'create_receipt']);

        $this->client = Client::create([
            'company' => 'شركة الأمل للتجارة',
            'category_id' => $this->category->id,
        ]);
    }

    public function test_user_can_access_custom_receipt_import_page(): void
    {
        $this->actingAs($this->user)
            ->get(CustomReceiptImport::getUrl())
            ->assertSuccessful()
            ->assertSee('استيراد سندات القبض من ملف');
    }

    public function test_template_download_route_is_working(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('receipts.import.template'));

        $response->assertSuccessful();
        $response->assertHeader('content-disposition');
    }

    public function test_receipt_import_service_imports_valid_receipt(): void
    {
        Storage::fake('local');

        $csvData = implode("\n", [
            'العميل,المبلغ,طريقة الدفع,رقم المرجع,الملاحظات',
            'شركة الأمل للتجارة,15000,نقداً,REF-100,دفعة حساب',
        ]);

        $filePath = Storage::disk('local')->path('test_receipts.csv');
        file_put_contents($filePath, $csvData);

        $service = new ReceiptImportService;
        $columnMap = [
            'client' => 'العميل',
            'amount' => 'المبلغ',
            'payment_method' => 'طريقة الدفع',
            'reference_number' => 'رقم المرجع',
            'notes' => 'الملاحظات',
        ];

        $result = $service->import($filePath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(1, $result->successful);
        $this->assertEquals(0, $result->failed);

        $this->assertDatabaseHas('receipts', [
            'client_id' => $this->client->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'reference_number' => 'REF-100',
            'notes' => 'دفعة حساب',
        ]);
    }

    public function test_receipt_import_service_updates_invoice_status(): void
    {
        Storage::fake('local');

        $invoice = Invoice::create([
            'client_id' => $this->client->id,
            'total_amount' => 10000,
            'status' => 'posted',
            'issue_date' => now(),
            'due_date' => now(),
        ]);

        $csvData = implode("\n", [
            'العميل,الفاتورة,المبلغ,طريقة الدفع',
            "شركة الأمل للتجارة,{$invoice->id},10000,تحويل بنكي",
        ]);

        $filePath = Storage::disk('local')->path('test_receipts_inv.csv');
        file_put_contents($filePath, $csvData);

        $service = new ReceiptImportService;
        $columnMap = [
            'client' => 'العميل',
            'invoice' => 'الفاتورة',
            'amount' => 'المبلغ',
            'payment_method' => 'طريقة الدفع',
        ];

        $result = $service->import($filePath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->successful);
        $this->assertDatabaseHas('receipts', [
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount' => 10000,
            'payment_method' => 'transfer',
        ]);

        $this->assertEquals('paid', $invoice->refresh()->status);
    }

    public function test_receipt_import_fails_with_missing_client(): void
    {
        Storage::fake('local');

        $csvData = implode("\n", [
            'العميل,المبلغ',
            'شركة غير موجودة اطلاقا,5000',
        ]);

        $filePath = Storage::disk('local')->path('test_receipts_fail.csv');
        file_put_contents($filePath, $csvData);

        $service = new ReceiptImportService;
        $columnMap = [
            'client' => 'العميل',
            'amount' => 'المبلغ',
        ];

        $result = $service->import($filePath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
        $this->assertCount(1, $result->errors);
    }

    public function test_receipt_importer_livewire_component_flow(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\ReceiptImporterComponent::class)
            ->assertSet('step', 1)
            ->assertSee('استيراد سندات القبض المتقدم');
    }
}
