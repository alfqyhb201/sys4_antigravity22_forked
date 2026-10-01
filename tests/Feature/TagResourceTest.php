<?php

namespace Tests\Feature;

use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Pages\CreateTag;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Models\Category;
use App\Models\Client;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagResourceTest extends TestCase
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
    public function test_can_render_list_page(): void
    {
        $this->get(TagResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_create_page(): void
    {
        $this->get(TagResource::getUrl('create'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_edit_page(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم اختبار',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
        ]);

        $this->get(TagResource::getUrl('edit', ['record' => $tag]))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_create_tag_via_form(): void
    {
        Livewire::test(CreateTag::class)
            ->fillForm([
                'name' => 'وسم جديد',
                'importance' => 'high',
                'tag_group_id' => $this->tagGroup->id,
                'is_active' => true,
                'is_auto_assigned' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', [
            'name' => 'وسم جديد',
            'importance' => 'high',
            'tag_group_id' => $this->tagGroup->id,
        ]);
    }

    /** @test */
    public function test_can_edit_tag_via_form(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم قديم',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(EditTag::class, [
            'record' => $tag->getKey(),
        ])
            ->fillForm([
                'name' => 'وسم معدل',
                'importance' => 'veryhigh',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'وسم معدل',
            'importance' => 'veryhigh',
        ]);
    }

    /** @test */
    public function test_clears_sending_fields_when_scheduled_sending_toggled_off(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'وسم مجدول',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_there_date_for_sending' => true,
            'weekly_day' => ['Monday', 'Tuesday'],
            'weekly_time' => '10:00:00',
            'weekly_times' => 2,
        ]);

        Livewire::test(EditTag::class, [
            'record' => $tag->getKey(),
        ])
            ->fillForm([
                'is_there_date_for_sending' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'is_there_date_for_sending' => false,
            'date_for_sending_yearly' => null,
            'weekly_day' => null,
            'weekly_time' => null,
            'weekly_time_sm' => null,
            'weekly_times' => null,
        ]);
    }

    /** @test */
    public function test_detaches_clients_when_auto_assigned_is_enabled(): void
    {
        $category = Category::factory()->create();
        $client = Client::factory()->create([
            'category_id' => $category->id,
        ]);

        $tag = Tag::factory()->create([
            'name' => 'وسم مخصص لعميل',
            'tag_group_id' => $this->tagGroup->id,
            'added_by_user' => $this->user->id,
            'is_auto_assigned' => false,
        ]);

        $tag->clients()->attach($client->id);

        $this->assertDatabaseHas('client_tag', [
            'tag_id' => $tag->id,
            'client_id' => $client->id,
        ]);

        Livewire::test(EditTag::class, [
            'record' => $tag->getKey(),
        ])
            ->fillForm([
                'is_auto_assigned' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('client_tag', [
            'tag_id' => $tag->id,
            'client_id' => $client->id,
        ]);
    }
}
