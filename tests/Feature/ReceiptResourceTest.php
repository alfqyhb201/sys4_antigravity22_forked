<?php

namespace Tests\Feature;

use App\Filament\Resources\ReceiptResource;
use App\Filament\Resources\ReceiptResource\Pages\ListReceipts;
use App\Models\Category;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReceiptResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Currency $currency;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);

        collect([
            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
            'view_any_client',
            'view_client',
        ])->each(fn ($p) => Permission::findOrCreate($p));

        $this->user->givePermissionTo([
            'view_any_receipt',
            'view_receipt',
            'create_receipt',
            'update_receipt',
            'delete_receipt',
            'view_any_client',
            'view_client',
        ]);

        $this->actingAs($this->user);

        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 530,
            'is_base' => true,
            'is_active' => true,
        ]);

        $location = Location::create([
            'name' => 'موقع اختبار',
            'added_by_user' => $this->user->id,
        ]);

        $category = Category::create([
            'name' => 'تصنيف اختبار',
        ]);

        $this->client = Client::create([
            'company' => 'مؤسسة التجارة العالمية',
            'client_name' => 'محمد عبدالله',
            'contact_number' => '777123456',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
            'status' => true,
        ]);
    }

    protected function createReceipt(array $attributes = []): Receipt
    {
        return Receipt::create(array_merge([
            'client_id' => $this->client->id,
            'amount' => 500.00,
            'original_amount' => 500.00,
            'unallocated_amount' => 500.00,
            'paid_currency_id' => $this->currency->id,
            'receipt_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference_number' => 'REF-'.fake()->unique()->numerify('#####'),
            'notes' => 'سند تجريبي',
            'created_by_user' => $this->user->id,
        ], $attributes));
    }

    protected function createInvoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'client_id' => $this->client->id,
            'currency_id' => $this->currency->id,
            'invoice_number' => 'INV-TEST-'.fake()->unique()->numberBetween(100, 999),
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'total_amount' => 1000.00,
            'status' => 'posted',
            'created_by_user' => $this->user->id,
        ], $attributes));
    }

    public function test_can_render_receipts_list_page(): void
    {
        $this->get(ReceiptResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_search_by_reference_number(): void
    {
        $receiptA = $this->createReceipt(['reference_number' => 'REF-SPECIAL-777']);
        $receiptB = $this->createReceipt(['reference_number' => 'REF-OTHER-888']);

        Livewire::test(ListReceipts::class)
            ->searchTable('SPECIAL-777')
            ->assertCanSeeTableRecords([$receiptA])
            ->assertCanNotSeeTableRecords([$receiptB]);
    }

    public function test_can_search_by_notes(): void
    {
        $receiptA = $this->createReceipt(['notes' => 'حوالة خاصة بمشروع البرج']);
        $receiptB = $this->createReceipt(['notes' => 'دفعة عادية لشهر يناير']);

        Livewire::test(ListReceipts::class)
            ->searchTable('مشروع البرج')
            ->assertCanSeeTableRecords([$receiptA])
            ->assertCanNotSeeTableRecords([$receiptB]);
    }

    public function test_tabs_filter_records_correctly(): void
    {
        $invoice = $this->createInvoice();

        // 1. سند مسدد لفاتورة
        $invoicedReceipt = $this->createReceipt([
            'invoice_id' => $invoice->id,
            'unallocated_amount' => 0.00,
            'receipt_date' => now()->subDays(2)->toDateString(),
        ]);

        // 2. سند دفعة مقدمة متاح
        $advanceAvailable = $this->createReceipt([
            'invoice_id' => null,
            'unallocated_amount' => 300.00,
            'receipt_date' => now()->subDays(1)->toDateString(),
        ]);

        // 3. سند دفعة مقدمة مستهلك
        $advanceExhausted = $this->createReceipt([
            'invoice_id' => null,
            'unallocated_amount' => 0.00,
            'receipt_date' => now()->subDays(3)->toDateString(),
        ]);

        // 4. سند مقبوضات اليوم
        $todayReceipt = $this->createReceipt([
            'receipt_date' => now()->toDateString(),
        ]);

        // تبويب مسددة لفواتير
        Livewire::test(ListReceipts::class)
            ->set('activeTab', 'invoiced')
            ->assertCanSeeTableRecords([$invoicedReceipt])
            ->assertCanNotSeeTableRecords([$advanceAvailable, $advanceExhausted]);

        // تبويب دفعات مقدمة متاحة
        Livewire::test(ListReceipts::class)
            ->set('activeTab', 'advances_available')
            ->assertCanSeeTableRecords([$advanceAvailable])
            ->assertCanNotSeeTableRecords([$invoicedReceipt, $advanceExhausted]);

        // تبويب دفعات مستهلكة
        Livewire::test(ListReceipts::class)
            ->set('activeTab', 'advances_exhausted')
            ->assertCanSeeTableRecords([$advanceExhausted])
            ->assertCanNotSeeTableRecords([$invoicedReceipt, $advanceAvailable]);

        // تبويب مقبوضات اليوم
        Livewire::test(ListReceipts::class)
            ->set('activeTab', 'today')
            ->assertCanSeeTableRecords([$todayReceipt])
            ->assertCanNotSeeTableRecords([$invoicedReceipt, $advanceExhausted]);
    }

    public function test_can_filter_by_date_range(): void
    {
        $oldReceipt = $this->createReceipt(['receipt_date' => '2025-01-01']);
        $targetReceipt = $this->createReceipt(['receipt_date' => '2025-06-15']);
        $futureReceipt = $this->createReceipt(['receipt_date' => '2025-12-31']);

        Livewire::test(ListReceipts::class)
            ->filterTable('receipt_date', [
                'from' => '2025-06-01',
                'until' => '2025-06-30',
            ])
            ->assertCanSeeTableRecords([$targetReceipt])
            ->assertCanNotSeeTableRecords([$oldReceipt, $futureReceipt]);
    }

    public function test_can_filter_by_allocation_status(): void
    {
        $invoice = $this->createInvoice();

        $invoicedReceipt = $this->createReceipt([
            'invoice_id' => $invoice->id,
            'unallocated_amount' => 0.00,
        ]);
        $advanceAvailable = $this->createReceipt([
            'invoice_id' => null,
            'unallocated_amount' => 500.00,
        ]);

        Livewire::test(ListReceipts::class)
            ->filterTable('allocation_status', 'invoiced')
            ->assertCanSeeTableRecords([$invoicedReceipt])
            ->assertCanNotSeeTableRecords([$advanceAvailable]);

        Livewire::test(ListReceipts::class)
            ->filterTable('allocation_status', 'advance_available')
            ->assertCanSeeTableRecords([$advanceAvailable])
            ->assertCanNotSeeTableRecords([$invoicedReceipt]);
    }

    public function test_can_filter_by_client(): void
    {
        $location = Location::first();
        $category = Category::first();

        $clientB = Client::create([
            'company' => 'شركة النور للمقاولات',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
            'status' => true,
        ]);

        $receiptA = $this->createReceipt(['client_id' => $this->client->id]);
        $receiptB = $this->createReceipt(['client_id' => $clientB->id]);

        Livewire::test(ListReceipts::class)
            ->filterTable('client_id', $this->client->id)
            ->assertCanSeeTableRecords([$receiptA])
            ->assertCanNotSeeTableRecords([$receiptB]);
    }

    public function test_default_sort_orders_receipts_by_receipt_date_desc(): void
    {
        $older = $this->createReceipt(['receipt_date' => '2025-01-01']);
        $newer = $this->createReceipt(['receipt_date' => '2025-02-01']);

        Livewire::test(ListReceipts::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_can_filter_and_restore_trashed_receipts(): void
    {
        $activeReceipt = $this->createReceipt();
        $trashedReceipt = $this->createReceipt();
        $trashedReceipt->delete();

        Livewire::test(ListReceipts::class)
            ->assertCanSeeTableRecords([$activeReceipt])
            ->assertCanNotSeeTableRecords([$trashedReceipt])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$trashedReceipt])
            ->callTableAction(\Filament\Tables\Actions\RestoreAction::class, $trashedReceipt);

        $this->assertFalse($trashedReceipt->fresh()->trashed());
    }
}
