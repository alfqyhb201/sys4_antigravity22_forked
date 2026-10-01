<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);

        // إنشاء الصلاحيات المطلوبة للاختبار
        collect([
            'view_any_invoice',
            'view_invoice',
            'create_invoice',
            'update_invoice',
            'delete_invoice',
            'view_any_client',
            'view_client',
        ])->each(fn ($p) => \Spatie\Permission\Models\Permission::create(['name' => $p]));

        $this->user->givePermissionTo([
            'view_any_invoice',
            'view_invoice',
            'create_invoice',
            'update_invoice',
            'delete_invoice',
            'view_any_client',
            'view_client',
        ]);
        $this->actingAs($this->user);

        \App\Models\Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 530,
            'is_base' => true,
            'is_active' => true,
        ]);
    }

    protected function createClient(array $attributes = []): Client
    {
        $location = \App\Models\Location::create([
            'name' => 'موقع اختبار',
            'added_by_user' => $this->user->id,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'تصنيف اختبار',
        ]);

        return Client::create(array_merge([
            'company' => 'شركة اختبار',
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
            'status' => true,
        ], $attributes));
    }

    protected function createInvoice(array $attributes = []): Invoice
    {
        $client = $attributes['client_id'] ?? null
            ? null
            : $this->createClient();

        $currency = \App\Models\Currency::getBase() ?? \App\Models\Currency::first();

        return Invoice::create(array_merge([
            'client_id' => $client?->id ?? $attributes['client_id'],
            'currency_id' => $currency?->id,
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'total_amount' => 0.00,
            'status' => 'draft',
            'notes' => 'ملاحظات اختبارية',
            'created_by_user' => $this->user->id,
        ], $attributes));
    }

    /** @test */
    public function test_can_render_list_page(): void
    {
        $this->get(InvoiceResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_create_page(): void
    {
        $this->get(InvoiceResource::getUrl('create'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_edit_page(): void
    {
        $invoice = $this->createInvoice();

        $this->get(InvoiceResource::getUrl('edit', ['record' => $invoice]))
            ->assertSuccessful();
    }

    /** @test */
    public function test_form_has_client_side_calculation_attributes(): void
    {
        Livewire::test(CreateInvoice::class)
            ->assertSeeHtml('data-calc="invoice-total"')
            ->assertSeeHtml('data-calc="quantity"')
            ->assertSeeHtml('data-calc="unit_amount"')
            ->assertSeeHtml('data-calc="total"');
    }

    /** @test */
    public function test_mutate_form_data_calculates_total_amount_from_items(): void
    {
        $page = new CreateInvoice;
        $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);

        $result = $method->invoke($page, [
            'items' => [
                ['quantity' => 2, 'unit_amount' => 150],
                ['quantity' => 1, 'unit_amount' => 100],
            ],
        ]);

        $this->assertEquals(400.00, (float) $result['total_amount']);
    }

    /** @test */
    public function test_invoice_item_calculates_total_automatically_on_save(): void
    {
        $invoice = $this->createInvoice();
        $item = $invoice->items()->create([
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_amount' => 150,
        ]);

        $this->assertEquals(300.00, (float) $item->fresh()->total);
    }

    /** @test */
    public function test_can_create_invoice_and_saves_total_amount(): void
    {
        \Filament\Forms\Components\Repeater::fake();

        $client = $this->createClient();

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'client_id' => $client->id,
                'status' => 'draft',
                'items' => [
                    [
                        'description' => 'Item 1',
                        'quantity' => 2,
                        'unit_amount' => 150,
                        'total' => 300,
                    ],
                ],
                'total_amount' => 300,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = Invoice::first();
        $this->assertNotNull($invoice);
        $this->assertEquals(300.00, (float) $invoice->total_amount);
    }

    /** @test */
    public function test_can_edit_invoice_and_saves_total_amount(): void
    {
        \Filament\Forms\Components\Repeater::fake();

        $invoice = $this->createInvoice([
            'total_amount' => 300.00,
        ]);
        $invoice->items()->create([
            'description' => 'Item 1',
            'quantity' => 2,
            'unit_amount' => 150,
            'total' => 300,
        ]);

        Livewire::test(\App\Filament\Resources\InvoiceResource\Pages\EditInvoice::class, [
            'record' => $invoice->getKey(),
        ])
            ->fillForm([
                'client_id' => $invoice->client_id,
                'currency_id' => $invoice->currency_id,
                'status' => 'draft',
                'items' => [
                    [
                        'description' => 'Item 1',
                        'quantity' => 3,
                        'unit_amount' => 150,
                        'total' => 450,
                    ],
                ],
                'total_amount' => 450,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertEquals(450.00, (float) $invoice->total_amount);
    }

    /** @test */
    public function test_contracts_are_filtered_by_client(): void
    {
        $client1 = $this->createClient(['company' => 'Client A']);
        $client2 = $this->createClient(['company' => 'Client B']);

        $currency = \App\Models\Currency::getBase() ?? \App\Models\Currency::first();

        $contract1 = \App\Models\Contract::factory()->create([
            'client_id' => $client1->id,
            'currency_id' => $currency->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'total_amount' => 500,
        ]);

        $contract2 = \App\Models\Contract::factory()->create([
            'client_id' => $client2->id,
            'currency_id' => $currency->id,
            'billing_cycle' => 'yearly',
            'status' => 'active',
            'total_amount' => 1200,
        ]);

        Livewire::test(CreateInvoice::class)
            ->fillForm([
                'client_id' => $client1->id,
            ])
            ->assertFormFieldExists('contract_id', function (\Filament\Forms\Components\Select $field) use ($contract1, $contract2) {
                $options = $field->getOptions();

                return isset($options[$contract1->id]) && ! isset($options[$contract2->id]);
            });
    }

    /** @test */
    public function test_contracts_options_are_empty_when_no_client_selected(): void
    {
        $client = $this->createClient(['company' => 'Client A']);
        $currency = \App\Models\Currency::getBase() ?? \App\Models\Currency::first();

        \App\Models\Contract::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'total_amount' => 500,
        ]);

        Livewire::test(CreateInvoice::class)
            ->assertFormFieldExists('contract_id', function (\Filament\Forms\Components\Select $field) {
                $options = $field->getOptions();

                return empty($options) && $field->isDisabled();
            });
    }

    /** @test */
    public function test_can_filter_trashed_invoices(): void
    {
        $activeInvoice = $this->createInvoice([
            'invoice_number' => 'INV-ACTIVE-01',
            'status' => 'posted',
        ]);
        $trashedInvoice = $this->createInvoice([
            'invoice_number' => 'INV-TRASHED-01',
            'status' => 'posted',
        ]);
        $trashedInvoice->delete();

        Livewire::test(\App\Filament\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->assertCanSeeTableRecords([$activeInvoice])
            ->assertCanNotSeeTableRecords([$trashedInvoice])
            ->filterTable('trashed', true)
            ->assertCanSeeTableRecords([$trashedInvoice]);
    }

    /** @test */
    public function test_can_render_invoice_view_modal_action(): void
    {
        $invoice = $this->createInvoice([
            'status' => 'posted',
        ]);
        $invoice->items()->create([
            'description' => 'بند تجريبي',
            'quantity' => 1,
            'unit_amount' => 500,
            'total' => 500,
        ]);

        Livewire::test(\App\Filament\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->assertTableActionExists('viewInvoice');
    }

    /** @test */
    public function test_can_filter_invoices_by_client(): void
    {
        $client1 = $this->createClient(['company' => 'شركة أ']);
        $client2 = $this->createClient(['company' => 'شركة ب']);

        $invoice1 = $this->createInvoice(['client_id' => $client1->id, 'status' => 'posted']);
        $invoice2 = $this->createInvoice(['client_id' => $client2->id, 'status' => 'posted']);

        Livewire::test(\App\Filament\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->filterTable('client_id', $client1->id)
            ->assertCanSeeTableRecords([$invoice1])
            ->assertCanNotSeeTableRecords([$invoice2]);
    }

    /** @test */
    public function test_can_filter_invoices_by_tabs(): void
    {
        $draftInvoice = $this->createInvoice([
            'status' => 'draft',
            'invoice_number' => 'INV-DRAFT-01',
        ]);
        $postedInvoice = $this->createInvoice([
            'status' => 'posted',
            'invoice_number' => 'INV-POSTED-01',
        ]);
        $paidInvoice = $this->createInvoice([
            'status' => 'paid',
            'invoice_number' => 'INV-PAID-01',
        ]);

        Livewire::test(\App\Filament\Resources\InvoiceResource\Pages\ListInvoices::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$draftInvoice, $postedInvoice, $paidInvoice])
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$draftInvoice])
            ->assertCanNotSeeTableRecords([$postedInvoice, $paidInvoice])
            ->set('activeTab', 'posted')
            ->assertCanSeeTableRecords([$postedInvoice])
            ->assertCanNotSeeTableRecords([$draftInvoice, $paidInvoice])
            ->set('activeTab', 'paid')
            ->assertCanSeeTableRecords([$paidInvoice])
            ->assertCanNotSeeTableRecords([$draftInvoice, $postedInvoice]);
    }
}
