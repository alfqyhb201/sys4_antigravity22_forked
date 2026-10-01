<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LocationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_read_and_import_locations(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $rows = [
            ['اسم الموقع' => 'شارع حدة - صنعاء'],
            ['اسم الموقع' => 'شارع الزبيري - صنعاء'],
        ];

        $columnMap = [
            'name' => 'اسم الموقع',
        ];

        $service = new LocationImportService;
        $result = $service->import($rows, $columnMap);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('locations', [
            'name' => 'شارع حدة - صنعاء',
            'added_by_user' => $user->id,
        ]);
        $this->assertDatabaseHas('locations', [
            'name' => 'شارع الزبيري - صنعاء',
            'added_by_user' => $user->id,
        ]);
    }
}
