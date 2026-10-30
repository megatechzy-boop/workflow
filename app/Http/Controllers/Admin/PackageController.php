<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin: monthly packages (price, platforms, posts per format, services) */
class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', ['packages' => Package::withCount('clients')->orderBy('price')->get()]);
    }

    public function create(): View
    {
        return view('admin.packages.form', ['package' => new Package]);
    }

    public function store(Request $request): RedirectResponse
    {
        Package::create($this->validated($request));

        return redirect()->route('admin.packages.index')->with('status', 'Package added.');
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', compact('package'));
    }

    public function update(Request $request, Package $package): RedirectResponse
    {
        $oldName = $package->name;
        $package->update($this->validated($request));
        $changed = $this->refreshUnpaidBills($package, $oldName);

        return redirect()->route('admin.packages.index')
            ->with('status', 'Package updated.'.($changed ? " {$changed} unpaid bill(s) now use the new price." : ''));
    }

    /** Monthly bills of this package with no payment yet follow the current name and price */
    private function refreshUnpaidBills(Package $package, string $oldName): int
    {
        $bills = Invoice::whereNotNull('month')->whereNull('paid_at')->doesntHave('payments')
            ->whereIn('package_name', [$oldName, $package->name])
            ->whereHas('client', fn ($q) => $q->where('package_id', $package->id))
            ->get();

        foreach ($bills as $bill) {
            $bill->items = [['name' => $package->name.' package', 'detail' => $bill->month->format('F Y'), 'qty' => 1, 'rate' => $package->price]];
            $bill->package_name = $package->name;
            $bill->amount = $bill->subtotal() + $bill->gstAmount();
            $bill->save();
        }

        return $bills->count();
    }

    public function destroy(Package $package): RedirectResponse
    {
        if ($package->clients()->exists()) {
            return back()->with('status', 'Move its clients to another package first, or mark it inactive.');
        }
        $package->delete();

        return redirect()->route('admin.packages.index')->with('status', 'Package deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:10000000'],
            'platforms' => ['array'],
            'platforms.*' => [Rule::enum(Platform::class)],
            'static_posts' => ['required', 'integer', 'min:0', 'max:500'],
            'carousel_posts' => ['required', 'integer', 'min:0', 'max:500'],
            'reels' => ['required', 'integer', 'min:0', 'max:500'],
            'services' => ['nullable', 'string', 'max:5000'],
            'min_months' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);
        $data['platforms'] = array_values($data['platforms'] ?? []);
        // One service per line
        $data['services'] = array_values(array_filter(array_map('trim', preg_split('/\R/', $data['services'] ?? ''))));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
