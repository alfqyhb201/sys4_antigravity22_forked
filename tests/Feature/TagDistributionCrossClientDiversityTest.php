<?php

namespace Tests\Feature;

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
use App\Services\TagDistributionService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagDistributionCrossClientDiversityTest extends TestCase
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
        $this->actingAs($this->user);

        Filament::setCurrentPanel(
            Filament::getPanel('app'),
        );

        $this->category = Category::factory()->create(['name' => 'الصرافة والتحويلات']);
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);

        $this->designer = Designer::create([
            'user_id' => $this->user->id,
            'max_clients' => 20,
            'is_active' => true,
        ]);

        $this->weekStart = Carbon::now()->startOfWeek(Carbon::SATURDAY)->format('Y-m-d');
    }

    /**
     * Test that multiple clients in the same category with identical tags available
     * do not all receive the exact same tags in the exact same order.
     */
    public function test_cross_client_tag_diversity_in_same_category(): void
    {
        // Create 4 different tag groups
        $groups = [];
        for ($g = 1; $g <= 4; $g++) {
            $groups[$g] = TagGroup::create([
                'name' => "مجموعة {$g}",
                'added_by_user' => $this->user->id,
            ]);
        }

        // Create 16 High importance tags (4 per group)
        $allTags = [];
        for ($g = 1; $g <= 4; $g++) {
            for ($t = 1; $t <= 4; $t++) {
                $tag = Tag::create([
                    'name' => "تاق مجموعة {$g} رقم {$t}",
                    'tag_group_id' => $groups[$g]->id,
                    'importance' => 'high',
                    'is_active' => true,
                    'is_auto_assigned' => true,
                    'added_by_user' => $this->user->id,
                ]);
                $tag->categories()->attach($this->category->id);
                $allTags[] = $tag;
            }
        }

        // Create 5 clients in the same category, each needing 3 designs
        $assignments = collect();
        $clients = [];
        for ($c = 1; $c <= 5; $c++) {
            $client = Client::create([
                'company' => "شركة صرافة {$c}",
                'category_id' => $this->category->id,
                'importance_weights' => ['high' => 100, 'medium' => 0, 'low' => 0],
                'enable_very_high' => false,
                'created_by_user' => $this->user->id,
                'updated_by_user' => $this->user->id,
            ]);

            $contract = Contract::create([
                'client_id' => $client->id,
                'currency_id' => $this->currency->id,
                'payment_type' => 'monthly',
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::parse($this->weekStart)->subMonths(1),
                'end_date' => Carbon::parse($this->weekStart)->addMonths(5),
                'weekly_designs_count' => 3,
                'monthly_designs_count' => 12,
                'total_amount' => 1000,
                'status' => 'active',
                'created_by_user' => $this->user->id,
                'updated_by_user' => $this->user->id,
            ]);

            $assignment = ClientDesigner::create([
                'client_id' => $client->id,
                'contract_id' => $contract->id,
                'designer_id' => $this->designer->id,
                'week_start_date' => $this->weekStart,
                'is_side' => false,
            ]);

            $clients[$c] = $client;
            $assignments->push($assignment);
        }

        // Run autoDistribute
        $service = app(TagDistributionService::class);
        $count = $service->autoDistribute($assignments, $this->weekStart);

        $this->assertEquals(15, $count, 'Should distribute 3 tags for each of the 5 clients (total 15)');

        // Collect the assigned tag IDs for each client
        $clientTagSets = [];
        $uniqueAssignedTagIds = [];

        foreach ($assignments as $assignment) {
            $tags = ClientTagDistribution::where('client_designer_id', $assignment->id)->pluck('tag_id')->toArray();
            $clientTagSets[] = $tags;
            foreach ($tags as $tid) {
                $uniqueAssignedTagIds[$tid] = true;
            }
        }

        // Without diversity rotation, all 5 clients would get the exact same first 3 tags: [1, 2, 3] (total unique = 3)
        // With our diversity engine, tags are spread across multiple groups and tags (total unique >= 8)
        $uniqueCount = count($uniqueAssignedTagIds);
        $this->assertGreaterThan(
            5,
            $uniqueCount,
            "Unique tags distributed across 5 clients should be more than 5, got {$uniqueCount}"
        );

        // Verify that not all clients have identical tag sets
        $firstSet = $clientTagSets[0];
        $allIdentical = true;
        for ($i = 1; $i < count($clientTagSets); $i++) {
            if ($clientTagSets[$i] !== $firstSet) {
                $allIdentical = false;
                break;
            }
        }
        $this->assertFalse($allIdentical, 'Clients in the same category must not all receive identical tags');
    }

    /**
     * Test that distributeSmartForDesigner also diversifies tags across multiple clients.
     */
    public function test_smart_distribution_cross_client_diversity(): void
    {
        // Create 3 tag groups
        $groups = [];
        for ($g = 1; $g <= 3; $g++) {
            $groups[$g] = TagGroup::create([
                'name' => "مجموعة ذكية {$g}",
                'added_by_user' => $this->user->id,
            ]);
        }

        // Create 12 tags (High, Medium, Low) across groups
        for ($g = 1; $g <= 3; $g++) {
            foreach (['high', 'medium', 'low'] as $importance) {
                for ($k = 1; $k <= 2; $k++) {
                    $tag = Tag::create([
                        'name' => "تاق {$importance} مج{$g} رقم{$k}",
                        'tag_group_id' => $groups[$g]->id,
                        'importance' => $importance,
                        'is_active' => true,
                        'is_auto_assigned' => true,
                        'added_by_user' => $this->user->id,
                    ]);
                    $tag->categories()->attach($this->category->id);
                }
            }
        }

        // Create 4 clients in the same category
        $assignments = collect();
        for ($c = 1; $c <= 4; $c++) {
            $client = Client::create([
                'company' => "شركة تجريبية {$c}",
                'category_id' => $this->category->id,
                'importance_weights' => ['high' => 50, 'medium' => 25, 'low' => 25],
                'enable_very_high' => false,
                'created_by_user' => $this->user->id,
                'updated_by_user' => $this->user->id,
            ]);

            $contract = Contract::create([
                'client_id' => $client->id,
                'currency_id' => $this->currency->id,
                'payment_type' => 'monthly',
                'billing_cycle' => 'monthly',
                'start_date' => Carbon::parse($this->weekStart)->subMonths(1),
                'end_date' => Carbon::parse($this->weekStart)->addMonths(5),
                'weekly_designs_count' => 4,
                'monthly_designs_count' => 16,
                'total_amount' => 1000,
                'status' => 'active',
                'created_by_user' => $this->user->id,
                'updated_by_user' => $this->user->id,
            ]);

            $assignment = ClientDesigner::create([
                'client_id' => $client->id,
                'contract_id' => $contract->id,
                'designer_id' => $this->designer->id,
                'week_start_date' => $this->weekStart,
                'is_side' => false,
            ]);

            $assignments->push($assignment);
        }

        $service = app(TagDistributionService::class);
        $count = $service->distributeSmartForDesigner($assignments, $this->designer->id, $this->weekStart);

        $this->assertEquals(16, $count, 'Should distribute 4 tags for each of the 4 clients (total 16)');

        // Verify diversity across clients
        $clientTagSets = [];
        $uniqueAssignedTagIds = [];

        foreach ($assignments as $assignment) {
            $tags = ClientTagDistribution::where('client_designer_id', $assignment->id)->pluck('tag_id')->toArray();
            $clientTagSets[] = $tags;
            foreach ($tags as $tid) {
                $uniqueAssignedTagIds[$tid] = true;
            }
        }

        $this->assertGreaterThan(6, count($uniqueAssignedTagIds), 'Smart distribution should diversify tags across groups');
    }
}
