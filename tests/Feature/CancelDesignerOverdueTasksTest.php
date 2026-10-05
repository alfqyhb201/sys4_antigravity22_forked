<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelDesignerOverdueTasksTest extends TestCase
{
    use RefreshDatabase;

    private function makeDistribution(Designer $designer, string $date, string $status): ClientTagDistribution
    {
        $client = Client::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'location_id' => Location::factory()->create()->id,
        ]);
        $assignment = ClientDesigner::factory()->create([
            'designer_id' => $designer->id,
            'client_id' => $client->id,
        ]);

        return ClientTagDistribution::factory()->create([
            'client_designer_id' => $assignment->id,
            'distribution_date' => $date,
            'status' => $status,
        ]);
    }

    public function test_cancels_only_previous_month_overdue_tasks_of_named_designer(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));

        $target = Designer::factory()->create(['user_id' => User::factory()->create(['name' => 'اسامة غراب'])->id]);
        $other = Designer::factory()->create(['user_id' => User::factory()->create(['name' => 'مصمم آخر'])->id]);

        $old = $this->makeDistribution($target, '2026-09-15', 'pending');
        $oldChanges = $this->makeDistribution($target, '2026-09-02', 'changes_requested');
        $oldCompleted = $this->makeDistribution($target, '2026-09-10', 'completed');
        $thisMonth = $this->makeDistribution($target, '2026-10-02', 'pending');
        $otherOld = $this->makeDistribution($other, '2026-09-15', 'pending');

        $this->artisan('designers:cancel-overdue', ['--designer' => ['اسامة غراب']])
            ->assertSuccessful();

        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('cancelled', $oldChanges->fresh()->status);
        $this->assertSame('completed', $oldCompleted->fresh()->status);
        $this->assertSame('pending', $thisMonth->fresh()->status);
        $this->assertSame('pending', $otherOld->fresh()->status);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));

        $target = Designer::factory()->create(['user_id' => User::factory()->create(['name' => 'اسامة غراب'])->id]);
        $old = $this->makeDistribution($target, '2026-09-15', 'pending');

        $this->artisan('designers:cancel-overdue', ['--designer' => ['اسامة'], '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('pending', $old->fresh()->status);
    }

    public function test_requires_designer_option(): void
    {
        $this->artisan('designers:cancel-overdue')->assertFailed();
    }
}
