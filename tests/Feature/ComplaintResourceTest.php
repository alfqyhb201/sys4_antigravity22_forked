<?php

namespace Tests\Feature;

use App\Filament\Enums\ComplaintStatus;
use App\Filament\Resources\ComplaintResource;
use App\Filament\Resources\ComplaintResource\Pages\CreateComplaint;
use App\Filament\Resources\ComplaintResource\Pages\EditComplaint;
use App\Filament\Resources\ComplaintResource\Pages\ListComplaints;
use App\Models\Category;
use App\Models\Client;
use App\Models\Complaint;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComplaintResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['status' => 1, 'username' => 'testuser']);

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_any_complaint']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_complaint']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create_complaint']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_complaint']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'delete_complaint']);

        $this->user->givePermissionTo([
            'view_any_complaint',
            'view_complaint',
            'create_complaint',
            'update_complaint',
            'delete_complaint',
        ]);

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
     * إنشاء شكوى لاستخدامها في الاختبارات.
     */
    protected function createComplaint(array $attributes = []): Complaint
    {
        $client = $attributes['client_id'] ?? null
            ? null
            : $this->createClient();

        return Complaint::create(array_merge([
            'client_id' => $client?->id ?? $attributes['client_id'],
            'description' => 'شكوى اختبارية للتحقق من عمل النظام',
            'status' => ComplaintStatus::New->value,
            'added_by_user' => $this->user->id,
        ], $attributes));
    }

    /** @test */
    public function test_can_render_list_page(): void
    {
        $this->get(ComplaintResource::getUrl('index'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_create_page(): void
    {
        $this->get(ComplaintResource::getUrl('create'))
            ->assertSuccessful();
    }

    /** @test */
    public function test_can_render_view_page(): void
    {
        $complaint = $this->createComplaint();

        $this->get(ComplaintResource::getUrl('view', [
            'record' => $complaint,
        ]))->assertSuccessful();
    }

    /** @test */
    public function test_can_render_edit_page(): void
    {
        $complaint = $this->createComplaint();

        $this->get(ComplaintResource::getUrl('edit', [
            'record' => $complaint,
        ]))->assertSuccessful();
    }

    /** @test */
    public function test_can_list_complaints(): void
    {
        $client = $this->createClient();
        $complaints = collect();

        for ($i = 0; $i < 3; $i++) {
            $complaints->push($this->createComplaint([
                'client_id' => $client->id,
                'description' => "شكوى رقم {$i}",
            ]));
        }

        Livewire::test(ListComplaints::class)
            ->assertCanSeeTableRecords($complaints);
    }

    /** @test */
    public function test_can_create_complaint(): void
    {
        $client = $this->createClient();

        Livewire::test(CreateComplaint::class)
            ->fillForm([
                'client_id' => $client->id,
                'description' => 'شكوى جديدة من عميل',
                'status' => ComplaintStatus::New->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('complaints', [
            'client_id' => $client->id,
            'description' => 'شكوى جديدة من عميل',
            'status' => ComplaintStatus::New->value,
            'added_by_user' => $this->user->id,
        ]);
    }

    /** @test */
    public function test_can_validate_required_fields(): void
    {
        Livewire::test(CreateComplaint::class)
            ->fillForm([
                'client_id' => null,
                'description' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['client_id' => 'required', 'description' => 'required']);
    }

    /** @test */
    public function test_can_edit_complaint(): void
    {
        $complaint = $this->createComplaint();

        Livewire::test(EditComplaint::class, [
            'record' => $complaint->getRouteKey(),
        ])
            ->fillForm([
                'description' => 'تم تعديل الشكوى',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $complaint->refresh();
        $this->assertEquals('تم تعديل الشكوى', $complaint->description);
        $this->assertEquals($this->user->id, $complaint->updated_by_user);
    }

    /** @test */
    public function test_can_delete_complaint(): void
    {
        $complaint = $this->createComplaint();

        Livewire::test(EditComplaint::class, [
            'record' => $complaint->getRouteKey(),
        ])
            ->callAction(\Filament\Actions\DeleteAction::class);

        $this->assertModelMissing($complaint);
    }

    /** @test */
    public function test_can_resolve_complaint_from_table(): void
    {
        $complaint = $this->createComplaint();

        Livewire::test(ListComplaints::class)
            ->callTableAction('resolve', $complaint);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::Resolved, $complaint->status);
    }

    /** @test */
    public function test_can_reopen_complaint_from_table(): void
    {
        $complaint = $this->createComplaint([
            'status' => ComplaintStatus::Resolved->value,
        ]);

        Livewire::test(ListComplaints::class)
            ->callTableAction('reopen', $complaint);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::New, $complaint->status);
    }

    /** @test */
    public function test_client_complaints_relationship(): void
    {
        $client = $this->createClient();

        $this->createComplaint(['client_id' => $client->id]);
        $this->createComplaint(['client_id' => $client->id]);

        $this->assertEquals(2, $client->complaints()->count());
    }

    /** @test */
    public function test_complaint_deleted_when_client_deleted(): void
    {
        $client = $this->createClient();
        $complaint = $this->createComplaint(['client_id' => $client->id]);

        Client::withoutEvents(function () use ($client) {
            $client->forceDelete(); // force delete to trigger cascade
        });

        $this->assertModelMissing($complaint);
    }

    /** @test */
    public function test_can_filter_by_tabs(): void
    {
        $client = $this->createClient();

        $newComplaint = $this->createComplaint([
            'client_id' => $client->id,
            'status' => ComplaintStatus::New->value,
        ]);

        $resolvedComplaint = $this->createComplaint([
            'client_id' => $client->id,
            'status' => ComplaintStatus::Resolved->value,
        ]);

        Livewire::test(ListComplaints::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$newComplaint, $resolvedComplaint])
            ->set('activeTab', 'new')
            ->assertCanSeeTableRecords([$newComplaint])
            ->assertCanNotSeeTableRecords([$resolvedComplaint])
            ->set('activeTab', 'resolved')
            ->assertCanSeeTableRecords([$resolvedComplaint])
            ->assertCanNotSeeTableRecords([$newComplaint]);
    }

    /** @test */
    public function test_unresolved_complaints_widget_renders_and_has_no_replicate_action(): void
    {
        $complaint = $this->createComplaint([
            'status' => ComplaintStatus::New->value,
        ]);

        Livewire::test(\App\Filament\Widgets\UnresolvedComplaintsWidget::class)
            ->assertCanSeeTableRecords([$complaint])
            ->assertTableActionDoesNotExist('re');
    }
}
