<?php

namespace Tests\Feature;

use App\Enums\CalendarStatus;
use App\Enums\Role;
use App\Models\Calendar;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClearWorkDataTest extends TestCase
{
    use RefreshDatabase;

    private function seedWork(): void
    {
        $client = Client::create(['name' => 'Acme']);
        User::factory()->create(['role' => Role::Client, 'client_id' => $client->id]);
        $manager = User::factory()->create(['role' => Role::Manager]);
        Calendar::create([
            'client_id' => $client->id, 'month' => '2026-11-01',
            'created_by' => $manager->id, 'status' => CalendarStatus::ClientApproved,
        ]);
        Storage::disk('local')->put('contents/1/v1/a.jpg', 'x');
        Storage::disk('local')->put('chat/team/b.jpg', 'x');
    }

    public function test_deletes_work_data_and_keeps_users_and_clients(): void
    {
        Storage::fake('local');
        $this->seedWork();

        $this->artisan('data:clear-work --force')->assertSuccessful();

        $this->assertSame(0, Calendar::count());
        $this->assertSame(2, User::count());
        $this->assertSame(1, Client::count());
        $this->assertNotNull(User::where('role', Role::Client)->value('client_id'));
        Storage::disk('local')->assertMissing(['contents/1/v1/a.jpg', 'chat/team/b.jpg']);
    }

    public function test_needs_force_and_runs_only_once(): void
    {
        Storage::fake('local');
        $this->seedWork();

        $this->artisan('data:clear-work')->assertFailed();
        $this->assertSame(1, Calendar::count());

        $this->artisan('data:clear-work --force')->assertSuccessful();
        $this->seedWork(); // real data added after go-live
        $this->artisan('data:clear-work --force')->assertFailed();
        $this->assertSame(1, Calendar::count());
    }
}
