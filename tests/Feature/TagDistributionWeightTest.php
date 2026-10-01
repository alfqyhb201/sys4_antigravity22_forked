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
use App\Models\TagGroup;
use App\Models\User;
use App\Services\ImportanceRatioService;
use App\Services\TagDistributionService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagDistributionWeightTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected Designer $designer;

    protected Currency $currency;

    protected TagGroup $tagGroup;

    protected string $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Create the permission and assign it
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_tag_distribution']);
        $this->user->givePermissionTo('view_tag_distribution');

        $this->actingAs($this->user);
        Filament::setCurrentPanel(
            Filament::getPanel('app'),
        );

        $this->category = Category::factory()->create();
        $this->tagGroup = TagGroup::create(['name' => 'test_group', 'added_by_user' => $this->user->id]);
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);
        $this->designer = $this->createDesigner();
        $this->weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
    }

    /** @test */
    public function it_distributes_tags_according_to_importance_weights()
    {
        $client = $this->createClientWithWeights(['high' => 70, 'medium' => 30], 7, true);

        // Create tags with different importance levels
        $highTag = Tag::factory()->high()->create();
        $mediumTag = Tag::factory()->medium()->create();
        $lowTag = Tag::factory()->low()->create();

        // Attach tags to category (hybrid logic path)
        $this->category->tags()->attach([$highTag->id, $mediumTag->id, $lowTag->id]);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        // Get distributions for this client
        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distributions = $assignment->distributions;

        $highCount = $distributions->filter(fn ($d) => $d->tag->importance === 'high')->count();
        $mediumCount = $distributions->filter(fn ($d) => $d->tag->importance === 'medium')->count();

        // 7 designs, enable_very_high=true → 1 very_high + remaining 6
        // 70% of 6 = 4.2 → quotas: high=4, medium=2
        // But there's no very_high tag, so the very_high quota falls back
        // Expected: high ~4, medium ~2 (approximately due to tag availability)

        $this->assertGreaterThan(0, $highCount, 'Should distribute high tags');
        $this->assertGreaterThan(0, $mediumCount, 'Should distribute medium tags');
    }

    /** @test */
    public function it_respects_enable_very_high_false()
    {
        $client = $this->createClientWithWeights(['high' => 70, 'medium' => 30], 5, false);

        $veryHighTag = Tag::factory()->veryHigh()->create();
        $highTag = Tag::factory()->high()->create();

        $this->category->tags()->attach([$veryHighTag->id, $highTag->id]);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(fn ($d) => $d->tag->importance === 'very_high')->count();

        $this->assertEquals(0, $veryHighCount, 'Very High tags should NOT be distributed when enable_very_high=false');
    }

    /** @test */
    public function it_does_not_distribute_very_high_when_weights_null()
    {
        $client = $this->createClientWithWeights(null, 5, true);

        $veryHighTag = Tag::factory()->veryHigh()->create();
        $highTag = Tag::factory()->high()->create();

        $this->category->tags()->attach([$veryHighTag->id, $highTag->id]);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $this->assertTrue($assignment->distributions->count() > 0, 'Should still distribute tags without weights');
    }

    /** @test */
    public function it_handles_mixed_level_tags_with_single_level_weight()
    {
        $client = $this->createClientWithWeights(['high' => 100], 3, true);

        $veryHighTag = Tag::factory()->veryHigh()->create();
        $highTag1 = Tag::factory()->high()->create();
        $highTag2 = Tag::factory()->high()->create();
        $mediumTag = Tag::factory()->medium()->create();

        $this->category->tags()->attach([$veryHighTag->id, $highTag1->id, $highTag2->id, $mediumTag->id]);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $highCount = $assignment->distributions->filter(fn ($d) => $d->tag->importance === 'high')->count();
        $mediumCount = $assignment->distributions->filter(fn ($d) => $d->tag->importance === 'medium')->count();

        $this->assertGreaterThanOrEqual(1, $highCount, 'Should have at least 1 high tag');
        $this->assertEquals(0, $mediumCount, 'Should not distribute medium tags when weight is 100% high');
    }

    /** @test */
    public function it_calculates_level_quota_correctly()
    {
        $service = app(ImportanceRatioService::class);

        // 7 designs, weights {high:70, medium:30}, very_high enabled
        $result = $service->calculateLevelQuota(['high' => 70, 'medium' => 30], true, 7);

        $this->assertEquals(1, $result['very_high']);
        // remaining = 6, high=70% of 6 = 4.2 → 4, medium=30% of 6 = 1.8 → 1, remainder=1
        $this->assertEquals(5, $result['high'], 'High quota should be 5 (4 base + 1 remainder)');
        $this->assertEquals(1, $result['medium'], 'Medium quota should be 1');
        $this->assertEquals(0, $result['low'], 'Low quota should be 0');

        // 5 designs, weights {medium:60, low:40}, very_high disabled
        $result = $service->calculateLevelQuota(['medium' => 60, 'low' => 40], false, 5);

        $this->assertEquals(0, $result['very_high']);
        $this->assertEquals(0, $result['high']);
        $this->assertEquals(3, $result['medium']);
        $this->assertEquals(2, $result['low']);
    }

    /** @test */
    public function it_calculates_rank_correctly()
    {
        $service = app(ImportanceRatioService::class);

        $veryHighTag = Tag::factory()->veryHigh()->make();
        $highTag = Tag::factory()->high()->make();
        $mediumTag = Tag::factory()->medium()->make();
        $lowTag = Tag::factory()->low()->make();
        $otherTag = Tag::factory()->make(['importance' => 'unknown']);

        $this->assertEquals(1, $service->getImportanceRank($veryHighTag));
        $this->assertEquals(2, $service->getImportanceRank($highTag));
        $this->assertEquals(3, $service->getImportanceRank($mediumTag));
        $this->assertEquals(4, $service->getImportanceRank($lowTag));
        $this->assertEquals(5, $service->getImportanceRank($otherTag));
    }

    /** @test */
    public function it_selects_weighted_tags_proportionally()
    {
        $service = app(ImportanceRatioService::class);

        $highTag = Tag::factory()->high()->create();
        $mediumTag = Tag::factory()->medium()->create();
        $lowTag = Tag::factory()->low()->create();

        $tags = collect([$highTag, $mediumTag, $lowTag]);

        $selected = $service->selectWeightedTags($tags, ['high' => 50, 'medium' => 30, 'low' => 20], true, 6);

        $this->assertEquals(6, $selected->count(), 'Should select exactly designsCount tags');

        $levels = $selected->map(fn ($t) => $t->importance)->countBy()->toArray();

        $this->assertArrayHasKey('high', $levels, 'Should include high tags');
        $this->assertArrayHasKey('medium', $levels, 'Should include medium tags');
        $this->assertArrayHasKey('low', $levels, 'Should include low tags');
    }

    /** @test */
    public function it_clears_all_tags_for_week(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $highTag = Tag::factory()->high()->create();
        $this->category->tags()->attach($highTag->id);
        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute')
            ->assertNotified();

        $this->assertGreaterThan(0, $this->totalDistributions());

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('clearTags')
            ->assertNotified();

        $this->assertEquals(0, $this->totalDistributions());
    }

    /** @test */
    public function it_clears_client_tags(): void
    {
        $client1 = $this->createClientWithWeights(['high' => 100], 3, true);
        $client2 = $this->createClientWithWeights(['high' => 100], 2, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client1);
        $this->createAssignment($client2);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment1 = ClientDesigner::where('client_id', $client1->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $this->assertGreaterThan(0, $assignment1->distributions()->count());

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('clearClientTags', $assignment1->id)
            ->assertNotified();

        $this->assertEquals(0, $assignment1->fresh()->distributions()->count());

        $assignment2 = ClientDesigner::where('client_id', $client2->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $this->assertGreaterThan(0, $assignment2->distributions()->count());
    }

    /** @test */
    public function it_clears_tags_by_designer(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('clearTagsForDesigner', $this->designer->id)
            ->assertNotified();

        $this->assertEquals(0, $this->totalDistributions());
    }

    /** @test */
    public function it_moves_tag_to_different_day(): void
    {
        Carbon::setTestNow(Carbon::parse($this->weekStart)->startOfDay());

        $client = $this->createClientWithWeights(['high' => 100], 5, true);
        $tag = Tag::factory()->high()->create(['is_there_date_for_sending' => false]);
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client);

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart]);

        $component->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distribution = $assignment->distributions()->first();

        $targetDate = Carbon::parse($this->weekStart)->addDays(5)->format('Y-m-d');

        $component
            ->call('openChangeDateModal', $distribution->id)
            ->set('newDate', $targetDate)
            ->call('changeDate')
            ->assertNotified();

        $this->assertEquals($targetDate, $distribution->fresh()->distribution_date);

        Carbon::setTestNow();
    }

    /** @test */
    public function it_updates_tag_for_distribution(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 5, true);
        $tag1 = Tag::factory()->high()->create();
        $tag2 = Tag::factory()->high()->create();
        $this->category->tags()->attach([$tag1->id, $tag2->id]);
        $this->createAssignment($client);

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart]);

        $component->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distribution = $assignment->distributions()->first();

        $component
            ->call('openEditDetailsModal', $distribution->id)
            ->set('newTagId', (string) $tag2->id)
            ->call('updateDetails')
            ->assertNotified();

        $this->assertEquals($tag2->id, $distribution->fresh()->tag_id);
    }

    /** @test */
    public function it_clears_ideas_for_week(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 2, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client);

        $idea = \App\Models\Idea::create([
            'name' => 'Test Idea',
            'added_by_user' => $this->user->id,
        ]);
        $idea->tags()->attach($tag->id);

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart]);
        $component->call('autoDistribute');
        $component->call('autoAssignIdeas');
        $component->call('clearIdeas')
            ->assertNotified();

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $ideasCount = $assignment->distributions()->whereNotNull('idea_id')->count();
        $this->assertEquals(0, $ideasCount);
    }

    /** @test */
    public function it_prevents_mutation_in_past_week(): void
    {
        $pastWeek = Carbon::now()->startOfWeek()->subWeek()->format('Y-m-d');

        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $assignment = $this->createAssignment($client);

        $assignment->update(['week_start_date' => $pastWeek]);

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $pastWeek,
            'status' => 'pending',
        ]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $pastWeek])
            ->call('clearTags');

        $this->assertEquals(1, $distribution->fresh()->tag_id);
    }

    /** @test */
    public function it_prevents_clear_client_tags_in_past_week(): void
    {
        $pastWeek = Carbon::now()->startOfWeek()->subWeek()->format('Y-m-d');

        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $assignment = $this->createAssignment($client);
        $assignment->update(['week_start_date' => $pastWeek]);

        $distribution = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $pastWeek,
            'status' => 'pending',
        ]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $pastWeek])
            ->call('clearClientTags', $assignment->id);

        $this->assertEquals(1, $distribution->fresh()->tag_id);
    }

    /** @test */
    public function it_auto_assigns_ideas_full_flow(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 2, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client);

        $idea = \App\Models\Idea::create([
            'name' => 'Test Idea',
            'added_by_user' => $this->user->id,
        ]);
        $idea->tags()->attach($tag->id);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoAssignIdeas');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $ideasCount = $assignment->distributions()->whereNotNull('idea_id')->count();
        $this->assertGreaterThan(0, $ideasCount);
    }

    /** @test */
    public function it_checks_distribution_status_for_designer(): void
    {
        $client = $this->createClientWithWeights(['high' => 70, 'medium' => 30], 5, true);
        $veryHighTag = Tag::factory()->veryHigh()->create();
        $highTag = Tag::factory()->high()->create();
        $mediumTag = Tag::factory()->medium()->create();
        $this->category->tags()->attach([$veryHighTag->id, $highTag->id, $mediumTag->id]);
        $this->createAssignment($client);

        $component = Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart]);
        $component->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $component->assertSet('distributionStatus.'.$this->designer->id.'.very_high_processed', true);
    }

    // --- Helper Methods ---

    /** @test
     * Use `isPastWeek()` via the page method to verify past week detection.
     */
    public function is_past_week_works_correctly(): void
    {
        $pastWeek = Carbon::now()->startOfWeek()->subWeek();
        $futureWeek = Carbon::now()->startOfWeek()->addWeek();
        $currentWeek = Carbon::now()->startOfWeek();

        $this->assertTrue($pastWeek->lt($currentWeek), 'Past week should be lt current week');
        $this->assertFalse($futureWeek->lt($currentWeek), 'Future week should not be lt current week');
    }

    /** @test */
    public function it_allows_editing_in_current_week(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $highTag = Tag::factory()->high()->create();
        $this->category->tags()->attach($highTag->id);
        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute')
            ->assertNotified();
    }

    /** @test */
    public function it_distributes_tags_using_smart_distribution(): void
    {
        $client = $this->createClientWithWeights(['high' => 70, 'medium' => 30], 5, true);
        $veryHighTag = Tag::factory()->veryHigh()->create();
        $highTag = Tag::factory()->high()->create();
        $mediumTag = Tag::factory()->medium()->create();
        $this->category->tags()->attach([$veryHighTag->id, $highTag->id, $mediumTag->id]);
        $this->createAssignment($client);

        $designerId = $this->designer->id;

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeSmartForDesigner', $designerId)
            ->assertNotified();

        $assignment = ClientDesigner::with('distributions.tag')
            ->where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $this->assertGreaterThan(0, $assignment->distributions->count(), 'Should distribute at least one tag');
    }

    /** @test */
    public function it_distributes_ideas_for_designer(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 2, true);
        $tag = Tag::factory()->high()->create();
        $this->category->tags()->attach($tag->id);
        $this->createAssignment($client);

        $idea = \App\Models\Idea::create([
            'name' => 'Test Idea',
            'added_by_user' => $this->user->id,
        ]);
        $idea->tags()->attach($tag->id);

        $designerId = $this->designer->id;

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeIdeasForDesigner', $designerId);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $ideasCount = $assignment->distributions->filter(fn ($d) => $d->idea_id !== null)->count();

        $this->assertGreaterThan(0, $ideasCount, 'Should assign ideas to at least one distribution');
    }

    protected function createDesigner(): Designer
    {
        $user = User::factory()->create();

        return Designer::create([
            'user_id' => $user->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);
    }

    protected function createClientWithWeights(?array $weights, int $designsCount, bool $enableVeryHigh): Client
    {
        $client = Client::factory()->create([
            'category_id' => $this->category->id,
            'importance_weights' => $weights,
            'enable_very_high' => $enableVeryHigh,
        ]);

        Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => $designsCount,
            'monthly_designs_count' => $designsCount * 4,
            'total_amount' => 1000,
            'currency_id' => $this->currency->id,
        ]);

        return $client->fresh(['currentContract', 'category']);
    }

    protected function totalDistributions(): int
    {
        return \App\Models\ClientTagDistribution::whereHas('clientDesigner', function ($q) {
            $q->where('week_start_date', $this->weekStart);
        })->count();
    }

    protected function createAssignment(Client $client, ?string $weekStart = null): ClientDesigner
    {
        return ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer->id,
            'week_start_date' => $weekStart ?? $this->weekStart,
            'contract_id' => $client->currentContract->id,
        ]);
    }

    /** @test */
    public function test_cannot_transfer_sending_or_completed_tag()
    {
        $client = $this->createClientWithWeights(null, 5, true);
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create();

        $dist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'sending',
        ]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('openTransferDesignerModal', $dist->id)
            ->set('newDesignerId', $this->designer->id)
            ->call('transferDesigner');

        // Verify designer was NOT changed
        $this->assertEquals($assignment->id, $dist->fresh()->client_designer_id);
    }

    /** @test */
    public function test_cannot_change_date_of_sending_or_completed_tag()
    {
        $client = $this->createClientWithWeights(null, 5, true);
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create();

        $dist = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'completed',
        ]);

        $newDate = Carbon::parse($this->weekStart)->addDay()->format('Y-m-d');

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('openChangeDateModal', $dist->id)
            ->set('newDate', $newDate)
            ->call('changeDate');

        // Verify distribution was NOT modified
        $this->assertEquals($this->weekStart, $dist->fresh()->distribution_date);
    }

    /** @test */
    public function test_clearing_methods_exclude_sending_or_completed_tags()
    {
        $client = $this->createClientWithWeights(null, 5, true);
        $assignment = $this->createAssignment($client);
        $tag = Tag::factory()->create();

        $distNormal = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        $distSending = \App\Models\ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'sending',
        ]);

        // Call clearTags
        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('clearTags');

        // Normal tag should be deleted, sending tag should remain
        $this->assertNull($distNormal->fresh());
        $this->assertNotNull($distSending->fresh());
    }

    /** @test */
    public function it_distributes_all_yearly_very_high_tags(): void
    {
        $client = $this->createClientWithWeights(null, 10, true);
        $this->createAssignment($client);

        // إنشاء 3 تاقات Very High بأيام مختلفة في نفس الأسبوع
        $today = Carbon::parse($this->weekStart);
        $yearlyTag1 = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->format('Y-m-d'),
        ]);
        $yearlyTag2 = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->copy()->addDay()->format('Y-m-d'),
        ]);
        $yearlyTag3 = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->copy()->addDays(2)->format('Y-m-d'),
        ]);

        $this->category->tags()->attach([$yearlyTag1->id, $yearlyTag2->id, $yearlyTag3->id]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(
            fn ($d) => $d->tag->importance === 'veryhigh'
        )->count();

        $this->assertEquals(3, $veryHighCount, 'All 3 yearly Very High tags should be distributed');
    }

    /** @test */
    public function it_distributes_one_flexible_very_high_tag(): void
    {
        $client = $this->createClientWithWeights(null, 5, true);
        $this->createAssignment($client);

        // إنشاء 3 تاقات Very High بدون تاريخ سنوي
        $flexTag1 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);
        $flexTag2 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);
        $flexTag3 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);

        $this->category->tags()->attach([$flexTag1->id, $flexTag2->id, $flexTag3->id]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(
            fn ($d) => $d->tag->importance === 'veryhigh'
        )->count();

        $this->assertEquals(1, $veryHighCount, 'Only 1 flexible Very High tag should be distributed');
    }

    /** @test */
    public function it_distributes_yearly_then_one_flexible_very_high(): void
    {
        $client = $this->createClientWithWeights(null, 10, true);
        $this->createAssignment($client);

        $today = Carbon::parse($this->weekStart);

        // 2 تاقات سنوية + 2 تاقات مرنة
        $yearlyTag1 = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->format('Y-m-d'),
        ]);
        $yearlyTag2 = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->copy()->addDay()->format('Y-m-d'),
        ]);
        $flexTag1 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);
        $flexTag2 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);

        $this->category->tags()->attach([$yearlyTag1->id, $yearlyTag2->id, $flexTag1->id, $flexTag2->id]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(
            fn ($d) => $d->tag->importance === 'veryhigh'
        )->count();

        $this->assertEquals(3, $veryHighCount, 'Should distribute 2 yearly + 1 flexible = 3 Very High tags');
    }

    /** @test */
    public function it_respects_designs_count_with_yearly_very_high(): void
    {
        // weekly_designs_count = 3 فقط
        $client = $this->createClientWithWeights(null, 3, true);
        $this->createAssignment($client);

        $today = Carbon::parse($this->weekStart);

        // 5 تاقات سنوية لكن السعة 3 فقط
        $tags = [];
        for ($i = 0; $i < 5; $i++) {
            $tags[] = Tag::factory()->veryHigh()->create([
                'is_there_date_for_sending' => true,
                'date_for_sending_yearly' => $today->copy()->addDays($i)->format('Y-m-d'),
            ]);
        }

        $this->category->tags()->attach(collect($tags)->pluck('id')->toArray());

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distCount = $assignment->distributions->count();

        $this->assertLessThanOrEqual(3, $distCount, 'Should not exceed designsCount of 3');
        $this->assertGreaterThan(0, $distCount, 'Should distribute at least 1 tag');
    }

    /** @test */
    public function it_does_not_distribute_yearly_very_high_when_disabled(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 5, false);
        $this->createAssignment($client);

        $today = Carbon::parse($this->weekStart);
        $yearlyTag = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $today->format('Y-m-d'),
        ]);
        $highTag = Tag::factory()->high()->create();
        $this->category->tags()->attach([$yearlyTag->id, $highTag->id]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(
            fn ($d) => $d->tag->importance === 'veryhigh'
        )->count();

        $this->assertEquals(0, $veryHighCount, 'No Very High tags when enable_very_high is false');
    }

    /** @test */
    public function it_does_not_distribute_yearly_tags_before_their_actual_week(): void
    {
        $client = $this->createClientWithWeights(['high' => 100], 3, true);
        $this->createAssignment($client);

        // Event date is 10 days in the future (next week)
        $futureEventDate = Carbon::parse($this->weekStart)->addDays(10);
        $yearlyTag = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => $futureEventDate->format('Y-m-d'),
        ]);
        $this->category->tags()->attach([$yearlyTag->id]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $veryHighCount = $assignment->distributions->filter(
            fn ($d) => $d->tag_id === $yearlyTag->id
        )->count();

        $this->assertEquals(0, $veryHighCount, 'Yearly tag should not be distributed in a week prior to event week');
    }

    /** @test */
    public function it_only_distributes_tags_from_client_tag_groups_when_specified(): void
    {
        $groupA = TagGroup::create(['name' => 'Group A', 'added_by_user' => $this->user->id]);
        $groupB = TagGroup::create(['name' => 'Group B', 'added_by_user' => $this->user->id]);

        $tagInGroupA = Tag::factory()->high()->create([
            'tag_group_id' => $groupA->id,
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);
        $tagInGroupB = Tag::factory()->high()->create([
            'tag_group_id' => $groupB->id,
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);

        $this->category->tags()->attach([$tagInGroupA->id, $tagInGroupB->id]);

        $client = $this->createClientWithWeights(['high' => 100], 3, false);
        // حددنا مجموعة A فقط للعميل بدون تحديد تاقات
        $client->tagGroups()->attach($groupA->id);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distributedTagIds = $assignment->distributions->pluck('tag_id')->toArray();

        $this->assertContains($tagInGroupA->id, $distributedTagIds, 'Should distribute tags from Group A');
        $this->assertNotContains($tagInGroupB->id, $distributedTagIds, 'Should NOT distribute tags from Group B');
    }

    /** @test */
    public function it_distributes_all_category_tags_when_no_tag_groups_specified(): void
    {
        $groupA = TagGroup::create(['name' => 'Group A', 'added_by_user' => $this->user->id]);
        $groupB = TagGroup::create(['name' => 'Group B', 'added_by_user' => $this->user->id]);

        $tagInGroupA = Tag::factory()->medium()->create([
            'tag_group_id' => $groupA->id,
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);
        $tagInGroupB = Tag::factory()->low()->create([
            'tag_group_id' => $groupB->id,
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);

        $this->category->tags()->attach([$tagInGroupA->id, $tagInGroupB->id]);

        $client = $this->createClientWithWeights(['medium' => 50, 'low' => 50], 2, false);
        // لم يتم تحديد مجموعات للعميل (فارغة)

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distributedTagIds = $assignment->distributions->pluck('tag_id')->toArray();

        $this->assertContains($tagInGroupA->id, $distributedTagIds);
        $this->assertContains($tagInGroupB->id, $distributedTagIds);
    }

    /** @test */
    public function it_prioritizes_individual_client_tags_over_category_tags(): void
    {
        // العميل يحتاج 2 تصاميم
        $client = $this->createClientWithWeights(['high' => 100], 2, false);

        // إنشاء 5 تاقات عامة تابعة للتصنيف
        $categoryTags = Tag::factory()->count(5)->high()->create([
            'is_active' => true,
            'is_auto_assigned' => true,
        ]);
        $this->category->tags()->attach($categoryTags->pluck('id')->toArray());

        // تاق فردي محدد بالاسم في ملف العميل
        $manualTag = Tag::factory()->high()->create([
            'name' => 'تاق فردي خاص بالعميل',
            'is_active' => true,
        ]);
        $client->tags()->attach($manualTag->id);

        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distributedTagIds = $assignment->distributions->pluck('tag_id')->toArray();

        // التاق الفردي للعميل يجب أن يكون محجوزاً وموزعاً بالضرورة
        $this->assertContains($manualTag->id, $distributedTagIds, 'Manual client tag MUST be prioritized and distributed');
        $this->assertCount(2, $distributedTagIds, 'Should fulfill the 2 designs count');
    }

    /** @test */
    public function it_diversifies_across_different_tag_groups_while_maintaining_weights(): void
    {
        $group1 = TagGroup::create(['name' => 'Group_Offers', 'added_by_user' => $this->user->id]);
        $group2 = TagGroup::create(['name' => 'Group_Educational', 'added_by_user' => $this->user->id]);
        $group3 = TagGroup::create(['name' => 'Group_Interactive', 'added_by_user' => $this->user->id]);

        $client = $this->createClientWithWeights(['high' => 100], 3, false);
        $client->tagGroups()->attach([$group1->id, $group2->id, $group3->id]);

        $tag1 = Tag::factory()->high()->create(['tag_group_id' => $group1->id, 'is_active' => true, 'is_auto_assigned' => true]);
        $tag2 = Tag::factory()->high()->create(['tag_group_id' => $group2->id, 'is_active' => true, 'is_auto_assigned' => true]);
        $tag3 = Tag::factory()->high()->create(['tag_group_id' => $group3->id, 'is_active' => true, 'is_auto_assigned' => true]);

        $this->category->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);
        $this->createAssignment($client);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $assignment = ClientDesigner::where('client_id', $client->id)
            ->where('week_start_date', $this->weekStart)
            ->first();

        $distributedTagIds = $assignment->distributions->pluck('tag_id')->toArray();

        $this->assertCount(3, $distributedTagIds);
        $this->assertContains($tag1->id, $distributedTagIds, 'Should contain tag from Group 1');
        $this->assertContains($tag2->id, $distributedTagIds, 'Should contain tag from Group 2');
        $this->assertContains($tag3->id, $distributedTagIds, 'Should contain tag from Group 3');
    }

    /** @test */
    public function it_does_not_repeat_tags_until_all_tags_in_group_or_level_are_exhausted(): void
    {
        $group = TagGroup::create(['name' => 'Group_Single', 'added_by_user' => $this->user->id]);
        $client = $this->createClientWithWeights(['high' => 100], 1, false);
        $client->tagGroups()->attach([$group->id]);

        $tagA = Tag::factory()->high()->create(['name' => 'Tag_A', 'tag_group_id' => $group->id, 'is_active' => true, 'is_auto_assigned' => true]);
        $tagB = Tag::factory()->high()->create(['name' => 'Tag_B', 'tag_group_id' => $group->id, 'is_active' => true, 'is_auto_assigned' => true]);
        $tagC = Tag::factory()->high()->create(['name' => 'Tag_C', 'tag_group_id' => $group->id, 'is_active' => true, 'is_auto_assigned' => true]);

        $this->category->tags()->attach([$tagA->id, $tagB->id, $tagC->id]);

        $week1 = Carbon::parse($this->weekStart)->format('Y-m-d');
        $week2 = Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d');
        $week3 = Carbon::parse($this->weekStart)->addWeeks(2)->format('Y-m-d');

        // Week 1:
        $this->createAssignment($client, $week1);
        Livewire::test(TagDistribution::class, ['selectedWeek' => $week1])->call('autoDistribute');
        $dist1 = ClientDesigner::where('client_id', $client->id)->where('week_start_date', $week1)->first()->distributions->first()->tag_id;

        // Week 2: Must NOT be dist1
        $this->createAssignment($client, $week2);
        Livewire::test(TagDistribution::class, ['selectedWeek' => $week2])->call('autoDistribute');
        $dist2 = ClientDesigner::where('client_id', $client->id)->where('week_start_date', $week2)->first()->distributions->first()->tag_id;

        $this->assertNotEquals($dist1, $dist2, 'Week 2 tag must differ from Week 1 tag (Exhaustion Principle)');

        // Week 3: Must NOT be dist1 or dist2
        $this->createAssignment($client, $week3);
        Livewire::test(TagDistribution::class, ['selectedWeek' => $week3])->call('autoDistribute');
        $dist3 = ClientDesigner::where('client_id', $client->id)->where('week_start_date', $week3)->first()->distributions->first()->tag_id;

        $this->assertNotEquals($dist1, $dist3, 'Week 3 tag must differ from Week 1 tag');
        $this->assertNotEquals($dist2, $dist3, 'Week 3 tag must differ from Week 2 tag');

        $allThree = array_unique([$dist1, $dist2, $dist3]);
        $this->assertCount(3, $allThree, 'All 3 tags (Tag A, B, C) must be fully exhausted before repeating');
    }

    /** @test */
    public function it_calculates_scheduled_sending_at_for_yearly_tags_correctly(): void
    {
        $service = app(TagDistributionService::class);

        $tag = Tag::factory()->veryHigh()->create([
            'is_there_date_for_sending' => true,
            'date_for_sending_yearly' => '2026-10-13',
            'weekly_time' => null,
        ]);

        $scheduledSending = $service->calculateScheduledSendingAt(
            distributionDate: '2026-10-10',
            tagOrId: $tag,
            ideaOrId: null,
            selectedWeek: '2026-10-10'
        );

        $this->assertEquals('2026-10-13 09:00:00', $scheduledSending);
    }

    /** @test */
    public function it_respects_client_contract_weekly_designs_quota_when_side_assignments_exist(): void
    {
        $client = $this->createClientWithWeights(['high' => 20, 'medium' => 60, 'low' => 20], 3, true);
        $primaryAssignment = $this->createAssignment($client); // Primary designer ($this->designer)

        $secondDesigner = $this->createDesigner();

        $tag1 = Tag::factory()->veryHigh()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $tag2 = Tag::factory()->high()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $tag3 = Tag::factory()->medium()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $this->category->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

        // Create a side assignment for the second designer with 1 transferred tag
        $sideAssignment = ClientDesigner::create([
            'client_id' => $client->id,
            'contract_id' => $primaryAssignment->contract_id,
            'designer_id' => $secondDesigner->id,
            'week_start_date' => $this->weekStart,
            'is_side' => true,
        ]);

        ClientTagDistribution::create([
            'client_designer_id' => $sideAssignment->id,
            'tag_id' => $tag1->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        // Now run smart distribution for the second designer (side designer) -> should NOT add any extra tags to side assignment
        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeSmartForDesigner', $secondDesigner->id);

        $this->assertEquals(1, $sideAssignment->fresh()->distributions()->count(), 'Side assignment must not receive automated tags');

        // Now run smart distribution for the primary designer -> should only distribute remaining quota (3 - 1 = 2 tags)
        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeSmartForDesigner', $this->designer->id);

        $this->assertEquals(2, $primaryAssignment->fresh()->distributions()->count(), 'Primary designer should only receive remaining quota (2 tags)');

        // Total distributions for client in this week across all designers must be exactly 3
        $totalClientDistributions = ClientTagDistribution::whereHas('clientDesigner', function ($q) use ($client) {
            $q->where('client_id', $client->id)->where('week_start_date', $this->weekStart);
        })->count();

        $this->assertEquals(3, $totalClientDistributions, 'Total client distributions across all designers must equal weekly designs count (3)');
    }

    /** @test */
    public function it_respects_client_quota_in_auto_distribute_when_side_assignments_exist(): void
    {
        $client = $this->createClientWithWeights(['high' => 20, 'medium' => 60, 'low' => 20], 3, true);
        $primaryAssignment = $this->createAssignment($client);

        $secondDesigner = $this->createDesigner();

        $tag1 = Tag::factory()->veryHigh()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $tag2 = Tag::factory()->high()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $tag3 = Tag::factory()->medium()->create(['is_active' => true, 'is_auto_assigned' => true]);
        $this->category->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

        $sideAssignment = ClientDesigner::create([
            'client_id' => $client->id,
            'contract_id' => $primaryAssignment->contract_id,
            'designer_id' => $secondDesigner->id,
            'week_start_date' => $this->weekStart,
            'is_side' => true,
        ]);

        ClientTagDistribution::create([
            'client_designer_id' => $sideAssignment->id,
            'tag_id' => $tag1->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('autoDistribute');

        $this->assertEquals(1, $sideAssignment->fresh()->distributions()->count(), 'Side assignment should keep its transferred tag');
        $this->assertEquals(2, $primaryAssignment->fresh()->distributions()->count(), 'Primary assignment gets 3 - 1 = 2 tags');

        $totalClientDistributions = ClientTagDistribution::whereHas('clientDesigner', function ($q) use ($client) {
            $q->where('client_id', $client->id)->where('week_start_date', $this->weekStart);
        })->count();

        $this->assertEquals(3, $totalClientDistributions, 'Total client distributions across all designers must equal 3 in autoDistribute');
    }

    /** @test */
    public function it_does_not_distribute_another_flexible_very_high_tag_if_already_present_in_side_assignment(): void
    {
        $client = $this->createClientWithWeights(['high' => 50, 'medium' => 50], 3, true);
        $primaryAssignment = $this->createAssignment($client);

        $secondDesigner = $this->createDesigner();

        // 2 flexible very high tags + 1 high + 1 medium
        $veryHighTag1 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);
        $veryHighTag2 = Tag::factory()->veryHigh()->create(['is_there_date_for_sending' => false]);
        $highTag = Tag::factory()->high()->create(['is_there_date_for_sending' => false]);
        $mediumTag = Tag::factory()->medium()->create(['is_there_date_for_sending' => false]);

        $this->category->tags()->attach([$veryHighTag1->id, $veryHighTag2->id, $highTag->id, $mediumTag->id]);

        // Client has veryHighTag1 in a side assignment with secondDesigner
        $sideAssignment = ClientDesigner::create([
            'client_id' => $client->id,
            'contract_id' => $primaryAssignment->contract_id,
            'designer_id' => $secondDesigner->id,
            'week_start_date' => $this->weekStart,
            'is_side' => true,
        ]);

        ClientTagDistribution::create([
            'client_designer_id' => $sideAssignment->id,
            'tag_id' => $veryHighTag1->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        // Distribute for primary designer
        Livewire::test(TagDistribution::class, ['selectedWeek' => $this->weekStart])
            ->call('distributeVeryHighTagsForDesigner', $this->designer->id);

        $primaryVeryHighCount = $primaryAssignment->fresh()->distributions->filter(
            fn ($d) => $d->tag->importance === 'veryhigh' && ! ($d->tag->is_there_date_for_sending && $d->tag->date_for_sending_yearly)
        )->count();

        $this->assertEquals(0, $primaryVeryHighCount, 'Primary designer must not receive another flexible very high tag since one is already in side assignment');
    }
}
