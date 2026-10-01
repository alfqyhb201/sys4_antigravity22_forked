<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * اختبارات حفظ مجموعات الوسوم واستعادتها تلقائياً عند التعديل.
 *
 * تتحقق هذه الاختبارات من أن:
 * - مجموعات الوسوم تُحفظ حتى بدون تحديد وسوم.
 * - تُستعاد المجموعات عند فتح التعديل (hydration).
 * - تغيير المجموعات يزيل الوسوم الخارجة عن النطاق.
 */
class ClientTagGroupsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    /**
     * إعداد بيئة الاختبار.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);

        collect([
            'view_any_client',
            'view_client',
            'create_client',
            'update_client',
        ])->each(fn ($permission) => \Spatie\Permission\Models\Permission::create(['name' => $permission]));

        $this->user->givePermissionTo([
            'view_any_client',
            'view_client',
            'create_client',
            'update_client',
        ]);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->category = Category::create(['name' => 'تصنيف اختبار']);
    }

    /**
     * إنشاء مجموعة وسوم.
     */
    protected function createTagGroup(string $name = 'مجموعة وسوم'): TagGroup
    {
        return TagGroup::create([
            'name' => $name,
            'added_by_user' => $this->user->id,
        ]);
    }

    /**
     * إنشاء عميل لاستخدامه في الاختبارات.
     */
    protected function createClient(array $attributes = []): Client
    {
        $location = Location::create([
            'name' => 'موقع اختبار',
            'added_by_user' => $this->user->id,
        ]);

        return Client::create(array_merge([
            'company' => 'شركة اختبار',
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $this->category->id,
            'added_by_user' => $this->user->id,
            'status' => true,
        ], $attributes));
    }

    /**
     * التحقق من حفظ مجموعات الوسوم عند إنشاء عميل بدون وسوم.
     */
    public function test_it_persists_tag_groups_when_creating_a_client_without_tags(): void
    {
        $group1 = $this->createTagGroup('مجموعة 1');
        $group2 = $this->createTagGroup('مجموعة 2');

        $location = Location::create([
            'name' => 'موقع اختبار',
            'added_by_user' => $this->user->id,
        ]);

        Livewire::test(CreateClient::class)
            ->fillForm([
                'company' => 'شركة اختبار',
                'location_id' => $location->id,
                'category_id' => $this->category->id,
                'tag_group_filter' => [$group1->id, $group2->id],
                'tags' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $client = Client::where('company', 'شركة اختبار')->firstOrFail();

        $this->assertDatabaseHas('client_tag_group', [
            'client_id' => $client->id,
            'tag_group_id' => $group1->id,
        ]);
        $this->assertDatabaseHas('client_tag_group', [
            'client_id' => $client->id,
            'tag_group_id' => $group2->id,
        ]);
    }

    /**
     * التحقق من استعادة مجموعات الوسوم عند فتح التعديل (حتى بدون وسوم).
     */
    public function test_it_hydrates_tag_groups_when_editing_a_client(): void
    {
        $group1 = $this->createTagGroup('مجموعة 1');
        $group2 = $this->createTagGroup('مجموعة 2');

        $client = $this->createClient();
        $client->tagGroups()->attach([$group1->id, $group2->id]);

        $component = Livewire::test(EditClient::class, ['record' => $client->getRouteKey()]);

        $this->assertEqualsCanonicalizing(
            [$group1->id, $group2->id],
            $component->get('data.tag_group_filter'),
        );
    }

    /**
     * التحقق من أن حفظ صفحة التعديل لا يفشل إذا لم يُمرَّر مفتاح السقف الائتماني.
     */
    public function test_edit_client_save_mutator_handles_missing_credit_flag(): void
    {
        $client = $this->createClient();

        $page = new class extends EditClient
        {
            public function callMutateFormDataBeforeSave(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $data = $page->callMutateFormDataBeforeSave([
            'company' => 'شركة اختبار محدثة',
        ]);

        $this->assertSame('شركة اختبار محدثة', $data['company']);
        $this->assertSame($this->user->id, $data['updated_by_user']);
        $this->assertArrayNotHasKey('contract_grace_period_days', $data);
    }

    /**
     * التحقق من سلوك الانحدار: تغيير المجموعات يزيل الوسوم الخارجة عن النطاق.
     */
    public function test_it_removes_out_of_scope_tags_when_tag_groups_change(): void
    {
        $group1 = $this->createTagGroup('مجموعة 1');
        $group2 = $this->createTagGroup('مجموعة 2');

        $tagInGroup1 = Tag::factory()->create(['tag_group_id' => $group1->id]);
        $tagInGroup2 = Tag::factory()->create(['tag_group_id' => $group2->id]);

        $client = $this->createClient();
        $client->tagGroups()->attach([$group1->id, $group2->id]);
        $client->tags()->attach([$tagInGroup1->id, $tagInGroup2->id]);

        $component = Livewire::test(EditClient::class, ['record' => $client->getRouteKey()]);

        $component
            ->set('data.tag_group_filter', [$group1->id]);

        $component->assertSet('data.tag_group_filter', [$group1->id]);
        $component->assertSet('data.tags', [$tagInGroup1->id]);
    }

    /**
     * التحقق من تسجيل النشاط في ActivityLog عند تعديل مجموعات الوسوم وحفظها.
     */
    public function test_it_logs_activity_when_tag_groups_are_updated_via_edit_client(): void
    {
        $group1 = $this->createTagGroup('مجموعة 1');
        $group2 = $this->createTagGroup('مجموعة 2');
        $group3 = $this->createTagGroup('مجموعة 3');

        $client = $this->createClient([
            'company' => 'شركة النشاط المحدودة',
            'client_name' => 'سامي الأحمد',
        ]);
        $client->tagGroups()->attach([$group1->id, $group2->id]);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->fillForm([
                'tag_group_filter' => [$group1->id, $group3->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Client::class)
            ->where('subject_id', $client->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $properties = $activity->properties->toArray();
        $this->assertArrayHasKey('tag_groups', $properties['old']);
        $this->assertArrayHasKey('tag_groups', $properties['attributes']);
        $this->assertContains('مجموعة 2', $properties['old']['tag_groups']);
        $this->assertContains('مجموعة 3', $properties['attributes']['tag_groups']);
    }

    /**
     * التحقق من تسجيل النشاط في ActivityLog عند التعديل عبر EditAction في الجدول.
     */
    public function test_it_logs_activity_when_tag_groups_are_updated_via_edit_action(): void
    {
        $group1 = $this->createTagGroup('مجموعة 1');
        $group2 = $this->createTagGroup('مجموعة 2');
        $group3 = $this->createTagGroup('مجموعة 3');

        $client = $this->createClient([
            'company' => 'مؤسسة الرواد',
            'client_name' => 'فهد المنصور',
        ]);
        $client->tagGroups()->attach([$group1->id, $group2->id]);

        Livewire::test(\App\Filament\Resources\ClientResource\Pages\ListClients::class)
            ->mountTableAction('edit', $client)
            ->setTableActionData([
                'tag_group_filter' => [$group1->id, $group3->id],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Client::class)
            ->where('subject_id', $client->id)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $properties = $activity->properties->toArray();
        $this->assertArrayHasKey('tag_groups', $properties['old']);
        $this->assertArrayHasKey('tag_groups', $properties['attributes']);
        $this->assertContains('مجموعة 2', $properties['old']['tag_groups']);
        $this->assertContains('مجموعة 3', $properties['attributes']['tag_groups']);
    }
}
