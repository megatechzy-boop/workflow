<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function enquiry(array $override = []): array
    {
        return $override + [
            'name' => 'Rohit', 'business' => 'Zen Clinic', 'phone' => '98765 43210',
            'needs' => ['Social Media Marketing', 'SEO'], 'goal' => 'More patients', 'budget' => '₹50,000+',
        ];
    }

    public function test_public_enquiry_creates_lead_and_notifies_ops(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);

        $this->get(route('enquiry.form'))->assertOk()->assertSee('What do you need help with?');
        $this->post(route('enquiry.submit'), $this->enquiry())->assertRedirect(route('enquiry.thanks'));

        $lead = Lead::sole();
        $this->assertSame('new', $lead->status);
        $this->assertSame('919876543210', $lead->phoneDigits());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $designer->notifications()->count());
    }

    public function test_enquiry_needs_phone_and_ignores_bots(): void
    {
        $this->post(route('enquiry.submit'), $this->enquiry(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->post(route('enquiry.submit'), $this->enquiry(['website' => 'http://spam']))->assertRedirect(route('enquiry.thanks'));
        $this->assertSame(0, Lead::count());
    }

    public function test_leads_page_and_convert_to_client(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $lead = Lead::create($this->enquiry());

        $this->actingAs($smm)->get(route('leads.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('leads.index'))->assertOk()->assertSee('Zen Clinic');

        $this->actingAs($manager)->post(route('leads.convert', $lead))->assertRedirect();
        $this->assertSame('won', $lead->fresh()->status);
        $this->assertSame('Zen Clinic', $lead->fresh()->client->name);
    }

    public function test_onboarding_link_form_and_brief(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        $smm = User::factory()->create(['role' => Role::SocialMediaManager]);
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $client = Client::create(['name' => 'Acme']);

        $this->actingAs($manager)->post(route('clients.onboardingLink', $client));
        $token = $client->fresh()->onboarding_token;
        $this->assertSame(32, strlen($token));

        auth()->logout();
        $this->get(route('onboarding.form', 'wrong-token'))->assertNotFound();
        $this->get(route('onboarding.form', $token))->assertOk()->assertSee('Welcome, <strong>Acme</strong>', false);
        $this->post(route('onboarding.submit', $token), ['goals' => 'Double sales'])->assertSessionHasErrors('consent');
        $this->post(route('onboarding.submit', $token), [
            'goals' => 'Double sales', 'priority' => ['Generate Leads'], 'industry' => 'Healthcare', 'hc_format' => 'Online', 'consent' => '1',
        ])->assertRedirect(route('onboarding.thanks'));

        $client->refresh();
        $this->assertNotNull($client->onboarded_at);
        $this->assertSame(['Generate Leads'], $client->onboarding['priority']);
        $this->assertArrayNotHasKey('consent', $client->onboarding);

        $this->actingAs($smm)->get(route('clients.brief', $client))->assertOk()->assertSee('Double sales')->assertSee('Industry: Healthcare');
        $this->actingAs($designer)->get(route('clients.brief', $client))->assertForbidden();
    }
}
