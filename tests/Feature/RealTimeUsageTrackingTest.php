<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Enums\DesignTaskStatus;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DesignTask;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealTimeUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure category exists
        Category::factory()->create(['id' => 1]);
    }

    public function test_additional_designs_count_updates_on_task_approval(): void
    {
        $client = Client::factory()->create(['category_id' => 1]);
        $contract = Contract::factory()->create([
            'client_id' => $client->id,
            'additional_designs_enabled' => true,
            'additional_designs_count' => 0,
        ]);

        $client->update(['current_contract_id' => $contract->id]);

        $task = DesignTask::factory()->create([
            'client_id' => $client->id,
            'is_subscribed_client' => true,
            'is_template_update' => false,
            'status' => DesignTaskStatus::InReview,
        ]);

        $task->update(['status' => DesignTaskStatus::Approved]);

        $this->assertEquals(1, $contract->fresh()->additional_designs_count);
    }

    public function test_additional_designs_count_decrements_on_task_deletion(): void
    {
        $client = Client::factory()->create(['category_id' => 1]);
        $contract = Contract::factory()->create([
            'client_id' => $client->id,
            'additional_designs_enabled' => true,
            'additional_designs_count' => 1,
        ]);
        $client->update(['current_contract_id' => $contract->id]);

        $task = DesignTask::factory()->create([
            'client_id' => $client->id,
            'is_subscribed_client' => true,
            'is_template_update' => false,
            'status' => DesignTaskStatus::Approved,
        ]);

        $task->delete();

        $this->assertEquals(0, $contract->fresh()->additional_designs_count);
    }

    public function test_simple_requests_count_updates_on_order_completion(): void
    {
        $client = Client::factory()->create(['category_id' => 1]);
        $contract = Contract::factory()->create([
            'client_id' => $client->id,
            'simple_requests_enabled' => true,
            'simple_requests_count' => 0,
        ]);
        $client->update(['current_contract_id' => $contract->id]);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => OrderStatus::Pending,
        ]);

        $order->update(['status' => OrderStatus::Completed]);

        $this->assertEquals(1, $contract->fresh()->simple_requests_count);
    }

    public function test_simple_requests_count_decrements_on_order_deletion(): void
    {
        $client = Client::factory()->create(['category_id' => 1]);
        $contract = Contract::factory()->create([
            'client_id' => $client->id,
            'simple_requests_enabled' => true,
            'simple_requests_count' => 1,
        ]);
        $client->update(['current_contract_id' => $contract->id]);

        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => OrderStatus::Completed,
        ]);

        $order->delete();

        $this->assertEquals(0, $contract->fresh()->simple_requests_count);
    }
}
