<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemLogViewerPage;
use App\Models\User;
use App\Services\SystemLogReaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemLogViewerPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularAdmin;

    protected User $regularUser;

    protected string $testLogFile;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء الأدوار
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@test.com',
            'username' => 'superadmin',
        ]);
        $this->superAdmin->assignRole($superAdminRole);

        $this->regularAdmin = User::factory()->create([
            'email' => 'admin@test.com',
            'username' => 'regularadmin',
        ]);
        $this->regularAdmin->assignRole($adminRole);

        $this->regularUser = User::factory()->create([
            'email' => 'user@test.com',
            'username' => 'regularuser',
        ]);
        $this->regularUser->assignRole($userRole);

        // إنشاء ملف لوج اختباري معزول
        $this->testLogFile = storage_path('logs/test-sample.log');
        $sampleLogs = <<<'LOG'
[2026-10-05 12:00:00] local.INFO: Application started successfully. [] []
[2026-10-05 12:05:00] local.WARNING: Low disk space detected on disk C. [] []
[2026-10-05 12:10:00] local.ERROR: Database connection timed out. {"exception":"[object] (PDOException(code: 2002))"}
[stacktrace]
#0 /app/Database.php(15): PDO->__construct()
#1 {main}
[2026-10-05 12:15:00] local.DEBUG: Memory usage: 12MB [] []
LOG;
        File::put($this->testLogFile, $sampleLogs);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testLogFile)) {
            File::delete($this->testLogFile);
        }

        parent::tearDown();
    }

    public function test_system_log_reader_service_parses_and_filters_logs(): void
    {
        $service = app(SystemLogReaderService::class);

        // 1. قراءة كافة السجلات
        $result = $service->getLogs('test-sample.log', 'all', null, 1, 10);
        $this->assertEquals(4, $result['total_in_file']);
        $this->assertEquals(4, $result['total_matching']);
        $this->assertEquals(1, $result['stats']['error_count']);
        $this->assertEquals(1, $result['stats']['warning_count']);
        $this->assertEquals(1, $result['stats']['info_count']);
        $this->assertEquals(1, $result['stats']['debug_count']);

        // الأحدث أولاً
        $this->assertEquals('DEBUG', $result['entries'][0]['level']);

        // 2. فلترة الأخطاء فقط
        $errors = $service->getLogs('test-sample.log', 'errors', null, 1, 10);
        $this->assertEquals(1, $errors['total_matching']);
        $this->assertEquals('ERROR', $errors['entries'][0]['level']);
        $this->assertStringContainsString('Database connection timed out', $errors['entries'][0]['message']);
        $this->assertStringContainsString('PDO->__construct()', $errors['entries'][0]['stack_trace']);

        // 3. بحث نصي
        $search = $service->getLogs('test-sample.log', 'all', 'disk space', 1, 10);
        $this->assertEquals(1, $search['total_matching']);
        $this->assertEquals('WARNING', $search['entries'][0]['level']);
    }

    public function test_regular_user_and_admin_cannot_access_system_logs_page(): void
    {
        $this->actingAs($this->regularUser);
        $this->get(SystemLogViewerPage::getUrl())
            ->assertStatus(403);

        $this->actingAs($this->regularAdmin);
        $this->get(SystemLogViewerPage::getUrl())
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_system_logs_page(): void
    {
        $this->actingAs($this->superAdmin);

        $this->get(SystemLogViewerPage::getUrl())
            ->assertStatus(200)
            ->assertSee('مستعرض سجلات الأخطاء');
    }

    public function test_super_admin_can_interact_with_logs_via_livewire(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(SystemLogViewerPage::class)
            ->call('changeFile', 'test-sample.log')
            ->assertSet('selectedFile', 'test-sample.log')
            ->call('changeLevel', 'errors')
            ->assertSet('selectedLevel', 'errors')
            ->set('search', 'Database')
            ->assertSee('Database connection timed out')
            ->call('clearLog')
            ->assertNotified('تم تفريغ ملف السجل بنجاح ✅');

        $this->assertEquals(0, filesize($this->testLogFile));
    }
}
