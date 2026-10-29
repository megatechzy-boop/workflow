<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Customer upload alerts, deadline reminders, schedule, dashboard, monthly report, sheet import */
class ProductivityTest extends TestCase
{
    use RefreshDatabase;

    private Client $acme;

    private User $smm;

    private User $designer;

    private User $customer;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-04 10:00');
        $this->acme = Client::create(['name' => 'Acme']);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $this->designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->acme->id]);
        $this->calendar = Calendar::create(['client_id' => $this->acme->id, 'month' => '2026-11-01', 'created_by' => $this->smm->id, 'status' => CalendarStatus::Draft]);
    }

    private function taskOn(string $postDate, string $deadline): Task
    {
        $this->calendar->update(['status' => CalendarStatus::ClientApproved]);

        return $this->calendar->items()->create(['post_date' => $postDate, 'post_types' => ['instagram_post'], 'creative_copy' => 'Diwali offer'])
            ->task()->create(['assigned_to' => $this->designer->id, 'assigned_by' => $this->smm->id, 'deadline' => $deadline]);
    }

    public function test_customer_row_upload_notifies_designer_and_smm(): void
    {
        $task = $this->taskOn('2026-11-08', '2026-11-06');

        $this->actingAs($this->customer)->post('/assets', [
            'client_id' => $this->acme->id, 'calendar_item_id' => $task->calendar_item_id,
            'files' => [UploadedFile::fake()->create('shoot.mp4', 500, 'video/mp4')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Customer sent files', $this->designer->notifications()->first()->data['title']);
        $this->assertSame('Customer sent files', $this->smm->notifications()->first()->data['title']);
    }

    public function test_deadline_reminders_once_a_day(): void
    {
        $this->taskOn('2026-11-07', '2026-11-05'); // due tomorrow
        $this->taskOn('2026-11-06', '2026-11-04'); // due today
        $this->taskOn('2026-11-20', '2026-11-18'); // not yet

        $this->artisan('tasks:remind')->assertSuccessful();
        $this->assertSame(1, $this->designer->notifications()->count()); // one digest, not one per task
        $message = $this->designer->notifications()->first()->data['message'];
        $this->assertStringContainsString('2 task(s)', $message);
        $this->assertStringContainsString('due today', $message);
        $this->assertStringContainsString('due tomorrow', $message);

        $this->artisan('tasks:remind');                 // same day: no duplicate
        $this->assertSame(1, $this->designer->notifications()->count());

        Carbon::setTestNow('2026-11-05 09:00');          // next day: due today
        $this->artisan('tasks:remind');
        $this->assertSame(2, $this->designer->notifications()->count());
    }

    public function test_schedule_dashboard_and_report(): void
    {
        $this->taskOn('2026-11-08', '2026-11-03'); // overdue

        $this->actingAs($this->smm)->get('/schedule?month=2026-11')->assertOk()->assertSee('Acme')->assertSee('Late');
        $this->actingAs($this->designer)->get('/dashboard')->assertOk()->assertSee('My pending tasks')->assertSee('Late');
        $this->actingAs($this->smm)->get('/dashboard')->assertOk()->assertSee('Content to review')->assertSee('Posts this week');

        $this->actingAs($this->smm)->get('/reports/monthly?month=2026-11')->assertOk()->assertSee('Acme')->assertSee('Planned posts');
        $this->actingAs($this->customer)->get('/reports/monthly?month=2026-11')->assertOk();
        $this->actingAs($this->designer)->get('/reports/monthly')->assertForbidden();

        // Customer schedule hides calendars that were not sent to them
        $this->calendar->update(['status' => CalendarStatus::Draft]);
        $this->actingAs($this->customer)->get('/schedule?month=2026-11')->assertOk()->assertDontSee('Post');
    }

    public function test_import_rows_from_sheet_paste(): void
    {
        $paste = "Sr. No.\tDate to post\tPost type\tCreative copy for post\tVisual brief\tBrief about the post\tReference link, if any\tRemarks\n"
            ."1\t11 Nov Wednesday\tLinkedIn Post\t\"Headline: Journey\n\nPost Copy: three decades\"\tIvory tile\tCoaching\t\tHindi\n"
            ."2\t11 Nov Wednesday\tInstagram, Facebook Reel\tUnboxing\t15s cuts\tLaunch\thttps://example.com\t\n";

        $this->actingAs($this->smm)->post("/calendars/{$this->calendar->id}/import", ['rows' => $paste])
            ->assertSessionHasNoErrors()->assertRedirect("/calendars/{$this->calendar->id}");

        $items = $this->calendar->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame(['linkedin_post'], $items[0]->post_types);
        $this->assertSame("Headline: Journey\n\nPost Copy: three decades", $items[0]->creative_copy);
        $this->assertSame(['instagram_reel', 'facebook_reel'], $items[1]->post_types);
        $this->assertSame('2026-11-11', $items[1]->post_date->toDateString());
    }

    public function test_import_is_all_or_nothing_with_clear_errors(): void
    {
        $paste = "5 Nov\tInstagram Post\tok\n"
            ."40 Nov\tInstagram Post\tbad date\n"
            ."6 Nov\tInstagram Post and Reel\ttwo formats\n"
            ."7 Nov\tCarousel\tno platform\n";

        $this->actingAs($this->smm)->post("/calendars/{$this->calendar->id}/import", ['rows' => $paste])
            ->assertSessionHasErrors('rows');
        $this->assertSame(0, $this->calendar->items()->count());
        $this->assertCount(3, session('errors')->get('rows'));

        // Designers cannot import
        $this->actingAs($this->designer)->post("/calendars/{$this->calendar->id}/import", ['rows' => "5 Nov\tInstagram Post\tx"])->assertForbidden();
    }
}
