<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManagerPage;
use App\Filament\Pages\SystemLogViewerPage;
use App\Filament\Pages\SystemOperationsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $admin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['backup.backup.destination.disks' => ['local']]);
        config(['backup.backup.name' => 'TrueERP']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'superadmin@trueerp.test',
            'username' => 'superadmin',
        ]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->admin = User::factory()->create([
            'name' => 'Regular Admin User',
            'email' => 'admin@trueerp.test',
            'username' => 'regularadmin',
        ]);
        $this->admin->assignRole($adminRole);

        $this->regularUser = User::factory()->create([
            'name' => 'Standard User',
            'email' => 'user@trueerp.test',
            'username' => 'standarduser',
        ]);
        $this->regularUser->assignRole($userRole);
    }

    /**
     * التحقق من الصلاحيات السيادية وحظر المستخدمين الآخرين من صفحات السوبر أدمن.
     */
    public function test_strict_role_access_boundary_for_super_admin_pages(): void
    {
        $superAdminPages = [
            SystemOperationsPage::getUrl(),
            BackupManagerPage::getUrl(),
            SystemLogViewerPage::getUrl(),
        ];

        // 1. فحص المستخدم العادي (403 محظور)
        $this->actingAs($this->regularUser);
        foreach ($superAdminPages as $url) {
            $this->get($url)->assertForbidden();
        }

        // 2. فحص الأدمن العادي (403 محظور)
        $this->actingAs($this->admin);
        foreach ($superAdminPages as $url) {
            $this->get($url)->assertForbidden();
        }

        // 3. فحص السوبر أدمن (200 مسموح)
        $this->actingAs($this->superAdmin);
        foreach ($superAdminPages as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    /**
     * تجربة وتكامل مركز صيانة وعمليات النظام (System Operations).
     */
    public function test_system_operations_page_workflow(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemOperationsPage::class)
            ->assertSee('مواصفات وبيئة الخادم الحية')
            ->assertSee('أدوات تنظيف وإفراغ الكاش')
            ->assertSee('التحكم بوضع الصيانة')
            ->call('clearAppCache')
            ->assertNotified('تم تفريغ كاش التطبيق وكاش الإعدادات (Config & Cache) بنجاح.')
            ->call('clearViewCache')
            ->assertNotified('تم تفريغ كاش القوالب (Blade Views) بنجاح.')
            ->call('clearFilamentCache')
            ->assertNotified('تم تفريغ كاش لوحة Filament ومكوناتها وأيقوناتها بنجاح.');
    }

    /**
     * تجربة وتكامل مركز النسخ الاحتياطي (Backup Manager).
     */
    public function test_backup_manager_page_workflow(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(BackupManagerPage::class)
            ->assertSee('مركز النسخ الاحتياطي للنظام')
            ->call('openBackupModal', 'db')
            ->assertSet('showBackupModal', true)
            ->set('targetDisks', ['local'])
            ->call('runSelectedBackup')
            ->assertSet('showBackupModal', false)
            ->assertNotified();
    }

    /**
     * تجربة وتكامل مستعرض سجلات الأخطاء (System Log Viewer).
     */
    public function test_system_log_viewer_page_workflow(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemLogViewerPage::class)
            ->assertSee('مستعرض سجلات الأخطاء الحية')
            ->assertSee('إجمالي سجلات الملف')
            ->call('changeLevel', 'all')
            ->assertSet('selectedLevel', 'all')
            ->set('search', 'non_existing_search_term_12345')
            ->assertSee('لا توجد سجلات مطابقة');
    }
}
