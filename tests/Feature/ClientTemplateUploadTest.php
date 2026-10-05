<?php

namespace Tests\Feature;

use App\Filament\Enums\ClientTemplateType;
use App\Filament\Resources\ClientTemplateResource\Pages\ListClientTemplates;
use App\Models\Category;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientTemplateUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->category = Category::create([
            'name' => 'مطاعم',
        ]);

        $this->admin = User::factory()->create(['status' => 1]);
        Permission::firstOrCreate(['name' => 'view_any_client_template', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'update_client_template', 'guard_name' => 'web']);
        $this->admin->givePermissionTo(['view_any_client_template', 'update_client_template']);

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    private function createClient(): Client
    {
        return Client::factory()->create([
            'category_id' => $this->category->id,
            'company' => 'شركة تجريبية',
        ]);
    }

    public function test_can_upload_all_templates_via_upload_templates_action(): void
    {
        $this->actingAs($this->admin);
        $client = $this->createClient();

        $file = UploadedFile::fake()->image('cliche.png', 400, 400);

        Livewire::test(ListClientTemplates::class)
            ->callTableAction('uploadTemplates', $client, [
                'cliche_file' => $file,
                'cliche_local_path' => 'D:\Designs\cliche.psd',
                'greetings_file' => $file,
                'greetings_local_path' => 'D:\Designs\greetings.psd',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('client_templates', [
            'client_id' => $client->id,
            'type' => ClientTemplateType::Cliche->value,
            'local_path' => 'D:\Designs\cliche.psd',
        ]);

        $this->assertDatabaseHas('client_templates', [
            'client_id' => $client->id,
            'type' => ClientTemplateType::Greetings->value,
            'local_path' => 'D:\Designs\greetings.psd',
        ]);
    }

    public function test_can_upload_single_template_via_column_action(): void
    {
        $this->actingAs($this->admin);
        $client = $this->createClient();

        $file = UploadedFile::fake()->image('newborns.png', 400, 400);

        Livewire::test(ListClientTemplates::class)
            ->callTableAction('manage_col_newborns', $client, [
                'file' => $file,
                'local_path' => 'D:\Designs\newborns.psd',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('client_templates', [
            'client_id' => $client->id,
            'type' => ClientTemplateType::Newborns->value,
            'local_path' => 'D:\Designs\newborns.psd',
        ]);
    }

    public function test_unauthorized_user_cannot_access_client_templates_page(): void
    {
        $user = User::factory()->create(['status' => 1]);
        $this->actingAs($user);

        $response = $this->get('/admin/client-templates');
        $response->assertForbidden();
    }

    public function test_user_without_update_permission_cannot_see_upload_action(): void
    {
        $user = User::factory()->create(['status' => 1]);
        Permission::firstOrCreate(['name' => 'view_any_client_template', 'guard_name' => 'web']);
        $user->givePermissionTo('view_any_client_template');

        $this->actingAs($user);
        $client = $this->createClient();

        Livewire::test(ListClientTemplates::class)
            ->assertTableActionHidden('uploadTemplates', $client);
    }

    public function test_user_with_delete_permission_can_delete_template(): void
    {
        $user = User::factory()->create(['status' => 1]);
        Permission::firstOrCreate(['name' => 'view_any_client_template', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete_client_template', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_client_template', 'delete_client_template']);

        $this->actingAs($user);
        $client = $this->createClient();

        $template = \App\Models\ClientTemplate::create([
            'client_id' => $client->id,
            'type' => ClientTemplateType::Cliche->value,
            'file' => 'client-templates/cliche.png',
        ]);

        $this->assertDatabaseHas('client_templates', [
            'id' => $template->id,
        ]);

        $template->delete();

        $this->assertDatabaseMissing('client_templates', [
            'id' => $template->id,
        ]);
    }
}
