<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManagerPage;
use App\Models\User;
use App\Services\BackupManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupManagerPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['backup.backup.destination.disks' => ['local']]);
        config(['backup.backup.name' => 'TestApp']);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'designer', 'guard_name' => 'web']);
    }

    public function test_backup_manager_service_lists_and_manages_files(): void
    {
        $service = app(BackupManagerService::class);

        // إنشاء ملف نسخة احتياطية وهمي
        Storage::disk('local')->put('TestApp/test-backup-2026.zip', 'dummy zip contents');

        $backups = $service->getBackups();
        $this->assertCount(1, $backups);
        $this->assertEquals('test-backup-2026.zip', $backups[0]['filename']);
        $this->assertEquals('local', $backups[0]['disk']);

        $stats = $service->getStatistics();
        $this->assertEquals(1, $stats['total_count']);
        $this->assertNotNull($stats['total_size_formatted']);

        // اختبار الحذف
        $deleted = $service->deleteBackup('local', 'TestApp/test-backup-2026.zip');
        $this->assertTrue($deleted);
        $this->assertCount(0, $service->getBackups());
    }

    public function test_regular_user_and_admin_cannot_access_backup_manager(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(BackupManagerPage::getUrl())
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(BackupManagerPage::getUrl())
            ->assertForbidden();
    }

    public function test_super_admin_can_access_backup_manager(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        $this->get(BackupManagerPage::getUrl())
            ->assertSuccessful()
            ->assertSee('مركز النسخ الاحتياطي للنظام')
            ->assertSee('نسخة قاعدة البيانات فقط');
    }

    public function test_super_admin_can_delete_backup_via_livewire(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        // إنشاء ملف وهمي
        Storage::disk('local')->put('TestApp/backup-to-delete.zip', 'content');

        Livewire::test(BackupManagerPage::class)
            ->call('deleteBackup', 'local', 'TestApp/backup-to-delete.zip')
            ->assertHasNoErrors()
            ->assertNotified('تم حذف ملف النسخة الاحتياطية بنجاح.');

        $this->assertFalse(Storage::disk('local')->exists('TestApp/backup-to-delete.zip'));
    }

    public function test_super_admin_can_open_modal_and_run_backup(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        Livewire::test(BackupManagerPage::class)
            ->call('openBackupModal', 'db')
            ->assertSet('showBackupModal', true)
            ->assertSet('backupType', 'db')
            ->set('targetDisks', ['local'])
            ->call('runSelectedBackup')
            ->assertSet('showBackupModal', false)
            ->assertNotified();
    }
}
