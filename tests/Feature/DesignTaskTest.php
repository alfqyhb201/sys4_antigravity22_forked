<?php

namespace Tests\Feature;

use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Filament\Resources\DesignTaskResource;
use App\Filament\Resources\DesignTaskResource\Pages\ListDesignTasks;
use App\Models\Category;
use App\Models\Client;
use App\Models\Designer;
use App\Models\DesignTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * اختبارات ميزة مهام التصميم.
 */
class DesignTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار إنشاء مهمة تصميم بنجاح.
     */
    public function test_design_task_can_be_created(): void
    {
        $assigner = User::factory()->create(['status' => 1]);
        $designer = Designer::factory()->create();

        $task = DesignTask::factory()->create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'client_name' => 'عميل تجريبي',
            'priority' => DesignTaskPriority::High,
        ]);

        $this->assertDatabaseHas('design_tasks', [
            'id' => $task->id,
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'client_name' => 'عميل تجريبي',
            'status' => DesignTaskStatus::Pending->value,
        ]);
    }

    /**
     * اختبار علاقة المهمة مع المصمم.
     */
    public function test_design_task_belongs_to_designer(): void
    {
        $task = DesignTask::factory()->create();

        $this->assertInstanceOf(Designer::class, $task->designer);
    }

    /**
     * اختبار علاقة المهمة مع المنشئ.
     */
    public function test_design_task_belongs_to_assigner(): void
    {
        $task = DesignTask::factory()->create();

        $this->assertInstanceOf(User::class, $task->assigner);
    }

    /**
     * اختبار تحويل الحالة من pending إلى in_review.
     */
    public function test_task_can_be_submitted_for_review(): void
    {
        $task = DesignTask::factory()->create([
            'status' => DesignTaskStatus::Pending,
        ]);

        $task->update([
            'status' => DesignTaskStatus::InReview,
            'design_files' => ['path/to/file1.jpg', 'path/to/file2.png'],
            'submitted_at' => now(),
        ]);

        $task->refresh();

        $this->assertEquals(DesignTaskStatus::InReview, $task->status);
        $this->assertNotNull($task->submitted_at);
        $this->assertCount(2, $task->design_files);
    }

    /**
     * اختبار اعتماد مهمة تصميم.
     */
    public function test_task_can_be_approved(): void
    {
        $task = DesignTask::factory()->inReview()->create();

        $task->update([
            'status' => DesignTaskStatus::Approved,
        ]);

        $task->refresh();

        $this->assertEquals(DesignTaskStatus::Approved, $task->status);
    }

    /**
     * اختبار طلب تعديل على مهمة.
     */
    public function test_task_can_be_requested_for_revision(): void
    {
        $task = DesignTask::factory()->inReview()->create();

        $task->update([
            'status' => DesignTaskStatus::NeedsRevision,
            'revision_notes' => 'يرجى تعديل الألوان',
        ]);

        $task->refresh();

        $this->assertEquals(DesignTaskStatus::NeedsRevision, $task->status);
        $this->assertEquals('يرجى تعديل الألوان', $task->revision_notes);
    }

    /**
     * اختبار اسم العميل المعروض (عميل غير مشترك).
     */
    public function test_display_client_name_for_non_subscribed_client(): void
    {
        $task = DesignTask::factory()->create([
            'is_subscribed_client' => false,
            'client_name' => 'شركة تجريبية',
        ]);

        $this->assertEquals('شركة تجريبية', $task->display_client_name);
    }

    /**
     * اختبار الحالة الإضافية (is_extra) مع المبلغ.
     */
    public function test_extra_task_with_amount(): void
    {
        $task = DesignTask::factory()->create([
            'is_extra' => true,
            'amount' => 150.00,
        ]);

        $this->assertTrue($task->is_extra);
        $this->assertEquals('150.00', $task->amount);
    }

    /**
     * اختبار تحويل أولوية المهمة (Enum Cast).
     */
    public function test_priority_enum_cast(): void
    {
        $task = DesignTask::factory()->highPriority()->create();

        $this->assertInstanceOf(DesignTaskPriority::class, $task->priority);
        $this->assertEquals(DesignTaskPriority::High, $task->priority);
    }

    /**
     * اختبار دورة حياة المهمة الكاملة.
     */
    public function test_full_task_lifecycle(): void
    {
        // 1. إنشاء المهمة (Pending)
        $task = DesignTask::factory()->create();
        $this->assertEquals(DesignTaskStatus::Pending, $task->status);

        // 2. المصمم يرفع التصاميم (InReview)
        $task->update([
            'status' => DesignTaskStatus::InReview,
            'design_files' => ['design1.png'],
            'submitted_at' => now(),
        ]);
        $task->refresh();
        $this->assertEquals(DesignTaskStatus::InReview, $task->status);

        // 3. المسؤول يطلب تعديل (NeedsRevision)
        $task->update([
            'status' => DesignTaskStatus::NeedsRevision,
            'revision_notes' => 'تعديل الخلفية',
            'design_files' => null,
        ]);
        $task->refresh();
        $this->assertEquals(DesignTaskStatus::NeedsRevision, $task->status);

        // 4. المصمم يعيد الرفع (InReview)
        $task->update([
            'status' => DesignTaskStatus::InReview,
            'design_files' => ['design1_v2.png'],
            'submitted_at' => now(),
        ]);
        $task->refresh();
        $this->assertEquals(DesignTaskStatus::InReview, $task->status);

        // 5. المسؤول يوافق (Approved)
        $task->update([
            'status' => DesignTaskStatus::Approved,
        ]);
        $task->refresh();
        $this->assertEquals(DesignTaskStatus::Approved, $task->status);
    }

    /**
     * اختبار إنشاء مهمة مجدولة.
     */
    public function test_scheduled_task_can_be_created(): void
    {
        $scheduledDate = now()->addDays(3);
        $task = DesignTask::factory()->create([
            'scheduled_at' => $scheduledDate,
        ]);

        $this->assertNotNull($task->scheduled_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $task->scheduled_at);
    }

    /**
     * اختبار مهمة فورية (بدون جدولة).
     */
    public function test_immediate_task_has_null_scheduled_at(): void
    {
        $task = DesignTask::factory()->create([
            'scheduled_at' => null,
        ]);

        $this->assertNull($task->scheduled_at);
    }

    /**
     * اختبار رؤية المسؤول (Admin) لجميع المهام في الجدول.
     */
    public function test_admin_can_view_all_design_tasks_in_table(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        $user1 = User::factory()->create(['status' => 1]);
        $user2 = User::factory()->create(['status' => 1]);

        $task1 = DesignTask::factory()->create(['assigner_id' => $user1->id]);
        $task2 = DesignTask::factory()->create(['assigner_id' => $user2->id]);

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->assertCanSeeTableRecords([$task1, $task2]);
    }

    /**
     * اختبار رؤية المستخدم غير المسؤول لمهامه فقط.
     */
    public function test_non_admin_can_only_view_own_design_tasks_in_table(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);

        $user1 = User::factory()->create(['status' => 1]);
        $user1->givePermissionTo('view_any_design_task');

        $user2 = User::factory()->create(['status' => 1]);

        $task1 = DesignTask::factory()->create(['assigner_id' => $user1->id]);
        $task2 = DesignTask::factory()->create(['assigner_id' => $user2->id]);

        Livewire::actingAs($user1)
            ->test(ListDesignTasks::class)
            ->assertCanSeeTableRecords([$task1])
            ->assertCanNotSeeTableRecords([$task2]);
    }

    /**
     * اختبار احتساب الشارة لجميع المهام قيد المراجعة للأدمن.
     */
    public function test_admin_badge_counts_all_in_review_tasks(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');

        $user1 = User::factory()->create(['status' => 1]);
        $user2 = User::factory()->create(['status' => 1]);

        DesignTask::factory()->inReview()->create(['assigner_id' => $user1->id]);
        DesignTask::factory()->inReview()->create(['assigner_id' => $user2->id]);

        $this->actingAs($admin);
        $this->assertEquals('2', DesignTaskResource::getNavigationBadge());
    }

    /**
     * اختبار احتساب الشارة للمهام الخاصة بالمستخدم غير الأدمن فقط.
     */
    public function test_non_admin_badge_only_counts_own_in_review_tasks(): void
    {
        $user1 = User::factory()->create(['status' => 1]);
        $user2 = User::factory()->create(['status' => 1]);

        DesignTask::factory()->inReview()->create(['assigner_id' => $user1->id]);
        DesignTask::factory()->inReview()->create(['assigner_id' => $user2->id]);

        $this->actingAs($user1);
        $this->assertEquals('1', DesignTaskResource::getNavigationBadge());
    }

    /**
     * اختبار إرسال إشعار للمصمم عند إنشاء مهمة جانبية جديدة.
     */
    public function test_designer_receives_notification_when_design_task_is_created(): void
    {
        $designerUser = User::factory()->create(['name' => 'مصمم إشعارات']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);
        $assigner = User::factory()->create();

        $task = DesignTask::create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'client_name' => 'متجر الأناقة',
            'priority' => DesignTaskPriority::High,
            'status' => DesignTaskStatus::Pending,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designerUser->id,
        ]);
    }

    /**
     * اختبار إرسال إشعار للمصمم عند طلب تعديل على المهمة الجانبية.
     */
    public function test_designer_receives_notification_when_design_task_revision_is_requested(): void
    {
        $designerUser = User::factory()->create(['name' => 'مصمم التعديلات']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);

        $task = DesignTask::factory()->inReview()->create([
            'designer_id' => $designer->id,
            'client_name' => 'مطعم النجوم',
        ]);

        // Clear existing notifications from creation
        \Illuminate\Support\Facades\DB::table('notifications')->truncate();

        $task->update([
            'status' => DesignTaskStatus::NeedsRevision,
            'revision_notes' => 'يرجى تعديل الشعار والخط',
            'revision_files' => ['clients/1/design-tasks/1/revisions/note.png'],
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designerUser->id,
        ]);
    }

    /**
     * اختبار إنشاء مهمة عبر صفحة CreateDesignTask وإرسال إشعار للمصمم.
     */
    public function test_create_design_task_page_notifies_designer(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_design_task', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->givePermissionTo(['view_any_design_task', 'create_design_task']);

        $designerUser = User::factory()->create(['name' => 'مصمم الواجهة']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Resources\DesignTaskResource\Pages\CreateDesignTask::class)
            ->fillForm([
                'designer_id' => $designer->id,
                'client_name' => 'مؤسسة الإبداع',
                'priority' => DesignTaskPriority::High->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('design_tasks', [
            'designer_id' => $designer->id,
            'client_name' => 'مؤسسة الإبداع',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designerUser->id,
        ]);
    }

    /**
     * اختبار تصفية الجدول حسب تبويبات الحالة الذكية.
     */
    public function test_list_design_tasks_status_tabs_filtering(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        $pendingTask = DesignTask::factory()->create(['status' => DesignTaskStatus::Pending]);
        $inReviewTask = DesignTask::factory()->inReview()->create();
        $needsRevisionTask = DesignTask::factory()->create(['status' => DesignTaskStatus::NeedsRevision]);
        $approvedTask = DesignTask::factory()->create(['status' => DesignTaskStatus::Approved]);

        // Tab: Pending
        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->set('activeTab', 'pending')
            ->assertCanSeeTableRecords([$pendingTask])
            ->assertCanNotSeeTableRecords([$inReviewTask, $needsRevisionTask, $approvedTask]);

        // Tab: In Review
        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->set('activeTab', 'in_review')
            ->assertCanSeeTableRecords([$inReviewTask])
            ->assertCanNotSeeTableRecords([$pendingTask, $needsRevisionTask, $approvedTask]);

        // Tab: Needs Revision
        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->set('activeTab', 'needs_revision')
            ->assertCanSeeTableRecords([$needsRevisionTask])
            ->assertCanNotSeeTableRecords([$pendingTask, $inReviewTask, $approvedTask]);

        // Tab: All
        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$pendingTask, $inReviewTask, $needsRevisionTask, $approvedTask]);
    }

    /**
     * اختبار دقة العدادات الحية في تبويبات الحالة مع مراعاة صلاحيات المشرف غير الأدمن.
     */
    public function test_list_design_tasks_status_tabs_counts_and_scoping(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        $user1 = User::factory()->create(['status' => 1]);
        $user1->givePermissionTo('view_any_design_task');

        $user2 = User::factory()->create(['status' => 1]);

        // Create tasks for user1
        DesignTask::factory()->create(['assigner_id' => $user1->id, 'status' => DesignTaskStatus::InReview]);
        DesignTask::factory()->create(['assigner_id' => $user1->id, 'status' => DesignTaskStatus::NeedsRevision]);

        // Create tasks for user2
        DesignTask::factory()->create(['assigner_id' => $user2->id, 'status' => DesignTaskStatus::InReview]);
        DesignTask::factory()->create(['assigner_id' => $user2->id, 'status' => DesignTaskStatus::Pending]);

        // As Admin: sees all counts
        $adminComponent = Livewire::actingAs($admin)->test(ListDesignTasks::class)->instance();
        $adminTabs = $adminComponent->getTabs();

        $this->assertEquals('pending', $adminComponent->getDefaultActiveTab());
        $this->assertEquals(4, $adminTabs['all']->getBadge());
        $this->assertEquals(2, $adminTabs['in_review']->getBadge());
        $this->assertEquals(1, $adminTabs['needs_revision']->getBadge());
        $this->assertEquals(1, $adminTabs['pending']->getBadge());
        $this->assertArrayNotHasKey('approved', $adminTabs);

        // As Non-admin (user1): sees only their counts
        $user1Component = Livewire::actingAs($user1)->test(ListDesignTasks::class)->instance();
        $user1Tabs = $user1Component->getTabs();

        $this->assertEquals(2, $user1Tabs['all']->getBadge());
        $this->assertEquals(1, $user1Tabs['in_review']->getBadge());
        $this->assertEquals(1, $user1Tabs['needs_revision']->getBadge());
        $this->assertEquals(0, $user1Tabs['pending']->getBadge());
        $this->assertArrayNotHasKey('approved', $user1Tabs);
    }

    /**
     * اختبار اعتماد المهمة بنجاح من داخل درج المراجعة.
     */
    public function test_approve_task_from_drawer_updates_status_and_increments_cliche(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        $category = Category::factory()->create();
        $client = Client::factory()->create([
            'category_id' => $category->id,
            'cliche_counter' => 5,
        ]);
        $task = DesignTask::factory()->inReview()->create([
            'is_subscribed_client' => true,
            'client_id' => $client->id,
            'assigner_id' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->call('approveTask', $task->id)
            ->assertDispatched('close-modal');

        $task->refresh();
        $client->refresh();

        $this->assertEquals(DesignTaskStatus::Approved, $task->status);
        $this->assertEquals(6, $client->cliche_counter);
    }

    /**
     * اختبار طلب تعديل من داخل درج المراجعة مع نصوص ومرفقات/لقطات شاشة.
     */
    public function test_request_task_revision_from_drawer_saves_notes_and_files(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        $task = DesignTask::factory()->inReview()->create([
            'assigner_id' => $admin->id,
            'design_files' => ['clients/1/design-tasks/1/submission.png'],
        ]);

        // Base64 1x1 transparent PNG
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->call('requestTaskRevision', $task->id, 'يرجى تغيير الخط والألوان', [$base64Image])
            ->assertDispatched('close-modal');

        $task->refresh();

        $this->assertEquals(DesignTaskStatus::NeedsRevision, $task->status);
        $this->assertEquals('يرجى تغيير الخط والألوان', $task->revision_notes);
        $this->assertNull($task->design_files);
        $this->assertNotNull($task->revision_files);
        $this->assertCount(1, $task->revision_files);
        $this->assertTrue(Storage::disk('public')->exists($task->revision_files[0]));

        // Cleanup created test file
        Storage::disk('public')->delete($task->revision_files[0]);
    }

    /**
     * اختبار أن زر CreateAction في ترويسة صفحة ListDesignTasks مخفي برمجياً.
     */
    public function test_header_create_action_is_hidden_on_list_design_tasks(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_any_design_task');

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->assertActionHidden('create');
    }

    /**
     * اختبار إنشاء مهمة سريعة من أعلى صفحة ListDesignTasks لعميل مشترك وإشعار المصمم.
     */
    public function test_quick_task_can_be_created_from_list_design_tasks_for_subscribed_client(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo(['view_any_design_task', 'create_design_task']);

        $designerUser = User::factory()->create(['name' => 'مصمم محترف']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);
        $category = Category::factory()->create();
        $client = Client::factory()->create([
            'category_id' => $category->id,
            'company' => 'شركة النخبة',
        ]);

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->fillForm([
                'company' => 'شركة النخبة',
                'designer_id' => $designer->id,
                'priority' => DesignTaskPriority::High->value,
                'description' => 'تصميم بنر إعلاني لحملة اليوم الوطني',
            ], 'createTaskForm')
            ->call('createTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('design_tasks', [
            'designer_id' => $designer->id,
            'assigner_id' => $admin->id,
            'client_id' => $client->id,
            'client_name' => 'شركة النخبة',
            'is_subscribed_client' => true,
            'priority' => DesignTaskPriority::High->value,
            'description' => 'تصميم بنر إعلاني لحملة اليوم الوطني',
            'status' => DesignTaskStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designerUser->id,
        ]);
    }

    /**
     * اختبار إنشاء مهمة سريعة لعميل غير مسجل.
     */
    public function test_quick_task_can_be_created_for_unregistered_client(): void
    {
        Permission::firstOrCreate(['name' => 'view_any_design_task', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create_design_task', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['status' => 1]);
        $admin->assignRole('admin');
        $admin->givePermissionTo(['view_any_design_task', 'create_design_task']);

        $designerUser = User::factory()->create(['name' => 'مصمم آخر']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);

        Livewire::actingAs($admin)
            ->test(ListDesignTasks::class)
            ->fillForm([
                'company' => 'محل ورود عشوائي',
                'designer_id' => $designer->id,
                'priority' => DesignTaskPriority::Low->value,
                'description' => 'تصميم كرت شخصي',
            ], 'createTaskForm')
            ->call('createTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('design_tasks', [
            'designer_id' => $designer->id,
            'assigner_id' => $admin->id,
            'client_id' => null,
            'client_name' => 'محل ورود عشوائي',
            'is_subscribed_client' => false,
            'priority' => DesignTaskPriority::Low->value,
            'description' => 'تصميم كرت شخصي',
            'status' => DesignTaskStatus::Pending->value,
        ]);
    }
}
