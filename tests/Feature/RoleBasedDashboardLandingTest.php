<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountingDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DesignerDashboard;
use App\Filament\Pages\ReviewerDashboard;
use App\Filament\Pages\SocialMediaPublishing;
use App\Filament\Pages\SupervisorDashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleBasedDashboardLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'supervisor']);
        Role::firstOrCreate(['name' => 'designer']);
        Role::firstOrCreate(['name' => 'reviewer']);
        Role::firstOrCreate(['name' => 'accountant']);
        Role::firstOrCreate(['name' => 'social_media']);

        Permission::firstOrCreate(['name' => 'view_admin_dashboard']);
        Permission::firstOrCreate(['name' => 'view_designer_dashboard']);
        Permission::firstOrCreate(['name' => 'view_reviewer_dashboard']);
        Permission::firstOrCreate(['name' => 'view_supervisor_dashboard']);
        Permission::firstOrCreate(['name' => 'view_financial_reports']);
        Permission::firstOrCreate(['name' => 'view_social_media_publishing']);
    }

    public function test_get_default_dashboard_url_returns_expected_urls_for_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertEquals(Dashboard::getUrl(), $admin->getDefaultDashboardUrl());

        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');
        $this->assertEquals(SupervisorDashboard::getUrl(), $supervisor->getDefaultDashboardUrl());

        $designer = User::factory()->create();
        $designer->assignRole('designer');
        $this->assertEquals(DesignerDashboard::getUrl(), $designer->getDefaultDashboardUrl());

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $this->assertEquals(ReviewerDashboard::getUrl(), $reviewer->getDefaultDashboardUrl());

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');
        $this->assertEquals(AccountingDashboard::getUrl(), $accountant->getDefaultDashboardUrl());

        $socialMedia = User::factory()->create();
        $socialMedia->assignRole('social_media');
        $this->assertEquals(SocialMediaPublishing::getUrl(), $socialMedia->getDefaultDashboardUrl());
    }

    public function test_admin_user_can_access_main_dashboard_without_redirect(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(Dashboard::class)
            ->assertStatus(200);
    }

    public function test_supervisor_can_access_main_dashboard_without_redirect(): void
    {
        $supervisor = User::factory()->create();
        $supervisor->assignRole('supervisor');

        Livewire::actingAs($supervisor)
            ->test(Dashboard::class)
            ->assertStatus(200);
    }

    public function test_designer_is_redirected_to_designer_dashboard_when_accessing_main_dashboard(): void
    {
        $designer = User::factory()->create();
        $designer->assignRole('designer');
        $designer->givePermissionTo('view_designer_dashboard');

        Livewire::actingAs($designer)
            ->test(Dashboard::class)
            ->assertRedirect(DesignerDashboard::getUrl());
    }

    public function test_reviewer_is_redirected_to_reviewer_dashboard_when_accessing_main_dashboard(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $reviewer->givePermissionTo('view_reviewer_dashboard');

        Livewire::actingAs($reviewer)
            ->test(Dashboard::class)
            ->assertRedirect(ReviewerDashboard::getUrl());
    }

    public function test_accountant_is_redirected_to_accounting_dashboard_when_accessing_main_dashboard(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');
        $accountant->givePermissionTo('view_financial_reports');

        Livewire::actingAs($accountant)
            ->test(Dashboard::class)
            ->assertRedirect(AccountingDashboard::getUrl());
    }

    public function test_social_media_is_redirected_to_social_publishing_when_accessing_main_dashboard(): void
    {
        $socialMedia = User::factory()->create();
        $socialMedia->assignRole('social_media');
        $socialMedia->givePermissionTo('view_social_media_publishing');

        Livewire::actingAs($socialMedia)
            ->test(Dashboard::class)
            ->assertRedirect(SocialMediaPublishing::getUrl());
    }

    public function test_navigation_sidebar_shows_main_dashboard_for_admin_and_hides_it_for_designer(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $adminNavigation = Filament::getNavigation();

        $adminHasHome = false;
        foreach ($adminNavigation as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'الرئيسية') {
                    $adminHasHome = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($adminHasHome, 'Admin should have "الرئيسية" in navigation');

        $designer = User::factory()->create();
        $designer->assignRole('designer');
        $designer->givePermissionTo('view_designer_dashboard');

        $this->actingAs($designer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $designerNavigation = Filament::getNavigation();

        $designerHasHome = false;
        foreach ($designerNavigation as $group) {
            foreach ($group->getItems() as $item) {
                if ($item->getLabel() === 'الرئيسية') {
                    $designerHasHome = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($designerHasHome, 'Designer should NOT have "الرئيسية" in navigation');
    }
}
