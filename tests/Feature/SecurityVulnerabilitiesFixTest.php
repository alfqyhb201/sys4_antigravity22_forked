<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientFinancialDetail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityVulnerabilitiesFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create standard roles
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('accountant', 'web');
        Role::findOrCreate('designer', 'web');

        // Create standard permissions
        Permission::findOrCreate('view_financial_reports', 'web');
        Permission::findOrCreate('view_client_financial', 'web');
        Permission::findOrCreate('update_users', 'web');
        Permission::findOrCreate('delete_users', 'web');
        Permission::findOrCreate('view_any_users', 'web');
        Permission::findOrCreate('manage_settings', 'web');
        Permission::findOrCreate('delete_role', 'web');
        Permission::findOrCreate('create_category', 'web');
        Permission::findOrCreate('create_location', 'web');
        Permission::findOrCreate('view_any_invoice', 'web');
        Permission::findOrCreate('create_invoice', 'web');
        Permission::findOrCreate('view_any_receipt', 'web');
        Permission::findOrCreate('create_receipt', 'web');
        Permission::findOrCreate('view_any_contract', 'web');
        Permission::findOrCreate('create_contract', 'web');
    }

    public function test_unauthorized_user_cannot_access_client_financial_detail_page(): void
    {
        $designer = User::factory()->create(['status' => 1]);
        $designer->assignRole('designer');

        $category = \App\Models\Category::create(['name' => 'General Category']);
        $client = Client::factory()->create(['category_id' => $category->id]);

        $this->actingAs($designer);

        $this->assertFalse(ClientFinancialDetail::canAccess());

        $response = $this->get("/admin/client/{$client->id}/financial");
        $response->assertForbidden();
    }

    public function test_accountant_and_admin_can_access_client_financial_detail_page(): void
    {
        $accountant = User::factory()->create(['status' => 1]);
        $accountant->assignRole('accountant');

        $category = \App\Models\Category::create(['name' => 'Finance Category']);
        $client = Client::factory()->create(['category_id' => $category->id]);

        $this->actingAs($accountant);

        $this->assertTrue(ClientFinancialDetail::canAccess());

        $response = $this->get("/admin/client/{$client->id}/financial");
        $response->assertOk();
    }

    public function test_non_admin_user_cannot_update_or_delete_admin_user(): void
    {
        $staff = User::factory()->create(['status' => 1]);
        $staff->givePermissionTo(['update_users', 'delete_users']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');

        $normalUser = User::factory()->create(['status' => 1]);

        // Staff can update normal user
        $this->assertTrue($staff->can('update', $normalUser));
        $this->assertTrue($staff->can('delete', $normalUser));

        // Staff CANNOT update or delete admin user
        $this->assertFalse($staff->can('update', $admin));
        $this->assertFalse($staff->can('delete', $admin));
        $this->assertFalse($staff->can('forceDelete', $admin));
    }

    public function test_admin_user_can_update_and_delete_users(): void
    {
        $admin1 = User::factory()->create(['status' => 1]);
        $admin1->assignRole('admin');
        $admin1->givePermissionTo(['update_users', 'delete_users']);

        $admin2 = User::factory()->create(['status' => 1]);
        $admin2->assignRole('admin');

        $normalUser = User::factory()->create(['status' => 1]);

        // Admin can update normal user and other admin
        $this->assertTrue($admin1->can('update', $normalUser));
        $this->assertTrue($admin1->can('update', $admin2));

        // Admin can delete normal user and other admin (but not self)
        $this->assertTrue($admin1->can('delete', $normalUser));
        $this->assertTrue($admin1->can('delete', $admin2));
        $this->assertFalse($admin1->can('delete', $admin1));
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('delete_role');

        $adminRole = Role::findByName('admin', 'web');
        $superAdminRole = Role::findByName('super_admin', 'web');
        $customRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        // Admin cannot delete admin or super_admin roles
        $this->assertFalse($admin->can('delete', $adminRole));
        $this->assertFalse($admin->can('delete', $superAdminRole));

        // Admin CAN delete custom role
        $this->assertTrue($admin->can('delete', $customRole));
    }

    public function test_role_permission_manager_access_is_restricted(): void
    {
        $staff = User::factory()->create(['status' => 1]);
        $staff->assignRole('designer');

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');

        $settingsManager = User::factory()->create(['status' => 1]);
        $settingsManager->givePermissionTo('manage_settings');

        // Staff cannot access
        $this->actingAs($staff);
        $this->assertFalse(\App\Filament\Pages\RolePermissionManager::canAccess());

        // Admin can access
        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Pages\RolePermissionManager::canAccess());

        // Settings manager can access
        $this->actingAs($settingsManager);
        $this->assertTrue(\App\Filament\Pages\RolePermissionManager::canAccess());
    }

    public function test_custom_import_pages_access_control(): void
    {
        $unauthorizedUser = User::factory()->create(['status' => 1]);

        $categoryImporter = User::factory()->create(['status' => 1]);
        $categoryImporter->givePermissionTo('create_category');

        $locationImporter = User::factory()->create(['status' => 1]);
        $locationImporter->givePermissionTo('create_location');

        $this->actingAs($unauthorizedUser);
        $this->assertFalse(\App\Filament\Pages\CustomCategoryImport::canAccess());
        $this->assertFalse(\App\Filament\Pages\CustomLocationImport::canAccess());

        $this->actingAs($categoryImporter);
        $this->assertTrue(\App\Filament\Pages\CustomCategoryImport::canAccess());
        $this->assertFalse(\App\Filament\Pages\CustomLocationImport::canAccess());

        $this->actingAs($locationImporter);
        $this->assertFalse(\App\Filament\Pages\CustomCategoryImport::canAccess());
        $this->assertTrue(\App\Filament\Pages\CustomLocationImport::canAccess());
    }

    public function test_financial_models_policies_enforce_permissions(): void
    {
        $userWithoutPerms = User::factory()->create(['status' => 1]);
        $financeUser = User::factory()->create(['status' => 1]);
        $financeUser->givePermissionTo([
            'view_any_invoice',
            'create_invoice',
            'view_any_receipt',
            'create_receipt',
            'view_any_contract',
            'create_contract',
        ]);

        $invoice = new \App\Models\Invoice;
        $receipt = new \App\Models\Receipt;
        $contract = new \App\Models\Contract;

        // Unauthorized user checks
        $this->assertFalse($userWithoutPerms->can('viewAny', \App\Models\Invoice::class));
        $this->assertFalse($userWithoutPerms->can('create', \App\Models\Invoice::class));
        $this->assertFalse($userWithoutPerms->can('viewAny', \App\Models\Receipt::class));
        $this->assertFalse($userWithoutPerms->can('create', \App\Models\Receipt::class));
        $this->assertFalse($userWithoutPerms->can('viewAny', \App\Models\Contract::class));
        $this->assertFalse($userWithoutPerms->can('create', \App\Models\Contract::class));

        // Authorized user checks
        $this->assertTrue($financeUser->can('viewAny', \App\Models\Invoice::class));
        $this->assertTrue($financeUser->can('create', \App\Models\Invoice::class));
        $this->assertTrue($financeUser->can('viewAny', \App\Models\Receipt::class));
        $this->assertTrue($financeUser->can('create', \App\Models\Receipt::class));
        $this->assertTrue($financeUser->can('viewAny', \App\Models\Contract::class));
        $this->assertTrue($financeUser->can('create', \App\Models\Contract::class));
    }
}
