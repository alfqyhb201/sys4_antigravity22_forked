<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use App\Services\TagImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagImportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TagImportService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagImportService;
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_read_csv_file_and_return_rows()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'importance', 'tag_group'],
            ['tag_one', 'high', 'group_a'],
            ['tag_two', 'medium', 'group_b'],
        ]);

        $rows = $this->service->readFile($csvPath);

        $this->assertCount(2, $rows);
        $this->assertEquals('tag_one', $rows[0]['name']);
        $this->assertEquals('high', $rows[0]['importance']);
        $this->assertEquals('tag_two', $rows[1]['name']);
        $this->assertEquals('group_b', $rows[1]['tag_group']);
    }

    /** @test */
    public function it_can_detect_columns_from_csv()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'importance', 'categories', 'locations'],
            ['x', 'high', 'cat1', 'loc1'],
        ]);

        $columns = $this->service->detectColumns($csvPath);

        $this->assertEquals(['name', 'importance', 'categories', 'locations'], $columns);
    }

    /** @test */
    public function it_can_preview_rows_with_column_map()
    {
        $csvPath = $this->createTempCsv([
            ['الاسم', 'الأهمية', 'المجموعة'],
            ['tag_a', 'high', 'group_x'],
            ['tag_b', 'low', 'group_y'],
            ['tag_c', 'medium', 'group_z'],
        ]);

        $columnMap = [
            'name' => 'الاسم',
            'importance' => 'الأهمية',
            'tag_group' => 'المجموعة',
        ];

        $preview = $this->service->previewRows($csvPath, $columnMap, 2);

        $this->assertCount(2, $preview);
        $this->assertEquals('tag_a', $preview[0]['name']);
        $this->assertEquals('high', $preview[0]['importance']);
        $this->assertEquals('tag_b', $preview[1]['name']);
    }

    /** @test */
    public function it_can_import_new_tags_from_csv()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'importance', 'is_active'],
            ['tag_imported_1', 'high', '1'],
            ['tag_imported_2', 'low', '1'],
        ]);

        $columnMap = [
            'name' => 'name',
            'importance' => 'importance',
            'is_active' => 'is_active',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(2, $result->total);
        $this->assertEquals(2, $result->successful);
        $this->assertEquals(0, $result->failed);

        $this->assertDatabaseHas('tags', [
            'name' => 'tag_imported_1',
            'importance' => 'high',
        ]);
        $this->assertDatabaseHas('tags', [
            'name' => 'tag_imported_2',
            'importance' => 'low',
        ]);
    }

    /** @test */
    public function it_can_update_existing_tag_if_name_already_exists()
    {
        $existingTag = Tag::factory()->create([
            'name' => 'existing_tag',
            'importance' => 'low',
        ]);

        $csvPath = $this->createTempCsv([
            ['name', 'importance'],
            ['existing_tag', 'high'],
        ]);

        $columnMap = [
            'name' => 'name',
            'importance' => 'importance',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->successful);

        $this->assertDatabaseHas('tags', [
            'name' => 'existing_tag',
            'importance' => 'high',
        ]);
    }

    /** @test */
    public function it_requires_name_field()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'importance'],
            ['', 'high'],
        ]);

        $columnMap = [
            'name' => 'name',
            'importance' => 'importance',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->total);
        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
        $this->assertStringContainsString('مطلوب', $result->errors[2] ?? '');
    }

    /** @test */
    public function it_rejects_invalid_importance_value()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'importance'],
            ['bad_tag', 'invalid_importance'],
        ]);

        $columnMap = [
            'name' => 'name',
            'importance' => 'importance',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
        $this->assertStringContainsString('غير صالحة', $result->errors[2] ?? '');
    }

    /** @test */
    public function it_resolves_tag_group_relationship()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'tag_group'],
            ['tag_with_group', 'my_test_group'],
        ]);

        $columnMap = [
            'name' => 'name',
            'tag_group' => 'tag_group',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertDatabaseHas('tags_groups', [
            'name' => 'my_test_group',
        ]);

        $tagGroup = TagGroup::where('name', 'my_test_group')->first();
        $this->assertDatabaseHas('tags', [
            'name' => 'tag_with_group',
            'tag_group_id' => $tagGroup->id,
        ]);
    }

    /** @test */
    public function it_resolves_categories_relationship()
    {
        Category::factory()->create(['name' => 'تصميم جرافيك']);

        $csvPath = $this->createTempCsv([
            ['name', 'categories'],
            ['tag_with_cats', 'تصميم جرافيك'],
        ]);

        $columnMap = [
            'name' => 'name',
            'categories' => 'categories',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $tag = Tag::where('name', 'tag_with_cats')->first();
        $this->assertNotNull($tag);
        $this->assertCount(1, $tag->categories);
        $this->assertEquals('تصميم جرافيك', $tag->categories->first()->name);
    }

    /** @test */
    public function it_creates_new_category_if_not_exists()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'categories'],
            ['tag_new_cat', 'قسم جديد'],
        ]);

        $columnMap = [
            'name' => 'name',
            'categories' => 'categories',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertDatabaseHas('categories', ['name' => 'قسم جديد']);
    }

    /** @test */
    public function it_resolves_locations_relationship()
    {
        Location::factory()->create(['name' => 'الرياض']);

        $csvPath = $this->createTempCsv([
            ['name', 'locations'],
            ['tag_with_loc', 'الرياض'],
        ]);

        $columnMap = [
            'name' => 'name',
            'locations' => 'locations',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $tag = Tag::where('name', 'tag_with_loc')->first();
        $this->assertNotNull($tag);
        $this->assertCount(1, $tag->locations);
        $this->assertEquals('الرياض', $tag->locations->first()->name);
    }

    /** @test */
    public function it_adds_warning_for_non_existent_client()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'clients'],
            ['tag_no_client', 'شركة غير موجودة'],
        ]);

        $columnMap = [
            'name' => 'name',
            'clients' => 'clients',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(1, $result->successful);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('غير موجود', array_values($result->warnings)[0]);
    }

    /** @test */
    public function it_resolves_multiple_clients_by_company_name()
    {
        $category = Category::factory()->create();
        $client1 = Client::factory()->create(['company' => 'شركة الأمل', 'category_id' => $category->id]);
        $client2 = Client::factory()->create(['company' => 'شركة النور', 'category_id' => $category->id]);

        $csvPath = $this->createTempCsv([
            ['name', 'clients'],
            ['tag_multi_clients', 'شركة الأمل,شركة النور'],
        ]);

        $columnMap = [
            'name' => 'name',
            'clients' => 'clients',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $tag = Tag::where('name', 'tag_multi_clients')->first();
        $this->assertNotNull($tag);
        $this->assertCount(2, $tag->clients);
    }

    /** @test */
    public function it_handles_boolean_fields_various_formats()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'is_active', 'is_auto_assigned'],
            ['tag_bool_1', '1', '0'],
            ['tag_bool_2', 'true', 'false'],
            ['tag_bool_3', 'yes', 'no'],
        ]);

        $columnMap = [
            'name' => 'name',
            'is_active' => 'is_active',
            'is_auto_assigned' => 'is_auto_assigned',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(3, $result->successful);

        $this->assertDatabaseHas('tags', ['name' => 'tag_bool_1', 'is_active' => 1, 'is_auto_assigned' => 0]);
        $this->assertDatabaseHas('tags', ['name' => 'tag_bool_2', 'is_active' => 1, 'is_auto_assigned' => 0]);
        $this->assertDatabaseHas('tags', ['name' => 'tag_bool_3', 'is_active' => 1, 'is_auto_assigned' => 0]);
    }

    /** @test */
    public function it_handles_date_field()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'date_for_sending_yearly', 'is_there_date_for_sending'],
            ['tag_date', '2026-12-25', '1'],
        ]);

        $columnMap = [
            'name' => 'name',
            'date_for_sending_yearly' => 'date_for_sending_yearly',
            'is_there_date_for_sending' => 'is_there_date_for_sending',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertDatabaseHas('tags', [
            'name' => 'tag_date',
            'is_there_date_for_sending' => 1,
            'date_for_sending_yearly' => '2026-12-25',
        ]);
    }

    /** @test */
    public function it_handles_invalid_date()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'date_for_sending_yearly'],
            ['tag_bad_date', 'not-a-date'],
        ]);

        $columnMap = [
            'name' => 'name',
            'date_for_sending_yearly' => 'date_for_sending_yearly',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
    }

    /** @test */
    public function it_handles_repetition_field()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'is_repetition', 'repetition', 'weekly_times', 'is_there_date_for_sending'],
            ['tag_repeat', '1', 'weekly', '3', '1'],
        ]);

        $columnMap = [
            'name' => 'name',
            'is_repetition' => 'is_repetition',
            'repetition' => 'repetition',
            'weekly_times' => 'weekly_times',
            'is_there_date_for_sending' => 'is_there_date_for_sending',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertDatabaseHas('tags', [
            'name' => 'tag_repeat',
            'is_repetition' => 1,
            'repetition' => 'weekly',
            'weekly_times' => 3,
            'is_there_date_for_sending' => 1,
        ]);
    }

    /** @test */
    public function it_rejects_invalid_repetition()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'repetition'],
            ['bad_repeat', 'daily'],
        ]);

        $columnMap = [
            'name' => 'name',
            'repetition' => 'repetition',
        ];

        $result = $this->service->import($csvPath, $columnMap, $this->user->id);

        $this->assertEquals(0, $result->successful);
        $this->assertEquals(1, $result->failed);
    }

    /** @test */
    public function it_parses_weekly_day_as_array()
    {
        $csvPath = $this->createTempCsv([
            ['name', 'weekly_day', 'is_there_date_for_sending'],
            ['tag_days', 'Saturday,Monday', '1'],
        ]);

        $columnMap = [
            'name' => 'name',
            'weekly_day' => 'weekly_day',
            'is_there_date_for_sending' => 'is_there_date_for_sending',
        ];

        $this->service->import($csvPath, $columnMap, $this->user->id);

        $tag = Tag::where('name', 'tag_days')->first();
        $this->assertNotNull($tag);
        $this->assertEquals(['Saturday', 'Monday'], $tag->weekly_day);
        $this->assertTrue($tag->is_there_date_for_sending);
    }

    /**
     * إنشاء ملف CSV مؤقت للاختبار.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function createTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tag_import_test_').'.csv';

        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->assertFileExists($path);

        return $path;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
