<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarMonthTabsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-19 12:00');
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        foreach ([['Alpha Yoga', '2026-10-01', CalendarStatus::ClientApproved], ['Beta Hall', '2026-11-01', CalendarStatus::ClientApproved],
            ['Gamma Clinic', '2026-11-01', CalendarStatus::Submitted], ['Delta Gym', '2026-12-01', CalendarStatus::ClientApproved]] as [$name, $month, $status]) {
            Calendar::create(['client_id' => Client::create(['name' => $name])->id, 'month' => $month, 'status' => $status, 'created_by' => $this->admin->id]);
        }
    }

    /** the calendar's Open link: client names also sit in the filter dropdown */
    private function link(string $name): string
    {
        return route('calendars.show', Calendar::whereHas('client', fn ($q) => $q->where('name', $name))->sole());
    }

    public function test_opens_on_the_coming_month_with_a_tab_per_month(): void
    {
        $this->actingAs($this->admin)->get('/calendars')->assertOk()
            ->assertSeeInOrder(['Dec 2026', 'Nov 2026', 'Oct 2026', 'All'])
            ->assertSee($this->link('Delta Gym'), false)->assertDontSee($this->link('Beta Hall'), false)->assertDontSee($this->link('Alpha Yoga'), false);
    }

    public function test_other_month_and_all(): void
    {
        $this->actingAs($this->admin)->get('/calendars?month=2026-11')->assertSee($this->link('Beta Hall'), false)->assertSee($this->link('Gamma Clinic'), false)->assertDontSee($this->link('Delta Gym'), false)
            ->assertSee('Waiting for approval'); // November has a calendar waiting: its tab gets the dot
        $this->get('/calendars?month=all')->assertSee($this->link('Alpha Yoga'), false)->assertSee($this->link('Delta Gym'), false);
        $this->get('/calendars?month=2025-01')->assertSee($this->link('Delta Gym'), false); // unknown month: back to the default
    }

    public function test_falls_back_to_the_newest_month(): void
    {
        $this->travelTo('2027-05-10');
        $this->actingAs($this->admin)->get('/calendars')->assertSee($this->link('Delta Gym'), false)->assertDontSee($this->link('Beta Hall'), false);
    }
}
