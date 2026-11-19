<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuickPostTest extends TestCase
{
    use RefreshDatabase;

    private User $smm;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-11-19 10:00');
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->client = Client::create(['name' => 'Acme Hall']);
    }

    private function give(): void
    {
        DB::table('user_permissions')->insert(['user_id' => $this->smm->id, 'permission' => 'quick_post']);
        $this->smm = $this->smm->fresh();
    }

    private function send(array $extra = [])
    {
        return $this->actingAs($this->smm)->post(route('contents.quickStore'), $extra + [
            'client_id' => $this->client->id, 'post_type' => 'carousel', 'platforms' => ['instagram', 'facebook'], 'post_date' => '2026-11-22',
            'title' => 'Wedding season offer', 'caption' => 'Book the hall for your wedding.', 'hashtags' => '#solapur',
            'images' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')],
        ]);
    }

    public function test_needs_the_admin_given_permission(): void
    {
        $this->actingAs($this->smm)->get(route('contents.quick'))->assertForbidden();
        $this->send()->assertForbidden();
        $this->get(route('calendars.index'))->assertDontSee('+ Quick post');

        $this->give();
        $this->actingAs($this->smm)->get(route('calendars.index'))->assertSee('+ Quick post');
        $this->get(route('contents.quick'))->assertOk()->assertSee('Acme Hall');
    }

    public function test_quick_post_is_approved_at_once_without_a_customer_step(): void
    {
        $this->give();
        $this->send()->assertRedirect();

        $content = Content::sole();
        $this->assertSame([ContentStatus::Approved, 'carousel', $this->smm->id], [$content->status, $content->post_type, $content->approver_id]);
        $version = $content->versions()->sole();
        $this->assertSame(['Book the hall for your wedding.', 2], [$version->caption, $version->media()->count()]);

        $item = $content->task->item;
        $this->assertTrue($item->is_quick);
        $this->assertSame(['instagram_carousel', 'facebook_carousel'], $item->post_types);
        $this->assertSame([CalendarStatus::ClientApproved, '2026-11'], [$item->calendar->status, $item->calendar->month->format('Y-m')]);
        $this->actingAs($this->smm)->get(route('contents.show', $content))->assertSee('Mark Scheduled')->assertSee('Quick post (no calendar, no Customer approval)'); // FB / IG publish shows once the brand has a Page
        $this->get(route('calendars.show', $item->calendar))->assertSee('Quick post')->assertSee('Wedding season offer');
    }

    public function test_joins_the_existing_month_calendar_and_checks_input(): void
    {
        $this->give();
        $calendar = Calendar::create(['client_id' => $this->client->id, 'month' => '2026-11-01', 'status' => CalendarStatus::ClientApproved, 'created_by' => $this->smm->id]);
        $this->send()->assertSessionHasNoErrors();
        $this->assertSame(1, Calendar::count());
        $this->assertSame($calendar->id, Content::sole()->task->item->calendar_id);

        $this->send(['caption' => ''])->assertSessionHasErrors('caption');
        $this->send(['post_date' => '2026-11-01'])->assertSessionHasErrors('post_date');
        $this->send(['post_type' => 'reel'])->assertSessionHasErrors('video');
    }
}
