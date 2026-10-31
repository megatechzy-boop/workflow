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

class BrandAccessTest extends TestCase
{
    use RefreshDatabase;

    private Client $mine;

    private Client $other;

    private User $smm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mine = Client::create(['name' => 'Acme']);
        $this->other = Client::create(['name' => 'Zen Clinic']);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->smm->brands()->sync([$this->mine->id]);
    }

    private function calendar(Client $client, CalendarStatus $status = CalendarStatus::ClientApproved): Calendar
    {
        $owner = User::factory()->create(['role' => Role::Admin]);

        return Calendar::create(['client_id' => $client->id, 'month' => '2026-11-01', 'created_by' => $owner->id, 'status' => $status]);
    }

    public function test_smm_with_brands_sees_only_those_brands(): void
    {
        $mine = $this->calendar($this->mine);
        $other = $this->calendar($this->other);

        $this->actingAs($this->smm)->get('/calendars')->assertOk()->assertSee('Acme')->assertDontSee('Zen Clinic');
        $this->actingAs($this->smm)->get("/calendars/{$mine->id}")->assertOk();
        $this->actingAs($this->smm)->get("/calendars/{$other->id}")->assertRedirect("/calendars"); // not allowed => back to the list
        $this->actingAs($this->smm)->getJson('/chat/client:'.$this->other->id.'/messages')->assertForbidden();
        $this->actingAs($this->smm)->post('/calendars', ['client_id' => $this->other->id, 'month' => '2026-12'])->assertSessionHasErrors('client_id');
    }

    public function test_smm_without_brands_and_ops_see_everything(): void
    {
        $other = $this->calendar($this->other);
        $free = User::factory()->create(['role' => Role::SocialMediaManager]);
        $ops = User::factory()->create(['role' => Role::Manager]);
        $ops->brands()->sync([$this->mine->id]); // the limit is only for Social Media Managers

        $this->actingAs($free)->get("/calendars/{$other->id}")->assertOk();
        $this->actingAs($ops)->get("/calendars/{$other->id}")->assertOk();
    }

    public function test_internal_brand_skips_the_customer(): void
    {
        $this->mine->update(['is_internal' => true]);
        $calendar = $this->calendar($this->mine, CalendarStatus::Submitted);
        $item = $calendar->items()->create(['post_date' => '2026-11-05', 'post_types' => ['instagram_post']]);
        User::factory()->create(['role' => Role::GraphicDesigner]);
        $ops = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($ops)->post("/calendars/{$calendar->id}/review", ['action' => 'approved'])
            ->assertSessionHas('status', 'Calendar approved (internal brand). Tasks assigned.');
        $this->assertSame(CalendarStatus::ClientApproved, $calendar->fresh()->status);
        $this->assertNotNull($item->fresh()->task);

        $content = Content::forceCreate(['task_id' => $item->fresh()->task->id, 'post_type' => 'post', 'approver_id' => $this->smm->id, 'status' => ContentStatus::Submitted, 'current_version' => 1]);
        $content->versions()->create(['number' => 1, 'submitted_by' => $this->smm->id]);
        $this->actingAs($this->smm)->post(route('contents.review', $content), ['action' => 'approved']);
        $this->assertSame(ContentStatus::Approved, $content->fresh()->status);
    }
}
