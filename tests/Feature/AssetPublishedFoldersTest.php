<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\ContentMedia;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetPublishedFoldersTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $smm;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create(['name' => 'Acme']);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'status' => CalendarStatus::ClientApproved, 'created_by' => $this->smm->id]);
    }

    /** a post with its versions; returns the media of each version [v1 => [...], v2 => [...]] */
    private function publishedPost(string $type, ContentStatus $status, string $date, array $versions): array
    {
        $item = $this->calendar->items()->create(['post_date' => $date, 'post_types' => ['instagram_'.$type]]);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $this->smm->id, 'assigned_by' => $this->smm->id, 'deadline' => $date]);
        $content = Content::forceCreate(['task_id' => $task->id, 'post_type' => $type, 'approver_id' => $this->smm->id, 'status' => $status, 'current_version' => count($versions)]);
        $out = [];
        foreach ($versions as $n => $files) {
            $v = $content->versions()->create(['number' => $n + 1, 'submitted_by' => $this->smm->id]);
            foreach ($files as $i => [$kind, $name]) {
                $out[$n + 1][] = $v->media()->create(['kind' => $kind, 'path' => "contents/{$name}", 'original_name' => $name, 'sort_order' => $i + 1]);
            }
        }

        return $out;
    }

    public function test_published_videos_and_photos_have_their_own_folders(): void
    {
        $this->publishedPost('reel', ContentStatus::Published, '2026-11-20', [[['video', 'reel-old.mp4']], [['video', 'reel-final.mp4'], ['cover', 'cover.jpg']]]);
        $this->publishedPost('carousel', ContentStatus::Published, '2026-11-10', [[['image', 'slide1.jpg'], ['image', 'slide2.jpg']]]);
        $this->publishedPost('post', ContentStatus::Approved, '2026-11-25', [[['image', 'not-yet.jpg']]]); // not published: stays out

        $base = ['client' => $this->client->id];
        $this->actingAs($this->smm)->get(route('assets.index', $base))->assertOk()
            ->assertSee('Published videos')->assertSee('Published photos');

        $this->get(route('assets.index', $base + ['folder' => 'videos']))->assertOk()
            ->assertSee('reel-final.mp4')->assertDontSee('reel-old.mp4')->assertDontSee('cover.jpg')->assertDontSee('slide1.jpg');

        $this->get(route('assets.index', $base + ['folder' => 'photos']))->assertOk()
            ->assertSeeInOrder(['slide 1', 'slide 2'])->assertSee('slide2.jpg')->assertDontSee('not-yet.jpg')->assertDontSee('reel-final.mp4');
    }

    public function test_brand_files_split_into_photos_and_videos(): void
    {
        foreach ([['shoot-day1.mp4', 'video/mp4'], ['logo.png', 'image/png']] as [$name, $mime]) {
            \App\Models\Asset::create(['client_id' => $this->client->id, 'uploaded_by' => $this->smm->id, 'path' => 'assets/'.$name,
                'original_name' => $name, 'mime' => $mime, 'size' => 100]);
        }
        $this->actingAs($this->smm)->get(route('assets.index', ['client' => $this->client->id, 'kind' => 'videos']))->assertOk()
            ->assertSee('shoot-day1.mp4')->assertDontSee('logo.png');
        $this->get(route('assets.index', ['client' => $this->client->id, 'kind' => 'photos']))->assertSee('logo.png')->assertDontSee('shoot-day1.mp4');
        $this->get(route('assets.index', ['client' => $this->client->id]))->assertSee('shoot-day1.mp4')->assertSee('logo.png')->assertSeeInOrder(['All', 'Photos', 'Videos']);
    }

    public function test_customer_sees_their_brands_published_files(): void
    {
        $media = $this->publishedPost('post', ContentStatus::Published, '2026-11-12', [[['image', 'diwali.jpg']]])[1][0];
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
        $this->actingAs($customer)->get(route('assets.index', ['folder' => 'photos']))->assertOk()->assertSee('diwali.jpg');

        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);
        $this->actingAs($other)->get(route('assets.index', ['folder' => 'photos']))->assertOk()->assertDontSee('diwali.jpg');
        $this->get(route('media.download', $media))->assertForbidden();
    }
}
