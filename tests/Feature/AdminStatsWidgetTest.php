<?php

namespace Tests\Feature;

use App\Filament\Widgets\AdminStatsWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'designer']);
    }

    public function test_active_contracts_stat_is_visible_for_admin_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(AdminStatsWidget::class)
            ->assertStatus(200)
            ->assertSee('الاشتراكات النشطة');
    }

    public function test_active_contracts_stat_is_hidden_for_non_admin_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('designer');

        Livewire::actingAs($user)
            ->test(AdminStatsWidget::class)
            ->assertStatus(200)
            ->assertDontSee('الاشتراكات النشطة');
    }
}
