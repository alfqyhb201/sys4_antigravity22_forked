<?php

namespace Tests\Feature;

use App\Filament\Pages\GeneralSettingsPage;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(
            Filament::getPanel('admin')
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        Permission::firstOrCreate(['name' => 'manage_settings']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);

        $this->regularUser = User::factory()->create();
    }

    public function test_guest_cannot_access_general_settings_page(): void
    {
        $this->get('/admin/general-settings-page')
            ->assertRedirect();
    }

    public function test_user_without_permission_or_role_cannot_access_general_settings_page(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/admin/general-settings-page')
            ->assertForbidden();
    }

    public function test_admin_user_can_access_general_settings_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get('/admin/general-settings-page')
            ->assertOk();
    }

    public function test_user_with_manage_settings_permission_can_access_general_settings_page(): void
    {
        $this->regularUser->givePermissionTo('manage_settings');

        $this->actingAs($this->regularUser)
            ->get('/admin/general-settings-page')
            ->assertOk();
    }

    public function test_system_setting_max_file_size_default_and_setter(): void
    {
        // عندما لا يكون هناك إعداد في قاعدة البيانات، يجب إرجاع القيمة الافتراضية
        $defaultConfig = (int) config('filesystems.max_file_size', 10240);
        $this->assertEquals($defaultConfig, SystemSetting::getMaxFileSize());

        // بعد الحفظ بالكيلوبايت
        SystemSetting::setMaxFileSize(15360); // 15 MB
        $this->assertEquals(15360, SystemSetting::getMaxFileSize());
        $this->assertEquals('15360', SystemSetting::get('max_file_size'));
    }

    public function test_general_settings_page_can_save_max_file_size(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(GeneralSettingsPage::class)
            ->fillForm([
                'max_file_size_mb' => 25,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(25600, SystemSetting::getMaxFileSize());
        $this->assertEquals(25600, config('filesystems.max_file_size'));
    }

    public function test_general_settings_page_validates_minimum_file_size(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(GeneralSettingsPage::class)
            ->fillForm([
                'max_file_size_mb' => 0,
            ])
            ->call('save')
            ->assertHasFormErrors(['max_file_size_mb']);
    }
}
