<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemOperationsPage;
use App\Models\User;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemOperationsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_system_health_service_returns_complete_metrics(): void
    {
        $service = app(SystemHealthService::class);
        $overview = $service->getSystemHealthOverview();

        $this->assertArrayHasKey('environment', $overview);
        $this->assertArrayHasKey('database', $overview);
        $this->assertArrayHasKey('storage', $overview);
        $this->assertArrayHasKey('drivers', $overview);
        $this->assertArrayHasKey('maintenance', $overview);

        $this->assertEquals(PHP_VERSION, $overview['environment']['php_version']);
        $this->assertEquals(app()->version(), $overview['environment']['laravel_version']);
        $this->assertNotNull($overview['database']['driver']);
    }

    public function test_regular_user_and_admin_cannot_access_system_operations_page(): void
    {
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        $this->get(SystemOperationsPage::getUrl())
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(SystemOperationsPage::getUrl())
            ->assertForbidden();
    }

    public function test_super_admin_can_access_system_operations_page(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        $this->get(SystemOperationsPage::getUrl())
            ->assertSuccessful()
            ->assertSee('مركز صيانة وعمليات النظام')
            ->assertSee('أدوات تنظيف وإفراغ الكاش');
    }

    public function test_livewire_cache_clearing_actions_work(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        Livewire::test(SystemOperationsPage::class)
            ->call('clearAppCache')
            ->assertHasNoErrors()
            ->assertNotified('تم تفريغ كاش التطبيق وكاش الإعدادات (Config & Cache) بنجاح.')
            ->call('clearRouteCache')
            ->assertHasNoErrors()
            ->assertNotified('تم تفريغ كاش المسارات (Routes) بنجاح.')
            ->call('clearViewCache')
            ->assertHasNoErrors()
            ->assertNotified('تم تفريغ كاش القوالب (Blade Views) بنجاح.')
            ->call('clearFilamentCache')
            ->assertHasNoErrors()
            ->assertNotified('تم تفريغ كاش لوحة Filament ومكوناتها وأيقوناتها بنجاح.')
            ->call('clearAllCache')
            ->assertHasNoErrors()
            ->assertNotified('تم تنظيف وإفراغ كافة ملفات الكاش للنظام بالكامل بنجاح ⚡');
    }

    public function test_maintenance_mode_can_be_enabled_and_disabled(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        Livewire::test(SystemOperationsPage::class)
            ->set('maintenanceSecret', 'testsecrettoken123')
            ->call('enableMaintenance')
            ->assertHasNoErrors();

        $this->assertTrue(app()->isDownForMaintenance());

        // إعادة تشغيل النظام
        Livewire::test(SystemOperationsPage::class)
            ->call('disableMaintenance')
            ->assertHasNoErrors();

        $this->assertFalse(app()->isDownForMaintenance());
    }
}
