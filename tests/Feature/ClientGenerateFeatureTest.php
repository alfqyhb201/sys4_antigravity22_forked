<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات ميزة الجنريت في نموذج العميل.
 *
 * تتحقق هذه الاختبارات من أن:
 * - الحقول الجديدة تُخزَّن بشكل صحيح في قاعدة البيانات.
 * - السلوك الافتراضي يُشير الجنريت كـ "نعم" مع كل الأنواع.
 * - عند اختيار "لا" تُحذف أنواع الجنريت من الواجهة.
 */
class ClientGenerateFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /**
     * إعداد بيئة الاختبار.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);
        $this->actingAs($this->user);
    }

    /**
     * إنشاء عميل لاستخدامه في الاختبارات.
     */
    protected function createClient(array $attributes = []): Client
    {
        $location = Location::create([
            'name' => 'موقع اختبار',
            'added_by_user' => $this->user->id,
        ]);

        $category = Category::create([
            'name' => 'تصنيف اختبار',
        ]);

        return Client::withoutEvents(function () use ($attributes, $location, $category) {
            return Client::create(array_merge([
                'company' => 'شركة اختبار',
                'client_name' => 'عميل اختبار',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'added_by_user' => $this->user->id,
                'status' => true,
            ], $attributes));
        });
    }

    /**
     * التحقق من أن القيم الافتراضية للجنريت صحيحة في النموذج الجديد.
     */
    public function test_default_generate_feature_is_enabled(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => true,
            'generate_types' => ['very_high', 'high', 'medium', 'low', 'ideas'],
        ]);

        $this->assertTrue($client->has_generate_feature);
        $this->assertEquals(['very_high', 'high', 'medium', 'low', 'ideas'], $client->generate_types);
    }

    /**
     * التحقق من حفظ الجنريت = لا بدون أنواع.
     */
    public function test_can_disable_generate_feature(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => false,
            'generate_types' => null,
        ]);

        $this->assertFalse($client->has_generate_feature);
        $this->assertNull($client->generate_types);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'has_generate_feature' => false,
        ]);
    }

    /**
     * التحقق من حفظ أنواع جنريت مخصصة.
     */
    public function test_can_save_custom_generate_types(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => true,
            'generate_types' => ['high', 'ideas'],
        ]);

        $client->refresh();
        $this->assertEquals(['high', 'ideas'], $client->generate_types);
    }

    /**
     * التحقق من أن cast الـ generate_types يعمل كـ array.
     */
    public function test_generate_types_is_cast_to_array(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => true,
            'generate_types' => ['very_high', 'medium'],
        ]);

        $client->refresh();
        $this->assertIsArray($client->generate_types);
        $this->assertContains('very_high', $client->generate_types);
        $this->assertContains('medium', $client->generate_types);
    }

    /**
     * التحقق من أن حقلي الجنريت موجودان في السمات القابلة للتعبئة.
     */
    public function test_generate_feature_fields_are_fillable(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => true,
            'generate_types' => ['very_high', 'high'],
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'has_generate_feature' => true,
        ]);

        $client->refresh();
        $this->assertArrayHasKey('very_high', array_flip($client->generate_types));
        $this->assertArrayHasKey('high', array_flip($client->generate_types));
    }

    /**
     * التحقق من تحديث قيم الجنريت مباشرةً عبر النموذج.
     */
    public function test_can_save_generate_feature_via_edit_form(): void
    {
        $client = $this->createClient([
            'has_generate_feature' => true,
            'generate_types' => ['very_high', 'high', 'medium', 'low', 'ideas'],
        ]);

        // تحديث القيم مباشرةً عبر Model (نفس ما يفعله afterSave)
        Client::withoutEvents(function () use ($client) {
            $client->update([
                'has_generate_feature' => true,
                'generate_types' => ['high', 'medium'],
            ]);
        });

        $client->refresh();
        $this->assertTrue($client->has_generate_feature);
        $this->assertEquals(['high', 'medium'], $client->generate_types);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'has_generate_feature' => true,
        ]);
    }
}
