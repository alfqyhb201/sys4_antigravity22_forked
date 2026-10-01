<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Pages\CreateOrder;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use App\Services\RoundRobinService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_order(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        $order = Order::factory()->create([
            'client_name' => 'شركة الأمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'client_name' => 'شركة الأمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_order_belongs_to_designer(): void
    {
        $designer = Designer::factory()->create();
        $order = Order::factory()->create(['designer_id' => $designer->id]);

        $this->assertTrue($order->designer->is($designer));
    }

    public function test_order_belongs_to_assigner(): void
    {
        $assigner = User::factory()->create();
        $order = Order::factory()->create(['assigner_id' => $assigner->id]);

        $this->assertTrue($order->assigner->is($assigner));
    }

    public function test_designer_has_many_orders(): void
    {
        $designer = Designer::factory()->create();
        Order::factory(3)->create(['designer_id' => $designer->id]);

        $this->assertCount(3, $designer->orders);
    }

    public function test_round_robin_assigns_least_loaded_designer(): void
    {
        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();
        $designerC = Designer::factory()->create();

        Order::factory(5)->create(['designer_id' => $designerA->id, 'status' => OrderStatus::Pending]);
        Order::factory(2)->create(['designer_id' => $designerB->id, 'status' => OrderStatus::Pending]);
        Order::factory()->create(['designer_id' => $designerC->id, 'status' => OrderStatus::Completed]);

        $service = new RoundRobinService;
        $next = $service->assignNextDesigner();

        $this->assertEquals($designerC->id, $next->id);
    }

    public function test_round_robin_ignores_completed_orders(): void
    {
        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();

        Order::factory(5)->create(['designer_id' => $designerA->id, 'status' => OrderStatus::Pending]);
        Order::factory(5)->create(['designer_id' => $designerB->id, 'status' => OrderStatus::Completed]);

        $service = new RoundRobinService;
        $next = $service->assignNextDesigner();

        $this->assertEquals($designerB->id, $next->id);
    }

    public function test_round_robin_assigns_designer_with_least_today_orders_when_active_equal(): void
    {
        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();

        // Both have 0 active orders, but Designer A completed 3 orders today, Designer B completed 1 order today
        Order::factory(3)->create([
            'designer_id' => $designerA->id,
            'status' => OrderStatus::Completed,
            'created_at' => now(),
        ]);
        Order::factory(1)->create([
            'designer_id' => $designerB->id,
            'status' => OrderStatus::Completed,
            'created_at' => now(),
        ]);

        $service = new RoundRobinService;
        $next = $service->assignNextDesigner();

        $this->assertEquals($designerB->id, $next->id);
    }

    public function test_round_robin_prioritizes_designer_who_waited_longer_or_never_assigned(): void
    {
        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();
        $designerC = Designer::factory()->create();

        // Designer A received order 2 hours ago (completed)
        Order::factory()->create([
            'designer_id' => $designerA->id,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subHours(2),
        ]);

        // Designer B received order 10 minutes ago (completed)
        Order::factory()->create([
            'designer_id' => $designerB->id,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subMinutes(10),
        ]);

        // Designer C never received any order (today=0, active=0, latest=null)

        $service = new RoundRobinService;

        // Designer C should get it first (never assigned)
        $this->assertEquals($designerC->id, $service->assignNextDesigner()->id);

        // If C also had an order 3 hours ago (longer than A), C would still be prioritized
    }

    public function test_round_robin_rotates_fairly_among_idle_designers(): void
    {
        $assigner = User::factory()->create();
        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();
        $designerC = Designer::factory()->create();

        $service = new RoundRobinService;

        // 1st order -> Designer A
        $d1 = $service->assignNextDesigner();
        $this->assertEquals($designerA->id, $d1->id);
        Order::factory()->create([
            'designer_id' => $d1->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subMinutes(30),
        ]);

        // 2nd order -> Designer B (A has 1 today, B has 0)
        $d2 = $service->assignNextDesigner();
        $this->assertEquals($designerB->id, $d2->id);
        Order::factory()->create([
            'designer_id' => $d2->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subMinutes(20),
        ]);

        // 3rd order -> Designer C (A and B have 1 today, C has 0)
        $d3 = $service->assignNextDesigner();
        $this->assertEquals($designerC->id, $d3->id);
        Order::factory()->create([
            'designer_id' => $d3->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
            'created_at' => now()->subMinutes(10),
        ]);

        // 4th order -> all have 1 order today. Designer A received order longest ago (30 min ago) -> A gets it!
        $d4 = $service->assignNextDesigner();
        $this->assertEquals($designerA->id, $d4->id);
    }

    public function test_status_enum_labels(): void
    {
        $this->assertEquals('في الانتظار', OrderStatus::Pending->getLabel());
        $this->assertEquals('قيد التنفيذ', OrderStatus::InProgress->getLabel());
        $this->assertEquals('قيد المراجعة', OrderStatus::InReview->getLabel());
        $this->assertEquals('مكتمل', OrderStatus::Completed->getLabel());
        $this->assertEquals('ملغي', OrderStatus::Cancelled->getLabel());
    }

    public function test_can_request_revision_from_table(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        $order = Order::factory()->create([
            'client_name' => 'شركة الأمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('requestRevision', $order);

        $order->refresh();
        $this->assertEquals(OrderStatus::Pending, $order->status);
    }

    public function test_can_complete_order_from_table(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        $order = Order::factory()->create([
            'client_name' => 'شركة الأمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('approveOrder', $order);

        $order->refresh();
        $this->assertEquals(OrderStatus::Completed, $order->status);
    }

    public function test_order_completion_does_not_double_increment_contract_count(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        $location = \App\Models\Location::create(['name' => 'موقع اختبار']);
        $category = \App\Models\Category::create(['name' => 'تصنيف اختبار']);
        $currency = \App\Models\Currency::create(['currency' => 'YER', 'currency_name' => 'ريال', 'value' => 1]);

        $client = \App\Models\Client::create([
            'company' => 'شركة الأمل',
            'client_name' => 'عميل اختبار',
            'location_id' => $location->id,
            'category_id' => $category->id,
            'status' => true,
            'contact_number' => '123456789',
        ]);

        $contract = \App\Models\Contract::create([
            'client_id' => $client->id,
            'status' => 'active',
            'payment_type' => 'advance',
            'billing_cycle' => 'monthly',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'weekly_designs_count' => 1,
            'monthly_designs_count' => 4,
            'total_amount' => 1000,
            'currency_id' => $currency->id,
            'simple_requests_enabled' => true,
            'simple_request_price' => 50,
            'simple_requests_count' => 0,
        ]);

        $order = Order::factory()->create([
            'client_name' => 'شركة الأمل',
            'client_id' => $client->id,
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Complete the order first time
        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('approveOrder', $order);

        $contract->refresh();
        $this->assertEquals(1, $contract->simple_requests_count);

        // Try to complete it again (should do nothing because status is already completed)
        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('approveOrder', $order);

        $contract->refresh();
        $this->assertEquals(1, $contract->simple_requests_count);
    }

    public function test_orders_are_filtered_by_active_tab(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        $activeOrder = Order::factory()->create([
            'client_name' => 'الطلب النشط',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending,
        ]);

        $completedOrder = Order::factory()->create([
            'client_name' => 'الطلب المكتمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
        ]);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // By default activeTab is 'active'
        Livewire::test(CreateOrder::class)
            ->assertCanSeeTableRecords([$activeOrder])
            ->assertCanNotSeeTableRecords([$completedOrder]);

        // Switching to 'completed' tab
        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'completed')
            ->assertCanSeeTableRecords([$completedOrder])
            ->assertCanNotSeeTableRecords([$activeOrder]);

        // Switching to 'all' tab
        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$activeOrder, $completedOrder]);
    }

    public function test_order_creation_sends_database_notification(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('company', 'شركة الأمل')
            ->set('description', 'تصميم لوجو جديد')
            ->call('create');

        $this->assertDatabaseHas('orders', [
            'client_name' => 'شركة الأمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $designer->user->id,
        ]);

        $notification = \DB::table('notifications')
            ->where('notifiable_id', $designer->user->id)
            ->first();

        $this->assertNotNull($notification);
        $data = json_decode($notification->data, true);
        $this->assertEquals('طلب تصميم جديد 📋', $data['title']);
        $this->assertStringContainsString('شركة الأمل', $data['body']);
    }

    public function test_database_notification_dispatches_filament_event(): void
    {
        $user = User::factory()->create();

        \Illuminate\Support\Facades\Event::fake([
            \Filament\Notifications\Events\DatabaseNotificationsSent::class,
        ]);

        $notification = \Filament\Notifications\Notification::make()
            ->title('إشعار تجريبي')
            ->body('هذا إشعار تجريبي للتحقق من البث')
            ->icon('heroicon-o-shopping-cart')
            ->iconColor('success');

        $notification->sendToDatabase($user, isEventDispatched: true);

        \Illuminate\Support\Facades\Event::assertDispatched(
            \Filament\Notifications\Events\DatabaseNotificationsSent::class
        );
    }

    public function test_unread_notifications_endpoint_returns_json_and_count(): void
    {
        $user = User::factory()->create();

        $notification = \Filament\Notifications\Notification::make()
            ->title('إشعار تجريبي')
            ->body('هذا إشعار تجريبي للتحقق من المسار')
            ->icon('heroicon-o-shopping-cart')
            ->iconColor('success')
            ->actions([
                \Filament\Notifications\Actions\Action::make('view')
                    ->label('عرض لوحة التحكم')
                    ->url('/admin/designer-dashboard'),
            ]);

        $notification->sendToDatabase($user, isEventDispatched: true);

        $response = $this->getJson('/admin/notifications/unread-count');
        $response->assertStatus(401);

        $response = $this->actingAs($user)->getJson('/admin/notifications/unread-count');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'count',
                'notifications' => [
                    '*' => [
                        'id',
                        'title',
                        'body',
                        'message',
                        'url',
                        'icon',
                        'created_at',
                    ],
                ],
            ]);

        $data = $response->json();
        $this->assertEquals(1, $data['count']);
        $this->assertEquals('إشعار تجريبي', $data['notifications'][0]['title']);
        $this->assertEquals('/admin/designer-dashboard', $data['notifications'][0]['url']);
    }

    public function test_datalist_includes_all_clients_without_fifty_limit(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user->givePermissionTo('view_create_order');

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = \App\Models\Category::create(['name' => 'تصنيف تجريبي']);
        $location = \App\Models\Location::create(['name' => 'موقع تجريبي']);

        for ($i = 1; $i <= 60; $i++) {
            Client::factory()->create([
                'company' => "شركة رقم {$i}",
                'category_id' => $category->id,
                'location_id' => $location->id,
                'added_by_user' => $user->id,
            ]);
        }

        $component = Livewire::test(CreateOrder::class);
        $datalist = $component->instance()->form->getComponent('company')->getDatalistOptions();

        $this->assertCount(60, $datalist);
        $this->assertContains('شركة رقم 60', $datalist);
    }

    public function test_can_directly_assign_order_to_specific_designer(): void
    {
        $assigner = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $designer1 = Designer::factory()->create();
        $designer2 = Designer::factory()->create();

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('company', 'شركة النور')
            ->set('designer_id', $designer2->id)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'client_name' => 'شركة النور',
            'designer_id' => $designer2->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    public function test_can_toggle_and_select_all_and_deselect_designers(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user->givePermissionTo('view_create_order');

        $designer1 = Designer::factory()->create();
        $designer2 = Designer::factory()->create();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->call('toggleDesigner', $designer1->id)
            ->assertSet('selectedDesigners', [$designer1->id])
            ->call('toggleDesigner', $designer2->id)
            ->assertSet('selectedDesigners', [$designer1->id, $designer2->id])
            ->call('deselectAllDesigners')
            ->assertSet('selectedDesigners', [])
            ->call('selectAllDesigners');

        $this->assertCount(Designer::count(), session('selected_designers'));
    }

    public function test_request_revision_appends_notes_to_description(): void
    {
        $assigner = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $designer = Designer::factory()->create();
        $order = Order::factory()->create([
            'client_name' => 'شركة المجد',
            'description' => 'تصميم بوست انستغرام',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('requestRevision', $order, data: [
                'revision_notes' => 'يرجى تغيير الشعار إلى اللون الذهبي',
            ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertStringContainsString('يرجى تغيير الشعار إلى اللون الذهبي', $order->description);
        $this->assertStringContainsString('تصميم بوست انستغرام', $order->description);

        $order->update(['status' => OrderStatus::InReview]);

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->callTableAction('requestRevision', $order, data: [
                'revision_notes' => 'الملاحظة الأحدث فقط',
            ]);

        $order->refresh();
        $this->assertEquals(OrderStatus::Pending, $order->status);
        $this->assertStringContainsString('الملاحظة الأحدث فقط', $order->description);
        $this->assertStringContainsString('تصميم بوست انستغرام', $order->description);
        $this->assertStringNotContainsString('يرجى تغيير الشعار إلى اللون الذهبي', $order->description);
    }

    public function test_in_review_tab_filters_orders(): void
    {
        $assigner = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $designer = Designer::factory()->create();

        $orderReview = Order::factory()->create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        $orderCompleted = Order::factory()->create([
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
        ]);

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'in_review')
            ->assertCanSeeTableRecords([$orderReview])
            ->assertCanNotSeeTableRecords([$orderCompleted]);
    }

    public function test_active_tab_only_shows_pending_and_in_progress_orders(): void
    {
        $assigner = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $designer = Designer::factory()->create();

        $orderPending = Order::factory()->create([
            'client_name' => 'طلب قيد الانتظار',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending,
        ]);

        $orderInProgress = Order::factory()->create([
            'client_name' => 'طلب قيد التنفيذ',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InProgress,
        ]);

        $orderInReview = Order::factory()->create([
            'client_name' => 'طلب قيد المراجعة',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::InReview,
        ]);

        $orderCompleted = Order::factory()->create([
            'client_name' => 'طلب مكتمل',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Completed,
        ]);

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->set('activeTab', 'active')
            ->assertCanSeeTableRecords([$orderPending, $orderInProgress])
            ->assertCanNotSeeTableRecords([$orderInReview, $orderCompleted]);
    }

    public function test_client_context_identifies_active_and_suspended_contracts(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user->givePermissionTo('view_create_order');

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $category = \App\Models\Category::create(['name' => 'تصنيف عقود']);
        $location = \App\Models\Location::create(['name' => 'موقع عقود']);

        $activeClient = Client::factory()->create([
            'company' => 'شركة نشطة',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'added_by_user' => $user->id,
        ]);

        $currency = \App\Models\Currency::create(['currency' => 'YER', 'currency_name' => 'ريال', 'value' => 1]);

        \App\Models\Contract::factory()->create([
            'client_id' => $activeClient->id,
            'currency_id' => $currency->id,
            'status' => 'active',
        ]);

        $suspendedClient = Client::factory()->create([
            'company' => 'شركة موقفة',
            'category_id' => $category->id,
            'location_id' => $location->id,
            'added_by_user' => $user->id,
        ]);

        \App\Models\Contract::factory()->create([
            'client_id' => $suspendedClient->id,
            'currency_id' => $currency->id,
            'status' => 'suspended',
        ]);

        $activeTest = Livewire::test(CreateOrder::class)->set('company', 'شركة نشطة');
        $activeContext = $activeTest->instance()->clientContext;
        $this->assertTrue($activeContext['is_active']);
        $this->assertFalse($activeContext['is_suspended']);

        $suspendedTest = Livewire::test(CreateOrder::class)->set('company', 'شركة موقفة');
        $suspendedContext = $suspendedTest->instance()->clientContext;
        $this->assertFalse($suspendedContext['is_active']);
        $this->assertTrue($suspendedContext['is_suspended']);
        $this->assertEquals('موقف', $suspendedContext['status_label']);
    }

    public function test_can_bulk_create_orders_assigned_to_specific_designer(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user->givePermissionTo('view_create_order');

        $designer = Designer::factory()->create();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $pastedText = "1. شركة الأمل الذهبية\n2- مؤسسة النور الساطعة\n• متجر الأناقة الحديثة";

        Livewire::test(CreateOrder::class)
            ->set('creationMode', 'bulk')
            ->set('bulk_companies', $pastedText)
            ->set('bulk_designer_id', $designer->id)
            ->set('bulk_description', 'تصاميم تسويقية لعروض نهاية الشهر')
            ->call('createBulk')
            ->assertNotified();

        $this->assertDatabaseHas('orders', [
            'client_name' => 'شركة الأمل الذهبية',
            'designer_id' => $designer->id,
            'description' => 'تصاميم تسويقية لعروض نهاية الشهر',
        ]);
        $this->assertDatabaseHas('orders', [
            'client_name' => 'مؤسسة النور الساطعة',
            'designer_id' => $designer->id,
        ]);
        $this->assertDatabaseHas('orders', [
            'client_name' => 'متجر الأناقة الحديثة',
            'designer_id' => $designer->id,
        ]);

        $this->assertEquals(3, Order::where('designer_id', $designer->id)->count());
    }

    public function test_bulk_create_orders_auto_distributes_across_designers(): void
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user->givePermissionTo('view_create_order');

        $designerA = Designer::factory()->create();
        $designerB = Designer::factory()->create();
        $designerC = Designer::factory()->create();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $pastedText = "عميل رقم 1\nعميل رقم 2\nعميل رقم 3";

        Livewire::test(CreateOrder::class)
            ->set('creationMode', 'bulk')
            ->set('bulk_companies', $pastedText)
            ->set('bulk_designer_id', null) // Auto round-robin
            ->call('createBulk')
            ->assertNotified();

        // 3 orders distributed among 3 designers -> 1 order each!
        $this->assertEquals(1, Order::where('designer_id', $designerA->id)->count());
        $this->assertEquals(1, Order::where('designer_id', $designerB->id)->count());
        $this->assertEquals(1, Order::where('designer_id', $designerC->id)->count());
    }

    public function test_regular_user_only_sees_their_own_orders(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $designer = Designer::factory()->create();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $user1->givePermissionTo('view_create_order');

        $order1 = Order::factory()->create([
            'client_name' => 'طلب المستخدم الأول',
            'designer_id' => $designer->id,
            'assigner_id' => $user1->id,
            'status' => OrderStatus::Pending,
        ]);

        $order2 = Order::factory()->create([
            'client_name' => 'طلب المستخدم الثاني',
            'designer_id' => $designer->id,
            'assigner_id' => $user2->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($user1);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->assertCanSeeTableRecords([$order1])
            ->assertCanNotSeeTableRecords([$order2]);
    }

    public function test_admin_user_sees_all_orders(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->givePermissionTo('view_create_order');

        $otherUser = User::factory()->create();
        $designer = Designer::factory()->create();

        $orderOfOtherUser = Order::factory()->create([
            'client_name' => 'طلب مستخدم آخر',
            'designer_id' => $designer->id,
            'assigner_id' => $otherUser->id,
            'status' => OrderStatus::Pending,
        ]);

        $orderOfAdmin = Order::factory()->create([
            'client_name' => 'طلب الأدمن',
            'designer_id' => $designer->id,
            'assigner_id' => $admin->id,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->assertCanSeeTableRecords([$orderOfOtherUser, $orderOfAdmin]);
    }

    public function test_orders_are_ordered_from_newest_to_oldest_by_default(): void
    {
        $assigner = User::factory()->create();
        $designer = Designer::factory()->create();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_create_order']);
        $assigner->givePermissionTo('view_create_order');

        $olderOrder = Order::factory()->create([
            'client_name' => 'الطلب القديم',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending,
            'created_at' => now()->subDays(2),
        ]);

        $newerOrder = Order::factory()->create([
            'client_name' => 'الطلب الحديث',
            'designer_id' => $designer->id,
            'assigner_id' => $assigner->id,
            'status' => OrderStatus::Pending,
            'created_at' => now(),
        ]);

        $this->actingAs($assigner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateOrder::class)
            ->assertCanSeeTableRecords([$newerOrder, $olderOrder], inOrder: true);
    }
}
