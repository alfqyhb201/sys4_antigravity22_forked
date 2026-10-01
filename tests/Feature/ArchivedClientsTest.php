<?php

namespace Tests\Feature;

use App\Filament\Pages\Archive\ArchivedClients;
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
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArchivedClientsTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        return $admin;
    }

    private function createClientWithDesigns(int $designsCount = 3, array $clientOverrides = []): array
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create(array_merge([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'cliche_counter' => 5,
        ], $clientOverrides));

        $designerUser = User::factory()->create(['name' => 'مصمم تجريبي']);
        $designer = Designer::factory()->create(['user_id' => $designerUser->id]);

        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $tag = Tag::factory()->create(['name' => 'جمعة مباركة']);
        $creator = User::factory()->create();
        $idea = Idea::create([
            'name' => 'تصميم أسبوعي',
            'added_by_user' => $creator->id,
        ]);

        $distributions = [];
        for ($i = 0; $i < $designsCount; $i++) {
            $distributions[] = ClientTagDistribution::factory()->create([
                'client_designer_id' => $clientDesigner->id,
                'tag_id' => $tag->id,
                'idea_id' => $idea->id,
                'status' => 'completed',
                'attachment_path' => 'designs/test_'.$i.'.jpg',
                'completed_at' => now()->subDays($i),
            ]);
        }

        return [
            'client' => $client,
            'designer' => $designer,
            'designerUser' => $designerUser,
            'clientDesigner' => $clientDesigner,
            'distributions' => $distributions,
            'tag' => $tag,
            'idea' => $idea,
        ];
    }

    public function test_admin_and_supervisor_can_access_archived_clients_page(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ArchivedClients::class)
            ->assertSuccessful();

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Role::firstOrCreate(['name' => 'supervisor']));
        $this->actingAs($supervisor);

        Livewire::test(ArchivedClients::class)
            ->assertSuccessful();
    }

    public function test_user_with_view_archive_permission_can_access(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_archive']);
        $user->givePermissionTo('view_archive');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ArchivedClients::class)
            ->assertSuccessful();
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(ArchivedClients::canAccess());
    }

    public function test_shows_clients_with_completed_distributions(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $dataWithDesigns = $this->createClientWithDesigns(2, ['company' => 'شركة الأفق المتقدم']);

        // Client without any completed designs
        $emptyClient = Client::factory()->create([
            'company' => 'شركة بدون تصاميم',
            'category_id' => Category::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ArchivedClients::class)
            ->assertCanSeeTableRecords([$dataWithDesigns['client']])
            ->assertCanNotSeeTableRecords([$emptyClient]);
    }

    public function test_quick_peek_table_action_exists(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $this->createClientWithDesigns(1);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ArchivedClients::class)
            ->assertTableActionExists('quickPeek')
            ->assertTableActionExists('viewDesigns');
    }

    public function test_submit_revision_updates_distribution_and_decrements_cliche_counter(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $data = $this->createClientWithDesigns(1, ['cliche_counter' => 6]);
        $dist = $data['distributions'][0];
        $client = $data['client'];
        $designerUser = $data['designerUser'];

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);
        $component->call('submitRevision', $dist->id, 'تعديل موضع الشعار والألوان');

        $dist->refresh();
        $this->assertEquals('changes_requested', $dist->status);
        $this->assertEquals('تعديل موضع الشعار والألوان', $dist->reviewer_feedback);
        $this->assertEquals(now()->format('Y-m-d'), $dist->distribution_date);

        // Cliche counter decremented by 1
        $this->assertEquals(5, $client->fresh()->cliche_counter);

        // Notification sent to designer
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $designerUser->id,
            'notifiable_type' => User::class,
        ]);
    }

    public function test_submit_revision_handles_empty_feedback(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $data = $this->createClientWithDesigns(1);
        $dist = $data['distributions'][0];

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);
        $component->call('submitRevision', $dist->id, '   ');

        $dist->refresh();
        $this->assertEquals('completed', $dist->status); // Status unchanged
    }

    public function test_download_zip_handles_empty_or_nonexistent_files(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Storage::fake('public');

        $data = $this->createClientWithDesigns(1);
        $client = $data['client'];

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);
        $component->call('downloadZip', $client->id);
        $component->assertSuccessful();
    }

    public function test_download_zip_downloads_when_files_exist(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Storage::fake('public');

        $data = $this->createClientWithDesigns(1);
        $client = $data['client'];
        $dist = $data['distributions'][0];

        // Put a fake file on storage
        Storage::disk('public')->put($dist->attachment_path, 'fake image content');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);
        $component->call('downloadZip', $client->id);
        $component->assertFileDownloaded();
    }

    public function test_switching_tabs_and_global_designs_explorer(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $data = $this->createClientWithDesigns(3);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);

        // Initial tab is clients
        $this->assertEquals('clients', $component->get('activeTab'));

        // Switch to all_designs
        $component->set('activeTab', 'all_designs');
        $this->assertEquals('all_designs', $component->get('activeTab'));

        // Check computed properties
        $globalDesigns = $component->instance()->globalDesigns;
        $this->assertCount(3, $globalDesigns);

        // Test filtering by Tag
        $component->set('explorerTag', (string) $data['tag']->id);
        $this->assertCount(3, $component->instance()->globalDesigns);

        // Test filtering by nonexistent search
        $component->set('explorerSearch', 'نص_غير_موجود_إطلاقاً');
        $this->assertCount(0, $component->instance()->globalDesigns);

        // Test reset filters
        $component->call('resetExplorerFilters');
        $this->assertEquals('', $component->get('explorerSearch'));
        $this->assertEquals('all', $component->get('explorerTag'));
        $this->assertCount(3, $component->instance()->globalDesigns);
    }

    public function test_global_designs_bulk_zip_download(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Storage::fake('public');

        $data = $this->createClientWithDesigns(2);
        $dist1 = $data['distributions'][0];
        $dist2 = $data['distributions'][1];

        Storage::disk('public')->put($dist1->attachment_path, 'fake content 1');
        Storage::disk('public')->put($dist2->attachment_path, 'fake content 2');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(ArchivedClients::class);
        $component->set('selectedDesignIds', [$dist1->id, $dist2->id]);

        $component->call('downloadSelectedExplorerZip');
        $component->assertFileDownloaded();
    }
}
