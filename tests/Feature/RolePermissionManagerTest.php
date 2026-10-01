<?php

namespace Tests\Feature;

use App\Filament\Pages\RolePermissionManager;
use App\Models\User;
use App\Services\PermissionDiscoveryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Role $adminRole;

    protected Role $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        $this->adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->editorRole = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($this->adminRole);

        $this->regularUser = User::factory()->create();
    }

    public function test_permission_discovery_service_discovers_resources_pages_and_actions(): void
    {
        $service = new PermissionDiscoveryService;

        $resources = $service->discoverResources();
        $pages = $service->discoverPages();
        $custom = $service->discoverCustomActions();
        $all = $service->getAllDiscoveredPermissions();

        $this->assertNotEmpty($resources);
        $this->assertArrayHasKey('client', $resources);
        $this->assertArrayHasKey('invoice', $resources);
        $this->assertArrayHasKey('users', $resources);

        $this->assertNotEmpty($pages);
        $this->assertNotEmpty($custom);
        $this->assertArrayHasKey('record_payment', $custom);
        $this->assertArrayHasKey('approve_discount', $custom);

        $this->assertGreaterThan(50, count($all));
    }

    public function test_permission_discovery_service_syncs_missing_permissions_into_database(): void
    {
        $service = new PermissionDiscoveryService;

        // Perform initial sync
        $result = $service->syncMissingPermissions();

        $this->assertGreaterThan(0, $result['total_discovered']);

        // All discovered permissions should now exist in permissions table
        $allDiscovered = array_keys($service->getAllDiscoveredPermissions());
        $dbCount = Permission::whereIn('name', $allDiscovered)->count();

        $this->assertEquals(count($allDiscovered), $dbCount);

        // A second sync should be idempotent (0 created)
        $secondResult = $service->syncMissingPermissions();
        $this->assertEquals(0, $secondResult['created_count']);
    }

    public function test_guest_cannot_access_role_permission_manager_page(): void
    {
        $this->get('/admin/role-permission-manager')
            ->assertRedirect();
    }

    public function test_regular_user_cannot_access_role_permission_manager_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/role-permission-manager')
            ->assertForbidden();
    }

    public function test_admin_user_can_access_role_permission_manager_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/role-permission-manager')
            ->assertOk()
            ->assertSee('إدارة وتخصيص صلاحيات الأدوار');
    }

    public function test_livewire_can_switch_role_and_save_permissions(): void
    {
        $this->actingAs($this->adminUser);

        // Pre-create some test permissions
        Permission::firstOrCreate(['name' => 'view_any_client', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_client', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete_client', 'guard_name' => 'web']);

        Livewire::test(RolePermissionManager::class)
            ->call('selectRole', $this->editorRole->id)
            ->assertSet('selectedRoleId', $this->editorRole->id)
            ->call('togglePermission', 'view_any_client')
            ->call('togglePermission', 'create_client')
            ->call('saveRolePermissions')
            ->assertNotified('تم حفظ صلاحيات الدور (editor) بنجاح');

        $this->assertTrue($this->editorRole->fresh()->hasPermissionTo('view_any_client'));
        $this->assertTrue($this->editorRole->fresh()->hasPermissionTo('create_client'));
        $this->assertFalse($this->editorRole->fresh()->hasPermissionTo('delete_client'));
    }

    public function test_livewire_can_create_new_role(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(RolePermissionManager::class)
            ->set('newRoleName', 'audit_officer')
            ->call('createNewRole')
            ->assertNotified();

        $this->assertDatabaseHas('roles', [
            'name' => 'audit_officer',
        ]);
    }

    public function test_permission_simulator_evaluates_role_and_user_permissions(): void
    {
        $this->actingAs($this->adminUser);

        $perm = Permission::firstOrCreate(['name' => 'view_any_invoice', 'guard_name' => 'web']);
        $this->editorRole->givePermissionTo($perm);

        $component = Livewire::test(RolePermissionManager::class)
            ->set('testSubjectType', 'role')
            ->set('testSubjectId', $this->editorRole->id)
            ->set('testPermission', 'view_any_invoice')
            ->call('runPermissionTest');

        $result = $component->get('testResult');
        $this->assertNotNull($result);
        $this->assertTrue($result['hasAccess']);

        // Test non-assigned permission
        $component->set('testPermission', 'delete_invoice')
            ->call('runPermissionTest');

        $result = $component->get('testResult');
        $this->assertFalse($result['hasAccess']);
    }
}
