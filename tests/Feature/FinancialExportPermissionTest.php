<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialExportPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'value' => 1.0,
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->category = Category::create(['name' => 'General']);

        Permission::firstOrCreate(['name' => 'export_financial_data']);
        Permission::firstOrCreate(['name' => 'view_financial_reports']);
        Permission::firstOrCreate(['name' => 'view_any_invoice']);
        Permission::firstOrCreate(['name' => 'view_client']);
        Permission::firstOrCreate(['name' => 'view_client_financial']);

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super_admin']);
    }

    public function test_user_with_export_financial_data_can_access_debtors_report(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('export_financial_data');

        $response = $this->actingAs($user)->get(route('reports.client-debtors'));
        $response->assertOk();
    }

    public function test_user_with_export_financial_data_can_access_client_statement_report(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('export_financial_data');

        $client = Client::factory()->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id, 'print' => 1]));
        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_access_debtors_report(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.client-debtors'));
        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_client_statement_report(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['category_id' => $this->category->id]);

        $response = $this->actingAs($user)->get(route('reports.client-statement', ['client' => $client->id]));
        $response->assertForbidden();
    }

    public function test_admin_can_access_both_reports(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $client = Client::factory()->create(['category_id' => $this->category->id]);

        $this->actingAs($admin)->get(route('reports.client-debtors'))->assertOk();
        $this->actingAs($admin)->get(route('reports.client-statement', ['client' => $client->id]))->assertOk();
    }
}
