<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_import_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rows = [
            ['اسم التصنيف' => 'تصميم الجرافيك'],
            ['اسم التصنيف' => 'التسويق الرقمي'],
        ];

        $columnMap = [
            'name' => 'اسم التصنيف',
        ];

        $service = new CategoryImportService;
        $result = $service->import($rows, $columnMap);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('categories', ['name' => 'تصميم الجرافيك']);
        $this->assertDatabaseHas('categories', ['name' => 'التسويق الرقمي']);
    }

    public function test_skips_empty_name_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rows = [
            ['اسم التصنيف' => ''],
            ['اسم التصنيف' => 'المحتوى الإبداعي'],
        ];

        $columnMap = ['name' => 'اسم التصنيف'];

        $service = new CategoryImportService;
        $result = $service->import($rows, $columnMap);

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_firstorcreate_avoids_duplicates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Category::factory()->create(['name' => 'موجود مسبقاً']);

        $rows = [
            ['اسم التصنيف' => 'موجود مسبقاً'],
        ];

        $columnMap = ['name' => 'اسم التصنيف'];

        $service = new CategoryImportService;
        $result = $service->import($rows, $columnMap);

        $this->assertEquals(1, $result['imported']);
        $this->assertDatabaseCount('categories', 1);
    }
}
