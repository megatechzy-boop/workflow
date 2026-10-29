<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
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
        $package->update($this->validated($request));

        return redirect()->route('admin.packages.index')->with('status', 'Package updated.');
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
