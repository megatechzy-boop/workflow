<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\MetaAudience;
use App\Models\MetaConnection;
use App\Models\MetaLead;
use App\Support\MetaAudiences;
use App\Support\MetaGraph;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Meta ads > Audiences: saved audiences per client, picked in New ad */
class AudienceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authorizeUser($request);
        $clients = Client::readyForAds($user);

        return view('ads.audiences', [
            'clients' => $clients,
            'audiences' => MetaAudience::with('client', 'source')->whereIn('client_id', $clients->pluck('id'))->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->authorizeUser($request);
        $data = $request->validate([
            'client_id' => ['required', Rule::in(Client::readyForAds($user)->pluck('id')->all())],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(MetaAudience::TYPES))],
            'days' => ['nullable', 'required_if:type,page,instagram,website', 'integer', 'min:1', 'max:365'],
            'lookalike_percent' => ['nullable', 'required_if:type,lookalike', 'integer', 'min:1', 'max:10'],
            'source_id' => ['nullable', 'required_if:type,lookalike', 'exists:meta_audiences,id'],
            'contacts' => ['nullable', 'string', 'max:500000'],
            'with_leads' => ['nullable', 'boolean'],
        ]);
        $client = Client::findOrFail($data['client_id']);
        abort_if(isset($data['source_id']) && MetaAudience::find($data['source_id'])->client_id !== $client->id, 422);

        $contacts = [];
        if ($data['type'] === 'list') {
            $contacts = preg_split('/[\r\n,;]+/', (string) ($data['contacts'] ?? ''));
            if ($request->boolean('with_leads')) {
                MetaLead::where('client_id', $client->id)->get()->each(function (MetaLead $l) use (&$contacts) {
                    array_push($contacts, (string) $l->phone, (string) $l->email);
                });
            }
            if (! MetaAudiences::hashed($contacts)) {
                return back()->withInput()->withErrors(['contacts' => 'Add at least one phone number or email.']);
            }
        }

        $audience = MetaAudience::create(['meta_ad_account_id' => $client->meta_ad_account_id, 'created_by' => $user->id]
            + collect($data)->only(['client_id', 'name', 'type', 'days', 'lookalike_percent', 'source_id'])->all());
        try {
            MetaAudiences::create($audience, $contacts);
        } catch (Throwable $e) {
            $audience->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);

            return back()->with('status', 'Meta did not accept the audience: '.$e->getMessage());
        }

        return back()->with('status', 'Audience ready. Meta fills it within a few hours; pick it in New ad.');
    }

    public function destroy(Request $request, MetaAudience $audience): RedirectResponse
    {
        $user = $this->authorizeUser($request);
        abort_unless($user->canSeeBrand($audience->client_id), 403);
        if ($audience->audience_id && ($token = MetaConnection::token())) {
            try {
                MetaGraph::delete("/{$audience->audience_id}", $token);
            } catch (Throwable $e) {
                report($e); // already gone on Meta: still remove it here
            }
        }
        $audience->delete();

        return back()->with('status', 'Audience deleted.');
    }

    private function authorizeUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent), 403);

        return $user;
    }
}
