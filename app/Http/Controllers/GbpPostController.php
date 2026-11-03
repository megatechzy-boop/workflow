<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\GbpPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** GBP posts: one post at a time, no approval step; gbp:posts publishes them when due */
class GbpPostController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->user($request);
        $filters = $request->validate([
            'client' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(GbpPost::STATUSES))],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return view('gbp-posts.index', [
            'posts' => GbpPost::with('client:id,name')
                ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('client_id', $ids))
                ->when($filters['client'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['month'] ?? null, fn ($q, $m) => $q->whereBetween('scheduled_at', [Carbon::parse($m.'-01')->startOfMonth(), Carbon::parse($m.'-01')->endOfMonth()]))
                ->latest('scheduled_at')->paginate(30)->withQueryString(),
            'clients' => $this->clients($user)->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->user($request);

        return $this->form($request, new GbpPost(['post_type' => 'update', 'client_id' => $request->integer('client') ?: null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $this->validated($request, null);
        $data['image_path'] = $request->file('image')->store('gbp-posts', 'local');
        $post = GbpPost::create($data + ['created_by' => $user->id]);

        return redirect()->route('gbp-posts.index')->with('status', $this->scheduledMessage($post));
    }

    public function edit(Request $request, GbpPost $gbpPost): View
    {
        $this->authorizePost($request, $gbpPost);

        return $this->form($request, $gbpPost);
    }

    /** Saving a scheduled or failed post (re)schedules it with fresh tries */
    public function update(Request $request, GbpPost $gbpPost): RedirectResponse
    {
        $this->authorizePost($request, $gbpPost);
        abort_unless($gbpPost->editable(), 422, 'This post is already on Google.');
        $data = $this->validated($request, $gbpPost);
        if ($request->hasFile('image')) {
            Storage::disk('local')->delete($gbpPost->image_path);
            $data['image_path'] = $request->file('image')->store('gbp-posts', 'local');
        }
        $gbpPost->update($data + ['status' => 'scheduled', 'attempts' => 0, 'retry_at' => null, 'error' => null]);

        return redirect()->route('gbp-posts.index')->with('status', $this->scheduledMessage($gbpPost));
    }

    public function destroy(Request $request, GbpPost $gbpPost): RedirectResponse
    {
        $this->authorizePost($request, $gbpPost);
        abort_unless($gbpPost->editable(), 422, 'This post is already on Google; delete it there.');
        Storage::disk('local')->delete($gbpPost->image_path);
        $gbpPost->delete();

        return back()->with('status', 'Post deleted.');
    }

    /** Google fetches the image from this signed link (no login) */
    public function media(GbpPost $post): BinaryFileResponse
    {
        return response()->file(Storage::disk('local')->path($post->image_path));
    }

    /** Signed-in image for the list and form */
    public function image(Request $request, GbpPost $gbpPost): BinaryFileResponse
    {
        $this->authorizePost($request, $gbpPost);

        return response()->file(Storage::disk('local')->path($gbpPost->image_path));
    }

    private function form(Request $request, GbpPost $post): View
    {
        return view('gbp-posts.form', ['post' => $post, 'clients' => $this->clients($request->user())]);
    }

    private function validated(Request $request, ?GbpPost $post): array
    {
        $type = $request->input('post_type');
        $dated = in_array($type, ['offer', 'event'], true);
        $validator = validator($request->all(), [
            'client_id' => ['required', Rule::in($this->clients($request->user())->pluck('id'))],
            'post_type' => ['required', Rule::in(array_keys(GbpPost::TYPES))],
            'caption' => ['required', 'string', 'max:1500'],
            'image' => [$post ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png', 'min:10', 'max:5120', 'dimensions:min_width=250,min_height=250'],
            'cta_type' => ['nullable', Rule::in(array_keys(GbpPost::CTAS))],
            'cta_url' => [Rule::requiredIf(! $dated && $request->filled('cta_type') && $request->input('cta_type') !== 'CALL'), 'nullable', 'url:https,http', 'max:500'],
            'title' => [Rule::requiredIf($dated), 'nullable', 'string', 'max:58'],
            'starts_at' => [Rule::requiredIf($dated), 'nullable', 'date'],
            'ends_at' => [Rule::requiredIf($dated), 'nullable', 'date', 'after:starts_at', 'after:now'],
            'coupon' => ['nullable', 'string', 'max:58'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'scheduled_at' => [Rule::requiredIf(! $request->boolean('now')), 'nullable', 'date'],
        ], ['image.dimensions' => 'The image must be at least 250 × 250 pixels.', 'image.min' => 'The image must be at least 10 KB (Google rejects tiny images).']);
        $validator->after(function (Validator $v) use ($request) {
            $client = Client::with('gbpLocation')->find($request->input('client_id'));
            if ($client && ! $client->gbpLocation) {
                $v->errors()->add('client_id', 'This client has no Google Business location linked (Admin > Google Business).');
            }
            if ($request->input('cta_type') === 'CALL' && $client?->gbpLocation && ! $client->gbpLocation->phone) {
                $v->errors()->add('cta_type', 'The Google profile has no phone number, so "Call now" will not work.');
            }
        });
        $data = $validator->validate();

        $client = Client::with('gbpLocation')->find($data['client_id']);
        $isOffer = $data['post_type'] === 'offer';

        return [
            'client_id' => $client->id,
            'location_id' => $client->gbpLocation->id,
            'post_type' => $data['post_type'],
            'caption' => $data['caption'],
            'cta_type' => $isOffer ? null : ($data['cta_type'] ?? null),
            'cta_url' => ($data['cta_type'] ?? null) === 'CALL' && ! $isOffer ? null : ($data['cta_url'] ?? null),
            'title' => $dated ? $data['title'] : null,
            'starts_at' => $dated ? $data['starts_at'] : null,
            'ends_at' => $dated ? $data['ends_at'] : null,
            'coupon' => $isOffer ? ($data['coupon'] ?? null) : null,
            'terms' => $isOffer ? ($data['terms'] ?? null) : null,
            'scheduled_at' => $request->boolean('now') ? now() : Carbon::parse($data['scheduled_at']),
        ];
    }

    private function scheduledMessage(GbpPost $post): string
    {
        return $post->scheduled_at->isFuture()
            ? 'Scheduled for '.$post->scheduled_at->format('d M, h:i A').'.'
            : 'Queued. It goes to Google within a minute or two.';
    }

    /** Clients with a linked GBP location, within the user's brands */
    private function clients($user)
    {
        return Client::where('is_active', true)->whereHas('gbpLocation')->with('gbpLocation')
            ->when($user->brandLimit(), fn ($q, $ids) => $q->whereIn('id', $ids))->orderBy('name')->get();
    }

    private function user(Request $request)
    {
        abort_unless($request->user()->hasPermission(Permission::PublishContent), 403);

        return $request->user();
    }

    private function authorizePost(Request $request, GbpPost $post): void
    {
        abort_unless($this->user($request)->canSeeBrand($post->client_id), 403);
    }
}
