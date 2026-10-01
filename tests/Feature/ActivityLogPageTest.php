<?php

namespace Tests\Feature;

use App\Filament\Pages\ActivityLogPage;
use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogPageTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Category $category;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_activity_log']);
        $this->adminUser->givePermissionTo('view_activity_log');

        $this->category = Category::factory()->create();
        $this->location = Location::factory()->create();
    }

    private function createClient(array $attributes = []): Client
    {
        return Client::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ], $attributes));
    }

    public function test_activity_log_page_can_be_rendered(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityLogPage::class)
            ->assertStatus(200);
    }

    public function test_activity_log_displays_logged_activities_and_client_name(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'شركة الاختيار الأول',
            'client_name' => 'محمد فرحان',
        ]);

        $client->update([
            'company' => 'شركة الاختيار المحدثة',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'updated',
        ]);

        $activity = Activity::where('subject_type', Client::class)->latest('id')->first();
        $this->assertNotNull($activity);
        $subjectName = ActivityLogPage::getSubjectName($activity);
        $this->assertStringContainsString('شركة الاختيار المحدثة', $subjectName);

        Livewire::test(ActivityLogPage::class)
            ->assertCanSeeTableRecords(Activity::where('subject_type', Client::class)->get());
    }

    public function test_modal_details_renders_old_and_new_data_for_updates(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'الاسم القديم',
        ]);

        $client->update([
            'company' => 'الاسم الجديد',
        ]);

        $updateActivity = Activity::where('subject_type', Client::class)
            ->where('description', 'updated')
            ->first();

        $this->assertNotNull($updateActivity);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $updateActivity,
        ]);

        $view->assertSee('الاسم القديم');
        $view->assertSee('الاسم الجديد');
        $view->assertSee('القيمة السابقة');
        $view->assertSee('القيمة الجديدة');
        $view->assertSee('الاسم الجديد');
    }

    public function test_modal_details_renders_deleted_data_for_deletions(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'عميل محذوف',
        ]);

        $client->delete();

        $deleteActivity = Activity::where('subject_type', Client::class)
            ->where('description', 'deleted')
            ->first();

        $this->assertNotNull($deleteActivity);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $deleteActivity,
        ]);

        $view->assertSee('عميل محذوف');
        $view->assertSee('البيانات التي تم حذفها');
    }

    public function test_client_tag_groups_changes_are_logged_and_rendered_in_modal(): void
    {
        $this->actingAs($this->adminUser);

        $tagGroup1 = TagGroup::factory()->create(['name' => 'مطاعم']);
        $tagGroup2 = TagGroup::factory()->create(['name' => 'كافيهات']);
        $tagGroup3 = TagGroup::factory()->create(['name' => 'فنادق']);

        $client = $this->createClient([
            'company' => 'شركة الضيافة',
            'client_name' => 'خالد عبد الله',
        ]);

        $client->tagGroups()->sync([$tagGroup1->id, $tagGroup2->id]);
        $client = Client::find($client->id);
        $client->captureOriginalRelations();

        // Simulate user editing tag groups in Filament
        $client->tagGroups()->sync([$tagGroup1->id, $tagGroup3->id]);
        $client->logRelationshipChanges();

        $activity = Activity::where('subject_type', Client::class)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $properties = $activity->properties->toArray();

        $this->assertArrayHasKey('tag_groups', $properties['old']);
        $this->assertArrayHasKey('tag_groups', $properties['attributes']);
        $this->assertContains('كافيهات', $properties['old']['tag_groups']);
        $this->assertContains('فنادق', $properties['attributes']['tag_groups']);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $activity,
        ]);

        $view->assertSee('شركة الضيافة');
        $view->assertSee('مجموعات الوسوم');
        $view->assertSee('مطاعم');
        $view->assertSee('كافيهات');
        $view->assertSee('فنادق');
    }

    public function test_client_tags_changes_are_logged_and_rendered_in_modal(): void
    {
        $this->actingAs($this->adminUser);

        $tagGroup = TagGroup::factory()->create(['name' => 'وجبات']);
        $tag1 = Tag::create(['name' => 'برجر', 'tag_group_id' => $tagGroup->id, 'is_active' => true]);
        $tag2 = Tag::create(['name' => 'بيتزا', 'tag_group_id' => $tagGroup->id, 'is_active' => true]);
        $tag3 = Tag::create(['name' => 'شاورما', 'tag_group_id' => $tagGroup->id, 'is_active' => true]);

        $client = $this->createClient([
            'company' => 'مطعم الأصالة',
        ]);

        $client->tags()->sync([$tag1->id, $tag2->id]);
        $client = Client::find($client->id);
        $client->captureOriginalRelations();

        $client->tags()->sync([$tag1->id, $tag3->id]);
        $client->logRelationshipChanges();

        $activity = Activity::where('subject_type', Client::class)
            ->where('description', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $properties = $activity->properties->toArray();

        $this->assertArrayHasKey('tags', $properties['old']);
        $this->assertArrayHasKey('tags', $properties['attributes']);
        $this->assertContains('بيتزا', $properties['old']['tags']);
        $this->assertContains('شاورما', $properties['attributes']['tags']);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $activity,
        ]);

        $view->assertSee('مطعم الأصالة');
        $view->assertSee('الوسوم');
        $view->assertSee('بيتزا');
        $view->assertSee('شاورما');
    }

    public function test_get_subject_url_resolves_valid_urls_for_resources(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'شركة الربط السريع',
        ]);

        $activity = Activity::where('subject_type', Client::class)->latest('id')->first();
        $this->assertNotNull($activity);

        $url = ActivityLogPage::getSubjectUrl($activity);
        $this->assertNotNull($url);
        $this->assertStringContainsString((string) $client->id, $url);
        $this->assertStringContainsString('clients', $url);
    }

    public function test_modal_renders_direct_link_for_subject(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'شركة الواحة',
        ]);

        $activity = Activity::where('subject_type', Client::class)->latest('id')->first();
        $this->assertNotNull($activity);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $activity,
        ]);

        $view->assertSee('فتح العنصر الأصلي');
        $view->assertSee('الانتقال إلى عميل:');
        $view->assertSee('شركة الواحة');
        $view->assertSee('/admin/clients/'.$client->id);
    }

    public function test_get_subject_url_returns_null_for_non_existent_record(): void
    {
        $this->actingAs($this->adminUser);

        $activity = Activity::create([
            'subject_type' => Client::class,
            'subject_id' => 99999999,
            'description' => 'deleted',
        ]);

        $url = ActivityLogPage::getSubjectUrl($activity);
        $this->assertNull($url);
    }

    public function test_table_can_render_open_subject_action(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient([
            'company' => 'شركة تجربة الأزرار',
        ]);

        $activity = Activity::where('subject_type', Client::class)->latest('id')->first();
        $this->assertNotNull($activity);

        Livewire::test(ActivityLogPage::class)
            ->assertTableActionExists('open_subject')
            ->assertTableActionExists('view_details');
    }

    public function test_get_subject_url_for_invoice_and_contract(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة العقود والفواتير']);

        $contract = \App\Models\Contract::factory()->create([
            'client_id' => $client->id,
        ]);

        $contractActivity = Activity::where('subject_type', \App\Models\Contract::class)->latest('id')->first();
        $this->assertNotNull($contractActivity);
        $contractUrl = ActivityLogPage::getSubjectUrl($contractActivity);
        $this->assertNotNull($contractUrl);
        $this->assertStringContainsString('contracts/'.$contract->id.'/edit', $contractUrl);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
        ]);

        $invoiceActivity = Activity::where('subject_type', \App\Models\Invoice::class)->latest('id')->first();
        $this->assertNotNull($invoiceActivity);
        $invoiceUrl = ActivityLogPage::getSubjectUrl($invoiceActivity);
        $this->assertNotNull($invoiceUrl);
        $this->assertStringContainsString('invoices/'.$invoice->id.'/edit', $invoiceUrl);
    }

    public function test_get_subject_url_for_system_setting(): void
    {
        $this->actingAs($this->adminUser);

        $activity = Activity::create([
            'subject_type' => \App\Models\SystemSetting::class,
            'subject_id' => 1,
            'description' => 'updated',
        ]);

        $url = ActivityLogPage::getSubjectUrl($activity);
        $this->assertNotNull($url);
        $this->assertStringContainsString('general-settings-page', $url);
    }

    public function test_get_subject_name_and_url_for_client_tag_distribution(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة التصاميم الرائدة']);
        $tagGroup = TagGroup::factory()->create(['name' => 'إعلانات']);
        $tag = Tag::create(['name' => 'عرض نهاية الأسبوع', 'tag_group_id' => $tagGroup->id, 'is_active' => true]);

        $designerUser = User::factory()->create(['name' => 'مصمم محترف']);
        $designer = \App\Models\Designer::create([
            'user_id' => $designerUser->id,
            'rate' => '1.00',
            'shift_hours' => 8,
        ]);

        $clientDesigner = \App\Models\ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'is_fixed' => true,
        ]);

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'distribution_date' => now(),
            'status' => 'pending',
        ]);

        $activity = Activity::where('subject_type', \App\Models\ClientTagDistribution::class)
            ->where('subject_id', $distribution->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);

        $subjectName = ActivityLogPage::getSubjectName($activity);
        $this->assertNotNull($subjectName);
        $this->assertStringContainsString('عرض نهاية الأسبوع', $subjectName);
        $this->assertStringContainsString('شركة التصاميم الرائدة', $subjectName);

        $subjectUrl = ActivityLogPage::getSubjectUrl($activity);
        $this->assertNotNull($subjectUrl);
        $this->assertStringContainsString('clients/'.$client->id, $subjectUrl);
    }

    public function test_get_subject_name_for_complaint_and_designer(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة خدمة العملاء']);
        $complaint = \App\Models\Complaint::create([
            'client_id' => $client->id,
            'description' => '<p>تأخر في تسليم التصميم</p>',
            'status' => 'new',
        ]);

        $complaintActivity = Activity::where('subject_type', \App\Models\Complaint::class)->latest('id')->first();
        $this->assertNotNull($complaintActivity);
        $complaintName = ActivityLogPage::getSubjectName($complaintActivity);
        $this->assertStringContainsString('شركة خدمة العملاء', $complaintName);
        $this->assertStringContainsString('تأخر في تسليم التصميم', $complaintName);

        $designerUser = User::factory()->create(['name' => 'فارس المصمم']);
        $designer = \App\Models\Designer::create([
            'user_id' => $designerUser->id,
            'rate' => '1.00',
            'shift_hours' => 8,
        ]);

        $designerActivity = Activity::where('subject_type', \App\Models\Designer::class)->latest('id')->first();
        $this->assertNotNull($designerActivity);
        $designerName = ActivityLogPage::getSubjectName($designerActivity);
        $this->assertStringContainsString('فارس المصمم', $designerName);
    }

    public function test_translate_event_and_event_color_handles_custom_financial_events(): void
    {
        // Standard CRUD
        $this->assertEquals('إنشاء', ActivityLogPage::translateEvent('created'));
        $this->assertEquals('success', ActivityLogPage::eventColor('created'));

        $this->assertEquals('تعديل', ActivityLogPage::translateEvent('updated'));
        $this->assertEquals('warning', ActivityLogPage::eventColor('updated'));

        $this->assertEquals('حذف', ActivityLogPage::translateEvent('deleted'));
        $this->assertEquals('danger', ActivityLogPage::eventColor('deleted'));

        $this->assertEquals('استعادة', ActivityLogPage::translateEvent('restored'));
        $this->assertEquals('info', ActivityLogPage::eventColor('restored'));

        // Advance receipt (Custom)
        $advanceReceiptDesc = 'تسجيل سند دفعة مقدمة (فائض سداد): 14,700.00';
        $this->assertEquals('سند دفعة مقدمة', ActivityLogPage::translateEvent($advanceReceiptDesc));
        $this->assertEquals('success', ActivityLogPage::eventColor($advanceReceiptDesc));

        // Allocation (Custom)
        $allocationDesc = 'تخصيص مبلغ 5,000.00 من سند دفعة مقدمة #12 لصالح الفاتورة #45';
        $this->assertEquals('تخصيص دفعة', ActivityLogPage::translateEvent($allocationDesc));
        $this->assertEquals('info', ActivityLogPage::eventColor($allocationDesc));

        // Other custom
        $shortCustom = 'تحديث حالة العقد';
        $this->assertEquals('تحديث حالة العقد', ActivityLogPage::translateEvent($shortCustom));
        $this->assertEquals('primary', ActivityLogPage::eventColor($shortCustom));

        $longCustom = 'عملية تصدير بيانات مخصصة للعملاء';
        $this->assertEquals(\Illuminate\Support\Str::limit($longCustom, 22), ActivityLogPage::translateEvent($longCustom));
        $this->assertEquals('primary', ActivityLogPage::eventColor($longCustom));
    }

    public function test_table_filters_by_custom_financial_events(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة الفلاتر المخصصة']);

        $act1 = Activity::create([
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'تسجيل سند دفعة مقدمة (فائض سداد): 14,700.00',
        ]);

        $act2 = Activity::create([
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'تخصيص مبلغ 200.00 من سند دفعة مقدمة #1',
        ]);

        $act3 = Activity::create([
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'created',
        ]);

        Livewire::test(ActivityLogPage::class)
            ->filterTable('description', 'advance_receipt')
            ->assertCanSeeTableRecords([$act1])
            ->assertCanNotSeeTableRecords([$act2, $act3]);

        Livewire::test(ActivityLogPage::class)
            ->filterTable('description', 'allocation')
            ->assertCanSeeTableRecords([$act2])
            ->assertCanNotSeeTableRecords([$act1, $act3]);
    }

    public function test_quick_stats_computes_correct_metrics(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة الإحصائيات السريعة']);
        $user2 = User::factory()->create(['name' => 'سارة المحاسبة']);

        // Activity 1: created client by adminUser
        $act1 = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $this->adminUser->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'created',
            'created_at' => now(),
        ]);

        // Activity 2: updated client by user2
        $act2 = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $user2->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'updated',
            'created_at' => now(),
        ]);

        // Activity 3: deleted client by user2
        $act3 = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $user2->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'deleted',
            'created_at' => now(),
        ]);

        $component = new ActivityLogPage;
        $component->statsPeriod = 'today';
        $stats = $component->getQuickStats();

        $this->assertGreaterThanOrEqual(3, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['deleted']);
        $this->assertEquals($user2->name, $stats['topUser']['name']);
        $this->assertEquals('عميل', $stats['topSubject']['name']);
    }

    public function test_quick_stats_period_filter_changes_scope(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة الفترات']);

        // Past activity (10 days ago)
        Activity::create([
            'causer_type' => User::class,
            'causer_id' => $this->adminUser->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'deleted',
            'created_at' => now()->subDays(10),
        ]);

        // Today activity
        Activity::create([
            'causer_type' => User::class,
            'causer_id' => $this->adminUser->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'created',
            'created_at' => now(),
        ]);

        $component = new ActivityLogPage;

        $component->setStatsPeriod('today');
        $todayStats = $component->getQuickStats();
        $this->assertEquals(0, $todayStats['deleted']);

        $component->setStatsPeriod('all');
        $allStats = $component->getQuickStats();
        $this->assertGreaterThanOrEqual(1, $allStats['deleted']);
    }

    public function test_quick_filters_interactivity_on_activity_log_page(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة التفاعل']);
        $user2 = User::factory()->create(['name' => 'فاطمة مديرة الحسابات']);

        $act1 = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $this->adminUser->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'created',
            'created_at' => now(),
        ]);

        $act2 = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $user2->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'deleted',
            'created_at' => now(),
        ]);

        Livewire::test(ActivityLogPage::class)
            ->call('filterByAction', 'deleted')
            ->assertCanSeeTableRecords([$act2])
            ->assertCanNotSeeTableRecords([$act1])
            ->assertSee('تصفية سريعة مطبقة على الجدول')
            ->call('resetQuickFilter')
            ->assertCanSeeTableRecords([$act1, $act2])
            ->call('filterByUser', $user2->id, $user2->name)
            ->assertCanSeeTableRecords([$act2])
            ->assertCanNotSeeTableRecords([$act1])
            ->call('filterBySubjectType', Client::class, 'عميل')
            ->assertCanSeeTableRecords([$act1, $act2])
            ->call('applyStatsPeriodToTable')
            ->assertCanSeeTableRecords([$act1, $act2]);
    }

    public function test_modal_details_excludes_system_fields(): void
    {
        $this->actingAs($this->adminUser);

        $client = $this->createClient(['company' => 'شركة تنقية الحقول']);

        $activity = Activity::create([
            'causer_type' => User::class,
            'causer_id' => $this->adminUser->id,
            'subject_type' => Client::class,
            'subject_id' => $client->id,
            'description' => 'updated',
            'properties' => [
                'old' => [
                    'company' => 'الاسم القديم للمؤسسة',
                    'updated_at' => '2026-09-30 10:00:00',
                    'created_at' => '2026-09-20 08:00:00',
                    'id' => $client->id,
                    'remember_token' => 'secret-old-token',
                ],
                'attributes' => [
                    'company' => 'الاسم الجديد للمؤسسة',
                    'updated_at' => '2026-10-01 12:00:00',
                    'created_at' => '2026-09-20 08:00:00',
                    'id' => $client->id,
                    'remember_token' => 'secret-new-token',
                ],
            ],
        ]);

        $view = $this->view('filament.pages.activity-log-modal', [
            'activity' => $activity,
        ]);

        // Actual business fields should be visible
        $view->assertSee('الاسم القديم للمؤسسة');
        $view->assertSee('الاسم الجديد للمؤسسة');
        $view->assertSee('1 حقل/حقول');

        // System fields should NOT be in the table
        $view->assertDontSee('secret-old-token');
        $view->assertDontSee('secret-new-token');
        $view->assertDontSee('2026-09-30 10:00:00');
    }

    public function test_table_displays_empty_state_with_clear_filters_action(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActivityLogPage::class)
            ->filterTable('description', 'deleted')
            ->assertSee('لا توجد نشاطات مطابقة')
            ->assertSee('لم يتم العثور على أي سجلات نشاط مطابقة لمعايير البحث أو الفلاتر المحددة.')
            ->assertTableActionExists('clear_filters')
            ->callTableAction('clear_filters');
    }
}
