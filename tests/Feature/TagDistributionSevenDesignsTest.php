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

class TagDistributionSevenDesignsTest extends TestCase
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

        $this->category = Category::factory()->create(['name' => 'الصرافة']);
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
     * Test that a client with 7 weekly designs receives all 7 designs during distributeSmartForDesigner.
     */
    public function test_client_with_seven_designs_receives_all_seven_tags_in_smart_distribution(): void
    {
        $group = TagGroup::create(['name' => 'مجموعة رئيسية', 'added_by_user' => $this->user->id]);

        // Create 1 very high, 4 high, 3 medium, 2 low tags
        $veryHigh = Tag::create([
            'name' => 'تاق عالي جدا',
            'tag_group_id' => $group->id,
            'importance' => 'veryhigh',
            'is_active' => true,
            'is_auto_assigned' => true,
            'added_by_user' => $this->user->id,
        ]);
        $veryHigh->categories()->attach($this->category->id);

        for ($i = 1; $i <= 4; $i++) {
            $t = Tag::create([
                'name' => "تاق عالي {$i}",
                'tag_group_id' => $group->id,
                'importance' => 'high',
                'is_active' => true,
                'is_auto_assigned' => true,
                'added_by_user' => $this->user->id,
            ]);
            $t->categories()->attach($this->category->id);
        }

        for ($i = 1; $i <= 3; $i++) {
            $t = Tag::create([
                'name' => "تاق متوسط {$i}",
                'tag_group_id' => $group->id,
                'importance' => 'medium',
                'is_active' => true,
                'is_auto_assigned' => true,
                'added_by_user' => $this->user->id,
            ]);
            $t->categories()->attach($this->category->id);
        }

        for ($i = 1; $i <= 2; $i++) {
            $t = Tag::create([
                'name' => "تاق منخفض {$i}",
                'tag_group_id' => $group->id,
                'importance' => 'low',
                'is_active' => true,
                'is_auto_assigned' => true,
                'added_by_user' => $this->user->id,
            ]);
            $t->categories()->attach($this->category->id);
        }

        $client = Client::create([
            'company' => 'شركة 7 تصاميم',
            'category_id' => $this->category->id,
            'importance_weights' => ['high' => 50, 'medium' => 30, 'low' => 20],
            'enable_very_high' => true,
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
            'weekly_designs_count' => 7,
            'monthly_designs_count' => 28,
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

        $service = app(TagDistributionService::class);
        $count = $service->distributeSmartForDesigner(collect([$assignment]), $this->designer->id, $this->weekStart);

        $this->assertEquals(7, $count, 'Smart distribution must distribute all 7 designs for the client');
        $this->assertEquals(7, ClientTagDistribution::where('client_designer_id', $assignment->id)->count(), 'Database must contain exactly 7 distributed tags');
    }

    /**
     * Test that autoDistribute also distributes all 7 designs.
     */
    public function test_client_with_seven_designs_receives_all_seven_tags_in_auto_distribute(): void
    {
        $group = TagGroup::create(['name' => 'مجموعة رئيسية 2', 'added_by_user' => $this->user->id]);

        for ($i = 1; $i <= 10; $i++) {
            $t = Tag::create([
                'name' => "تاق متنوع {$i}",
                'tag_group_id' => $group->id,
                'importance' => 'high',
                'is_active' => true,
                'is_auto_assigned' => true,
                'added_by_user' => $this->user->id,
            ]);
            $t->categories()->attach($this->category->id);
        }

        $client = Client::create([
            'company' => 'شركة أوتو 7 تصاميم',
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
            'weekly_designs_count' => 7,
            'monthly_designs_count' => 28,
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

        $service = app(TagDistributionService::class);
        $count = $service->autoDistribute(collect([$assignment]), $this->weekStart);

        $this->assertEquals(7, $count, 'Auto distribute must distribute all 7 designs for the client');
        $this->assertEquals(7, ClientTagDistribution::where('client_designer_id', $assignment->id)->count());
    }
}
