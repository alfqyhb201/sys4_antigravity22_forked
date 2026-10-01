<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Models\Category;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientTagFilterTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'testuser_client_tags',
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_client']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_client']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_client']);

        $this->user->givePermissionTo([
            'view_any_client',
            'create_client',
            'update_client',
        ]);

        $this->actingAs($this->user);
    }

    public function test_tag_options_are_filtered_by_client_category_and_location(): void
    {
        $categoryTobacco = Category::create(['name' => 'سجائر ومنتجات التبغ']);
        $categoryFood = Category::create(['name' => 'مواد غذائية']);

        $locationSanaa = Location::create(['name' => 'صنعاء']);
        $locationAden = Location::create(['name' => 'عدن']);

        $groupTobacco = TagGroup::create(['name' => 'مجموعة سجائر', 'assign_all_categories' => false]);
        $groupTobacco->categories()->sync([$categoryTobacco->id]);

        $groupFood = TagGroup::create(['name' => 'مجموعة أغذية', 'assign_all_categories' => false]);
        $groupFood->categories()->sync([$categoryFood->id]);

        // وسم خاص بالتبغ في صنعاء
        $tagTobaccoSanaa = Tag::create([
            'name' => 'وسم سجائر صنعاء',
            'tag_group_id' => $groupTobacco->id,
            'assign_all_categories' => false,
            'assign_all_locations' => false,
            'is_active' => true,
        ]);
        $tagTobaccoSanaa->categories()->sync([$categoryTobacco->id]);
        $tagTobaccoSanaa->locations()->sync([$locationSanaa->id]);

        // وسم خاص بالأغذية في صنعاء
        $tagFoodSanaa = Tag::create([
            'name' => 'وسم أغذية صنعاء',
            'tag_group_id' => $groupFood->id,
            'assign_all_categories' => false,
            'assign_all_locations' => false,
            'is_active' => true,
        ]);
        $tagFoodSanaa->categories()->sync([$categoryFood->id]);
        $tagFoodSanaa->locations()->sync([$locationSanaa->id]);

        // وسم شامل لكل التصنيفات والمواقع (مثل الجمعة)
        $tagGeneral = Tag::create([
            'name' => 'وسم عام الجمعة',
            'tag_group_id' => $groupTobacco->id,
            'assign_all_categories' => true,
            'assign_all_locations' => true,
            'is_active' => true,
        ]);

        $component = Livewire::test(CreateClient::class)
            ->set('data.category_id', $categoryTobacco->id)
            ->set('data.location_id', $locationSanaa->id);

        // يجب أن يرى وسم السجائر والوسم العام، ولا يرى وسم الأغذية
        $flatFields = $component->instance()->form->getFlatFields();
        $tagsComponent = $flatFields['tags'] ?? null;
        $this->assertNotNull($tagsComponent);

        $options = $tagsComponent->getOptions();
        $this->assertArrayHasKey($tagTobaccoSanaa->id, $options);
        $this->assertArrayHasKey($tagGeneral->id, $options);
        $this->assertArrayNotHasKey($tagFoodSanaa->id, $options);
    }

    public function test_tag_options_are_further_filtered_when_tag_group_is_selected(): void
    {
        $category = Category::create(['name' => 'تصنيف تجريبي']);
        $location = Location::create(['name' => 'موقع تجريبي']);

        $groupA = TagGroup::create(['name' => 'مجموعة أ', 'assign_all_categories' => true]);
        $groupB = TagGroup::create(['name' => 'مجموعة ب', 'assign_all_categories' => true]);

        $tagA = Tag::create([
            'name' => 'وسم أ',
            'tag_group_id' => $groupA->id,
            'assign_all_categories' => true,
            'assign_all_locations' => true,
            'is_active' => true,
        ]);

        $tagB = Tag::create([
            'name' => 'وسم ب',
            'tag_group_id' => $groupB->id,
            'assign_all_categories' => true,
            'assign_all_locations' => true,
            'is_active' => true,
        ]);

        $component = Livewire::test(CreateClient::class)
            ->set('data.category_id', $category->id)
            ->set('data.location_id', $location->id)
            ->set('data.tag_group_filter', [$groupA->id]);

        $flatFields = $component->instance()->form->getFlatFields();
        $tagsComponent = $flatFields['tags'] ?? null;
        $this->assertNotNull($tagsComponent);

        $options = $tagsComponent->getOptions();

        $this->assertArrayHasKey($tagA->id, $options);
        $this->assertArrayNotHasKey($tagB->id, $options);
    }
}
