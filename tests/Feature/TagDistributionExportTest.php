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
use App\Models\Idea;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagDistributionExportService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TagDistributionExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Designer $designer1;

    protected Designer $designer2;

    protected Client $client1;

    protected Client $client2;

    protected Contract $contract1;

    protected Contract $contract2;

    protected string $weekStart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_tag_distribution']);
        $this->user->givePermissionTo('view_tag_distribution');

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);

        $category = Category::factory()->create(['name' => 'مطاعم']);

        $user1 = User::factory()->create(['name' => 'أحمد المصمم']);
        $this->designer1 = Designer::create([
            'user_id' => $user1->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'current_load' => 0,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
            'work_status' => 'active',
            'specialty' => 'social_media',
        ]);

        $user2 = User::factory()->create(['name' => 'سارة المصممة']);
        $this->designer2 = Designer::create([
            'user_id' => $user2->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'current_load' => 0,
            'rate' => 9,
            'shift_hours' => 8,
            'discipline_score' => 10,
            'amount_of_designs' => 120,
            'work_status' => 'active',
            'specialty' => 'branding',
        ]);

        $this->client1 = Client::create([
            'client_name' => 'محمد الأحمد',
            'company' => 'شركة النور',
            'phone' => '777111222',
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        $this->client2 = Client::create([
            'client_name' => 'خالد علي',
            'company' => 'مؤسسة الأمل',
            'phone' => '777333444',
            'category_id' => $category->id,
            'added_by_user' => $this->user->id,
        ]);

        $this->weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');

        $this->contract1 = Contract::create([
            'client_id' => $this->client1->id,
            'currency_id' => $currency->id,
            'weekly_designs_count' => 6,
            'monthly_designs_count' => 24,
            'total_amount' => 500,
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'start_date' => Carbon::now()->subMonths(1),
            'end_date' => Carbon::now()->addMonths(1),
        ]);

        $this->contract2 = Contract::create([
            'client_id' => $this->client2->id,
            'currency_id' => $currency->id,
            'weekly_designs_count' => 4,
            'monthly_designs_count' => 16,
            'total_amount' => 400,
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'start_date' => Carbon::now()->subMonths(1),
            'end_date' => Carbon::now()->addMonths(1),
        ]);
    }

    public function test_export_service_creates_multi_sheet_excel_for_each_designer(): void
    {
        $assignment1 = ClientDesigner::create([
            'client_id' => $this->client1->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $this->contract1->id,
            'week_start_date' => $this->weekStart,
            'is_side' => false,
        ]);

        $assignment2 = ClientDesigner::create([
            'client_id' => $this->client2->id,
            'designer_id' => $this->designer2->id,
            'contract_id' => $this->contract2->id,
            'week_start_date' => $this->weekStart,
            'is_side' => false,
        ]);

        $tag1 = Tag::factory()->create(['name' => 'بوست تفاعلي', 'is_active' => true]);
        $tag2 = Tag::factory()->create(['name' => 'ريلز إعلاني', 'is_active' => true]);
        $idea1 = Idea::create(['name' => 'خصم نهاية الأسبوع']);

        ClientTagDistribution::create([
            'client_designer_id' => $assignment1->id,
            'tag_id' => $tag1->id,
            'distribution_date' => $this->weekStart,
            'idea_id' => $idea1->id,
            'status' => 'pending',
        ]);

        ClientTagDistribution::create([
            'client_designer_id' => $assignment2->id,
            'tag_id' => $tag2->id,
            'distribution_date' => Carbon::parse($this->weekStart)->addDays(1)->format('Y-m-d'),
            'custom_idea' => 'عرض خاص ومميز',
            'status' => 'pending',
        ]);

        $assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])->where('week_start_date', $this->weekStart)->get();

        $service = new TagDistributionExportService;
        $filePath = $service->export($assignments, $this->weekStart);

        $this->assertFileExists($filePath);
        $this->assertGreaterThan(0, filesize($filePath));

        // التحقق من بنية الملف ومحتويات الشيتات باستخدام OpenSpout Reader
        $reader = new Reader;
        $reader->open($filePath);

        $sheetNames = [];
        $sheetRows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getName();
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheetRows[$sheet->getName()] = $rows;
        }

        $reader->close();
        @unlink($filePath);

        // يجب أن يحتوي على شيت لكل مصمم
        $this->assertContains('أحمد المصمم', $sheetNames);
        $this->assertContains('سارة المصممة', $sheetNames);

        // التحقق من شيت المصمم أحمد
        $ahmedRows = $sheetRows['أحمد المصمم'];
        $this->assertNotEmpty($ahmedRows);

        // سطر الترويسة (السطر الأول)
        $headerRow = $ahmedRows[0];
        $this->assertEquals('اسم العميل', $headerRow[0]);
        $this->assertEquals('عدد التصاميم', $headerRow[1]);
        $this->assertStringContainsString('السبت', $headerRow[2]);

        // سطر بيانات العميل (السطر الثاني)
        $dataRow = $ahmedRows[1];
        $this->assertEquals('شركة النور', $dataRow[0]);
        $this->assertEquals(6, $dataRow[1]);
        $this->assertStringContainsString('بوست تفاعلي', $dataRow[2]);
        $this->assertStringContainsString('خصم نهاية الأسبوع', $dataRow[2]);

        // سطر الإجماليات (السطر الثالث)
        $totalRow = $ahmedRows[2];
        $this->assertStringContainsString('الإجمالي', $totalRow[0]);
        $this->assertEquals(6, $totalRow[1]);

        // التحقق من شيت المصممة سارة
        $saraRows = $sheetRows['سارة المصممة'];
        $this->assertNotEmpty($saraRows);
        $saraDataRow = $saraRows[1];
        $this->assertEquals('مؤسسة الأمل', $saraDataRow[0]);
        $this->assertEquals(4, $saraDataRow[1]);
        $this->assertStringContainsString('ريلز إعلاني', $saraDataRow[3]); // الأحد
        $this->assertStringContainsString('عرض خاص ومميز', $saraDataRow[3]);
    }

    public function test_export_service_handles_empty_assignments(): void
    {
        $service = new TagDistributionExportService;
        $filePath = $service->export(collect(), $this->weekStart);

        $this->assertFileExists($filePath);
        $this->assertGreaterThan(0, filesize($filePath));

        $reader = new Reader;
        $reader->open($filePath);

        $sheetNames = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getName();
        }
        $reader->close();
        @unlink($filePath);

        $this->assertContains('توزيع التاقات', $sheetNames);
    }

    public function test_export_service_handles_multiple_tags_on_same_day(): void
    {
        $assignment = ClientDesigner::create([
            'client_id' => $this->client1->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $this->contract1->id,
            'week_start_date' => $this->weekStart,
            'is_side' => false,
        ]);

        $tag1 = Tag::factory()->create(['name' => 'بوست ثابت', 'is_active' => true]);
        $tag2 = Tag::factory()->create(['name' => 'فيديو ريلز', 'is_active' => true]);

        ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag1->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        ClientTagDistribution::create([
            'client_designer_id' => $assignment->id,
            'tag_id' => $tag2->id,
            'distribution_date' => $this->weekStart,
            'status' => 'pending',
        ]);

        $assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])->where('week_start_date', $this->weekStart)->get();

        $service = new TagDistributionExportService;
        $filePath = $service->export($assignments, $this->weekStart);

        $reader = new Reader;
        $reader->open($filePath);

        $firstSheet = null;
        foreach ($reader->getSheetIterator() as $sheet) {
            $firstSheet = $sheet;
            break;
        }

        $rows = [];
        foreach ($firstSheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        $reader->close();
        @unlink($filePath);

        $clientRow = $rows[1];
        $this->assertStringContainsString('بوست ثابت', $clientRow[2]);
        $this->assertStringContainsString('فيديو ريلز', $clientRow[2]);
    }

    public function test_export_service_sanitizes_invalid_sheet_name_characters(): void
    {
        $userSpecial = User::factory()->create(['name' => 'مصمم/خاص*مع?رموز:ممنوعة[]']);
        $designerSpecial = Designer::create([
            'user_id' => $userSpecial->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'current_load' => 0,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
            'work_status' => 'active',
            'specialty' => 'social_media',
        ]);

        $assignment = ClientDesigner::create([
            'client_id' => $this->client1->id,
            'designer_id' => $designerSpecial->id,
            'contract_id' => $this->contract1->id,
            'week_start_date' => $this->weekStart,
            'is_side' => false,
        ]);

        $assignments = ClientDesigner::with([
            'designer.user',
            'client.category',
            'contract',
            'distributions.idea',
            'distributions.tag',
        ])->where('id', $assignment->id)->get();

        $service = new TagDistributionExportService;
        $filePath = $service->export($assignments, $this->weekStart);

        $reader = new Reader;
        $reader->open($filePath);

        $sheetNames = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getName();
        }
        $reader->close();
        @unlink($filePath);

        $this->assertNotEmpty($sheetNames);
        // لا يجب أن يحتوي اسم الشيت على أي من الحروف الممنوعة: \ / ? * : [ ]
        foreach ($sheetNames as $name) {
            $this->assertDoesNotMatchRegularExpression('/[\\\\\\/\?\*\:\[\]]/', $name);
            $this->assertLessThanOrEqual(31, mb_strlen($name));
        }
    }

    public function test_unauthorized_user_cannot_access_page_or_export(): void
    {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $this->assertFalse(TagDistribution::canAccess());

        $response = $this->get(TagDistribution::getUrl());
        $response->assertForbidden();
    }

    public function test_export_excel_page_action_returns_download_response(): void
    {
        ClientDesigner::create([
            'client_id' => $this->client1->id,
            'designer_id' => $this->designer1->id,
            'contract_id' => $this->contract1->id,
            'week_start_date' => $this->weekStart,
            'is_side' => false,
        ]);

        Livewire::test(TagDistribution::class)
            ->call('exportExcel')
            ->assertFileDownloaded();
    }
}
