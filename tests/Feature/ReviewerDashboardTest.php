<?php

namespace Tests\Feature;

use App\Filament\Enums\ClientTemplateType;
use App\Filament\Pages\ReviewerDashboard;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\ClientTemplate;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReviewerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private Designer $designer;

    private Client $client1;

    private Client $client2;

    private ClientTagDistribution $dist1;

    private ClientTagDistribution $dist2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reviewer = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_reviewer_dashboard']);
        $this->reviewer->givePermissionTo('view_reviewer_dashboard');

        $designerUser = User::factory()->create(['name' => 'مصمم محترف']);
        $this->designer = Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
        ]);

        $category = Category::factory()->create();
        $currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->reviewer->id,
        ]);

        $this->client1 = Client::factory()->create([
            'company' => 'شركة النخبة للتجارة',
            'category_id' => $category->id,
        ]);

        $this->client2 = Client::factory()->create([
            'company' => 'مؤسسة الشروق الرقمية',
            'category_id' => $category->id,
        ]);

        $assignment1 = ClientDesigner::create([
            'client_id' => $this->client1->id,
            'designer_id' => $this->designer->id,
            'week_start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        $assignment2 = ClientDesigner::create([
            'client_id' => $this->client2->id,
            'designer_id' => $this->designer->id,
            'week_start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
        ]);

        $tag1 = Tag::factory()->high()->create(['name' => 'بوست رئيسي']);
        $tag2 = Tag::factory()->high()->create(['name' => 'ستوري عرض']);

        $this->dist1 = ClientTagDistribution::create([
            'client_designer_id' => $assignment1->id,
            'tag_id' => $tag1->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'reviewing',
            'attachment_path' => 'clients/1/submissions/1/design.png',
        ]);

        $this->dist2 = ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag2->id,
            'distribution_date' => Carbon::yesterday()->format('Y-m-d'),
            'status' => 'reviewing',
            'attachment_path' => 'clients/2/submissions/2/design.png',
        ]);
    }

    public function test_reviewer_dashboard_renders_with_reviewing_items(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->assertStatus(200)
            ->assertSee('شركة النخبة للتجارة')
            ->assertSee('مؤسسة الشروق الرقمية');
    }

    public function test_search_filters_reviewing_items_by_company_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->set('search', 'النخبة')
            ->assertSeeHtml("review-item-today-{$this->dist1->id}")
            ->assertDontSeeHtml("review-item-previous-{$this->dist2->id}")
            ->assertViewHas('todayCount', 1)
            ->assertViewHas('previousCount', 0);
    }

    public function test_selected_client_filter_works(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->set('selectedClient', $this->client2->id)
            ->assertSeeHtml("review-item-previous-{$this->dist2->id}")
            ->assertDontSeeHtml("review-item-today-{$this->dist1->id}")
            ->assertViewHas('todayCount', 0)
            ->assertViewHas('previousCount', 1);
    }

    public function test_can_approve_design(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('approve', [], ['id' => $this->dist1->id])
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $this->dist1->id,
            'status' => 'sending',
            'reviewer_id' => $this->reviewer->id,
        ]);
    }

    public function test_approving_design_in_early_morning_schedules_sending_for_same_day(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // اعتماد يوم الأربعاء الساعة 2:00 فجراً
        Carbon::setTestNow(Carbon::parse('2026-10-07 02:00:00'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('approve', [], ['id' => $this->dist1->id])
            ->assertNotified();

        $this->dist1->refresh();

        // يجب أن يُجدول لنفس اليوم (الأربعاء) الساعة 12:00 ظهراً
        $this->assertEquals('2026-10-07 12:00:00', $this->dist1->scheduled_sending_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_approving_design_during_day_schedules_sending_for_next_day(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // اعتماد يوم الأربعاء الساعة 2:00 ظهراً
        Carbon::setTestNow(Carbon::parse('2026-10-07 14:00:00'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('approve', [], ['id' => $this->dist1->id])
            ->assertNotified();

        $this->dist1->refresh();

        // يجب أن يُجدول لليوم التالي (الخميس) الساعة 12:00 ظهراً
        $this->assertEquals('2026-10-08 12:00:00', $this->dist1->scheduled_sending_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_can_request_changes_with_feedback(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('requestChanges', [
                'reviewer_feedback' => 'يرجى تصحيح الخطأ الإملائي في النص المكتوب.',
            ], ['id' => $this->dist2->id])
            ->assertNotified();

        $this->assertDatabaseHas('client_tag_distributions', [
            'id' => $this->dist2->id,
            'status' => 'changes_requested',
            'reviewer_feedback' => 'يرجى تصحيح الخطأ الإملائي في النص المكتوب.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->designer->user_id,
        ]);
    }

    public function test_can_request_changes_with_attachments(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $attachments = [
            'clients/2/distributions/2/revisions/screenshot1.png',
            'clients/2/distributions/2/revisions/screenshot2.png',
        ];

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('requestChanges', [
                'reviewer_feedback' => 'يرجى مراجعة الصور المرفقة والتعديل حسب التوضيح.',
                'reviewer_attachments' => $attachments,
            ], ['id' => $this->dist2->id])
            ->assertNotified();

        $this->dist2->refresh();
        $this->assertSame('changes_requested', $this->dist2->status);
        $this->assertSame('يرجى مراجعة الصور المرفقة والتعديل حسب التوضيح.', $this->dist2->reviewer_feedback);
        $this->assertEquals($attachments, $this->dist2->reviewer_attachments);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->designer->user_id,
        ]);
    }

    public function test_can_update_changes_request(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // First, set the distribution to changes_requested with existing feedback
        $this->dist2->update([
            'status' => 'changes_requested',
            'reviewer_feedback' => 'ملاحظة قديمة',
            'reviewer_attachments' => ['old/path/img.png'],
        ]);

        $newFeedback = 'ملاحظة محدّثة بعد المراجعة المجددة';
        $newAttachments = ['clients/2/distributions/2/revisions/new_screenshot.png'];

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('updateChanges', [
                'reviewer_feedback' => $newFeedback,
                'reviewer_attachments' => $newAttachments,
            ], ['id' => $this->dist2->id])
            ->assertNotified();

        $this->dist2->refresh();

        // Status should remain changes_requested
        $this->assertSame('changes_requested', $this->dist2->status);
        // Feedback should be updated
        $this->assertSame($newFeedback, $this->dist2->reviewer_feedback);
        // Attachments should be updated
        $this->assertEquals($newAttachments, $this->dist2->reviewer_attachments);

        // Designer should receive a notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->designer->user_id,
        ]);
    }

    public function test_update_changes_action_ignores_non_changes_requested_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // dist1 is in 'reviewing' status — updateChanges should NOT alter it
        $originalFeedback = $this->dist1->reviewer_feedback;

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->callAction('updateChanges', [
                'reviewer_feedback' => 'لا ينبغي أن تُحدَّث',
                'reviewer_attachments' => [],
            ], ['id' => $this->dist1->id]);

        $this->dist1->refresh();
        $this->assertSame($originalFeedback, $this->dist1->reviewer_feedback);
        $this->assertSame('reviewing', $this->dist1->status);
    }

    public function test_reviewer_dashboard_renders_client_cliche_and_logo_drawer_data(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Update client1 with logo and approved cliche template
        $this->client1->update([
            'logo_path' => 'clients/1/logo.png',
        ]);

        ClientTemplate::create([
            'client_id' => $this->client1->id,
            'type' => ClientTemplateType::Cliche->value,
            'file' => 'clients/1/templates/cliche.png',
            'local_path' => 'D:/Assets/Clients/Nokhba/cliche.psd',
        ]);

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->assertSuccessful()
            ->assertSee('شركة النخبة للتجارة')
            ->assertSee('الكليشة الرسمية المعتمدة')
            ->assertSee('شعار العميل المعتمد');
    }

    public function test_reviewer_dashboard_handles_client_without_cliche_or_logo_gracefully(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Ensure client2 has neither logo nor cliche
        $this->client2->update([
            'logo_path' => null,
        ]);
        $this->client2->templates()->delete();

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->assertSuccessful()
            ->assertSee('مؤسسة الشروق الرقمية')
            ->assertSee('لا توجد كليشة رسمية مسجلة')
            ->assertSee('لا يوجد شعار مرفوع');
    }

    public function test_revision_tab_renders_client_drawer_triggers(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->dist2->update([
            'status' => 'changes_requested',
            'reviewer_feedback' => 'ملاحظات تدقيق',
        ]);

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->set('activeTab', 'revision')
            ->assertSuccessful()
            ->assertSee('مؤسسة الشروق الرقمية');
    }

    public function test_resubmitted_review_item_displays_previous_change_request_and_badge(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // dist1 is in 'reviewing' status with previous feedback (re-submitted after changes requested)
        $this->dist1->update([
            'status' => 'reviewing',
            'reviewer_feedback' => 'يرجى تكبير اللوجو وتغيير لون الخلفية للأبيض',
            'reviewer_attachments' => ['clients/1/revisions/mock_feedback.png'],
        ]);

        Livewire::actingAs($this->reviewer)
            ->test(ReviewerDashboard::class)
            ->assertSuccessful()
            ->assertSee('مُعاد تسليمه بعد تعديل')
            ->assertSee('ملاحظات التعديل السابقة المطلوب التحقق منها:')
            ->assertSee('يرجى تكبير اللوجو وتغيير لون الخلفية للأبيض')
            ->assertSee('تأكد من تنفيذ المصمم لهذه التعديلات');
    }
}
