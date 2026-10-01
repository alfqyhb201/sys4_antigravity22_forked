<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomInvoiceImport;
use App\Models\Category;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceImportTest extends TestCase
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
            'view_any_invoice',
            'view_invoice',
            'create_invoice',
            'update_invoice',
            'delete_invoice',
        ])->each(fn ($p) => Permission::create(['name' => $p]));

        $this->user->givePermissionTo(['view_any_invoice', 'create_invoice']);

        $this->client = Client::create([
            'company' => 'شركة الأمل للتجارة',
            'category_id' => $this->category->id,
        ]);
    }

    public function test_user_can_access_custom_invoice_import_page(): void
    {
        $this->actingAs($this->user)
            ->get(CustomInvoiceImport::getUrl())
            ->assertSuccessful()
            ->assertSee('استيراد الفواتير من ملف');
    }

    public function test_template_download_route_is_working(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('invoices.import.template'));

        $response->assertSuccessful();
        $response->assertHeader('content-disposition');
    }

    public function test_invoice_import_service_imports_valid_invoice(): void
    {
        Storage::fake('local');

        $csvData = implode("\n", [
            'العميل,وصف البند,الكمية,سعر الوحدة,الحالة,الملاحظات',
            'شركة الأمل للتجارة,تصميم شعار جديد,1,20000,posted,فاتورة شهرية',
        ]);

        $filePath = Storage::disk('local')->path('test_invoices.csv');
        file_put_contents($filePath, $csvData);

        $service = new InvoiceImportService;
        $columnMap = [
            'client' => 'العميل',
            'description' => 'وصف البند',
            'quantity' => 'الكمية',
            'unit_amount' => 'سعر الوحدة',
            'status' => 'الحالة',
            'notes' => 'الملاحظات',
        ];

        $result = $service->import($filePath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(1, $result->successful);
        $this->assertEquals(0, $result->failed);

        $this->assertDatabaseHas('invoices', [
            'client_id' => $this->client->id,
            'total_amount' => 20000,
            'status' => 'posted',
            'notes' => 'فاتورة شهرية',
        ]);

        $invoice = Invoice::first();
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('تصميم شعار جديد', $invoice->items->first()->description);
        $this->assertEquals(20000, $invoice->items->first()->unit_amount);
    }

    public function test_invoice_import_fails_with_missing_client(): void
    {
        Storage::fake('local');

        $csvData = implode("\n", [
            'العميل,وصف البند,سعر الوحدة',
            'شركة غير موجودة اطلاقا,تصميم شعار,5000',
        ]);

        $filePath = Storage::disk('local')->path('test_invoices_fail.csv');
        file_put_contents($filePath, $csvData);

        $service = new InvoiceImportService;
        $columnMap = [
            'client' => 'العميل',
            'description' => 'وصف البند',
            'unit_amount' => 'سعر الوحدة',
        ];

        $result = $service->import($filePath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
        $this->assertCount(1, $result->errors);
    }

    public function test_invoice_importer_livewire_component_flow(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\InvoiceImporterComponent::class)
            ->assertSet('step', 1)
            ->assertSee('استيراد الفواتير المتقدم');
    }
}
