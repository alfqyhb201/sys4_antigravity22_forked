<?php

namespace Tests\Feature;

use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Models\Category;
use App\Models\Client;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class TagImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TagGroup $tagGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'testuser',
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_tag']);

        $this->user->givePermissionTo([
            'view_any_tag',
            'view_tag',
            'create_tag',
            'update_tag',
            'delete_tag',
        ]);

        $this->actingAs($this->user);

        $this->tagGroup = TagGroup::create([
            'name' => 'مجموعة اختبارية',
            'added_by_user' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_can_import_tag_with_basic_fields(): void
    {
        $csvContent = "name,importance,tag_group\nوسم مستورد,high,مجموعة اختبارية";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم مستورد',
            'importance' => 'high',
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);

        $tag = Tag::where('name', 'وسم مستورد')->first();
        $this->assertNotNull($tag);
        $this->assertEquals($this->tagGroup->id, $tag->tag_group_id);
    }

    /** @test */
    public function test_can_import_tag_with_new_tag_group(): void
    {
        $csvContent = "name,tag_group\nوسم بمجموعة جديدة,مجموعة جديدة للاستيراد";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم بمجموعة جديدة',
        ]);

        $this->assertDatabaseHas('tags_groups', [
            'name' => 'مجموعة جديدة للاستيراد',
        ]);

        $tag = Tag::where('name', 'وسم بمجموعة جديدة')->first();
        $this->assertNotNull($tag->tagGroup);
        $this->assertEquals('مجموعة جديدة للاستيراد', $tag->tagGroup->name);
    }

    /** @test */
    public function test_can_import_tag_with_categories_locations_and_clients(): void
    {
        // إنشاء عميل مسبقاً لأن category_id مطلوب
        $category = Category::create(['name' => 'تصنيف أ']);
        $client = Client::create([
            'company' => 'شركة عميل',
            'category_id' => $category->id,
        ]);

        $csvContent = "name,tag_group,categories,locations,clients\nوسم متعدد,مجموعة اختبارية,تصنيف أ,موقع أ,شركة عميل";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $tag = Tag::where('name', 'وسم متعدد')->first();
        $this->assertNotNull($tag);

        $this->assertDatabaseHas('locations', ['name' => 'موقع أ']);

        $this->assertCount(1, $tag->categories);
        $this->assertCount(1, $tag->locations);
        $this->assertCount(1, $tag->clients);

        $this->assertEquals('تصنيف أ', $tag->categories->first()->name);
        $this->assertEquals('موقع أ', $tag->locations->first()->name);
        $this->assertEquals('شركة عميل', $tag->clients->first()->company);
    }

    /** @test */
    public function test_can_import_tag_with_multiple_relations(): void
    {
        // إنشاء علاقات مسبقة لضمان عدم التكرار
        Category::create(['name' => 'تصنيف 1']);
        Category::create(['name' => 'تصنيف 2']);
        $cat = Category::first();
        Client::create(['company' => 'شركة 1', 'category_id' => $cat->id]);
        Client::create(['company' => 'شركة 2', 'category_id' => $cat->id]);

        $csvContent = "name,tag_group,categories,locations,clients\nوسم علاقات متعددة,مجموعة اختبارية,\"تصنيف 1, تصنيف 2\",\"موقع أ, موقع ب\",\"شركة 1, شركة 2\"";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $tag = Tag::where('name', 'وسم علاقات متعددة')->first();
        $this->assertNotNull($tag);
        $this->assertCount(2, $tag->categories);
        $this->assertCount(2, $tag->locations);
        $this->assertCount(2, $tag->clients);
    }

    /** @test */
    public function test_updates_existing_tag_on_reimport(): void
    {
        // إنشاء وسم موجود مسبقاً
        Tag::create([
            'name' => 'وسم موجود',
            'importance' => 'low',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);

        $csvContent = "name,importance,tag_group\nوسم موجود,veryhigh,مجموعة اختبارية";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم موجود',
            'importance' => 'veryhigh',
        ]);

        // التأكد من عدم وجود وسم مكرر
        $this->assertEquals(1, Tag::where('name', 'وسم موجود')->count());
    }

    /** @test */
    public function test_import_tag_with_scheduling_fields(): void
    {
        $csvContent = "name,tag_group,is_there_date_for_sending,weekly_day,weekly_time,weekly_time_sm\nوسم مجدول,مجموعة اختبارية,1,\"Monday, Wednesday\",10:00,14:00";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $tag = Tag::where('name', 'وسم مجدول')->first();
        $this->assertNotNull($tag);
        $this->assertTrue($tag->is_there_date_for_sending);
        $this->assertIsArray($tag->weekly_day);
        $this->assertContains('Monday', $tag->weekly_day);
        $this->assertContains('Wednesday', $tag->weekly_day);
        $this->assertEquals('10:00', $tag->weekly_time);
        $this->assertEquals('14:00', $tag->weekly_time_sm);
    }

    /** @test */
    public function test_import_clears_scheduling_fields_when_sending_disabled(): void
    {
        $csvContent = "name,tag_group,is_there_date_for_sending,weekly_day,weekly_time\nوسم بدون جدولة,مجموعة اختبارية,0,Monday,10:00";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $tag = Tag::where('name', 'وسم بدون جدولة')->first();
        $this->assertNotNull($tag);
        $this->assertFalse($tag->is_there_date_for_sending);
        // يجب أن يتم تصفير الحقول بواسطة حدث saving في الـ Model
        $this->assertNull($tag->weekly_day);
        $this->assertNull($tag->weekly_time);
        $this->assertNull($tag->weekly_time_sm);
        $this->assertNull($tag->weekly_times);
        $this->assertNull($tag->date_for_sending_yearly);
    }

    /** @test */
    public function test_import_reports_failed_rows_when_name_missing(): void
    {
        $csvContent = "name,tag_group\n,مجموعة اختبارية";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        // التحقق من أن الاستيراد سجل فشلاً للصف الذي لا يحتوي على اسم
        $import = Import::latest()->first();
        $this->assertNotNull($import);
        $this->assertGreaterThan(0, $import->getFailedRowsCount());
    }

    /** @test */
    public function test_import_sets_user_tracking_fields(): void
    {
        $csvContent = "name,tag_group\nوسم بتتبع مستخدم,مجموعة اختبارية";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم بتتبع مستخدم',
            'added_by_user' => $this->user->id,
            'updated_by_user' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_can_import_with_arabic_column_headers(): void
    {
        // إنشاء عميل مسبقاً لأن category_id مطلوب
        $category = Category::create(['name' => 'تصنيف تجريبي']);
        $client = Client::create([
            'company' => 'شركة تجريبية',
            'category_id' => $category->id,
        ]);

        $csvContent = "الاسم,مجموعة الوسوم,التصنيفات,المواقع,العملاء\nوسم عربي,مجموعة اختبارية,تصنيف تجريبي,موقع تجريبي,شركة تجريبية";
        $file = UploadedFile::fake()->createWithContent('tags.csv', $csvContent);

        Livewire::test(ListTags::class)
            ->callTableAction('import', null, ['file' => $file]);

        $tag = Tag::where('name', 'وسم عربي')->first();
        $this->assertNotNull($tag);

        $this->assertCount(1, $tag->categories);
        $this->assertCount(1, $tag->locations);
        $this->assertCount(1, $tag->clients);

        $this->assertEquals('تصنيف تجريبي', $tag->categories->first()->name);
        $this->assertEquals('موقع تجريبي', $tag->locations->first()->name);
        $this->assertEquals('شركة تجريبية', $tag->clients->first()->company);
    }
}
