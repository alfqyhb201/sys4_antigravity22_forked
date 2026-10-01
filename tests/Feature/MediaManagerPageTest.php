<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaManagerPage;
use App\Models\Client;
use App\Models\DesignTask;
use App\Models\User;
use App\Services\MediaManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaManagerPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@example.com',
        ]);

        $this->adminUser->assignRole($role);
    }

    public function test_media_manager_page_accessible_by_admin(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertTrue(MediaManagerPage::canAccess());

        Livewire::test(MediaManagerPage::class)
            ->assertSuccessful();
    }

    public function test_media_manager_page_denied_for_unauthorized_users(): void
    {
        $regularUser = User::factory()->create([
            'name' => 'مستخدم عادي',
            'email' => 'user@example.com',
        ]);

        $this->actingAs($regularUser);

        $this->assertFalse(MediaManagerPage::canAccess());
    }

    public function test_media_manager_service_identifies_orphaned_and_linked_files(): void
    {
        // 1. إنشاء ملف مرتبط بمهمة تصميم في التخزين
        $linkedPath = 'clients/10/design-tasks/1/output/design.jpg';
        Storage::disk('public')->put($linkedPath, 'fake image content');

        // إنشاء مهمة في قاعدة البيانات تحتوي على المسار
        DesignTask::factory()->create([
            'design_files' => [$linkedPath],
        ]);

        // 2. إنشاء ملف مرتبط بشعار عميل
        $category = \App\Models\Category::factory()->create();
        $logoPath = 'clients/logos/client_logo.png';
        Storage::disk('public')->put($logoPath, 'fake logo content');
        Client::factory()->create([
            'category_id' => $category->id,
            'logo_path' => $logoPath,
        ]);

        // 3. إنشاء ملف يتيم غير مسجل في أي جدول
        $orphanPath = 'clients/999/design-tasks/999/output/orphan.png';
        Storage::disk('public')->put($orphanPath, 'orphan file content');

        $service = app(MediaManagerService::class);
        $scanned = $service->scanFiles('public', null, null, null, false);

        $this->assertCount(3, $scanned);

        $orphanedFiles = array_filter($scanned, fn ($f) => $f['is_orphan']);
        $linkedFiles = array_filter($scanned, fn ($f) => ! $f['is_orphan']);

        $this->assertCount(1, $orphanedFiles);
        $this->assertCount(2, $linkedFiles);

        $this->assertEquals($orphanPath, array_values($orphanedFiles)[0]['path']);
    }

    public function test_media_manager_page_filters_and_deletes_single_file(): void
    {
        $this->actingAs($this->adminUser);

        $orphanFile = 'clients/123/design-tasks/456/output/banner.jpg';
        Storage::disk('public')->put($orphanFile, 'test content');

        Livewire::test(MediaManagerPage::class)
            ->assertSee('banner.jpg')
            ->call('deleteSingleFile', $orphanFile, 'public');

        Storage::disk('public')->assertMissing($orphanFile);
    }

    public function test_media_manager_page_bulk_delete_selected_files(): void
    {
        $this->actingAs($this->adminUser);

        $file1 = 'clients/1/test1.jpg';
        $file2 = 'clients/1/test2.jpg';
        Storage::disk('public')->put($file1, 'content 1');
        Storage::disk('public')->put($file2, 'content 2');

        Livewire::test(MediaManagerPage::class)
            ->set('selectedFiles', [$file1, $file2])
            ->call('deleteSelected');

        Storage::disk('public')->assertMissing($file1);
        Storage::disk('public')->assertMissing($file2);
    }

    public function test_clean_all_orphaned_files(): void
    {
        $this->actingAs($this->adminUser);

        $orphanFile = 'ideas/orphan_idea.png';
        Storage::disk('public')->put($orphanFile, 'orphan idea');

        // تقديم وقت وهمي للملف لتجاوز حاجز الأمان
        touch(Storage::disk('public')->path($orphanFile), now()->subHours(2)->timestamp);

        $service = app(MediaManagerService::class);
        $result = $service->cleanAllOrphanedFiles(1);

        $this->assertGreaterThanOrEqual(1, $result['deleted_count']);
        Storage::disk('public')->assertMissing($orphanFile);
    }

    public function test_clean_livewire_temp_files(): void
    {
        $this->actingAs($this->adminUser);

        $tempFile = 'livewire-tmp/temp_upload.jpg';
        Storage::disk('local')->put($tempFile, 'temporary upload');

        touch(Storage::disk('local')->path($tempFile), now()->subHours(30)->timestamp);

        $service = app(MediaManagerService::class);
        $result = $service->cleanLivewireTmp(24);

        $this->assertEquals(1, $result['deleted_count']);
        Storage::disk('local')->assertMissing($tempFile);
    }
}
