<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** A form from an old tab / a second tap: back to the page with a popup, the status stays */
class StaleFormTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', 'auth'])->group(function () {
            Route::match(['get', 'post'], '_test/forbidden', fn () => abort(403));
            Route::post('_test/locked', fn () => abort(422, 'This bill is already sent.'));
            Route::post('_test/chat', fn () => abort(403));
        });
        Route::middleware('web')->post('_test/expired', fn () => abort(419));
        $this->user = User::factory()->create(['role' => Role::Admin]);
    }

    public function test_form_already_done_goes_back_with_a_popup(): void
    {
        $this->actingAs($this->user)->from('/contents/44')->post('/_test/forbidden')
            ->assertForbidden()
            ->assertSee('<meta http-equiv="refresh" content="0;url='.url('/contents/44').'">', false)
            ->assertSessionHas('popup', 'This could not be done: it was already done, or it changed meanwhile. The page now shows the latest state.');

        $this->actingAs($this->user)->from('/admin/billing')->post('/_test/locked')->assertStatus(422)->assertSessionHas('popup');
        $this->get('/admin/billing')->assertSee('This could not be done'); // the popup shows on the page
    }

    public function test_expired_page_says_try_again(): void
    {
        $this->from('/login')->post('/_test/expired')->assertStatus(419)
            ->assertSessionHas('popup', 'This page was open for too long. Please try again.');
    }

    public function test_upload_gets_json_that_opens_the_page(): void
    {
        $this->actingAs($this->user)->from('/contents/44')->post('/_test/forbidden', [], ['X-Upload-Progress' => '1', 'Accept' => 'application/json'])
            ->assertForbidden()->assertExactJson(['redirect' => url('/contents/44'), 'errors' => true]);
    }

    public function test_opening_a_page_and_script_calls_keep_the_403(): void
    {
        $this->actingAs($this->user)->get('/_test/forbidden')->assertForbidden()->assertSessionMissing('popup');
        $this->actingAs($this->user)->postJson('/_test/chat')->assertForbidden()->assertSessionMissing('popup');
    }
}
