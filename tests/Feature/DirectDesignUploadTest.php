<?php

namespace Tests\Feature;

use App\Filament\Pages\SupervisorDashboard;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DirectDesignUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Designer $designer;

    private Client $client;

    private ClientDesigner $assignment;

    private Tag $tag;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_supervisor_dashboard']);
        Permission::firstOrCreate(['name' => 'view_tag_distribution']);
        $this->user->givePermissionTo(['view_supervisor_dashboard', 'view_tag_distribution']);

        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->designer = Designer::create([
            'user_id' => $this->user->id,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'rate' => 8,
            'shift_hours' => 8,
            'discipline_score' => 9,
            'amount_of_designs' => 100,
        ]);

        $category = Category::factory()->create();
        $this->currency = Currency::create([
            'currency' => 'YER',
            'currency_name' => 'ريال يمني',
            'value' => 1,
            'added_by_user' => $this->user->id,
        ]);

        $this->client = Client::factory()->create([
            'category_id' => $category->id,
            'importance_weights' => ['high' => 100],
            'enable_very_high' => true,
        ]);

        Contract::create([
            'client_id' => $this->client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'weekly_designs_count' => 3,
            'monthly_designs_count' => 12,
            'total_amount' => 1000,
            'currency_id' => $this->currency->id,
        ]);

        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->assignment = ClientDesigner::create([
            'designer_id' => $this->designer->id,
            'client_id' => $this->client->id,
            'week_start_date' => $weekStart,
        ]);

        $this->tag = Tag::factory()->create([
            'name' => 'جمعة مباركة',
            'weekly_time' => '10:00:00',
        ]);
    }

    public function test_supervisor_can_upload_design_directly_and_mark_as_sending(): void
    {
        Storage::fake('public');

        $distribution = ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $fakeImage = UploadedFile::fake()->image('friday_design.jpg', 800, 800);

        Livewire::test(SupervisorDashboard::class)
            ->call('prepareDirectUpload', $distribution->id)
            ->assertSet('uploadTaskId', $distribution->id)
            ->assertSet('uploadTargetStatus', 'sending')
            ->set('uploadFile', $fakeImage)
            ->set('uploadNotes', 'تم التصميم والرفع المباشر بواسطة المشرف')
            ->call('saveDirectUpload', $distribution->id)
            ->assertHasNoErrors();

        $distribution->refresh();

        $this->assertSame('sending', $distribution->status);
        $this->assertSame($this->user->id, $distribution->reviewer_id);
        $this->assertSame('تم التصميم والرفع المباشر بواسطة المشرف', $distribution->designer_notes);
        $this->assertNotNull($distribution->attachment_path);
        $this->assertNotNull($distribution->scheduled_sending_at);
        Storage::disk('public')->assertExists($distribution->attachment_path);
    }

    public function test_supervisor_can_upload_design_and_send_to_review(): void
    {
        Storage::fake('public');

        $distribution = ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $fakeImage = UploadedFile::fake()->image('review_design.png', 600, 600);

        Livewire::test(SupervisorDashboard::class)
            ->call('prepareDirectUpload', $distribution->id)
            ->set('uploadTargetStatus', 'reviewing')
            ->set('uploadFile', $fakeImage)
            ->call('saveDirectUpload', $distribution->id)
            ->assertHasNoErrors();

        $distribution->refresh();

        $this->assertSame('reviewing', $distribution->status);
        $this->assertNotNull($distribution->attachment_path);
        Storage::disk('public')->assertExists($distribution->attachment_path);
    }

    public function test_admin_can_upload_design_from_tag_distribution_page(): void
    {
        Storage::fake('public');

        $distribution = ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        $fakeImage = UploadedFile::fake()->image('tag_dist_upload.webp', 1000, 1000);

        Livewire::test(TagDistribution::class)
            ->call('openUploadDesignModal', $distribution->id)
            ->assertSet('uploadDistributionId', $distribution->id)
            ->assertSet('uploadTargetStatus', 'sending')
            ->set('uploadFile', $fakeImage)
            ->set('uploadNotes', 'رفع معتمد من جدول التوزيع')
            ->call('saveUploadedDesign')
            ->assertHasNoErrors();

        $distribution->refresh();

        $this->assertSame('sending', $distribution->status);
        $this->assertSame($this->user->id, $distribution->reviewer_id);
        $this->assertSame('رفع معتمد من جدول التوزيع', $distribution->designer_notes);
        $this->assertNotNull($distribution->attachment_path);
        Storage::disk('public')->assertExists($distribution->attachment_path);
    }

    public function test_direct_upload_validates_image_file(): void
    {
        $distribution = ClientTagDistribution::create([
            'client_designer_id' => $this->assignment->id,
            'tag_id' => $this->tag->id,
            'distribution_date' => Carbon::now()->format('Y-m-d'),
            'status' => 'pending',
        ]);

        Livewire::test(SupervisorDashboard::class)
            ->call('prepareDirectUpload', $distribution->id)
            ->call('saveDirectUpload', $distribution->id)
            ->assertHasErrors(['uploadFile' => 'required']);
    }
}
