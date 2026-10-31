<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Client;
use App\Models\MetaComment;
use App\Models\MetaLead;
use App\Support\MetaGraph;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Facebook / Instagram comments (answered from here) and lead-form leads, synced by meta:sync */
class MetaInboxController extends Controller
{
    public function comments(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent), 403);
        $open = $request->query('show') !== 'all';

        return view('meta.comments', ['open' => $open, 'comments' => MetaComment::with('metaPost.page', 'replier')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))
            ->when($open, fn ($q) => $q->whereNull('replied_at'))
            ->latest('commented_at')->paginate(30)->withQueryString(),
            'clients' => Client::pluck('name', 'id')]);
    }

    public function reply(Request $request, MetaComment $comment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent) && $user->canSeeBrand($comment->client_id), 403);
        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);

        try {
            MetaGraph::replyToComment($comment->metaPost->page, $comment->platform, $comment->comment_id, $data['reply']);
        } catch (Throwable $e) {
            return back()->withErrors(['reply' => 'Meta: '.$e->getMessage()]);
        }
        $comment->update(['reply' => $data['reply'], 'replied_at' => now(), 'replied_by' => $user->id]);

        return back()->with('status', 'Reply posted.');
    }

    public function leads(Request $request): View
    {
        $user = $request->user();
        $query = $this->leadQuery($user);
        $clientIds = (clone $query)->distinct()->pluck('client_id');

        return view('meta.leads', [
            'leads' => $query->with('client', 'page')
                ->when($request->query('client'), fn ($q, $id) => $q->where('client_id', $id))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('lead_at')->paginate(50)->withQueryString(),
            'clients' => Client::whereIn('id', $clientIds)->orderBy('name')->pluck('name', 'id'),
            'isCustomer' => $user->role === Role::Client,
        ]);
    }

    public function updateLead(Request $request, MetaLead $lead): RedirectResponse
    {
        abort_unless($this->leadQuery($request->user())->whereKey($lead->id)->exists(), 403);
        $lead->update($request->validate(['status' => ['required', Rule::in(array_keys(MetaLead::STATUSES))]]));

        return back();
    }

    /** Customer: own brand only. Team: publishers / client managers, within their brands. */
    private function leadQuery($user)
    {
        if ($user->role === Role::Client) {
            return MetaLead::where('client_id', $user->client_id ?? 0);
        }
        abort_unless($user->hasPermission(Permission::PublishContent) || $user->hasPermission(Permission::ManageClients), 403);

        return MetaLead::query()->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids));
    }
}
