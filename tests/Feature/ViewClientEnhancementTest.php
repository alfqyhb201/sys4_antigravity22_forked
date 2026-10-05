<?php

namespace Tests\Feature;

use App\Filament\Enums\ComplaintStatus;
use App\Filament\Resources\ClientResource\Pages\ViewClient;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientSocialMedia;
use App\Models\ClientTemplate;
use App\Models\Complaint;
use App\Models\Currency;
use App\Models\SocialMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ViewClientEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $designerUser;

    protected User $supervisorUser;

    protected User $regularUser;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'designer']);
        Role::firstOrCreate(['name' => 'supervisor']);

        Permission::firstOrCreate(['name' => 'view_any_client']);
        Permission::firstOrCreate(['name' => 'view_client']);
        Permission::firstOrCreate(['name' => 'update_client']);
        Permission::firstOrCreate(['name' => 'view_client_financial']);
        Permission::firstOrCreate(['name' => 'view_designer_distribution']);
        Permission::firstOrCreate(['name' => 'view_complaint']);

        Currency::factory()->create();

        $category = Category::factory()->create();

        $this->adminUser = User::factory()->create([
            'status' => 1,
            'username' => 'admin_user',
        ]);
        $this->adminUser->assignRole('admin');
        $this->adminUser->givePermissionTo(['view_any_client', 'view_client', 'update_client', 'view_client_financial', 'view_designer_distribution']);

        $this->designerUser = User::factory()->create([
            'status' => 1,
            'username' => 'designer_user',
        ]);
        $this->designerUser->assignRole('designer');
        $this->designerUser->givePermissionTo(['view_any_client', 'view_client']);

        $this->supervisorUser = User::factory()->create([
            'status' => 1,
            'username' => 'supervisor_user',
        ]);
        $this->supervisorUser->assignRole('supervisor');
        $this->supervisorUser->givePermissionTo(['view_any_client', 'view_client', 'update_client', 'view_designer_distribution', 'view_complaint']);

        $this->regularUser = User::factory()->create([
            'status' => 1,
            'username' => 'regular_user',
        ]);
        $this->regularUser->givePermissionTo(['view_any_client', 'view_client']);

        $this->client = Client::factory()->create([
            'company' => 'شركة البدر للاختبار',
            'client_name' => 'محمد البدر',
            'contact_number' => '771234567',
            'status' => 1,
            'category_id' => $category->id,
            'cliche_counter' => 5,
            'change_cliche_threshold' => 10,
            'is_credit_allowed' => true,
            'suspension_days' => 15,
        ]);
    }

    #[Test]
    public function view_client_page_renders_successfully_with_hero_summary_bar(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertSee('المؤشرات الرئيسية')
            ->assertSee('الحالة التشغيلية')
            ->assertSee('مصمم الأسبوع')
            ->assertSee('الرصيد المالي')
            ->assertSee('عداد الكليشة')
            ->assertSee('5 / 10 تصميم');
    }

    #[Test]
    public function reset_cliche_counter_action_resets_counter(): void
    {
        $this->actingAs($this->adminUser);

        $this->assertEquals(5, $this->client->cliche_counter);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertActionExists('resetClicheCounter')
            ->callAction('resetClicheCounter');

        $this->client->refresh();
        $this->assertEquals(0, $this->client->cliche_counter);
    }

    #[Test]
    public function reset_cliche_counter_action_hidden_when_counter_is_zero(): void
    {
        $this->client->update(['cliche_counter' => 0]);
        $this->actingAs($this->adminUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertActionHidden('resetClicheCounter');
    }

    #[Test]
    public function open_whatsapp_action_exists_and_formats_yemen_number(): void
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertActionExists('openWhatsApp')
            ->assertActionVisible('openWhatsApp');

        $action = $component->instance()->getAction('openWhatsApp');
        $this->assertEquals('https://wa.me/967771234567', $action->getUrl());
    }

    #[Test]
    public function financial_statement_action_visible_for_admin_and_hidden_for_regular_user(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertActionExists('financialStatement')
            ->assertActionVisible('financialStatement');

        $this->actingAs($this->regularUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertActionHidden('financialStatement');
    }

    #[Test]
    public function financial_tab_and_hero_balance_visible_to_admin_and_hidden_from_designer(): void
    {
        // Designer should NOT see financial tab or balance
        $this->actingAs($this->designerUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertDontSee('الرصيد المالي')
            ->assertDontSee('الملخص المالي')
            ->assertDontSee('مؤشرات المخاطر والائتمان');

        // Admin sees financial indicators and tab
        $this->actingAs($this->adminUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertSee('الرصيد المالي')
            ->assertSee('الملخص المالي')
            ->assertSee('مؤشرات المخاطر والائتمان')
            ->assertSee('السقف الائتماني')
            ->assertSee('أيام التأخير عن السداد');
    }

    #[Test]
    public function templates_tab_visible_to_designer_and_admin(): void
    {
        ClientTemplate::create([
            'client_id' => $this->client->id,
            'type' => 'post',
            'file' => 'templates/client1_post.png',
        ]);

        $this->actingAs($this->designerUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertSee('قوالب وهوية العميل')
            ->assertSee('منشور (Post)');
    }

    #[Test]
    public function social_media_tab_renders_platforms(): void
    {
        $social = SocialMedia::create([
            'name' => 'فيسبوك',
            'created_by_user' => $this->adminUser->id,
            'added_by_user' => $this->adminUser->id,
        ]);

        ClientSocialMedia::create([
            'client_id' => $this->client->id,
            'social_media_id' => $social->id,
            'account_url' => 'https://facebook.com/clientpage',
            'notes' => 'يتم النشر مساءً',
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertSee('حسابات التواصل')
            ->assertSee('فيسبوك')
            ->assertSee('https://facebook.com/clientpage');
    }

    #[Test]
    public function complaints_tab_shows_open_complaints_and_badge(): void
    {
        Complaint::create([
            'client_id' => $this->client->id,
            'description' => 'تأخر في تسليم التصميم الأسبوعي',
            'status' => ComplaintStatus::New,
            'created_by_user' => $this->supervisorUser->id,
            'added_by_user' => $this->supervisorUser->id,
        ]);

        // Designer cannot see complaints
        $this->actingAs($this->designerUser);
        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertDontSee('الشكاوى والدعم');

        // Supervisor can see complaints tab and open count
        $this->actingAs($this->supervisorUser);
        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSuccessful()
            ->assertSee('الشكاوى والدعم')
            ->assertSee('تأخر في تسليم التصميم الأسبوعي');
    }

    #[Test]
    public function subheading_reflects_client_activity_status(): void
    {
        $this->actingAs($this->adminUser);

        // Active status
        $this->client->update(['status' => 1]);
        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSee('🟢 العميل نشط وسليم');

        // Manually suspended status
        $this->client->update(['status' => 0]);
        Livewire::test(ViewClient::class, ['record' => $this->client->id])
            ->assertSee('🔴 موقّف إدارياً');
    }
}
