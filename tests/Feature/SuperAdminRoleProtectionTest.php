<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminRoleProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_setup_super_admin_command_creates_role_and_assigns_user(): void
    {
        $user = User::factory()->create(['username' => 'testuser']);

        $this->artisan('app:setup-super-admin', ['identifier' => $user->username])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_bypasses_all_gate_checks(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $regularUser = User::factory()->create();

        // فحص صلاحية وهمية غير معرّفة حتى في قاعدة البيانات
        $this->assertTrue(Gate::forUser($superAdmin)->check('any_nonexistent_custom_permission'));
        $this->assertFalse(Gate::forUser($regularUser)->check('any_nonexistent_custom_permission'));
    }

    public function test_admin_cannot_update_or_delete_super_admin_user(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // منح صلاحية update_users و delete_users للـ admin
        Permission::firstOrCreate(['name' => 'update_users']);
        Permission::firstOrCreate(['name' => 'delete_users']);
        $admin->givePermissionTo(['update_users', 'delete_users']);

        // التحقق من أن Admin لا يمكنه تعديل أو حذف Super Admin
        $this->assertFalse($admin->can('update', $superAdmin));
        $this->assertFalse($admin->can('delete', $superAdmin));
        $this->assertFalse($admin->can('restore', $superAdmin));
        $this->assertFalse($admin->can('forceDelete', $superAdmin));
    }

    public function test_super_admin_can_update_other_users_and_admins(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $otherSuperAdmin = User::factory()->create();
        $otherSuperAdmin->assignRole('super_admin');

        $this->assertTrue($superAdmin->can('update', $admin));
        $this->assertTrue($superAdmin->can('delete', $admin));
        $this->assertTrue($superAdmin->can('update', $otherSuperAdmin));
    }

    public function test_admin_cannot_update_super_admin_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminRole = Role::findByName('super_admin', 'web');

        Permission::firstOrCreate(['name' => 'update_role']);
        $admin->givePermissionTo('update_role');

        $this->assertFalse($admin->can('update', $superAdminRole));
    }

    public function test_super_admin_role_cannot_be_deleted_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $superAdminRole = Role::findByName('super_admin', 'web');

        Permission::firstOrCreate(['name' => 'delete_role']);
        $admin->givePermissionTo('delete_role');

        $this->assertFalse($admin->can('delete', $superAdminRole));
    }
}
