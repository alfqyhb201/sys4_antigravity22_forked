<?php

namespace Tests\Feature;

use App\Filament\Pages\SocialMediaPublishing;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Contract;
use App\Models\Designer;
use App\Models\SocialMedia;
use App\Models\SocialMediaPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialMediaPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_be_associated_with_social_media_platforms(): void
    {
        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $socialMedia = SocialMedia::create(['name' => 'انستغرام']);

        $client->socialMedia()->attach($socialMedia->id, [
            'account_url' => 'https://instagram.com/test_client',
            'notes' => 'حساب انستغرام رسمي',
        ]);

        $this->assertDatabaseHas('client_social_media', [
            'client_id' => $client->id,
            'social_media_id' => $socialMedia->id,
            'account_url' => 'https://instagram.com/test_client',
        ]);

        $this->assertCount(1, $client->fresh()->socialMedia);
    }

    public function test_scope_for_social_media_publishing_filters_eligible_designs(): void
    {
        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();
        $socialMedia = SocialMedia::create(['name' => 'انستغرام']);
        $designer = Designer::factory()->create();

        // 1. Client with active contract (marketing_amount = 0) AND has social media attached -> ELIGIBLE
        $clientEligible = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $clientEligible->id,
            'status' => 'active',
            'marketing_amount' => 0,
        ]);
        $clientEligible->socialMedia()->attach($socialMedia->id);

        $clientDesigner1 = ClientDesigner::create([
            'client_id' => $clientEligible->id,
            'designer_id' => $designer->id,
        ]);
        $eligibleDistribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner1->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinute(),
        ]);

        // 2. Client with active contract but NO social media attached -> INELIGIBLE
        $clientNoSocial = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $clientNoSocial->id,
            'status' => 'active',
            'marketing_amount' => 500,
        ]);
        $clientDesigner2 = ClientDesigner::create([
            'client_id' => $clientNoSocial->id,
            'designer_id' => $designer->id,
        ]);
        $ineligibleNoSocial = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner2->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinute(),
        ]);

        // 3. Client with inactive contract AND has social media -> INELIGIBLE
        $clientInactiveContract = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $clientInactiveContract->id,
            'status' => 'expired',
            'marketing_amount' => 500,
        ]);
        $clientInactiveContract->socialMedia()->attach($socialMedia->id);
        $clientDesigner3 = ClientDesigner::create([
            'client_id' => $clientInactiveContract->id,
            'designer_id' => $designer->id,
        ]);
        $ineligibleInactiveContract = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner3->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinute(),
        ]);

        $results = ClientTagDistribution::forSocialMediaPublishing()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($eligibleDistribution));
        $this->assertFalse($results->contains($ineligibleNoSocial));
        $this->assertFalse($results->contains($ineligibleInactiveContract));
    }

    public function test_social_media_posting_creates_post_record_and_keeps_distribution_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $distribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ]);

        $platform = SocialMedia::create(['name' => 'فيسبوك']);

        $post = SocialMediaPost::create([
            'client_tag_distribution_id' => $distribution->id,
            'social_media_id' => $platform->id,
            'user_id' => $user->id,
            'published_at' => now(),
            'notes' => 'تم النشر بنجاح',
        ]);

        $this->assertDatabaseHas('social_media_posts', [
            'client_tag_distribution_id' => $distribution->id,
            'social_media_id' => $platform->id,
            'user_id' => $user->id,
        ]);

        // Status should remain 'sending' (independent publishing)
        $this->assertEquals('sending', $distribution->fresh()->status->value ?? $distribution->fresh()->status);
    }

    public function test_social_media_publishing_page_renders_successfully(): void
    {
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($user);

        Livewire::test(SocialMediaPublishing::class)
            ->assertStatus(200);
    }

    public function test_completed_whatsapp_distributions_remain_visible_in_social_media_publishing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'marketing_amount' => 0,
        ]);
        $social = SocialMedia::create(['name' => 'انستغرام']);
        $client->socialMedia()->attach($social->id);

        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        // When marked as 'completed' via "تم الإرسال ليد العميل"
        $distributionCompletedWhatsApp = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'completed',
            'scheduled_sending_at' => now()->subMinutes(10),
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(SocialMediaPublishing::class)
            ->assertCanSeeTableRecords([$distributionCompletedWhatsApp]);
    }

    public function test_social_media_user_can_access_client_social_media_resource_page(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'social_media']);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_social_media']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->assertTrue(\App\Filament\Resources\ClientSocialMediaResource::canViewAny());

        Livewire::test(\App\Filament\Resources\ClientSocialMediaResource\Pages\ListClientSocialMedia::class)
            ->assertSuccessful();
    }

    public function test_client_social_media_has_social_media_filter(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $clientWithPlatform = Client::factory()->create([
            'company' => 'شركة تحتوي منصات',
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $social = SocialMedia::create(['name' => 'تويتر']);
        $clientWithPlatform->socialMedia()->attach($social->id);

        $clientWithoutPlatform = Client::factory()->create([
            'company' => 'شركة بدون منصات',
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);

        Livewire::test(\App\Filament\Resources\ClientSocialMediaResource\Pages\ListClientSocialMedia::class)
            ->assertCanSeeTableRecords([$clientWithPlatform])
            ->assertCanNotSeeTableRecords([$clientWithoutPlatform])
            ->set('activeTab', 'without_platforms')
            ->assertCanSeeTableRecords([$clientWithoutPlatform])
            ->assertCanNotSeeTableRecords([$clientWithPlatform]);
    }

    public function test_client_social_media_view_infolist_renders_successfully(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'company' => 'شركة تجريبية',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'notes' => 'ملاحظات وتوجيهات النشر التجريبية',
        ]);
        $social = SocialMedia::create(['name' => 'انستغرام']);
        $client->socialMedia()->attach($social->id, [
            'account_url' => 'https://instagram.com/demo',
            'notes' => 'حساب رسمي',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Resources\ClientSocialMediaResource\Pages\ListClientSocialMedia::class)
            ->mountTableAction('view', $client)
            ->assertSuccessful()
            ->assertSee('شركة تجريبية')
            ->assertSee('انستغرام');
    }

    public function test_can_create_and_edit_client_social_media_with_account_url(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'company' => 'شركة الربط',
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        $facebook = SocialMedia::create(['name' => 'فيسبوك']);
        $instagram = SocialMedia::create(['name' => 'انستغرام']);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Test create
        Livewire::test(\App\Filament\Resources\ClientSocialMediaResource\Pages\CreateClientSocialMedia::class)
            ->fillForm([
                'id' => $client->id,
                'notes' => 'توجيهات عامة للنشر',
                'clientSocialMedia' => [
                    [
                        'social_media_id' => $facebook->id,
                        'account_url' => 'https://facebook.com/myclient',
                        'notes' => 'حساب فيسبوك رسمي',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_social_media', [
            'client_id' => $client->id,
            'social_media_id' => $facebook->id,
            'account_url' => 'https://facebook.com/myclient',
            'notes' => 'حساب فيسبوك رسمي',
        ]);

        // Test edit
        Livewire::test(\App\Filament\Resources\ClientSocialMediaResource\Pages\EditClientSocialMedia::class, ['record' => $client->getKey()])
            ->assertSuccessful()
            ->fillForm([
                'clientSocialMedia' => [
                    [
                        'social_media_id' => $instagram->id,
                        'account_url' => 'https://instagram.com/myclient',
                        'notes' => 'حساب انستغرام جديد',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_social_media', [
            'client_id' => $client->id,
            'social_media_id' => $instagram->id,
            'account_url' => 'https://instagram.com/myclient',
            'notes' => 'حساب انستغرام جديد',
        ]);
    }

    public function test_social_media_publishing_page_tabs_and_publish_modal_render_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'company' => 'شركة النشر الذكي',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'notes' => "### توجيهات هامة\nيرجى استخدام الهاشتاق #الرياض دائماً.",
        ]);
        Contract::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'marketing_amount' => 500,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $dueDistribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subHour(),
        ]);

        $futureDistribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->addDays(2),
        ]);

        $twitter = SocialMedia::create(['name' => 'تويتر']);
        $client->socialMedia()->attach($twitter->id, [
            'account_url' => 'https://x.com/smart_publish',
            'notes' => 'الحساب الرسمي المعتمد',
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Test tabs filtering
        Livewire::test(SocialMediaPublishing::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$dueDistribution])
            ->assertCanNotSeeTableRecords([$futureDistribution])
            ->set('activeTab', 'upcoming')
            ->assertCanSeeTableRecords([$futureDistribution])
            ->assertCanNotSeeTableRecords([$dueDistribution])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$dueDistribution, $futureDistribution])
            // Test publish action modal contents
            ->mountTableAction('publishToSocialMedia', $dueDistribution)
            ->assertSuccessful()
            ->assertSee('توجيهات وهاشتاقات النشر')
            ->assertSee('يرجى استخدام الهاشتاق')
            ->assertSee('https://x.com/smart_publish')
            ->assertSee('الحساب الرسمي المعتمد');
    }

    public function test_publishing_status_scopes_and_stats_work_correctly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'marketing_amount' => 500,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $insta = SocialMedia::create(['name' => 'انستغرام']);
        $fb = SocialMedia::create(['name' => 'فيسبوك']);
        $client->socialMedia()->attach([$insta->id, $fb->id]);

        // Distribution 1: Not published (Pending)
        $distPending = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinutes(10),
        ]);

        // Distribution 2: Partially published (1 of 2 platforms)
        $distPartial = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinutes(5),
        ]);
        SocialMediaPost::create([
            'client_tag_distribution_id' => $distPartial->id,
            'social_media_id' => $insta->id,
            'user_id' => $admin->id,
            'published_at' => now(),
        ]);

        // Distribution 3: Fully published (2 of 2 platforms)
        $distCompleted = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinutes(2),
        ]);
        SocialMediaPost::create([
            'client_tag_distribution_id' => $distCompleted->id,
            'social_media_id' => $insta->id,
            'user_id' => $admin->id,
            'published_at' => now(),
        ]);
        SocialMediaPost::create([
            'client_tag_distribution_id' => $distCompleted->id,
            'social_media_id' => $fb->id,
            'user_id' => $admin->id,
            'published_at' => now(),
        ]);

        // Verify scopes
        $this->assertEquals('pending', $distPending->social_media_publishing_status);
        $this->assertEquals('partial', $distPartial->social_media_publishing_status);
        $this->assertEquals('completed', $distCompleted->social_media_publishing_status);

        $pendingRecords = ClientTagDistribution::wherePendingPublishing()->get();
        $this->assertTrue($pendingRecords->contains($distPending));
        $this->assertFalse($pendingRecords->contains($distPartial));
        $this->assertFalse($pendingRecords->contains($distCompleted));

        $partialRecords = ClientTagDistribution::wherePartiallyPublished()->get();
        $this->assertTrue($partialRecords->contains($distPartial));
        $this->assertFalse($partialRecords->contains($distPending));
        $this->assertFalse($partialRecords->contains($distCompleted));

        $fullyRecords = ClientTagDistribution::whereFullyPublished()->get();
        $this->assertTrue($fullyRecords->contains($distCompleted));
        $this->assertFalse($fullyRecords->contains($distPending));
        $this->assertFalse($fullyRecords->contains($distPartial));

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Test filtering by publishing status in Livewire
        Livewire::test(SocialMediaPublishing::class)
            ->filterTable('publishing_status', 'pending')
            ->assertCanSeeTableRecords([$distPending])
            ->assertCanNotSeeTableRecords([$distPartial, $distCompleted])
            ->filterTable('publishing_status', 'partial')
            ->assertCanSeeTableRecords([$distPartial])
            ->assertCanNotSeeTableRecords([$distPending, $distCompleted])
            ->set('activeTab', 'all')
            ->filterTable('publishing_status', 'completed')
            ->assertCanSeeTableRecords([$distCompleted])
            ->assertCanNotSeeTableRecords([$distPending, $distPartial]);
    }

    public function test_platform_filter_and_bulk_publish_action(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        // Client 1 with Snap
        $client1 = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $client1->id,
            'status' => 'active',
            'marketing_amount' => 500,
        ]);
        $designer = Designer::factory()->create();
        $cd1 = ClientDesigner::create([
            'client_id' => $client1->id,
            'designer_id' => $designer->id,
        ]);
        $snap = SocialMedia::create(['name' => 'سناب شات']);
        $client1->socialMedia()->attach($snap->id);

        $dist1 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $cd1->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinute(),
        ]);

        // Client 2 with TikTok
        $client2 = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $client2->id,
            'status' => 'active',
            'marketing_amount' => 500,
        ]);
        $cd2 = ClientDesigner::create([
            'client_id' => $client2->id,
            'designer_id' => $designer->id,
        ]);
        $tiktok = SocialMedia::create(['name' => 'تيك توك']);
        $client2->socialMedia()->attach($tiktok->id);

        $dist2 = ClientTagDistribution::factory()->create([
            'client_designer_id' => $cd2->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinute(),
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // Test platform filter
        Livewire::test(SocialMediaPublishing::class)
            ->filterTable('platform', $snap->id)
            ->assertCanSeeTableRecords([$dist1])
            ->assertCanNotSeeTableRecords([$dist2])
            ->filterTable('platform', $tiktok->id)
            ->assertCanSeeTableRecords([$dist2])
            ->assertCanNotSeeTableRecords([$dist1]);

        // Test bulk publish action
        Livewire::test(SocialMediaPublishing::class)
            ->callTableBulkAction('bulkPublish', [$dist1, $dist2], [
                'social_media_id' => $snap->id,
                'notes' => 'نشر جماعي تجريبي',
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('social_media_posts', [
            'client_tag_distribution_id' => $dist1->id,
            'social_media_id' => $snap->id,
            'notes' => 'نشر جماعي تجريبي',
        ]);
        $this->assertDatabaseHas('social_media_posts', [
            'client_tag_distribution_id' => $dist2->id,
            'social_media_id' => $snap->id,
            'notes' => 'نشر جماعي تجريبي',
        ]);
    }

    public function test_skip_publishing_and_reset_publishing_actions_work(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($admin);

        $category = \App\Models\Category::factory()->create();
        $location = \App\Models\Location::factory()->create();

        $client = Client::factory()->create([
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);
        Contract::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'marketing_amount' => 0,
        ]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        $platform = SocialMedia::create(['name' => 'انستغرام']);
        $client->socialMedia()->attach($platform->id);

        $distribution = ClientTagDistribution::factory()->create([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now()->subMinutes(5),
        ]);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        // 1. Call skipPublishing
        Livewire::test(SocialMediaPublishing::class)
            ->callTableAction('skipPublishing', $distribution)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('social_media_posts', [
            'client_tag_distribution_id' => $distribution->id,
            'social_media_id' => $platform->id,
            'notes' => 'تم التخطي / مستبعد من النشر',
        ]);

        // Distribution status remains untouched ('sending')
        $this->assertEquals('sending', $distribution->fresh()->status->value ?? $distribution->fresh()->status);

        // 2. Call resetPublishing (Revert) from completed tab
        Livewire::test(SocialMediaPublishing::class)
            ->set('activeTab', 'completed')
            ->callTableAction('resetPublishing', $distribution)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('social_media_posts', [
            'client_tag_distribution_id' => $distribution->id,
        ]);

        // 3. Bulk skip and reset
        Livewire::test(SocialMediaPublishing::class)
            ->callTableBulkAction('bulkSkipPublishing', [$distribution])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('social_media_posts', [
            'client_tag_distribution_id' => $distribution->id,
            'social_media_id' => $platform->id,
        ]);

        Livewire::test(SocialMediaPublishing::class)
            ->set('activeTab', 'completed')
            ->callTableBulkAction('bulkResetPublishing', [$distribution])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseMissing('social_media_posts', [
            'client_tag_distribution_id' => $distribution->id,
        ]);
    }
}
