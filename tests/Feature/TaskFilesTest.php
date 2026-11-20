<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Asset;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_sees_and_downloads_the_customers_video_on_the_task(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('assets/1/raw.mp4', 'video');
        $client = Client::create(['name' => 'Acme']);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $editor = User::factory()->create(['role' => Role::VideoEditor]);
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $client->id, 'name' => 'Acme Owner']);
        $calendar = Calendar::create(['client_id' => $client->id, 'month' => '2026-12-01', 'status' => CalendarStatus::ClientApproved, 'created_by' => $smm->id]);
        $item = $calendar->items()->create(['post_date' => '2026-12-03', 'post_types' => ['instagram_reel'], 'client_status' => 'approved']);
        $task = Task::forceCreate(['calendar_item_id' => $item->id, 'assigned_to' => $editor->id, 'assigned_by' => $smm->id, 'deadline' => '2026-12-01']);
        $asset = Asset::create(['client_id' => $client->id, 'calendar_item_id' => $item->id, 'uploaded_by' => $customer->id, 'path' => 'assets/1/raw.mp4',
            'original_name' => 'clinic-raw.mp4', 'mime' => 'video/mp4', 'size' => 5]);

        $this->actingAs($editor)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('Files for this post (1)')->assertSee('clinic-raw.mp4')->assertSee('Acme Owner')->assertSee(route('assets.download', $asset), false);
        $this->get(route('assets.download', $asset))->assertOk();
        $this->get(route('tasks.index'))->assertSee('1 file(s)');
    }
}
