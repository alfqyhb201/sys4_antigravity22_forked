<?php

namespace Tests\Feature;

use App\Filament\Pages\TagDistribution;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagDistributionEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected Designer $designer;

    protected Currency $currency;

    protected string $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_tag_distribution']);
        $this->user->givePermissionTo('view_tag_distribution');

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->category = Category::factory()->create();
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);
        $this->designer = $this->createDesigner();
        $this->weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
    }

    protected function createDesigner(array $attributes = []): Designer
    {
        $user = User::factory()->create();

        return Designer::create(array_merge([
            'user_id' => $user->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'current_load' => 0,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
            'work_status' => 'active',
            'added_by_user' => $this->user->id,
        ], $attributes));
    }

    protected function createClient(array $attributes = []): Client
    {
        return Client::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'added_by_user' => $this->user->id,
            'customer_rating_value' => 5,
        ], $attributes));
    }

    protected function createAssignment(Client $client, ?string $week = null): ClientDesigner
    {
        $contract = Contract::create([
            'client_id' => $client->id,
            'currency_id' => $this->currency->id,
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'added_by_user' => $this->user->id,
        ]);

        return ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer->id,
            'contract_id' => $contract->id,
            'week_start_date' => $week ?? $this->weekStart,
            'is_side' => false,
        ]);
    }

    /** @test */
    public function it_can_delete_single_tag_distribution(): void
    {
        $client = $this->createClient();
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $distribution = ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('deleteDistribution', $distribution->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('client_tag_distributions', [
            'id' => $distribution->id,
        ]);
    }

    /** @test */
    public function it_cannot_delete_tag_when_status_is_reviewing_or_sending_or_completed(): void
    {
        $client = $this->createClient();
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        foreach (['reviewing', 'sending', 'completed'] as $status) {
            $distribution = ClientTagDistribution::create([
                'client_designer_id' => $assignment->id,
                'tag_id' => $tag->id,
                'distribution_date' => $this->weekStart,
                'status' => $status,
            ]);

            Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
                ->call('deleteDistribution', $distribution->id);

            $this->assertDatabaseHas('client_tag_distributions', [
                'id' => $distribution->id,
            ]);
        }
    }

    /** @test */
    public function it_navigates_between_weeks_smoothly(): void
    {
        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $prevWeek = Carbon::parse($currentWeek)->subWeek()->startOfWeek()->format('Y-m-d');
        $nextWeek = Carbon::parse($currentWeek)->addWeek()->startOfWeek()->format('Y-m-d');

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $currentWeek])
            ->call('goToPreviousWeek')
            ->assertSet('selectedWeek', $prevWeek)
            ->call('goToNextWeek')
            ->assertSet('selectedWeek', $currentWeek)
            ->call('goToNextWeek')
            ->assertSet('selectedWeek', $nextWeek)
            ->call('goToCurrentWeek')
            ->assertSet('selectedWeek', $currentWeek);
    }

    /** @test */
    public function it_can_quick_add_tag_to_client_on_specific_day(): void
    {
        $client = $this->createClient();
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create(['name' => 'Quick Tag']);
        $this->category->tags()->attach([$tag->id]);

        $targetDate = Carbon::now()->addDay()->format('Y-m-d');

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('openQuickAddModal', $assignment->id, $targetDate)
            ->assertSet('quickAddAssignmentId', $assignment->id)
            ->assertSet('quickAddDate', $targetDate)
            ->set('quickAddTagId', $tag->id)
            ->set('quickAddCustomIdea', 'ملاحظة سريعة للمصمم')
            ->call('saveQuickAdd')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_tag_distributions', [
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $targetDate,
            'custom_idea' => 'ملاحظة سريعة للمصمم',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_does_not_trap_stepped_distribution_buttons_when_very_high_is_disabled(): void
    {
        $client = $this->createClient(['enable_very_high' => false]);
        $assignment = $this->createAssignment($client);

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart]);

        // When all clients have enable_very_high=false, hasDistributedVeryHigh must return true so next step is unlocked
        $this->assertTrue(
            $component->instance()->hasDistributedVeryHigh($this->designer->id),
            'hasDistributedVeryHigh should return true when no clients have very high tags enabled'
        );
    }
}
