<?php

namespace Tests\Feature;

use App\Filament\Pages\ActiveSessionsPage;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveSessionsPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create([
            'name' => 'مدير النظام',
            'email' => 'admin@example.com',
        ]);

        $this->adminUser->assignRole($role);
    }

    public function test_active_sessions_page_accessible_by_admin(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ActiveSessionsPage::class)
            ->assertSuccessful();
    }

    public function test_active_sessions_page_denied_for_unauthorized_users(): void
    {
        $regularUser = User::factory()->create([
            'name' => 'مستخدم عادي',
            'email' => 'user@example.com',
        ]);

        $this->actingAs($regularUser);

        $this->assertFalse(ActiveSessionsPage::canAccess());
    }

    public function test_active_sessions_page_filters_online_and_all_users(): void
    {
        $this->actingAs($this->adminUser);

        $now = now()->timestamp;

        $activeUser = User::factory()->create([
            'name' => 'مستخدم نشط الآن',
            'email' => 'active@example.com',
        ]);

        $offlineUser = User::factory()->create([
            'name' => 'مستخدم غير متصل',
            'email' => 'offline@example.com',
        ]);

        // جلسة نشطة قبل دقيقة
        Session::create([
            'id' => 'session_active',
            'user_id' => $activeUser->id,
            'ip_address' => '192.168.1.50',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'payload' => 'dummy_payload',
            'last_activity' => $now - 60,
        ]);

        // اختبار التبويب الافتراضي (المتصلون الآن)
        Livewire::test(ActiveSessionsPage::class)
            ->assertCanSeeTableRecords([$activeUser])
            ->assertCanNotSeeTableRecords([$offlineUser]);

        // اختبار تبويب (جميع المستخدمين)
        Livewire::test(ActiveSessionsPage::class)
            ->call('setTimeFilter', 'all')
            ->assertCanSeeTableRecords([$activeUser, $offlineUser]);
    }

    public function test_user_last_activity_persists_even_after_logout(): void
    {
        $this->actingAs($this->adminUser);

        $loggedOutUser = User::factory()->create([
            'name' => 'مستخدم مسجل خروج',
            'email' => 'loggedout@example.com',
        ]);

        // محاكاة حفظ آخر نشاط عبر الميدلوير
        Cache::put('user_last_activity_'.$loggedOutUser->id, [
            'timestamp' => now()->subMinutes(10)->timestamp,
            'ip_address' => '192.168.1.99',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
        ], now()->addDay());

        $this->assertNotNull($loggedOutUser->getLastActivityInfo());
        $this->assertEquals('192.168.1.99', $loggedOutUser->getLastActivityInfo()['ip_address']);

        // في تبويب جميع المستخدمين، يظهر المستخدم مع تفاصيل نشاطه
        Livewire::test(ActiveSessionsPage::class)
            ->call('setTimeFilter', 'all')
            ->assertCanSeeTableRecords([$loggedOutUser]);
    }

    public function test_terminate_user_sessions_action(): void
    {
        $this->actingAs($this->adminUser);

        $targetUser = User::factory()->create([
            'name' => 'مستخدم مستهدف',
            'email' => 'target@example.com',
        ]);

        Session::create([
            'id' => 'session_user_target',
            'user_id' => $targetUser->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => 'dummy_payload',
            'last_activity' => now()->timestamp,
        ]);

        Livewire::test(ActiveSessionsPage::class)
            ->callTableAction('terminate_user_sessions', $targetUser);

        $this->assertDatabaseMissing('sessions', [
            'id' => 'session_user_target',
        ]);
    }
}
