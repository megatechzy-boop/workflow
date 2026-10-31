@php($item = $content->task->item)
@php($type = $content->type())
@php($isReel = $type === App\Enums\PostType::Reel)
@php($images = $version->media->where('kind', 'image')->values())
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 tracking-tight">{{ $content->typeLabel() }} · {{ $item->calendar->client->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Post date: {{ $item->post_date->format('d M l') }} · Member: {{ $content->task->assignee->name }} · Approver: {{ $content->approver->name }}
                    · <a href="{{ route('tasks.show', $content->task) }}" class="text-brand-600 hover:underline">Task</a>
                </p>
            </div>
            <span class="px-3 py-1 rounded text-sm {{ $content->status->color() }}">{{ $content->status->label() }}</span>
        </div>
        {{-- Talk about this post in the brand's chat (the message carries a link back here) --}}
        @php($clientId = $item->calendar->client_id)
        <div class="flex flex-wrap gap-2 mt-3">
            <a href="{{ route('chat.index', ['room' => 'client:'.$clientId, 'content' => $content->id]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-xl border border-brand-200 bg-brand-50 text-sm font-medium text-brand-700 hover:bg-brand-100">
                <x-icon name="chat" class="w-4 h-4" /> {{ auth()->user()->role === App\Enums\Role::Client ? 'Discuss in chat' : 'Chat with client' }}
            </a>
            @if (auth()->user()->role !== App\Enums\Role::Client)
                <a href="{{ route('chat.index', ['room' => 'internal:'.$clientId, 'content' => $content->id]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 min-h-[40px] rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <x-icon name="lock" class="w-4 h-4" /> Team chat
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            {{-- Version tabs --}}
            <div class="flex flex-wrap gap-2">
                @foreach ($content->versions as $v)
                    <a href="{{ route('contents.show', [$content, 'v' => $v->number]) }}"
                       class="px-3 py-1 rounded-full text-sm border {{ $v->is($version) ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-700 border-gray-300' }}">
                        V{{ $v->number }}{{ $v->number === $content->current_version ? ' (latest)' : '' }}
                    </a>
                @endforeach
            </div>

            <div class="grid lg:grid-cols-5 gap-6">
                {{-- Media --}}
                <div class="min-w-0 lg:col-span-3 card p-4 space-y-4">
                    @if ($isReel)
                        @php($video = $version->media->firstWhere('kind', 'video'))
                        @php($cover = $version->media->firstWhere('kind', 'cover'))
                        <video id="reel" controls preload="metadata" class="w-full max-h-[70vh] bg-black rounded" @if ($cover) poster="{{ route('media.show', $cover) }}" @endif>
                            <source src="{{ route('media.show', $video) }}">
                        </video>
                        @if ($cover)
                            <div class="text-sm text-gray-600">Cover:</div>
                            <img src="{{ route('media.show', $cover) }}" alt="Reel cover" class="w-40 rounded border">
                        @endif
                    @else
                        <div class="grid {{ $images->count() > 1 ? 'sm:grid-cols-2' : '' }} gap-4">
                            @foreach ($images as $img)
                                <figure id="slide-{{ $loop->iteration }}">
                                    <img src="{{ route('media.show', $img) }}" alt="Slide {{ $loop->iteration }}" class="w-full rounded border">
                                    @if ($images->count() > 1)<figcaption class="text-xs text-gray-500 mt-1">Slide {{ $loop->iteration }}</figcaption>@endif
                                </figure>
                            @endforeach
                        </div>
                    @endif

                    {{-- Download: each file + all at once (the browser saves separate files) --}}
                    @php($files = $version->media->sortBy(fn ($m) => [$m->kind === 'cover' ? 1 : 0, $m->sort_order])->values())
                    <div class="flex flex-wrap items-center gap-2 border-t pt-4">
                        @foreach ($files as $m)
                            <a href="{{ route('media.download', $m) }}" class="dl-link inline-flex items-center gap-1 px-3 py-1.5 rounded border border-gray-300 text-xs text-gray-700 hover:bg-gray-50">
                                <x-icon name="download" class="w-4 h-4" /> {{ $m->kind === 'image' ? ($files->where('kind', 'image')->count() > 1 ? 'Slide '.$m->sort_order : 'Image') : ucfirst($m->kind) }}
                            </a>
                        @endforeach
                        @if ($files->count() > 1)
                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700"
                                onclick="document.querySelectorAll('.dl-link').forEach((a, i) => setTimeout(() => a.click(), i * 700))"><x-icon name="download" class="w-4 h-4" /> Download all ({{ $files->count() }})</button>
                        @endif
                        @if ($type === App\Enums\PostType::Carousel)
                            {{-- LinkedIn carousel = PDF document: one page per slide --}}
                            @php($pdfName = Str::slug($item->calendar->client->name.' '.$item->post_date->format('Y-m-d').' '.$content->typeLabel()).'-v'.$version->number.'.pdf')
                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded bg-blue-700 text-white text-xs font-semibold hover:bg-blue-600"
                                onclick="this.disabled = true; this.textContent = 'Building PDF…'; window.downloadCarouselPdf({{ Js::from($images->map(fn ($m) => route('media.show', $m))->values()) }}, {{ Js::from($pdfName) }}).then(() => { this.textContent = 'PDF downloaded'; }).catch(() => { this.textContent = 'PDF failed, please retry'; this.disabled = false; })"><x-icon name="report" class="w-4 h-4" /> PDF download (LinkedIn)</button>
                        @endif
                        @if ($version->caption || $version->hashtags)
                            <button type="button" class="inline-flex items-center px-3 py-1.5 rounded border border-brand-300 text-brand-700 text-xs font-semibold hover:bg-brand-50"
                                onclick="navigator.clipboard.writeText({{ Js::from(trim($version->caption."\n\n".$version->hashtags)) }}).then(() => { this.textContent = 'Copied!'; })"><x-icon name="clipboard" class="w-4 h-4" /> Copy caption</button>
                        @endif
                    </div>
                    @if ($files->count() > 1)
                        <p class="text-xs text-gray-400">On "Download all" the browser may ask once to allow multiple downloads — please allow it.</p>
                    @endif

                    {{-- Caption + hashtags: written by the SMM (Designers/Editors only upload files) --}}
                    @if (auth()->user()->can('editCaption', $content) && $version->number === $content->current_version)
                        <form method="POST" action="{{ route('contents.caption', $content) }}" class="border-t pt-4 space-y-2">
                            @csrf
                            <x-input-label for="caption" value="Caption (SMM)" />
                            <textarea id="caption" name="caption" rows="5" class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">{{ old('caption', $version->caption ?? $item->creative_copy) }}</textarea>
                            <x-input-label for="hashtags" value="Hashtags" />
                            <x-text-input id="hashtags" name="hashtags" class="block w-full text-sm" placeholder="#brand #offer" :value="old('hashtags', $version->hashtags)" />
                            <div class="flex items-center gap-2">
                                <x-secondary-button type="submit">Save caption</x-secondary-button>
                                @unless ($version->caption)<span class="text-xs text-gray-500">Pre-filled from the calendar's creative copy — edit and save.</span>@endunless
                            </div>
                        </form>
                    @elseif ($version->caption || $version->hashtags)
                        <div class="border-t pt-4 text-sm">
                            <div class="whitespace-pre-line text-gray-800">{{ $version->caption }}</div>
                            <div class="text-brand-700 mt-2">{{ $version->hashtags }}</div>
                        </div>
                    @endif
                    <p class="text-xs text-gray-400">V{{ $version->number }} · {{ $version->submitter->name }} · {{ $version->created_at->format('d M, h:i A') }}</p>
                </div>

                {{-- Actions + comments --}}
                <div class="min-w-0 lg:col-span-2 space-y-6">
                    @can('review', $content)
                        <div class="card p-4">
                            <h3 class="font-semibold text-gray-800 mb-2">Review (V{{ $content->current_version }})</h3>
                            <form method="POST" action="{{ route('contents.review', $content) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="3" placeholder="Comment (required for Revision)" class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">{{ old('body') }}</textarea>
                                <x-input-error :messages="$errors->get('body')" />
                                <div class="flex flex-wrap gap-2">
                                    <button name="action" value="approved" class="px-3 py-2 rounded-md bg-green-600 text-white text-sm font-semibold hover:bg-green-500">Approve</button>
                                    <button name="action" value="revision" class="px-3 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-400">Revision</button>
                                    <button name="action" value="rejected" class="px-3 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-500" onclick="return confirm('Reject this content? This is final.')">Reject</button>
                                </div>
                                <p class="text-xs text-gray-500">After approval the content goes to the Customer for final approval.</p>
                            </form>
                        </div>
                    @endcan

                    @can('customerReview', $content)
                        <div class="card p-4 border-2 border-purple-200">
                            <h3 class="font-semibold text-gray-800 mb-2">Your approval</h3>
                            <form method="POST" action="{{ route('contents.customerReview', $content) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="3" placeholder="Comment (required for Revision)" class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">{{ old('body') }}</textarea>
                                <x-input-error :messages="$errors->get('body')" />
                                <div class="flex flex-wrap gap-2">
                                    <button name="action" value="approved" class="px-3 py-2 rounded-md bg-green-600 text-white text-sm font-semibold hover:bg-green-500">Approve</button>
                                    <button name="action" value="revision" class="px-3 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-400">Revision</button>
                                </div>
                            </form>
                        </div>
                    @endcan

                    @can('markStatus', $content)
                        <form method="POST" action="{{ route('contents.status', $content) }}" class="card p-4">
                            @csrf
                            <x-primary-button>{{ $content->status === App\Enums\ContentStatus::Approved ? 'Mark Scheduled' : 'Mark Published' }}</x-primary-button>
                        </form>
                    @endcan

                    {{-- Facebook / Instagram: queue the approved post; meta:publish sends it within a minute --}}
                    @php($metaPosts = App\Models\MetaPost::where('content_id', $content->id)->get())
                    @if ($metaPosts->isNotEmpty() || (auth()->user()->can('markStatus', $content) && App\Support\MetaPublisher::platforms($content)))
                        <div class="card p-4 space-y-2">
                            <div class="font-semibold text-gray-900">Facebook / Instagram</div>
                            @foreach ($metaPosts as $metaPost)
                                <div class="text-sm">
                                    {{ ucfirst($metaPost->platform) }}:
                                    <span class="{{ ['done' => 'text-green-700', 'failed' => 'text-red-600'][$metaPost->status] ?? 'text-gray-600' }}">
                                        {{ ['queued' => 'Waiting to post…', 'processing' => 'Instagram is processing…', 'done' => 'Posted', 'failed' => 'Failed'][$metaPost->status] }}
                                    </span>
                                    @if ($metaPost->error)<div class="text-xs text-red-600">{{ $metaPost->error }}</div>@endif
                                </div>
                            @endforeach
                            @can('markStatus', $content)
                                @if ($metaPosts->isEmpty() || $metaPosts->contains('status', 'failed'))
                                    <form method="POST" action="{{ route('contents.meta', $content) }}">
                                        @csrf
                                        <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold">{{ $metaPosts->contains('status', 'failed') ? 'Try again' : 'Publish to '.collect(App\Support\MetaPublisher::platforms($content))->map(fn ($p) => ucfirst($p))->implode(' + ') }}</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    @endif

                    @can('replace', $content)
                        <details class="card p-4 border border-gray-200">
                            <summary class="cursor-pointer font-semibold text-gray-800">Wrong file? Replace V{{ $content->current_version }}</summary>
                            <p class="text-xs text-gray-500 mt-2">Allowed until the SMM reviews it. The version number stays the same; the old files are removed.</p>
                            <form method="POST" action="{{ route('contents.replace', $content) }}" enctype="multipart/form-data" class="space-y-3 mt-3"
                                onsubmit="return confirm('Replace the current files? The old files will be deleted.')">
                                @csrf
                                @include('contents._upload-fields', ['type' => $type])
                                <x-primary-button>Replace files</x-primary-button>
                            </form>
                        </details>
                    @endcan

                    @can('resubmit', $content)
                        <div class="card p-4 border-2 border-orange-200">
                            <h3 class="font-semibold text-gray-800 mb-2">Submit a new version (V{{ $content->current_version + 1 }})</h3>
                            @php($checklist = $content->comments->filter->isChecklistItem())
                            @if ($checklist->isNotEmpty())
                                <p class="text-sm mb-3 {{ $checklist->whereNull('fixed_at')->isEmpty() ? 'text-green-700' : 'text-orange-700' }}">
                                    Fixed checklist: {{ $checklist->whereNotNull('fixed_at')->count() }} / {{ $checklist->count() }} fixed
                                    — tick "Fixed" on each comment below.
                                </p>
                            @endif
                            <form method="POST" action="{{ route('contents.resubmit', $content) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                @include('contents._upload-fields', ['type' => $type])
                                <x-primary-button>Resubmit</x-primary-button>
                            </form>
                        </div>
                    @endcan

                    <div class="card p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Comments &amp; History</h3>

                        @can('comment', $content)
                            <form method="POST" action="{{ route('contents.comment', $content) }}" class="space-y-2 mb-4">
                                @csrf
                                <input type="hidden" name="version" value="{{ $version->number }}">
                                <textarea name="body" rows="2" required placeholder="V{{ $version->number }} — add a comment... (@name to mention)" class="block w-full border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm"></textarea>
                                @if ($handles->isNotEmpty())
                                    <p class="text-xs text-gray-400 break-words">Mention: @foreach ($handles as $h)<span class="mr-1">{{ '@'.$h }}</span>@endforeach</p>
                                @endif
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($images->count() > 1)
                                        <select name="slide" class="border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                                            <option value="">Whole carousel</option>
                                            @foreach ($images as $img)
                                                <option value="{{ $loop->iteration }}">Slide {{ $loop->iteration }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if ($isReel)
                                        <input name="at" id="at" placeholder="0:15" class="w-20 border-gray-300 rounded-xl shadow-sm focus:border-brand-500 focus:ring-brand-500 text-sm">
                                        <button type="button" class="text-xs text-brand-600 hover:underline"
                                            onclick="const t=Math.floor(document.getElementById('reel').currentTime);document.getElementById('at').value=Math.floor(t/60)+':'+String(t%60).padStart(2,'0')">Use current video time</button>
                                    @endif
                                    <x-secondary-button type="submit">Comment</x-secondary-button>
                                </div>
                                <x-input-error :messages="$errors->get('at')" />
                            </form>
                        @endcan

                        <ul class="space-y-3 text-sm">
                            @php($actionLabels = ['submitted' => 'Uploaded', 'approved' => 'Approved by SMM', 'revision' => 'Requested revision', 'rejected' => 'Rejected', 'customer_approved' => 'Customer approved', 'replaced' => 'Replaced files', 'customer_revision' => 'Customer requested changes', 'scheduled' => 'Marked Scheduled', 'published' => 'Marked Published'])
                            {{-- Customers only see customer comments, never internal team notes --}}
                            @php($timeline = auth()->user()->role === App\Enums\Role::Client ? $content->comments->filter(fn ($c) => $c->user->role === App\Enums\Role::Client) : $content->comments)
                            @forelse ($timeline->reverse() as $c)
                                <li class="border-l-4 pl-3 {{ in_array($c->action, ['revision', 'customer_revision'], true) ? 'border-orange-400' : ($c->action === 'comment' ? 'border-gray-200' : 'border-brand-200') }}">
                                    <div class="text-xs text-gray-500">
                                        <strong class="text-gray-800">{{ $c->user->name }}</strong>
                                        · V{{ $c->version->number }} · {{ $c->created_at->format('d M, h:i A') }}
                                        @if ($c->action !== 'comment') · <span class="font-semibold">{{ $actionLabels[$c->action] ?? $c->action }}</span>@endif
                                    </div>
                                    @if ($c->slide)
                                        <a href="#slide-{{ $c->slide }}" class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-xs">Slide {{ $c->slide }}</a>
                                    @endif
                                    @if ($c->timestamp())
                                        <button type="button" class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-xs text-brand-700"
                                            onclick="const v=document.getElementById('reel');if(v){v.currentTime={{ $c->at_seconds }};v.play();}"><x-icon name="play" class="w-3 h-3 inline -mt-0.5" /> {{ $c->timestamp() }}</button>
                                    @endif
                                    {{-- escape the body first, then only highlight @mentions --}}
                                    @if ($c->body)<div class="mt-1 whitespace-pre-line text-gray-800">{!! preg_replace('/@([\pL\pN_.]+)/u', '<span class="text-brand-700 font-semibold">@$1</span>', e($c->body)) !!}</div>@endif
                                    @if ($c->isChecklistItem())
                                        <div class="mt-1 flex items-center gap-2 text-xs">
                                            @if ($c->fixed_at)
                                                <span class="px-2 py-0.5 rounded bg-green-100 text-green-800"><x-icon name="tick" class="w-3.5 h-3.5 inline -mt-0.5" /> Fixed · {{ $c->fixer?->name }}</span>
                                            @endif
                                            @can('upload', [App\Models\Content::class, $content->task])
                                                <form method="POST" action="{{ route('contents.comments.fixed', [$content, $c]) }}">
                                                    @csrf
                                                    <button class="text-brand-600 hover:underline">{{ $c->fixed_at ? 'Undo' : 'Mark fixed' }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </li>
                            @empty
                                <li class="text-gray-500">Nothing yet.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
