<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientTemplate;
use App\Services\ImageThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientTemplateThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->category = Category::create([
            'name' => 'مطاعم',
        ]);
    }

    private function createClient(): Client
    {
        return Client::factory()->create([
            'category_id' => $this->category->id,
        ]);
    }

    public function test_image_thumbnail_service_generates_thumbnail_properly(): void
    {
        $file = UploadedFile::fake()->image('template.png', 800, 600);
        $path = $file->store('client-templates', 'public');

        $service = app(ImageThumbnailService::class);
        $thumbPath = $service->generateThumbnail($path, 150, 150);

        $this->assertNotNull($thumbPath);
        $this->assertTrue(Storage::disk('public')->exists($thumbPath));
        $this->assertTrue($service->thumbnailExists($path));
    }

    public function test_client_template_thumbnail_url_returns_thumbnail_if_exists_or_fallback_if_not(): void
    {
        $client = $this->createClient();

        // 1. Without thumbnail generated yet
        $file = UploadedFile::fake()->image('banner.png', 400, 400);
        $path = $file->store('client-templates', 'public');

        // Create template model without triggering thumbnail generation
        $template = new ClientTemplate([
            'client_id' => $client->id,
            'type' => 'banner',
            'file' => $path,
        ]);

        $this->assertStringContainsString($path, $template->thumbnail_url);

        // 2. Generate thumbnail and verify thumbnail_url points to thumbnail
        $service = app(ImageThumbnailService::class);
        $service->generateThumbnail($path);

        $expectedThumbName = pathinfo($path, PATHINFO_FILENAME).'.webp';
        $this->assertStringContainsString('thumbnails/'.$expectedThumbName, $template->thumbnail_url);
    }

    public function test_saving_client_template_generates_thumbnail_automatically(): void
    {
        $client = $this->createClient();
        $file = UploadedFile::fake()->image('profile.png', 500, 500);
        $path = $file->store('client-templates', 'public');

        $template = ClientTemplate::create([
            'client_id' => $client->id,
            'type' => 'profile',
            'file' => $path,
        ]);

        $service = app(ImageThumbnailService::class);
        $this->assertTrue($service->thumbnailExists($template->file));
    }

    public function test_artisan_command_generates_thumbnails_for_all_templates(): void
    {
        $client = $this->createClient();

        $file1 = UploadedFile::fake()->image('temp1.png', 300, 300);
        $path1 = $file1->store('client-templates', 'public');

        $file2 = UploadedFile::fake()->image('temp2.png', 300, 300);
        $path2 = $file2->store('client-templates', 'public');

        ClientTemplate::withoutEvents(function () use ($client, $path1, $path2) {
            ClientTemplate::create([
                'client_id' => $client->id,
                'type' => 'profile',
                'file' => $path1,
            ]);

            ClientTemplate::create([
                'client_id' => $client->id,
                'type' => 'cover',
                'file' => $path2,
            ]);
        });

        $service = app(ImageThumbnailService::class);
        $this->assertFalse($service->thumbnailExists($path1));
        $this->assertFalse($service->thumbnailExists($path2));

        $this->artisan('templates:generate-thumbnails')
            ->assertSuccessful();

        $this->assertTrue($service->thumbnailExists($path1));
        $this->assertTrue($service->thumbnailExists($path2));
    }
}
