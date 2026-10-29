<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->with('package')
            ->withCount('users')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.form', ['client' => new Client, 'packages' => $this->packages()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Client::create($this->validated($request));

        return redirect()->route('clients.index')->with('status', 'Client added.');
    }

    public function edit(Client $client): View
    {
        return view('clients.form', ['client' => $client, 'packages' => $this->packages($client)]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request));

        return redirect()->route('clients.index')->with('status', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('status', 'Client deleted.');
    }

    /** Active packages, plus the client's current one even if it was made inactive */
    private function packages(?Client $client = null)
    {
        return Package::where('is_active', true)->orWhere('id', $client?->package_id)->orderBy('price')->get();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'package_start' => ['nullable', 'date'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
