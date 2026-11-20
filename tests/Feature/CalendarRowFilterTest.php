<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarRowFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_filter_by_format_customer_check_work_and_person(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $editor = User::factory()->create(['role' => Role::VideoEditor, 'name' => 'Ravi Editor']);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner, 'name' => 'Pooja Designer']);
        $calendar = Calendar::create(['client_id' => $client->id, 'month' => '2026-12-01', 'status' => CalendarStatus::ClientApproved, 'created_by' => $smm->id]);

        $reel = $calendar->items()->create(['post_date' => '2026-12-02', 'post_types' => ['instagram_reel'], 'creative_copy' => 'ROW-REEL', 'client_status' => 'approved']);
        $task = Task::forceCreate(['calendar_item_id' => $reel->id, 'assigned_to' => $editor->id, 'assigned_by' => $smm->id, 'deadline' => '2026-11-30']);
        Content::forceCreate(['task_id' => $task->id, 'post_type' => 'reel', 'approver_id' => $smm->id, 'status' => ContentStatus::Published, 'current_version' => 1]);
        $post = $calendar->items()->create(['post_date' => '2026-12-05', 'post_types' => ['instagram_post'], 'creative_copy' => 'ROW-POST', 'client_status' => 'approved']);
        Task::forceCreate(['calendar_item_id' => $post->id, 'assigned_to' => $designer->id, 'assigned_by' => $smm->id, 'deadline' => '2026-12-03']);
        $calendar->items()->create(['post_date' => '2026-12-09', 'post_types' => ['instagram_reel'], 'creative_copy' => 'ROW-LATER', 'client_status' => 'later']);

        $url = fn (array $q = []) => route('calendars.show', [$calendar, ...$q]);
        $this->actingAs($smm)->get($url())->assertOk()->assertSee('ROW-REEL')->assertSee('ROW-POST')->assertSee('ROW-LATER')
            ->assertSee('Format: all')->assertSee('Work: all')->assertSee('Assigned to: all');

        $this->get($url(['format' => 'reel']))->assertSee('ROW-REEL')->assertSee('ROW-LATER')->assertDontSee('ROW-POST')->assertSee('Showing 2 of 3 rows');
        $this->get($url(['check' => 'later']))->assertSee('ROW-LATER')->assertDontSee('ROW-REEL');
        $this->get($url(['work' => 'published']))->assertSee('ROW-REEL')->assertDontSee('ROW-POST');
        $this->get($url(['work' => 'not_started']))->assertSee('ROW-POST')->assertDontSee('ROW-REEL');
        $this->get($url(['person' => (string) $designer->id, 'format' => 'post']))->assertSee('ROW-POST')->assertDontSee('ROW-REEL')->assertDontSee('ROW-LATER');
        $this->get($url(['format' => 'nonsense']))->assertSee('ROW-POST')->assertSee('ROW-REEL'); // unknown value: ignored

        // The Customer gets format + check only (no work / assignee)
        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => $client->id]);
        $this->actingAs($customer)->get($url())->assertOk()->assertSee('Customer check: all')->assertDontSee('Assigned to: all')->assertDontSee('Work: all');
    }
}
