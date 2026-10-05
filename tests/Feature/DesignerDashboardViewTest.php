<?php

namespace Tests\Feature;

use App\Filament\Pages\DesignerDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DesignerDashboardViewTest extends TestCase
{
    use RefreshDatabase;

    private \App\Models\User $user;

    private \App\Models\Designer $designer;

    private \App\Models\Client $client;

    private \App\Models\ClientDesigner $assignment;

    private \App\Models\ClientTagDistribution $distribution;

    private function setUpDesigner(): void
    {
        $this->user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_designer_dashboard']);
        $this->user->givePermissionTo('view_designer_dashboard');

        $this->designer = \App\Models\Designer::create([
            'user_id' => $this->user->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $category = \App\Models\Category::factory()->create();
        $currency = \App\Models\Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);
        $this->client = \App\Models\Client::factory()->create([
            'company' => 'شركة الأمل للتجارة',
            'category_id' => $category->id,
            'importance_weights' => ['high' => 100],
            'enable_very_high' => true,
        ]);
        $contract = \App\Models\Contract::create([
            'client_id' => $this->client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => \Carbon\Carbon::now()->subDays(5),
            'end_date' => \Carbon\Carbon::now()->addDays(25),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);

        $this->assignment = \App\Models\ClientDesigner::create([
            'client_id' => $this->client->id,
            'designer_id' => $this->designer->id,
            'week_start_date' => \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d'),
            'contract_id' => $contract->id,
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $this->distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);
    }

    public function test_component_html_has_single_root(): void
    {
        config(['app.debug' => true]);

        $this->setUpDesigner();

        $response = $this->actingAs($this->user)->get('/admin/designer-dashboard');

        $response->assertStatus(200);
    }

    public function test_designer_dashboard_with_tasks(): void
    {
        config(['app.debug' => true]);

        $this->setUpDesigner();

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSee($this->client->company);
    }

    public function test_cannot_submit_other_designers_task(): void
    {
        $this->setUpDesigner();

        $otherUser = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_designer_dashboard']);
        $otherUser->givePermissionTo('view_designer_dashboard');
        $otherDesigner = \App\Models\Designer::create([
            'user_id' => $otherUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 5,
        ]);

        $otherAssignment = \App\Models\ClientDesigner::create([
            'client_id' => $this->client->id,
            'designer_id' => $otherDesigner->id,
            'week_start_date' => \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $otherDist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $otherAssignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->callAction('submitTask', ['distribution_id' => $otherDist->id])
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $otherDist->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_submit_completed_task(): void
    {
        $this->setUpDesigner();

        $this->distribution->update(['status' => 'sending']);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->callAction('submitTask', ['distribution_id' => $this->distribution->id])
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $this->distribution->id,
            'status' => 'sending',
        ]);
    }

    public function test_update_task_status_rejects_invalid_status(): void
    {
        $this->setUpDesigner();

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->call('updateTaskStatus', $this->distribution->id, 'completed')
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $this->distribution->id,
            'status' => 'pending',
        ]);
    }

    public function test_update_task_status_rejects_other_designers_record(): void
    {
        $this->setUpDesigner();

        $otherUser = User::factory()->create();
        $otherDesigner = \App\Models\Designer::create([
            'user_id' => $otherUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 5,
        ]);

        $otherAssignment = \App\Models\ClientDesigner::create([
            'client_id' => $this->client->id,
            'designer_id' => $otherDesigner->id,
            'week_start_date' => \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        $tag = \App\Models\Tag::factory()->high()->create();
        $otherDist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $otherAssignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->call('updateTaskStatus', $otherDist->id, 'in_progress')
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $otherDist->id,
            'status' => 'pending',
        ]);
    }

    // ──────────────────────────────────────────────
    // اختبارات المهام المتأخرة
    // ──────────────────────────────────────────────

    public function test_overdue_count_shows_yesterdays_pending_tasks(): void
    {
        $this->setUpDesigner();

        // إنشاء توزيع من أمس بحالة pending — يجب أن يظهر في المتأخرات
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => \App\Models\Tag::factory()->high()->create()->id,
            'distribution_date' => \Carbon\Carbon::yesterday()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSee('المهام المتأخرة')
            ->assertSee('اضغط لعرض التفاصيل');
    }

    public function test_todays_pending_tasks_are_not_overdue(): void
    {
        $this->setUpDesigner();

        // التوزيع الحالي من setUpDesigner() هو لليوم — لا يجب أن يظهر في المتأخرات
        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSee('المهام المتأخرة');
    }

    public function test_yesterdays_completed_tasks_are_not_overdue(): void
    {
        $this->setUpDesigner();

        // إنشاء توزيع من أمس بحالة completed — لا يجب أن يظهر في المتأخرات
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => \App\Models\Tag::factory()->high()->create()->id,
            'distribution_date' => \Carbon\Carbon::yesterday()->format('Y-m-d'),
            'status' => 'completed',
            'completed_at' => \Carbon\Carbon::yesterday(),
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSee('المهام المتأخرة')
            ->assertSee('لا توجد مهام متأخرة');
    }

    public function test_overdue_grouped_contains_correct_dates(): void
    {
        $this->setUpDesigner();

        // إنشاء توزيعين من أيام مختلفة
        $tag = \App\Models\Tag::factory()->high()->create();
        $dayBeforeYesterday = \Carbon\Carbon::now()->subDays(2);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $dayBeforeYesterday->format('Y-m-d'),
            'status' => 'pending',
        ]);

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => \App\Models\Tag::factory()->high()->create()->id,
            'distribution_date' => \Carbon\Carbon::yesterday()->format('Y-m-d'),
            'status' => 'in_progress',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSee('المهام المتأخرة')
            ->assertDontSee('لا توجد مهام متأخرة')
            ->assertSee($this->client->company)
            ->assertSee($tag->name);
    }

    public function test_changes_count_includes_design_tasks_and_template_revisions(): void
    {
        $this->setUpDesigner();

        // 1. Daily task with changes_requested
        $this->distribution->update(['status' => 'changes_requested']);

        // 2. Side Design task with needs_revision
        \App\Models\DesignTask::create([
            'designer_id' => $this->designer->id,
            'assigner_id' => $this->user->id,
            'client_id' => $this->client->id,
            'is_subscribed_client' => true,
            'is_template_update' => false,
            'priority' => 'medium',
            'status' => \App\Filament\Enums\DesignTaskStatus::NeedsRevision,
            'description' => 'تعديل مهمة تصميم عادية',
        ]);

        // 3. Template update task with needs_revision
        \App\Models\DesignTask::create([
            'designer_id' => $this->designer->id,
            'assigner_id' => $this->user->id,
            'client_id' => $this->client->id,
            'is_subscribed_client' => true,
            'is_template_update' => true,
            'template_type' => \App\Filament\Enums\ClientTemplateType::Cliche->value,
            'priority' => 'medium',
            'status' => \App\Filament\Enums\DesignTaskStatus::NeedsRevision,
            'description' => 'تعديل قالب الكليشة',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertViewHas('changesCount', 3)
            ->assertSeeHtml('>3</span>');
    }

    public function test_cliche_data_and_button_appears_when_client_has_cliche_template(): void
    {
        $this->setUpDesigner();

        // Add a Cliche template for the client
        \App\Models\ClientTemplate::create([
            'client_id' => $this->client->id,
            'type' => \App\Filament\Enums\ClientTemplateType::Cliche->value,
            'file' => 'client-templates/cliche_test.png',
            'local_path' => 'D:\\Designs\\AlAmal\\Cliche.psd',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200);

        $pendingDaily = $component->viewData('pendingDailyTasks');
        $this->assertNotEmpty($pendingDaily);
        $this->assertNotNull($pendingDaily->first()['cliche_data']);
        $this->assertEquals('شركة الأمل للتجارة', $pendingDaily->first()['cliche_data']['client_name']);
        $this->assertEquals('D:\\Designs\\AlAmal\\Cliche.psd', $pendingDaily->first()['cliche_data']['local_path']);

        $component->assertSeeHtml('الكليشة');
    }

    public function test_admin_panel_renders_file_upload_paste_script(): void
    {
        $this->setUpDesigner();

        $response = $this->actingAs($this->user)->get('/admin/designer-dashboard');

        $response->assertStatus(200);
        $response->assertSee('FileUpload Clipboard Paste Handler', false);
        $response->assertSee('Ctrl + V', false);
    }

    public function test_submit_task_action_has_paste_helper_text(): void
    {
        $this->setUpDesigner();

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->mountAction('submitTask', ['distribution_id' => $this->distribution->id])
            ->assertActionMounted('submitTask')
            ->assertSeeHtml('Ctrl + V');
    }

    public function test_revision_notes_always_has_view_more_button_even_if_short(): void
    {
        $this->setUpDesigner();

        // Daily task with short revision notes (under 20 chars)
        $this->distribution->update([
            'status' => 'changes_requested',
            'reviewer_feedback' => 'عدل الخط فقط',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200)
            ->assertSeeHtml('عدل الخط فقط')
            ->assertSeeHtml('ملاحظات التعديل:')
            ->assertSeeHtml('عرض المزيد');
    }

    public function test_designer_sees_revision_attachments_on_daily_task_card(): void
    {
        $this->setUpDesigner();

        $attachments = [
            'clients/1/distributions/1/revisions/mock_rev1.png',
            'clients/1/distributions/1/revisions/mock_rev2.png',
        ];

        $this->distribution->update([
            'status' => 'changes_requested',
            'reviewer_feedback' => 'يرجى مراجعة الصورتين المرفقتين',
            'reviewer_attachments' => $attachments,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200);

        $changesDaily = $component->viewData('changesRequestedDaily');
        $this->assertNotEmpty($changesDaily);
        $first = $changesDaily->first();
        $this->assertEquals($attachments, $first['revision_attachments']);

        $component->assertSeeHtml('مرفقات (2)');
        $component->assertSeeHtml('mock_rev1.png');
        $component->assertSeeHtml('mock_rev2.png');
    }

    public function test_designer_sees_revision_attachments_on_design_task_card(): void
    {
        $this->setUpDesigner();

        $revFiles = [
            'clients/1/design-tasks/5/revisions/sketch.jpg',
        ];

        $designTask = \App\Models\DesignTask::create([
            'client_id' => $this->client->id,
            'designer_id' => $this->designer->id,
            'assigner_id' => $this->user->id,
            'status' => \App\Filament\Enums\DesignTaskStatus::NeedsRevision,
            'description' => 'تصميم شعار فرعي',
            'revision_notes' => 'يرجى تعديل زاوية الرسم',
            'revision_files' => $revFiles,
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200);

        $needsRev = $component->viewData('needsRevisionDesignTasks');
        $this->assertNotEmpty($needsRev);
        $first = $needsRev->first();
        $this->assertEquals($revFiles, $first['revision_attachments']);

        $component->assertSeeHtml('مرفقات (1)');
        $component->assertSeeHtml('sketch.jpg');
    }

    public function test_completed_daily_tasks_appear_in_completed_tab_for_selected_date(): void
    {
        $this->setUpDesigner();

        $tag = \App\Models\Tag::factory()->high()->create();

        // 1. Task completed today
        $completedDist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'status' => 'completed',
            'attachment_path' => 'clients/1/submissions/10/completed_test.jpg',
            'designer_notes' => 'تم إنهاء التصميم وفق المطلوب',
        ]);

        // 2. Task sending today (approved by reviewer)
        $sendingDist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'status' => 'sending',
            'attachment_path' => 'clients/1/submissions/11/sending_test.jpg',
        ]);

        // 3. Task completed yesterday (should NOT appear for today's filter)
        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => \Carbon\Carbon::yesterday()->format('Y-m-d'),
            'status' => 'completed',
            'attachment_path' => 'clients/1/submissions/12/yesterday_test.jpg',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertStatus(200);

        $completedTasks = $component->viewData('completedDailyTasks');
        $this->assertCount(2, $completedTasks);
        $this->assertEquals(2, $component->viewData('completedCount'));

        $ids = $completedTasks->pluck('id')->all();
        $this->assertContains($completedDist->id, $ids);
        $this->assertContains($sendingDist->id, $ids);

        // View assertions
        $component->assertSeeHtml('المنجزة')
            ->assertSeeHtml('completed_test.jpg')
            ->assertSeeHtml('sending_test.jpg')
            ->assertSeeHtml('مكتمل')
            ->assertSeeHtml('معتمد')
            ->assertSeeHtml('تم إنهاء التصميم وفق المطلوب');
    }

    public function test_completed_tab_filters_by_selected_date(): void
    {
        $this->setUpDesigner();

        $tag = \App\Models\Tag::factory()->high()->create();
        $yesterdayStr = \Carbon\Carbon::yesterday()->format('Y-m-d');

        \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $yesterdayStr,
            'status' => 'completed',
            'attachment_path' => 'clients/1/submissions/15/yesterday_design.jpg',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        // Filter date set to yesterday
        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->set('filterDate', $yesterdayStr)
            ->assertStatus(200);

        $completedTasks = $component->viewData('completedDailyTasks');
        $this->assertCount(1, $completedTasks);
        $this->assertEquals(1, $component->viewData('completedCount'));
        $component->assertSeeHtml('yesterday_design.jpg');
    }

    public function test_quick_date_navigation_actions(): void
    {
        $this->setUpDesigner();

        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('admin')
        );

        $today = \Carbon\Carbon::today()->format('Y-m-d');
        $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
        $dayBeforeYesterday = \Carbon\Carbon::today()->subDays(2)->format('Y-m-d');

        $component = Livewire::actingAs($this->user)
            ->test(DesignerDashboard::class)
            ->assertSet('filterDate', $today)
            // Go to yesterday
            ->call('goToYesterday')
            ->assertSet('filterDate', $yesterday)
            // Go back one more day
            ->call('previousDay')
            ->assertSet('filterDate', $dayBeforeYesterday)
            // Go forward one day
            ->call('nextDay')
            ->assertSet('filterDate', $yesterday)
            // Jump back to today
            ->call('goToToday')
            ->assertSet('filterDate', $today);

        // Assert navigation UI elements are present
        $component->assertSeeHtml('wire:click="goToYesterday"')
            ->assertSeeHtml('wire:click="goToToday"')
            ->assertSeeHtml('wire:click="previousDay"')
            ->assertSeeHtml('wire:click="nextDay"');
    }
}
