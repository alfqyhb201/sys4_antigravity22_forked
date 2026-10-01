<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SeedSequentialScenario extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-sequential-scenario';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a sequential contract lifecycle scenario with overtime designs in the actual database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting sequential contract lifecycle seeding...');

        $companyName = 'Sequential Scenario Test Company';
        $client = Client::where('company', $companyName)->first();

        if ($client) {
            $this->warn('Previous test client found. Cleaning up old test data...');
            foreach ($client->invoices as $inv) {
                $inv->receipts()->delete();
                $inv->items()->delete();
                $inv->delete();
            }
            $client->contracts()->delete();

            foreach ($client->clientDesigners as $cd) {
                $cd->distributions()->delete();
                $cd->delete();
            }
            $client->delete();
            $this->info('Cleanup completed.');
        }

        $location = Location::firstOrCreate(['name' => 'Test Location']);
        $category = Category::firstOrCreate(['name' => 'Test Category']);
        $currency = Currency::firstOrCreate(
            ['currency' => 'YER'],
            ['currency_name' => 'Yemeni Rial', 'value' => 530]
        );

        $designer = Designer::first();
        if (! $designer) {
            // $user = User::firstOrCreate(
            //     ['email' => 'designer@example.com'],
            //     ['name' => 'Test Designer', 'password' => bcrypt('password')]
            // );
            $user = User::first();
            $designer = Designer::create([
                'user_id' => $user->id,
                'min_capacity' => 1,
                'max_capacity' => 60,
            ]);
        }

        // 1. Create client
        $client = Client::create([
            'company' => $companyName,
            'client_name' => 'Ahmed Test Client',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '777888999',
        ]);

        // 2. Create contract (Advance payment, 10 designs, total 1000 YER + 200 YER marketing)
        $contract = Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::parse('2026-05-01'),
            'end_date' => Carbon::parse('2026-06-01'),
            'weekly_designs_count' => 2,
            'monthly_designs_count' => 10,
            'total_amount' => 1000.0,
            'marketing_amount' => 200.0,
            'currency_id' => $currency->id,
            'auto_renewal' => true,
            'simple_requests_enabled' => false,
            'additional_designs_enabled' => false,
            'grace_period_days' => 5,
        ]);

        // 3. Create first invoice (posted)
        $firstInvoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => Carbon::parse('2026-05-01'),
            'due_date' => Carbon::parse('2026-05-08'),
            'total_amount' => 1200.0,
            'status' => 'posted',
            'notes' => 'First cycle invoice (advance)',
        ]);

        $firstInvoice->items()->create([
            'description' => 'Design services - 10 designs monthly',
            'quantity' => 1,
            'unit_amount' => 1000.0,
            'total' => 1000.0,
        ]);

        $firstInvoice->items()->create([
            'description' => 'Marketing services',
            'quantity' => 1,
            'unit_amount' => 200.0,
            'total' => 200.0,
        ]);

        // 4. Pay the first invoice
        $paymentService = new PaymentService;
        $paymentService->payInvoice($firstInvoice, [
            'amount' => 1200.0,
            'payment_method' => 'cash',
            'receipt_date' => '2026-05-02',
            'notes' => 'Manual payment for first cycle',
        ]);

        $firstInvoice->refresh();
        $this->info('1. Client and contract created successfully.');
        $this->info("   - First invoice status (INV-{$firstInvoice->invoice_number}): {$firstInvoice->status} (remaining: {$firstInvoice->remaining}).");

        // 5. Setup designer mapping and tag distributions
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'week_start_date' => Carbon::parse('2026-05-01'),
        ]);

        $tagGroup = TagGroup::firstOrCreate(['name' => 'Experimental Tag Group']);
        $tag = Tag::firstOrCreate(
            ['name' => 'Scenario Test Tag'],
            ['name' => 'Scenario Test Tag', 'tag_group_id' => $tagGroup->id]
        );

        // Regular cycle design (2026-05-15)
        ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-05-15',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-05-15'),
        ]);

        // Overtime design 1 (2026-06-02)
        ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-06-02',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-02'),
        ]);

        // Overtime design 2 (2026-06-05)
        ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => '2026-06-05',
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-06-05'),
        ]);

        $this->info('2. Recorded 3 completed designs:');
        $this->info('   - 1 design within regular agreed period (2026-05-15)');
        $this->info('   - 2 overtime designs after contract end (2026-06-02 & 2026-06-05)');

        // 6. Renew the contract (creates NEW contract, old contract → 'renewed')
        $this->info('3. Renewing contract (creating new contract while preserving old contract history)...');
        $newContract = $contract->createRenewalContract();

        $contract->refresh();
        $secondInvoice = Invoice::where('contract_id', $newContract->id)->latest()->first();

        $this->line('--------------------------------------------------');
        $this->info('Old contract details after renewal:');
        $this->line(" - Old contract status (#{$contract->id}): {$contract->status}");
        $this->line(" - Old contract period: {$contract->start_date->format('Y-m-d')} to {$contract->end_date->format('Y-m-d')} (preserved as historical record)");
        $this->line('--------------------------------------------------');
        $this->info('New contract details:');
        $this->line(" - New contract ID: {$newContract->id}");
        $this->line(" - Client: {$client->company}");
        $this->line(" - Status: {$newContract->status}");
        $this->line(" - New contract period: {$newContract->start_date->format('Y-m-d')} to {$newContract->end_date->format('Y-m-d')}");
        $this->line('--------------------------------------------------');
        $this->info("Second issued invoice details - Number: {$secondInvoice->invoice_number}");
        $this->line(" - Status: {$secondInvoice->status}");
        $this->line(" - Due date: {$secondInvoice->due_date->format('Y-m-d')}");
        $this->line(' - Total amount: '.number_format($secondInvoice->total_amount, 2));
        $this->line(' - Invoice line items:');

        foreach ($secondInvoice->items as $item) {
            $this->line("   * [{$item->description}] -> Qty: {$item->quantity} | Unit price: ".number_format($item->unit_amount, 2).' | Total: '.number_format($item->total, 2));
        }
        $this->line('--------------------------------------------------');
        $this->info('Seeding completed successfully! You can now view this data directly in the database or Filament admin panel.');

        return Command::SUCCESS;
    }
}
