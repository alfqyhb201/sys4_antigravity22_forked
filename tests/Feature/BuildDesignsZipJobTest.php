<?php

namespace Tests\Feature;

use App\Filament\Pages\SendingFollowUp;
use App\Jobs\BuildDesignsZipJob;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BuildDesignsZipJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        Storage::fake('public');
        Storage::fake('local');
    }

    protected function createDistribution(array $attributes = []): ClientTagDistribution
    {
        $category = Category::factory()->create();
        $client = Client::factory()->create(['category_id' => $category->id]);
        $designer = Designer::factory()->create();
        $clientDesigner = ClientDesigner::create([
            'client_id' => $client->id,
            'designer_id' => $designer->id,
        ]);

        return ClientTagDistribution::factory()->create(array_merge([
            'client_designer_id' => $clientDesigner->id,
            'status' => 'sending',
            'scheduled_sending_at' => now(),
        ], $attributes));
    }

    public function test_job_successfully_creates_zip_when_attachments_exist(): void
    {
        $user = User::factory()->create();
        $token = 'testtoken1234567890123456789012';

        Storage::disk('public')->put('designs/test1.png', 'dummy image content');

        $dist = $this->createDistribution([
            'attachment_path' => 'designs/test1.png',
        ]);

        $job = new BuildDesignsZipJob([$dist->id], $token, $user->id);
        $job->handle();

        $progress = BuildDesignsZipJob::progress($token);
        $this->assertNotNull($progress);
        $this->assertEquals(100, $progress['percent']);
        $this->assertEquals('done', $progress['status']);
        $this->assertNotNull($progress['file']);
        $this->assertTrue(Storage::disk('local')->exists($progress['file']));
    }

    public function test_job_marks_as_empty_when_no_attachments_exist(): void
    {
        $user = User::factory()->create();
        $token = 'emptytoken12345678901234567890';

        $dist = $this->createDistribution([
            'attachment_path' => 'non_existing_file.png',
        ]);

        $job = new BuildDesignsZipJob([$dist->id], $token, $user->id);
        $job->handle();

        $progress = BuildDesignsZipJob::progress($token);
        $this->assertNotNull($progress);
        $this->assertEquals('empty', $progress['status']);
    }

    public function test_user_can_download_their_completed_zip(): void
    {
        $user = User::factory()->create();
        $token = 'downloadtoken123456789012345678';
        $relativePath = 'tmp-zips/designs-' . $token . '.zip';

        Storage::disk('local')->put($relativePath, 'fake zip binary data');

        BuildDesignsZipJob::putProgress($token, [
            'user_id' => $user->id,
            'percent' => 100,
            'status' => 'done',
            'file' => $relativePath,
            'message' => null,
        ]);

        $response = $this->actingAs($user)->get(route('designs-zip.download', ['token' => $token]));
        $response->assertOk();
    }

    public function test_other_user_cannot_download_someone_elses_zip(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = 'otheruserdownload12345678901234';
        $relativePath = 'tmp-zips/designs-' . $token . '.zip';

        Storage::disk('local')->put($relativePath, 'fake zip content');

        BuildDesignsZipJob::putProgress($token, [
            'user_id' => $owner->id,
            'percent' => 100,
            'status' => 'done',
            'file' => $relativePath,
            'message' => null,
        ]);

        $response = $this->actingAs($otherUser)->get(route('designs-zip.download', ['token' => $token]));
        $response->assertNotFound();
    }

    public function test_bulk_action_initializes_zip_and_processes_chunks(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Storage::disk('public')->put('designs/img1.png', 'binary image 1');
        Storage::disk('public')->put('designs/img2.png', 'binary image 2');

        $dist1 = $this->createDistribution(['attachment_path' => 'designs/img1.png']);
        $dist2 = $this->createDistribution(['attachment_path' => 'designs/img2.png']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::actingAs($admin)
            ->test(SendingFollowUp::class)
            ->callTableBulkAction('downloadSelected', [$dist1, $dist2])
            ->assertHasNoTableBulkActionErrors()
            ->assertDispatched('trigger-next-zip-chunk');

        $this->assertNotNull($component->get('zipToken'));
        $this->assertEquals(2, $component->get('totalZipCount'));

        // Process chunk
        $component->call('processNextZipChunk')
            ->assertDispatched('download-sending-zip')
            ->assertSet('zipToken', null)
            ->assertSet('zipStatus', 'idle');
    }

    public function test_user_can_download_sending_follow_up_zip_without_page_navigation(): void
    {
        $user = User::factory()->create();
        $token = 'sendingdownload123456789012345678';
        $relativePath = "tmp-zips/designs-{$token}.zip";

        Storage::disk('local')->put($relativePath, 'fake zip binary data');
        Cache::put("sending_zip:{$token}", [
            'user_id' => $user->id,
            'file' => $relativePath,
            'name' => 'designs-test.zip',
        ]);

        $response = $this->actingAs($user)->get(route('sending-follow-up-zip.download', ['token' => $token]));

        $response->assertOk();
    }

    public function test_other_user_cannot_download_sending_follow_up_zip(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = 'sendingotheruser123456789012345';

        Cache::put("sending_zip:{$token}", [
            'user_id' => $owner->id,
            'file' => "tmp-zips/designs-{$token}.zip",
            'name' => 'designs-test.zip',
        ]);

        $response = $this->actingAs($otherUser)->get(route('sending-follow-up-zip.download', ['token' => $token]));

        $response->assertNotFound();
    }
}
