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
        Permission::firstOrCreate(['name' => 'view_any_client', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'update_client', 'guard_name' => 'web']);
        $this->admin->givePermissionTo(['view_any_client', 'update_client']);

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
}
