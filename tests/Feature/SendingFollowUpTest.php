<?php

namespace Tests\Feature;

use App\Filament\Pages\SendingFollowUp;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\Idea;
use App\Models\Location;
use App\Models\Tag;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendingFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_and_admin_can_access_sending_follow_up_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        Livewire::test(SendingFollowUp::class)
            ->assertSuccessful();

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Role::firstOrCreate(['name' => 'supervisor']));
        $this->actingAs($supervisor);

        Livewire::test(SendingFollowUp::class)
            ->assertSuccessful();
    }

    public function test_tabs_and_timeframe_filtering_work_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $userDesigner = User::factory()->create(['name' => 'مصمم النخبة']);
        $designer = Designer::factory()->create(['user_id' => $userDesigner->id]);
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $tag = Tag::factory()->create(['name' => 'تاق تسويقي']);
        $idea = Idea::create(['name' => 'فكرة إبداعية']);

        // 1. Today distribution
        $distToday = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'idea_id' => $idea->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->startOfDay()->addHours(10),
        ]);

        // 2. Overdue distribution
        $distOverdue = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'idea_id' => $idea->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subDays(2),
        ]);

        // 3. Upcoming distribution
        $distUpcoming = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'idea_id' => $idea->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->addDays(3),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SendingFollowUp::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$distToday])
            ->assertCanNotSeeTableRecords([$distOverdue, $distUpcoming])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$distToday, $distOverdue, $distUpcoming])
            ->assertSee('مصمم النخبة')
            ->assertSee('فكرة إبداعية');
    }

    public function test_mark_as_completed_action_updates_status_and_increments_cliche_counter(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'cliche_counter' => 5,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $distribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SendingFollowUp::class)
            ->callTableAction('markAsCompleted', $distribution)
            ->assertHasNoTableActionErrors();

        $distribution->refresh();
        $this->assertEquals('completed', $distribution->status->value ?? $distribution->status);
        $this->assertEquals($admin->id, $distribution->sender_id);
        $this->assertNotNull($distribution->completed_at);

        // Client cliché counter incremented from 5 to 6
        $this->assertEquals(6, $client->fresh()->cliche_counter);
    }

    public function test_bulk_mark_as_completed_action_works(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'cliche_counter' => 0,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $dist1 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);
        $dist2 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SendingFollowUp::class)
            ->callTableBulkAction('markSelectedAsCompleted', [$dist1, $dist2])
            ->assertHasNoTableBulkActionErrors();

        $this->assertEquals('completed', $dist1->fresh()->status->value ?? $dist1->fresh()->status);
        $this->assertEquals('completed', $dist2->fresh()->status->value ?? $dist2->fresh()->status);
        $this->assertEquals(2, $client->fresh()->cliche_counter);
    }

    public function test_filters_by_client_and_designer_work(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $clientA = Client::factory()->create(['company' => 'شركة الفا', 'category_id' => $category->id, 'location_id' => $location->id]);
        $clientB = Client::factory()->create(['company' => 'شركة بيتا', 'category_id' => $category->id, 'location_id' => $location->id]);

        $userA = User::factory()->create(['name' => 'مصمم 1']);
        $userB = User::factory()->create(['name' => 'مصمم 2']);

        $designerA = Designer::factory()->create(['user_id' => $userA->id]);
        $designerB = Designer::factory()->create(['user_id' => $userB->id]);

        $cdA = ClientDesigner::create(['client_id' => $clientA->id, 'designer_id' => $designerA->id]);
        $cdB = ClientDesigner::create(['client_id' => $clientB->id, 'designer_id' => $designerB->id]);

        $distA = ClientTagDistribution::factory()->create([
            'client_designer_id' => $cdA->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);
        $distB = ClientTagDistribution::factory()->create([
            'client_designer_id' => $cdB->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SendingFollowUp::class)
            ->filterTable('client', $clientA->id)
            ->assertCanSeeTableRecords([$distA])
            ->assertCanNotSeeTableRecords([$distB])
            ->filterTable('client', $clientB->id)
            ->assertCanSeeTableRecords([$distB])
            ->assertCanNotSeeTableRecords([$distA]);

        Livewire::test(SendingFollowUp::class)
            ->filterTable('designer', $designerA->id)
            ->assertCanSeeTableRecords([$distA])
            ->assertCanNotSeeTableRecords([$distB]);
    }

    public function test_reviewer_display_and_filter_work(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $reviewerA = User::factory()->create(['name' => 'المراجع أحمد']);
        $reviewerB = User::factory()->create(['name' => 'المراجع خالد']);

        $distA = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'reviewer_id' => $reviewerA->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);
        $distB = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'reviewer_id' => $reviewerB->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SendingFollowUp::class)
            ->filterTable('reviewer', $reviewerA->id)
            ->assertCanSeeTableRecords([$distA])
            ->assertCanNotSeeTableRecords([$distB]);
    }

    public function test_request_changes_action_changes_status_and_notifies_designer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $category = Category::factory()->create();
        $client = Client::factory()->create(['category_id' => $category->id]);
        $designerUser = User::factory()->create(['name' => 'مصمم للتعديل']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $tag = Tag::factory()->create(['name' => 'تاق اختبار']);

        $dist = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'tag_id' => $tag->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(SendingFollowUp::class)
            ->set('activeTab', 'all')
            ->callTableAction('requestChanges', $dist, [
                'reviewer_feedback' => 'يرجى تعديل الألوان في التصميم.',
                'reviewer_attachments' => [],
            ])
            ->assertNotified();

        $dist->refresh();

        $this->assertSame('changes_requested', $dist->status);
        $this->assertSame('يرجى تعديل الألوان في التصميم.', $dist->reviewer_feedback);
        $this->assertSame($admin->id, $dist->reviewer_id);

        // Designer should receive a notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designerUser->id,
        ]);
    }
}
