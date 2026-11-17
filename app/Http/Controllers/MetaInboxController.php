<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Client;
use App\Models\MetaComment;
use App\Models\MetaLead;
use App\Models\MetaLeadRemark;
use App\Support\CaptionAi;
use App\Support\MetaGraph;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    /** AI draft of a comment reply: fills the box only, the SMM checks and posts it */
    public function aiReply(Request $request, MetaComment $comment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission(Permission::PublishContent) && $user->canSeeBrand($comment->client_id), 403);
        if (! config('services.groq.key')) {
            return back()->with('status', 'AI is not set up yet (GROQ_API_KEY in the server .env).');
        }

        try {
            $reply = CaptionAi::commentReply($comment);
        } catch (Throwable $e) {
            report($e);

            return back()->with('status', 'AI could not write a reply: '.\Illuminate\Support\Str::limit($e->getMessage(), 200));
        }

        return back()->withInput(['reply' => $reply, 'ai_comment' => $comment->id])->with('status', 'AI reply ready. Check it, edit if needed, then Reply.');
    }

    public function leads(Request $request): View
    {
        $user = $request->user();
        $clientIds = $this->leadQuery($user)->distinct()->pluck('client_id');

        return view('meta.leads', [
            'leads' => $this->filtered($request)->with('client', 'page', 'remarks.user')->paginate(50)->withQueryString(),
            'clients' => Client::whereIn('id', $clientIds)->orderBy('name')->pluck('name', 'id'),
            'isCustomer' => $user->role === Role::Client,
            'dueCount' => $this->leadQuery($user)->whereDate('follow_up_on', '<=', today())->whereNotIn('status', ['won', 'lost'])->count(),
        ]);
    }

    /** Same filters as the list, as a CSV that Excel / Google Sheets open (one column per form answer) */
    public function exportLeads(Request $request): StreamedResponse
    {
        $leads = $this->filtered($request)->with('client', 'remarks.user')->get();
        $questions = $leads->flatMap(fn ($l) => array_keys($l->fields ?? []))
            ->reject(fn ($q) => in_array($q, ['full_name', 'first_name', 'last_name', 'phone_number', 'phone', 'email'], true))->unique()->values();

        return response()->streamDownload(function () use ($leads, $questions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Hindi / Marathi correctly
            fputcsv($out, array_merge(['Date', 'Client', 'Form', 'Name', 'Phone', 'Email', 'Status', 'Repeat', 'Next follow-up', 'Last remark', 'All remarks'],
                $questions->map(fn ($q) => str_replace('_', ' ', $q))->all()));
            foreach ($leads as $lead) {
                fputcsv($out, array_merge([
                    $lead->lead_at?->format('Y-m-d H:i'), $lead->client->name ?? '', $lead->form_name, $lead->name, $lead->phone, $lead->email,
                    MetaLead::STATUSES[$lead->status] ?? $lead->status, $lead->is_repeat ? 'Yes' : '', $lead->follow_up_on?->format('Y-m-d'),
                    $lead->remarks->first()?->remark,
                    $lead->remarks->reverse()->map(fn ($r) => $r->created_at->format('d M H:i').' '.($r->user->name ?? '').': '.$r->remark)->implode(' | '),
                ], $questions->map(fn ($q) => $lead->fields[$q] ?? '')->all()));
            }
            fclose($out);
        }, 'meta-leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function remark(Request $request, MetaLead $lead): RedirectResponse
    {
        abort_unless($this->leadQuery($request->user())->whereKey($lead->id)->exists(), 403);
        $data = $request->validate([
            'remark' => ['required', 'string', 'max:1000'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        MetaLeadRemark::create($data + ['meta_lead_id' => $lead->id, 'user_id' => $request->user()->id]);
        // the latest remark sets (or clears) the next follow-up; writing one means the lead was contacted
        $lead->update(['follow_up_on' => $data['follow_up_on'] ?? null] + ($lead->status === 'new' ? ['status' => 'contacted'] : []));

        return back()->with('status', 'Remark saved.');
    }

    /** List / export filters: client, status, search (name, phone, email), follow-up due today or missed */
    private function filtered(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $digits = preg_replace('/\D/', '', $search);

        return $this->leadQuery($request->user())
            ->when($request->query('client'), fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%")
                ->when(strlen($digits) >= 4, fn ($w) => $w->orWhere('phone_key', 'like', '%'.substr($digits, -10).'%'))))
            ->when($request->query('followup') === 'today', fn ($q) => $q->whereDate('follow_up_on', '<=', today())
                ->whereNotIn('status', ['won', 'lost'])->orderBy('follow_up_on'))
            ->latest('lead_at');
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
