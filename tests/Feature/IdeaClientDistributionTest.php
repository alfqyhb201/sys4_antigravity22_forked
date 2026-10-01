<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\Idea;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagDistributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaClientDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Category $category;

    protected Designer $designer;

    protected Currency $currency;

    protected TagDistributionService $service;

    protected string $weekStart;

    protected \App\Models\TagGroup $tagGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->category = Category::factory()->create();
        $this->tagGroup = \App\Models\TagGroup::create([
            'name' => 'Group A',
            'added_by_user' => $this->user->id,
        ]);
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);

        $designerUser = User::factory()->create();
        $this->designer = Designer::create([
            'user_id' => $designerUser->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'current_load' => 0,
            'rate' => 8,
            'added_by_user' => $this->user->id,
        ]);

        $this->weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->service = app(TagDistributionService::class);
    }

    protected function createClientWithAssignment(string $name): array
    {
        $client = Client::factory()->create([
            'company' => $name,
            'category_id' => $this->category->id,
            'added_by_user' => $this->user->id,
        ]);

        $contract = Contract::create([
            'client_id' => $client->id,
            'currency_id' => $this->currency->id,
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 100,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addYear(),
            'added_by_user' => $this->user->id,
        ]);

        $assignment = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $this->designer->id,
            'week_start_date' => $this->weekStart,
            'contract_id' => $contract->id,
            'is_side' => false,
        ]);

        return [$client, $assignment];
    }

    public function test_idea_assigned_to_specific_client_is_only_given_to_that_client(): void
    {
        [$client1, $assignment1] = $this->createClientWithAssignment('Client A');
        [$client2, $assignment2] = $this->createClientWithAssignment('Client B');

        $tag = Tag::create([
            'name' => 'Tag Test',
            'tag_group_id' => $this->tagGroup->id,
            'importance' => 'medium',
            'is_active' => true,
            'is_auto_assigned' => true,
            'added_by_user' => $this->user->id,
        ]);

        // Create an idea assigned only to Client A
        $ideaForClientA = Idea::create([
            'name' => 'Specific Idea for Client A',
            'content' => 'Idea text',
            'is_visible_in_generator' => true,
            'added_by_user' => $this->user->id,
            'created_by_user' => $this->user->id,
        ]);
        $ideaForClientA->tags()->attach($tag->id);
        $ideaForClientA->clients()->attach($client1->id);

        // Create distributions for both clients with the same tag
        $dist1 = ClientTagDistribution::create([
            'client_designer_id' => $assignment1->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
            'created_by_user' => $this->user->id,
        ]);

        $dist2 = ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
            'created_by_user' => $this->user->id,
        ]);

        $assignments = collect([$assignment1, $assignment2]);
        $assignedCount = $this->service->assignIdeasToAssignments($assignments, $this->weekStart);

        $this->assertEquals(1, $assignedCount);
        $this->assertEquals($ideaForClientA->id, $dist1->fresh()->idea_id);
        $this->assertNull($dist2->fresh()->idea_id);
    }

    public function test_general_idea_is_available_for_any_client_matching_tag(): void
    {
        [$client1, $assignment1] = $this->createClientWithAssignment('Client A');
        [$client2, $assignment2] = $this->createClientWithAssignment('Client B');

        $tag = Tag::create([
            'name' => 'Tag Test 2',
            'tag_group_id' => $this->tagGroup->id,
            'importance' => 'medium',
            'is_active' => true,
            'is_auto_assigned' => true,
            'added_by_user' => $this->user->id,
        ]);

        // General idea with no specific clients
        $generalIdea = Idea::create([
            'name' => 'General Idea',
            'content' => 'General idea content',
            'is_visible_in_generator' => true,
            'added_by_user' => $this->user->id,
            'created_by_user' => $this->user->id,
        ]);
        $generalIdea->tags()->attach($tag->id);

        $dist1 = ClientTagDistribution::create([
            'client_designer_id' => $assignment1->id,
            'tag_id' => $tag->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
            'created_by_user' => $this->user->id,
        ]);

        $assignments = collect([$assignment1]);
        $assignedCount = $this->service->assignIdeasToAssignments($assignments, $this->weekStart);

        $this->assertEquals(1, $assignedCount);
        $this->assertEquals($generalIdea->id, $dist1->fresh()->idea_id);
    }

    public function test_load_available_ideas_for_tag_filters_by_specific_client(): void
    {
        [$client1, $assignment1] = $this->createClientWithAssignment('Client A');
        [$client2, $assignment2] = $this->createClientWithAssignment('Client B');

        $tag = Tag::create([
            'name' => 'Tag Test 3',
            'tag_group_id' => $this->tagGroup->id,
            'importance' => 'medium',
            'is_active' => true,
            'is_auto_assigned' => true,
            'added_by_user' => $this->user->id,
        ]);

        $ideaForClientA = Idea::create([
            'name' => 'Only for Client A',
            'content' => 'Content A',
            'is_visible_in_generator' => true,
            'added_by_user' => $this->user->id,
            'created_by_user' => $this->user->id,
        ]);
        $ideaForClientA->tags()->attach($tag->id);
        $ideaForClientA->clients()->attach($client1->id);

        $generalIdea = Idea::create([
            'name' => 'General Idea',
            'content' => 'General content',
            'is_visible_in_generator' => true,
            'added_by_user' => $this->user->id,
            'created_by_user' => $this->user->id,
        ]);
        $generalIdea->tags()->attach($tag->id);

        // Client A should see both ideas
        $ideasForA = $this->service->loadAvailableIdeasForTag($tag->id, $client1->id, $this->weekStart, null);
        $this->assertArrayHasKey($ideaForClientA->id, $ideasForA);
        $this->assertArrayHasKey($generalIdea->id, $ideasForA);

        // Client B should only see the general idea
        $ideasForB = $this->service->loadAvailableIdeasForTag($tag->id, $client2->id, $this->weekStart, null);
        $this->assertArrayNotHasKey($ideaForClientA->id, $ideasForB);
        $this->assertArrayHasKey($generalIdea->id, $ideasForB);
    }
}
