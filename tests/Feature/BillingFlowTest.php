<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\Package;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Client $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-01 09:00:00');
        Notification::fake();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $account = MetaAdAccount::create(['account_id' => 'act_55', 'name' => 'Acme Ads', 'currency' => 'INR']);
        $this->acme = Client::create(['name' => 'Acme', 'package_id' => Package::firstWhere('name', 'Growth')->id, 'meta_ad_account_id' => $account->id]);
        $this->customer = User::factory()->create(['role' => Role::Client, 'client_id' => $this->acme->id]);
    }

    /** November bill A, sent, ₹15,000, ₹5,000 paid */
    private function novemberBill(): Invoice
    {
        $bill = Invoice::create(['client_id' => $this->acme->id, 'month' => '2026-11-01', 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-05',
            'sent_at' => '2026-11-01 10:00:00', 'items' => [['name' => 'Growth package', 'qty' => 1, 'rate' => 15000, 'kind' => 'package']]]);
        $bill->payments()->create(['date' => '2026-11-04', 'method' => 'UPI', 'amount' => 5000]);

        return $bill->fresh();
    }

    public function test_first_of_month_bill_has_package_ad_spend_and_previous_balance_and_waits_for_send(): void
    {
        $november = $this->novemberBill(); // made before GST was on
        config(['business.gst_rate' => 18]);
        MetaConnection::create(['user_token' => 'user-token']);
        Http::fake(['*/act_55/insights*' => Http::response(['data' => [['spend' => '4210.60']]])]);

        $this->artisan('billing:monthly')->assertSuccessful();
        $this->artisan('billing:monthly'); // once a month only

        $december = Invoice::whereDate('month', '2026-12-01')->sole();
        $this->assertSame([
            ['name' => 'Growth package', 'detail' => 'December 2026', 'qty' => 1, 'rate' => 15000, 'kind' => 'package'],
            ['name' => 'Meta ads spend', 'detail' => 'November 2026', 'qty' => 1, 'rate' => 4211, 'kind' => 'ads'],
            ['name' => 'Previous balance', 'detail' => $november->number.' (Nov 2026)', 'qty' => 1, 'rate' => 10000, 'kind' => 'carry', 'from' => $november->id],
        ], $december->items);
        // GST only on the new work: (15,000 + 4,211) × 18% = 3,458
        $this->assertSame(15000 + 4211 + 10000 + 3458, $december->amount);
        $this->assertNull($december->sent_at);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/act_55/insights') && str_contains(urldecode($r->url()), '"since":"2026-11-01","until":"2026-11-30"'));

        // The Admin is told; the Customer sees nothing new until it is sent, and November stays open meanwhile
        Notification::assertSentTo($this->admin, WorkflowNotification::class, fn ($n) => $n->title === 'Bills ready to send');
        Notification::assertNotSentTo($this->customer, WorkflowNotification::class);
        $this->actingAs($this->customer)->get(route('my.invoice', $december))->assertNotFound();
        $this->actingAs($this->customer)->get(route('dashboard'))->assertDontSee($december->number)->assertSee('Overdue');
        $this->actingAs($this->admin)->get(route('admin.billing.index', ['month' => '2026-12']))
            ->assertSee('Not sent')->assertSee('Send all 1')->assertSee('Edit bill')
            ->assertSeeInOrder(['Billed', '₹22,669', 'Received', 'Pending', '₹22,669'], false); // totals: new work + GST; the previous balance is still open on November

        // A payment on November before sending: the previous balance line follows it
        $this->actingAs($this->admin)->post(route('admin.billing.pay', $november), ['amount' => 2000, 'date' => '2026-12-01', 'method' => 'UPI']);
        $this->actingAs($this->admin)->post(route('admin.billing.sendAll', ['month' => '2026-12']))->assertSessionHas('status', '1 bill(s) sent.');
        $december->refresh();
        $this->assertSame(8000, $december->items[2]['rate']);
        $this->assertSame(15000 + 4211 + 8000 + 3458, $december->amount);

        // November is moved now: no payments there, the Customer pays December
        $november->refresh();
        $this->assertSame('moved', $november->state());
        $this->assertSame(0, $november->open());
        $this->actingAs($this->admin)->post(route('admin.billing.pay', $november), ['amount' => 100, 'date' => '2026-12-01', 'method' => 'UPI'])
            ->assertSessionHas('status', "This balance moved to {$december->number}: record the payment there.");
        $this->assertSame(2, $november->payments()->count());
        $this->actingAs($this->customer)->get(route('dashboard'))->assertSee($december->number)->assertSee('Moved to '.$december->number);
        $this->actingAs($this->customer)->get(route('my.invoice', $november))->assertSee('BALANCE MOVED TO '.$december->number);
    }

    public function test_admin_edits_adds_ad_spend_and_sends(): void
    {
        $november = $this->novemberBill();
        $this->actingAs($this->admin)->post(route('admin.billing.generate', ['month' => '2026-12']));
        $december = Invoice::whereDate('month', '2026-12-01')->sole();
        $this->assertCount(2, $december->items); // no Meta connection: no ad spend line

        $this->actingAs($this->admin)->get(route('admin.billing.edit', $december))->assertOk()
            ->assertSee('+ Meta ads spend (November 2026)')->assertSee('Previous balance');

        // Ad spend added, previous balance taken out (it stays on November), then saved and sent
        $this->actingAs($this->admin)->put(route('admin.billing.update', $december), [
            'invoice_date' => '2026-12-01', 'due_date' => '2026-12-05', 'send' => 1,
            'items' => [
                ['name' => 'Growth package', 'detail' => 'December 2026', 'qty' => 1, 'rate' => 15000, 'kind' => 'package'],
                ['name' => 'Meta ads spend', 'detail' => 'November 2026', 'qty' => 1, 'rate' => 3000, 'kind' => 'ads'],
                ['name' => '', 'rate' => ''],
            ],
        ])->assertRedirect()->assertSessionHas('status', "Invoice {$december->number} saved and sent.");

        $december->refresh();
        $this->assertSame(18000, $december->amount);
        $this->assertNotNull($december->sent_at);
        $this->assertNull($november->fresh()->carried_to_id); // open again
        Notification::assertSentTo($this->customer, WorkflowNotification::class,
            fn ($n) => $n->title === 'New bill '.$december->number && $n->message === '₹18,000 for December 2026, due 05 Dec. Pay by UPI or bank transfer.');
        $this->actingAs($this->customer)->get(route('my.invoice', $december))->assertOk()->assertSee('Meta ads spend');

        // Sent: the lines are locked
        $this->actingAs($this->admin)->get(route('admin.billing.edit', $december))->assertRedirect();
        $this->actingAs($this->admin)->put(route('admin.billing.update', $december), ['invoice_date' => '2026-12-01', 'due_date' => '2026-12-05', 'items' => [['name' => 'X', 'rate' => 1]]])
            ->assertStatus(422);
    }

    public function test_deleting_the_new_bill_gives_the_balance_back(): void
    {
        $november = $this->novemberBill();
        $this->actingAs($this->admin)->post(route('admin.billing.generate', ['month' => '2026-12']));
        $december = Invoice::whereDate('month', '2026-12-01')->sole();
        $this->actingAs($this->admin)->post(route('admin.billing.send', $december));

        $this->actingAs($this->admin)->delete(route('admin.billing.destroy', $november))
            ->assertSessionHas('status', "Its balance is in {$december->number}: delete that bill first.");
        $this->actingAs($this->admin)->delete(route('admin.billing.destroy', $december));
        $this->assertNull($november->fresh()->carried_to_id);
        $this->assertSame('overdue', $november->fresh()->state());
    }

    public function test_reminders_two_days_before_on_the_day_then_every_three_days(): void
    {
        $bill = Invoice::create(['client_id' => $this->acme->id, 'invoice_date' => '2026-12-01', 'due_date' => '2026-12-05', 'sent_at' => now(),
            'items' => [['name' => 'Growth package', 'qty' => 1, 'rate' => 15000]]]);
        Invoice::create(['client_id' => $this->acme->id, 'invoice_date' => '2026-12-01', 'due_date' => '2026-12-05', // not sent: never reminded
            'items' => [['name' => 'Extra', 'qty' => 1, 'rate' => 500]]]);

        $remindedOn = [];
        foreach (range(1, 12) as $day) {
            Carbon::setTestNow("2026-12-$day 10:00:00");
            if (\App\Support\Billing::remind()) {
                $remindedOn[] = $day;
            }
        }
        $this->assertSame([3, 5, 8, 11], $remindedOn);
        Notification::assertSentTo($this->customer, WorkflowNotification::class,
            fn ($n) => $n->title === 'Payment reminder: '.$bill->number && $n->message === '₹15,000 was due on 05 Dec (3 days ago). Please ignore this if you have already paid.');
        Notification::assertSentTo($this->admin, WorkflowNotification::class,
            fn ($n) => $n->title === 'Payment reminders sent' && str_contains($n->message, '1 overdue: Acme'));

        // Paid: no more reminders
        $bill->payments()->create(['date' => '2026-12-12', 'method' => 'UPI', 'amount' => 15000]);
        $bill->syncPaid();
        Carbon::setTestNow('2026-12-14 10:00:00');
        $this->assertSame(0, \App\Support\Billing::remind());
    }

    public function test_one_off_invoice_can_be_saved_then_sent(): void
    {
        $this->actingAs($this->admin)->post(route('admin.billing.store'), [
            'client_id' => $this->acme->id, 'invoice_date' => '2026-12-01', 'due_date' => '2026-12-10',
            'items' => [['name' => 'Website renewal (yearly)', 'qty' => 1, 'rate' => 6000]],
        ]);
        $invoice = Invoice::sole();
        $this->assertNull($invoice->sent_at);
        Notification::assertNothingSent();

        $this->actingAs($this->admin)->post(route('admin.billing.send', $invoice))->assertSessionHas('status', "Invoice {$invoice->number} sent to the customer.");
        $this->assertNotNull($invoice->fresh()->sent_at);
        Notification::assertSentTo($this->customer, WorkflowNotification::class, fn ($n) => $n->title === 'New bill '.$invoice->number);
    }

    public function test_package_price_change_keeps_the_other_lines(): void
    {
        $bill = Invoice::create(['client_id' => $this->acme->id, 'month' => '2026-12-01', 'invoice_date' => '2026-12-01', 'due_date' => '2026-12-05', 'package_name' => 'Growth',
            'items' => [['name' => 'Growth package', 'detail' => 'December 2026', 'qty' => 1, 'rate' => 15000, 'kind' => 'package'],
                ['name' => 'Meta ads spend', 'detail' => 'November 2026', 'qty' => 1, 'rate' => 3000, 'kind' => 'ads']]]);
        $growth = Package::firstWhere('name', 'Growth');
        $this->actingAs($this->admin)->put(route('admin.packages.update', $growth), [
            'name' => 'Growth', 'price' => 18000, 'platforms' => $growth->platforms, 'static_posts' => 6,
            'carousel_posts' => 6, 'reels' => 6, 'services' => implode("\n", $growth->services), 'is_active' => 1,
        ]);

        $this->assertSame(21000, $bill->fresh()->amount);
        $this->assertSame('Meta ads spend', $bill->fresh()->items[1]['name']);
    }
}
