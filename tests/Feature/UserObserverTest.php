<?php

namespace Tests\Feature;

use App\Filament\Resources\UsersResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_clears_users_count_badge_cache(): void
    {
        // 1. Set cache manually or calculate badge
        Cache::put('filament_users_count', '10', 300);
        $this->assertEquals('10', Cache::get('filament_users_count'));

        // 2. Create a new user
        $user = User::factory()->create();

        // 3. Assert cache was forgotten
        $this->assertNull(Cache::get('filament_users_count'));

        // 4. Calling getNavigationBadge calculates fresh count
        $badge = UsersResource::getNavigationBadge();
        $this->assertEquals((string) User::count(), $badge);
    }

    public function test_user_deletion_clears_users_count_badge_cache(): void
    {
        $user = User::factory()->create();

        // Warm up cache
        UsersResource::getNavigationBadge();
        $this->assertNotNull(Cache::get('filament_users_count'));

        // Delete user
        $user->delete();

        // Assert cache was forgotten
        $this->assertNull(Cache::get('filament_users_count'));

        // Calling getNavigationBadge calculates fresh count
        $badge = UsersResource::getNavigationBadge();
        $this->assertEquals((string) User::count(), $badge);
    }
}
