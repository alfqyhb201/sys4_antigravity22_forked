<?php

namespace Tests\Feature;

use App\Filament\Resources\TagGroupResource;
use App\Filament\Resources\TagGroupResource\Pages\CreateTagGroup;
use App\Filament\Resources\TagGroupResource\Pages\EditTagGroup;
use App\Filament\Resources\TagGroupResource\Pages\ListTagGroups;
use App\Models\Category;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 1,
            'username' => 'testuser_tg',
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_tag_group']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_tag_group']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_tag_group']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_tag_group']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_tag_group']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_tag']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_tag']);

        $this->user->givePermissionTo([
            'view_any_tag_group',
            'view_tag_group',
            'create_tag_group',
            'update_tag_group',
            'delete_tag_group',
            'view_any_tag',
            'view_tag',
            'create_tag',
            'update_tag',
            'delete_tag',
        ]);

        $this->actingAs($this->user);
    }

    public function test_can_render_list_page(): void
    {
        $this->get(TagGroupResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_render_create_page(): void
    {
        $this->get(TagGroupResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_can_render_edit_page(): void
    {
        $tagGroup = TagGroup::create([
            'name' => 'مجموعة قديمة',
            'added_by_user' => $this->user->id,
        ]);

        $this->get(TagGroupResource::getUrl('edit', ['record' => $tagGroup]))
            ->assertSuccessful();
    }

    public function test_can_create_tag_group_with_all_categories_by_default(): void
    {
        $categoryA = Category::create(['name' => 'تصنيف أ']);
        $categoryB = Category::create(['name' => 'تصنيف ب']);

        Livewire::test(CreateTagGroup::class)
            ->fillForm([
                'name' => 'مجموعة شاملة',
                'assign_all_categories' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags_groups', [
            'name' => 'مجموعة شاملة',
            'assign_all_categories' => true,
        ]);

        $tagGroup = TagGroup::where('name', 'مجموعة شاملة')->first();
        $this->assertNotNull($tagGroup);
        $this->assertTrue($tagGroup->categories->contains($categoryA->id));
        $this->assertTrue($tagGroup->categories->contains($categoryB->id));
    }

    public function test_can_create_tag_group_with_custom_categories(): void
    {
        $categoryA = Category::create(['name' => 'تصنيف أ']);
        $categoryB = Category::create(['name' => 'تصنيف ب']);

        Livewire::test(CreateTagGroup::class)
            ->fillForm([
                'name' => 'مجموعة مخصصة',
                'assign_all_categories' => false,
                'categories' => [$categoryA->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tagGroup = TagGroup::where('name', 'مجموعة مخصصة')->first();
        $this->assertNotNull($tagGroup);
        $this->assertFalse((bool) $tagGroup->assign_all_categories);
        $this->assertTrue($tagGroup->categories->contains($categoryA->id));
        $this->assertFalse($tagGroup->categories->contains($categoryB->id));
    }

    public function test_can_edit_tag_group_via_form(): void
    {
        $tagGroup = TagGroup::create([
            'name' => 'مجموعة قديمة للتعديل',
            'assign_all_categories' => false,
            'added_by_user' => $this->user->id,
        ]);

        $categoryA = Category::create(['name' => 'تصنيف أ']);
        $categoryB = Category::create(['name' => 'تصنيف ب']);
        $tagGroup->categories()->sync([$categoryA->id]);

        Livewire::test(EditTagGroup::class, [
            'record' => $tagGroup->getKey(),
        ])
            ->fillForm([
                'name' => 'مجموعة معدلة',
                'assign_all_categories' => false,
                'categories' => [$categoryB->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tagGroup->refresh();
        $this->assertEquals('مجموعة معدلة', $tagGroup->name);
        $this->assertTrue($tagGroup->categories->contains($categoryB->id));
        $this->assertFalse($tagGroup->categories->contains($categoryA->id));
    }

    public function test_toggle_assign_all_categories_logic(): void
    {
        $categoryA = Category::create(['name' => 'تصنيف أ']);
        $categoryB = Category::create(['name' => 'تصنيف ب']);

        // When toggle is turned OFF (!state), categories are pre-filled with all category IDs
        Livewire::test(CreateTagGroup::class)
            ->set('data.assign_all_categories', false)
            ->assertSet('data.categories', [$categoryA->id, $categoryB->id]);

        // When toggle is turned ON (state), categories are cleared to []
        Livewire::test(CreateTagGroup::class)
            ->set('data.assign_all_categories', false)
            ->set('data.assign_all_categories', true)
            ->assertSet('data.categories', []);
    }

    public function test_category_observer_syncs_new_category_to_tag_groups_with_assign_all(): void
    {
        $groupAll = TagGroup::create([
            'name' => 'مجموعة شاملة',
            'assign_all_categories' => true,
            'added_by_user' => $this->user->id,
        ]);

        $groupCustom = TagGroup::create([
            'name' => 'مجموعة مخصصة',
            'assign_all_categories' => false,
            'added_by_user' => $this->user->id,
        ]);

        $newCategory = Category::create(['name' => 'تصنيف جديد']);

        $this->assertTrue($groupAll->fresh()->categories->contains($newCategory->id));
        $this->assertFalse($groupCustom->fresh()->categories->contains($newCategory->id));
    }

    public function test_can_filter_tag_groups_by_categories(): void
    {
        $categoryA = Category::create(['name' => 'تصنيف أ']);
        $categoryB = Category::create(['name' => 'تصنيف ب']);

        $groupA = TagGroup::create([
            'name' => 'مجموعة أ',
            'added_by_user' => $this->user->id,
        ]);
        $groupA->categories()->sync([$categoryA->id]);

        $groupB = TagGroup::create([
            'name' => 'مجموعة ب',
            'added_by_user' => $this->user->id,
        ]);
        $groupB->categories()->sync([$categoryB->id]);

        Livewire::test(ListTagGroups::class)
            ->filterTable('categories', [$categoryA->id])
            ->assertCanSeeTableRecords([$groupA])
            ->assertCanNotSeeTableRecords([$groupB]);
    }

    public function test_tags_relation_manager_renders_and_lists_tags(): void
    {
        $tagGroup = TagGroup::create([
            'name' => 'مجموعة رئيسية',
            'added_by_user' => $this->user->id,
        ]);

        $tag = \App\Models\Tag::create([
            'name' => 'وسم فرعي',
            'tag_group_id' => $tagGroup->id,
            'importance' => 'high',
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(\App\Filament\Resources\TagGroupResource\RelationManagers\TagsRelationManager::class, [
            'ownerRecord' => $tagGroup,
            'pageClass' => EditTagGroup::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$tag]);
    }

    public function test_tags_relation_manager_can_create_tag_without_specifying_tag_group_id(): void
    {
        $tagGroup = TagGroup::create([
            'name' => 'مجموعة للاختبار',
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(\App\Filament\Resources\TagGroupResource\RelationManagers\TagsRelationManager::class, [
            'ownerRecord' => $tagGroup,
            'pageClass' => EditTagGroup::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'وسم جديد عبر العلاقة',
                'importance' => 'medium',
                'assign_all_categories' => true,
                'assign_all_locations' => true,
                'is_active' => true,
                'is_auto_assigned' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم جديد عبر العلاقة',
            'tag_group_id' => $tagGroup->id,
        ]);
    }

    public function test_tags_relation_manager_can_edit_tag(): void
    {
        $tagGroup = TagGroup::create([
            'name' => 'مجموعة للاختبار والتعديل',
            'added_by_user' => $this->user->id,
        ]);

        $tag = \App\Models\Tag::create([
            'name' => 'وسم قبل التعديل',
            'tag_group_id' => $tagGroup->id,
            'importance' => 'low',
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(\App\Filament\Resources\TagGroupResource\RelationManagers\TagsRelationManager::class, [
            'ownerRecord' => $tagGroup,
            'pageClass' => EditTagGroup::class,
        ])
            ->callTableAction('edit', $tag, data: [
                'name' => 'وسم بعد التعديل',
                'importance' => 'veryhigh',
                'assign_all_categories' => true,
                'assign_all_locations' => true,
                'is_active' => true,
                'is_auto_assigned' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'وسم بعد التعديل',
            'importance' => 'veryhigh',
            'tag_group_id' => $tagGroup->id,
        ]);
    }
}
