<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Designer;
use App\Models\User;
use App\Services\DesignerDistributionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignerDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected DesignerDistributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DesignerDistributionService;
    }

    #[Test]
    public function it_can_distribute_clients_to_designers()
    {
        // إعداد البيانات
        $category = Category::factory()->create();

        $designer = $this->createDesigner([
            'min_capacity' => 5,
            'max_capacity' => 30,
            'rate' => 8,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
        ], 15); // 15 تصميم

        // التوزيع
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // التحقق
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['distributed']);
        $this->assertEquals(0, $result['failed']);

        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);
    }

    #[Test]
    public function it_prioritizes_higher_rated_designers_for_higher_rated_clients()
    {
        $category = Category::factory()->create();

        // مصمم بتقييم أقل
        $lowerRatedDesigner = $this->createDesigner([
            'rate' => 6,
            'max_capacity' => 50,
        ], [$category->id]);

        // مصمم بتقييم أعلى
        $higherRatedDesigner = $this->createDesigner([
            'rate' => 9,
            'max_capacity' => 50,
        ], [$category->id]);

        // عميل
        $client = $this->createClient([
            'category_id' => $category->id,
            'rating' => 5,
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب أن يتم اختيار المصمم الأعلى تقييماً
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $higherRatedDesigner->id,
        ]);
    }

    #[Test]
    public function it_respects_designer_capacity_based_on_designs_count()
    {
        $category = Category::factory()->create();

        // مصمم بسعة قصوى 20 تصميم
        $designer = $this->createDesigner([
            'max_capacity' => 20,
            'rate' => 8,
        ], [$category->id]);

        // إنشاء 3 عملاء، كل عميل 10 تصاميم (المجموع 30 تصميم)
        $clients = [];
        for ($i = 0; $i < 3; $i++) {
            $clients[] = $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب توزيع 2 فقط (10 + 10 = 20، العميل الثالث يتجاوز السعة القصوى)
        $this->assertEquals(2, $result['distributed']);
        $this->assertEquals(1, $result['failed']);
    }

    #[Test]
    public function it_distributes_pinned_clients_first()
    {
        $category = Category::factory()->create();

        $pinnedDesigner = $this->createDesigner([
            'rate' => 6,
            'max_capacity' => 50,
        ], [$category->id]);

        $otherDesigner = $this->createDesigner([
            'rate' => 9, // تقييم أعلى
            'max_capacity' => 50,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
            'fixed_designer_id' => $pinnedDesigner->id,
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // يجب تعيين المصمم المثبت للعميل
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $pinnedDesigner->id,
            'week_start_date' => $weekStart,
        ]);
    }

    #[Test]
    public function it_distributes_to_available_designers_when_first_reaches_capacity()
    {
        $category = Category::factory()->create();

        // مصمم أول بتقييم أعلى ولكن سعة 20 تصميم
        $designer1 = $this->createDesigner([
            'rate' => 9,
            'max_capacity' => 20,
        ], [$category->id]);

        // مصمم ثانٍ بتقييم أقل وسعة 20 تصميم
        $designer2 = $this->createDesigner([
            'rate' => 7,
            'max_capacity' => 20,
        ], [$category->id]);

        // إنشاء 3 عملاء، كل عميل 10 تصاميم
        for ($i = 0; $i < 3; $i++) {
            $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        // المصمم 1 يستقبل عميلين (20 تصميم) والمصمم 2 يستقبل العميل الثالث (10 تصاميم)
        $designer1Count = ClientDesigner::where('designer_id', $designer1->id)
            ->where('week_start_date', $weekStart)
            ->count();

        $designer2Count = ClientDesigner::where('designer_id', $designer2->id)
            ->where('week_start_date', $weekStart)
            ->count();

        $this->assertEquals(2, $designer1Count);
        $this->assertEquals(1, $designer2Count);
        $this->assertEquals(3, $result['distributed']);
        $this->assertEquals(0, $result['failed']);
    }

    #[Test]
    public function it_handles_clients_without_category()
    {
        $clientCategory = Category::factory()->create();

        $designer = $this->createDesigner([
            'max_capacity' => 50,
            'rate' => 8,
        ], []);

        $client = $this->createClient([
            'category_id' => $clientCategory->id,
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['distributed']);
    }

    #[Test]
    public function it_returns_error_when_no_designers_available()
    {
        $category = Category::factory()->create();
        $client = $this->createClient(['category_id' => $category->id], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('لا يوجد مصممين متاحين', $result['message']);
    }

    #[Test]
    public function it_generates_distribution_report()
    {
        $category = Category::factory()->create();

        $designer = $this->createDesigner([
            'max_capacity' => 50,
            'rate' => 8,
        ], [$category->id]);

        $client = $this->createClient([
            'category_id' => $category->id,
        ], 15);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->service->autoDistribute($weekStart);

        // الحصول على التقرير
        $report = $this->service->getDistributionReport($weekStart);

        $this->assertEquals($weekStart, $report['week_start']);
        $this->assertEquals(1, $report['total_assignments']);
        $this->assertArrayHasKey('designers', $report);
        $this->assertCount(1, $report['designers']);
    }

    #[Test]
    public function it_fails_distribution_when_client_designs_exceed_designer_capacity()
    {
        $category = Category::factory()->create();

        $designer = $this->createDesigner([
            'max_capacity' => 10,
            'rate' => 8,
        ], [$category->id]);

        // عميل يطلب 15 تصميم (أكبر من السعة القصوى للمصمم وهي 10)
        $client = $this->createClient([
            'category_id' => $category->id,
        ], 15);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertEquals(0, $result['distributed']);
        $this->assertEquals(1, $result['failed']);
        $this->assertDatabaseMissing('client_designer', [
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);
    }

    #[Test]
    public function it_excludes_contracts_with_zero_weekly_designs_from_distribution()
    {
        $category = Category::factory()->create();

        $designer = $this->createDesigner([
            'max_capacity' => 50,
            'rate' => 8,
        ], [$category->id]);

        // عميل باشتراك عدد تصاميمه الأسبوعية 0
        $clientWithZeroDesigns = $this->createClient([
            'category_id' => $category->id,
        ], 0);

        // عميل باشتراك عدد تصاميمه الأسبوعية 10
        $clientWithActiveDesigns = $this->createClient([
            'category_id' => $category->id,
        ], 10);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['distributed']);
        $this->assertEquals(0, $result['failed']);

        // التحقق من تعيين العميل ذو التصاميم النشطة
        $this->assertDatabaseHas('client_designer', [
            'client_id' => $clientWithActiveDesigns->id,
            'designer_id' => $designer->id,
            'week_start_date' => $weekStart,
        ]);

        // التحقق من استثناء العميل ذو 0 تصاميم
        $this->assertDatabaseMissing('client_designer', [
            'client_id' => $clientWithZeroDesigns->id,
            'week_start_date' => $weekStart,
        ]);
    }

    #[Test]
    public function it_guarantees_all_designers_reach_min_capacity_first_without_leaving_anyone_behind()
    {
        $category = Category::factory()->create();

        // 3 مصممين بتقييمات مختلفة وحد أدنى 20 وسعة قصوى 50
        $designerHigh = $this->createDesigner([
            'rate' => 9,
            'min_capacity' => 20,
            'max_capacity' => 50,
        ], [$category->id]);

        $designerMid = $this->createDesigner([
            'rate' => 7,
            'min_capacity' => 20,
            'max_capacity' => 50,
        ], [$category->id]);

        $designerLow = $this->createDesigner([
            'rate' => 4,
            'min_capacity' => 20,
            'max_capacity' => 50,
        ], [$category->id]);

        // 6 عملاء، كل عميل 10 تصاميم (المجموع 60 تصميم، يكفي تماماً لتغطية الحد الأدنى للثلاثة: 20 + 20 + 20)
        for ($i = 0; $i < 6; $i++) {
            $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertTrue($result['success']);
        $this->assertEquals(6, $result['distributed']);
        $this->assertEquals(0, $result['failed']);

        // التحقق من أن كل مصمم حصل على عميلين (20 تصميماً = الحد الأدنى) ولم يترك أي مصمم فارغاً
        $highCount = ClientDesigner::where('designer_id', $designerHigh->id)->where('week_start_date', $weekStart)->count();
        $midCount = ClientDesigner::where('designer_id', $designerMid->id)->where('week_start_date', $weekStart)->count();
        $lowCount = ClientDesigner::where('designer_id', $designerLow->id)->where('week_start_date', $weekStart)->count();

        $this->assertEquals(2, $highCount, 'المصمم الأعلى تقييماً يجب أن يصل للحد الأدنى 20');
        $this->assertEquals(2, $midCount, 'المصمم المتوسط تقييماً يجب أن يصل للحد الأدنى 20');
        $this->assertEquals(2, $lowCount, 'المصمم الأقل تقييماً يجب أن يصل للحد الأدنى 20 ولا يترك بصفر');
    }

    #[Test]
    public function it_balances_remainder_contracts_equally_among_designers_after_min_capacity()
    {
        $category = Category::factory()->create();

        // مصممان بحد أدنى 10 وسعة قصوى 40
        $designer1 = $this->createDesigner([
            'rate' => 9,
            'min_capacity' => 10,
            'max_capacity' => 40,
        ], [$category->id]);

        $designer2 = $this->createDesigner([
            'rate' => 7,
            'min_capacity' => 10,
            'max_capacity' => 40,
        ], [$category->id]);

        // 5 عملاء، كل عميل 10 تصاميم (المجموع 50 تصميم)
        // المرحلة 1: 10 للمصمم 1 و 10 للمصمم 2 (20 تصميم)
        // المرحلة 2: المتبقي 30 تصميم يتم توزيعهما بالتوازن (المصمم 1 يحصل على 2 والمصمم 2 يحصل على 1)
        for ($i = 0; $i < 5; $i++) {
            $this->createClient([
                'category_id' => $category->id,
            ], 10);
        }

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $result = $this->service->autoDistribute($weekStart);

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['distributed']);

        $d1Count = ClientDesigner::where('designer_id', $designer1->id)->where('week_start_date', $weekStart)->count();
        $d2Count = ClientDesigner::where('designer_id', $designer2->id)->where('week_start_date', $weekStart)->count();

        $this->assertEquals(3, $d1Count);
        $this->assertEquals(2, $d2Count);
    }

    // Helper Methods

    protected function createDesigner(array $attributes = [], array $categoryIds = []): Designer
    {
        $user = User::factory()->create();

        $designer = Designer::create(array_merge([
            'user_id' => $user->id,
            'min_capacity' => 5,
            'max_capacity' => 50,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ], $attributes));

        if (! empty($categoryIds)) {
            $designer->categories()->attach($categoryIds);
        }

        return $designer->fresh(['categories', 'user']);
    }

    protected function createClient(array $attributes = [], int $weeklyDesignsCount = 10): Client
    {
        $attributes['category_id'] ??= Category::factory()->create()->id;

        $client = Client::factory()->create($attributes);

        $currency = Currency::factory()->create();

        Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => $weeklyDesignsCount,
            'monthly_designs_count' => $weeklyDesignsCount * 4,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
        ]);

        return $client->fresh(['currentContract', 'category']);
    }
}
