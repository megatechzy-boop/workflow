<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\SalaryPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-29 10:00:00');
        $this->admin = User::factory()->create(['role' => Role::Admin]);
    }

    public function test_team_checks_in_and_out_customer_cannot(): void
    {
        $designer = User::factory()->create(['role' => Role::GraphicDesigner]);
        $this->actingAs($designer)->post(route('attendance.checkIn'))->assertRedirect();
        $this->actingAs($designer)->post(route('attendance.checkOut'))->assertRedirect();

        $day = Attendance::firstWhere('user_id', $designer->id);
        $this->assertSame('present', $day->status);
        $this->assertNotNull($day->check_out_at);

        $customer = User::factory()->create(['role' => Role::Client, 'client_id' => Client::create(['name' => 'A'])->id]);
        $this->actingAs($customer)->post(route('attendance.checkIn'))->assertForbidden();
    }

    public function test_office_pages_are_admin_only(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);
        foreach (['admin.attendance.index', 'admin.salary.index', 'admin.billing.index', 'admin.expenses.index'] as $route) {
            $this->actingAs($manager)->get(route($route))->assertForbidden();
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
    }

    public function test_salary_follows_attendance(): void
    {
        $editor = User::factory()->create(['role' => Role::VideoEditor, 'monthly_salary' => 27000]);
        // October 2026: 27 working days (4 Sundays). 20 present + 2 half days = 21 paid days
        $day = Carbon::parse('2026-10-01');
        $made = 0;
        while ($made < 22) {
            if (! $day->isSunday()) {
                Attendance::create(['user_id' => $editor->id, 'date' => $day->toDateString(), 'status' => $made < 20 ? 'present' : 'half_day']);
                $made++;
            }
            $day->addDay();
        }

        $this->actingAs($this->admin)->get(route('admin.salary.index', ['month' => '2026-10']))->assertSee('21/27')->assertSee('₹21,000');
        $this->actingAs($this->admin)->post(route('admin.salary.pay', [$editor, 'month' => '2026-10']))->assertRedirect();
        $this->assertSame(21000, SalaryPayment::firstWhere('user_id', $editor->id)->amount);
    }

    public function test_bills_come_from_package_price_once_per_month(): void
    {
        Client::create(['name' => 'Acme', 'package_id' => Package::firstWhere('name', 'Growth')->id]);
        Client::create(['name' => 'No package']);

        $this->actingAs($this->admin)->post(route('admin.billing.generate', ['month' => '2026-11']));
        $this->actingAs($this->admin)->post(route('admin.billing.generate', ['month' => '2026-11']));

        $invoice = Invoice::sole();
        $this->assertSame(15000, $invoice->amount);
        $this->assertSame('2026-11-05', $invoice->due_date->toDateString());

        $this->assertSame('A00324', $invoice->number);
    }

    public function test_part_payments_give_receipts_and_settle_the_invoice(): void
    {
        $client = Client::create(['name' => 'Katare Mills', 'address' => 'Akkalkot Road, Solapur']);
        $this->actingAs($this->admin)->post(route('admin.billing.store'), [
            'client_id' => $client->id, 'invoice_date' => '2026-10-29', 'due_date' => '2026-11-05',
            'items' => [['name' => 'Website update yearly', 'detail' => '2026-27', 'qty' => 1, 'rate' => 8000], ['name' => '', 'rate' => '']],
        ])->assertRedirect();
        $invoice = Invoice::sole();
        $this->assertSame(8000, $invoice->amount);

        $pay = fn ($amount) => $this->actingAs($this->admin)->post(route('admin.billing.pay', $invoice), ['amount' => $amount, 'date' => '2026-10-30', 'method' => 'UPI']);
        $pay(5000);
        $this->assertNull($invoice->fresh()->paid_at);
        $pay(9000)->assertSessionHasErrors('amount'); // more than what is due
        $pay(3000);
        $this->assertNotNull($invoice->fresh()->paid_at);
        $this->assertSame(['A00039', 'A00040'], $invoice->payments()->pluck('number')->all());

        $this->actingAs($this->admin)->get(route('admin.billing.show', $invoice))
            ->assertOk()->assertSee('A00324')->assertSee('Akkalkot Road')->assertSee('EIGHT THOUSAND RUPEES ONLY')->assertSee('PAID');
        $this->actingAs($this->admin)->get(route('admin.billing.receipt', $invoice->payments()->first()))
            ->assertOk()->assertSee('Payment Receipt')->assertSee('Five Thousand Rupees Only');
    }

    public function test_profit_is_received_minus_salaries_minus_expenses(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $invoice = Invoice::create(['client_id' => $client->id, 'due_date' => '2026-10-05', 'items' => [['name' => 'Scale', 'qty' => 1, 'rate' => 35000]]]);
        $invoice->payments()->create(['date' => '2026-10-06', 'method' => 'UPI', 'amount' => 35000]);
        SalaryPayment::create(['user_id' => $this->admin->id, 'month' => '2026-10-01', 'amount' => 20000, 'paid_at' => now()]);
        $this->actingAs($this->admin)->post(route('admin.expenses.store'), ['date' => '2026-10-10', 'title' => 'Rent', 'category' => 'rent', 'amount' => 12000]);

        $this->actingAs($this->admin)->get(route('admin.expenses.index', ['month' => '2026-10']))->assertSee('Rent')->assertSee('₹3,000');
    }
}
