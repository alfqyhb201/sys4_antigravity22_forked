<?php

namespace Tests\Feature;

use App\Filament\Exports\UserExporter;
use App\Filament\Resources\UsersResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'status' => 1,
            'username' => 'super_admin_test',
        ]);

        Permission::firstOrCreate(['name' => 'view_any_users']);
        Permission::firstOrCreate(['name' => 'view_users']);
        Permission::firstOrCreate(['name' => 'create_users']);
        Permission::firstOrCreate(['name' => 'update_users']);
        Permission::firstOrCreate(['name' => 'delete_users']);

        $this->adminUser->givePermissionTo([
            'view_any_users',
            'view_users',
            'create_users',
            'update_users',
            'delete_users',
        ]);

        $this->actingAs($this->adminUser);
    }

    public function test_can_render_users_list_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'accountant']);
        $user = User::factory()->create(['name' => 'محاسب تجريبي']);
        $user->assignRole($role);

        $this->get(UsersResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('محاسب تجريبي')
            ->assertSee('الأدوار');
    }

    public function test_can_render_create_page_with_roles_field(): void
    {
        $this->get(UsersResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('الأدوار الوظيفية')
            ->assertSee('تاريخ التوظيف');
    }

    public function test_can_create_user_with_nullable_hire_date_and_role(): void
    {
        $role = Role::firstOrCreate(['name' => 'designer']);

        $userData = [
            'name' => 'مصمم جديد بدون تاريخ',
            'username' => 'new_designer_user',
            'email' => 'designer_new@example.com',
            'password' => bcrypt('password123'),
            'hire_date' => null,
            'status' => 1,
            'work_phone_number' => '+967771234567',
        ];

        $user = User::create($userData);
        $user->assignRole($role);

        $this->assertDatabaseHas('users', [
            'email' => 'designer_new@example.com',
            'hire_date' => null,
            'work_phone_number' => '+967771234567',
        ]);

        $this->assertTrue($user->hasRole('designer'));
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $policy = new \App\Policies\UserPolicy;

        $canDeleteSelf = $policy->delete($this->adminUser, $this->adminUser);
        $this->assertFalse($canDeleteSelf, 'A user should never be allowed to delete their own account.');

        $otherUser = User::factory()->create();
        $canDeleteOther = $policy->delete($this->adminUser, $otherUser);
        $this->assertTrue($canDeleteOther, 'An authorized user should be able to delete another account.');
    }

    public function test_user_exporter_does_not_contain_password_column(): void
    {
        $columns = collect(UserExporter::getColumns())->map(fn ($col) => $col->getName())->toArray();

        $this->assertNotContains('password', $columns, 'Password column must not be exported for security reasons.');
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function test_can_render_infolist_with_roles_section(): void
    {
        $role = Role::firstOrCreate(['name' => 'supervisor']);
        $user = User::factory()->create(['name' => 'مشرف تجريبي']);
        $user->assignRole($role);

        $this->get(UsersResource::getUrl('view', ['record' => $user]))
            ->assertSuccessful()
            ->assertSee('مشرف تجريبي')
            ->assertSee('الأدوار الوظيفية');
    }
}
