<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoThumbTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_shows_a_picture_and_the_first_saved_frame_is_kept(): void
    {
        Storage::fake('local');
        $client = Client::create(['name' => 'Acme']);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $video = Asset::create(['client_id' => $client->id, 'uploaded_by' => $smm->id, 'path' => 'assets/v.mp4',
            'original_name' => 'shoot.mp4', 'mime' => 'video/mp4', 'size' => 100]);

        // No picture yet: no <video> in the grid, the tile asks the browser to make one
        $this->actingAs($smm)->get(route('assets.index', ['client' => $client->id]))->assertOk()
            ->assertDontSee("<video", false)->assertSee("data-save=\"".route("assets.thumb.store", $video), false);

        $this->post(route('assets.thumb.store', $video), ['thumb' => UploadedFile::fake()->image('a.jpg')])->assertNoContent();
        $first = $video->fresh()->thumb_path;
        Storage::disk('local')->assertExists($first);
        $this->post(route('assets.thumb.store', $video), ['thumb' => UploadedFile::fake()->image('b.jpg')])->assertNoContent();
        $this->assertSame($first, $video->fresh()->thumb_path);

        $this->get(route('assets.index', ['client' => $client->id]))->assertSee(route('assets.thumb', $video), false)
            ->assertDontSee("data-save", false);
        $this->get(route('assets.thumb', $video))->assertOk();

        $this->post(route('assets.thumb.store', $video), ['thumb' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('thumb');

        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);
        $this->actingAs($other)->get(route('assets.thumb', $video))->assertForbidden();
        $this->post(route('assets.thumb.store', $video), ['thumb' => UploadedFile::fake()->image('c.jpg')])->assertForbidden();

        $video->fresh()->delete();
        Storage::disk('local')->assertMissing($first);
    }
}
