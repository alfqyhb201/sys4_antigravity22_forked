<?php

namespace Tests\Feature;

use App\Filament\Pages\Archive\RecentlySentArchive;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\Location;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecentlySentArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function createCompletedDistribution(array $overrides = []): ClientTagDistribution
    {
        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create(array_merge([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'cliche_counter' => 5,
        ], $overrides['client'] ?? []));

        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        return ClientTagDistribution::factory()->create(array_merge([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'completed',
            'completed_at' => now(),
            'sender_id' => $overrides['sender_id'] ?? null,
        ], $overrides['distribution'] ?? []));
    }

    public function test_supervisor_and_admin_can_access_recently_sent_archive(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->assertSuccessful();

        $supervisor = User::factory()->create();
        $supervisor->assignRole(Role::firstOrCreate(['name' => 'supervisor']));
        $this->actingAs($supervisor);

        Livewire::test(RecentlySentArchive::class)
            ->assertSuccessful();
    }

    public function test_designer_cannot_access_recently_sent_archive(): void
    {
        $designer = User::factory()->create();
        $designer->assignRole(Role::firstOrCreate(['name' => 'designer']));
        $this->actingAs($designer);

        $this->assertFalse(RecentlySentArchive::canAccess());
    }

    public function test_shows_only_recently_completed_distributions(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // Recent (today) — should show
        $recent = $this->createCompletedDistribution([
            'distribution' => [
                'completed_at' => now(),
            ],
        ]);

        // Old (10 days ago) — should NOT show
        $old = $this->createCompletedDistribution([
            'distribution' => [
                'completed_at' => now()->subDays(10),
            ],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_undo_single_send_restores_to_sending_status(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $dist = $this->createCompletedDistribution([
            'distribution' => [
                'sender_id' => $admin->id,
                'completed_at' => now(),
            ],
        ]);

        $client = $dist->clientDesigner->client;
        $originalClicheCounter = $client->cliche_counter;

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->callTableAction('undoSend', $dist)
            ->assertHasNoTableActionErrors();

        $dist->refresh();
        $this->assertEquals('sending', $dist->status);
        $this->assertNull($dist->sender_id);
        $this->assertNull($dist->completed_at);

        // Cliche counter decremented
        $this->assertEquals($originalClicheCounter - 1, $client->fresh()->cliche_counter);
    }

    public function test_bulk_undo_send_restores_multiple_records(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $category = Category::factory()->create();
        $location = Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'cliche_counter' => 10,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $dist1 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'completed',
            'completed_at' => now(),
            'sender_id' => $admin->id,
        ]);
        $dist2 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'completed',
            'completed_at' => now(),
            'sender_id' => $admin->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->callTableBulkAction('undoSelectedSend', [$dist1, $dist2])
            ->assertHasNoTableBulkActionErrors();

        $this->assertEquals('sending', $dist1->fresh()->status);
        $this->assertEquals('sending', $dist2->fresh()->status);
        $this->assertNull($dist1->fresh()->completed_at);
        $this->assertNull($dist2->fresh()->completed_at);

        // Client counter decremented by 2 (from 10 to 8)
        $this->assertEquals(8, $client->fresh()->cliche_counter);
    }

    public function test_undo_does_not_decrement_cliche_counter_below_zero(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $dist = $this->createCompletedDistribution([
            'client' => ['cliche_counter' => 0],
            'distribution' => [
                'completed_at' => now(),
            ],
        ]);

        $client = $dist->clientDesigner->client;

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->callTableAction('undoSend', $dist)
            ->assertHasNoTableActionErrors();

        $this->assertEquals(0, $client->fresh()->cliche_counter);
        $this->assertEquals('sending', $dist->fresh()->status);
    }

    public function test_download_selected_bulk_action_exists(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(RecentlySentArchive::class)
            ->assertTableBulkActionExists('downloadSelected');
    }
}
