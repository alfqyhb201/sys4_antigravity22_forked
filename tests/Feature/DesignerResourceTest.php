<?php

namespace Tests\Feature;

use App\Filament\Resources\DesignerResource;
use App\Models\Category;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DesignerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'admin_test',
        ]);

        Permission::firstOrCreate(['name' => 'view_any_designer']);
        Permission::firstOrCreate(['name' => 'view_designer']);
        Permission::firstOrCreate(['name' => 'create_designer']);
        Permission::firstOrCreate(['name' => 'update_designer']);
        Permission::firstOrCreate(['name' => 'delete_designer']);

        $this->user->givePermissionTo([
            'view_any_designer',
            'view_designer',
            'create_designer',
            'update_designer',
            'delete_designer',
        ]);

        $this->actingAs($this->user);
    }

    public function test_get_eloquent_query_eager_loads_relations(): void
    {
        $designerUser = User::factory()->create(['name' => 'مصمم اختبار']);
        $category = Category::create(['name' => 'تصميم جرافيك']);

        $designer = Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 10,
            'max_capacity' => 50,
            'rate' => 8.0,
        ]);
        $designer->categories()->attach($category->id);

        $queryResult = DesignerResource::getEloquentQuery()->where('id', $designer->id)->first();

        $this->assertNotNull($queryResult);
        $this->assertTrue($queryResult->relationLoaded('user'), 'User relation should be eager loaded');
        $this->assertTrue($queryResult->relationLoaded('categories'), 'Categories relation should be eager loaded');
    }

    public function test_can_render_designers_list_page(): void
    {
        $designerUser = User::factory()->create(['name' => 'مصمم رئيسي']);
        $designer = Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 5,
            'max_capacity' => 20,
            'rate' => 9.0,
            'amount_of_designs' => 120,
        ]);

        $this->get(DesignerResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('مصمم رئيسي')
            ->assertSee('رصيد الخبرة السابق');
    }

    public function test_can_render_designer_create_page(): void
    {
        $this->get(DesignerResource::getUrl('create'))
            ->assertSuccessful()
            ->assertSee('بيانات الحساب والوظيفة')
            ->assertSee('محددات الطاقة والتقييم')
            ->assertSee('الأجهزة والبيانات الإدارية');
    }

    public function test_can_render_designer_edit_page(): void
    {
        $designer = Designer::factory()->create();

        $this->get(DesignerResource::getUrl('edit', ['record' => $designer]))
            ->assertSuccessful()
            ->assertSee('بيانات الحساب والوظيفة')
            ->assertSee('محددات الطاقة والتقييم');
    }
}
