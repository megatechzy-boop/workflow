<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetRenameTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $customer;

    private User $smm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = Client::create(['name' => 'Acme']);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->client->id]);
        $this->smm = User::factory()->create(['role' => Role::SocialMediaManager]);
    }

    private function asset(User $by, string $name = 'IMG_2041.JPG'): Asset
    {
        return Asset::create(['client_id' => $this->client->id, 'uploaded_by' => $by->id, 'path' => 'assets/x.jpg',
            'original_name' => $name, 'mime' => 'image/jpeg', 'size' => 1000]);
    }

    public function test_rename_keeps_the_extension(): void
    {
        $asset = $this->asset($this->customer);
        $this->actingAs($this->smm)->get(route('assets.index', ['client' => $this->client->id]))->assertSee('Rename');

        $this->patch(route('assets.rename', $asset), ['name' => 'Diwali poster'])->assertSessionHas('status');
        $this->assertSame('Diwali poster.JPG', $asset->fresh()->original_name);

        $this->patch(route('assets.rename', $asset), ['name' => 'Shop front.jpg']); // typed with the extension: not twice
        $this->assertSame('Shop front.JPG', $asset->fresh()->original_name);

        $this->patch(route('assets.rename', $asset), ['name' => 'a/b\\c:d?']); // characters a file name cannot hold
        $this->assertSame('a b c d.JPG', $asset->fresh()->original_name);
        $this->patch(route('assets.rename', $asset), ['name' => '   '])->assertSessionHasErrors('name');
    }

    public function test_customer_renames_only_their_own_files(): void
    {
        $mine = $this->asset($this->customer);
        $teams = $this->asset($this->smm);
        $this->actingAs($this->customer)->patch(route('assets.rename', $mine), ['name' => 'My logo'])->assertSessionHas('status');
        $this->patch(route('assets.rename', $teams), ['name' => 'x'])->assertForbidden();

        $other = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'Other'])->id]);
        $this->actingAs($other)->patch(route('assets.rename', $mine), ['name' => 'x'])->assertForbidden();
    }
}
