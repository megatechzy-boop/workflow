@php($item = $content->task->item)
@php($type = $content->type())
@php($isReel = $type === App\Enums\PostType::Reel)
@php($images = $version->media->where('kind', 'image')->values())
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $content->typeLabel() }} · {{ $item->calendar->client->name }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Post date: {{ $item->post_date->format('d M l') }} · Member: {{ $content->task->assignee->name }} · Approver: {{ $content->approver->name }}
                    · <a href="{{ route('tasks.show', $content->task) }}" class="text-indigo-600 hover:underline">Task</a>
                </p>
            </div>
            <span class="px-3 py-1 rounded text-sm {{ $content->status->color() }}">{{ $content->status->label() }}</span>
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
                       class="px-3 py-1 rounded-full text-sm border {{ $v->is($version) ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300' }}">
                        V{{ $v->number }}{{ $v->number === $content->current_version ? ' (latest)' : '' }}
                    </a>
                @endforeach
            </div>

            <div class="grid lg:grid-cols-5 gap-6">
                {{-- Media --}}
                <div class="lg:col-span-3 bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
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

                    @if ($version->caption || $version->hashtags)
                        <div class="border-t pt-4 text-sm">
                            <div class="whitespace-pre-line text-gray-800">{{ $version->caption }}</div>
                            <div class="text-indigo-700 mt-2">{{ $version->hashtags }}</div>
                        </div>
                    @endif
                    <p class="text-xs text-gray-400">V{{ $version->number }} · {{ $version->submitter->name }} · {{ $version->created_at->format('d M, h:i A') }}</p>
                </div>

                {{-- Actions + comments --}}
                <div class="lg:col-span-2 space-y-6">
                    @can('review', $content)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4">
                            <h3 class="font-semibold text-gray-800 mb-2">Review (V{{ $content->current_version }})</h3>
                            <form method="POST" action="{{ route('contents.review', $content) }}" class="space-y-3">
                                @csrf
                                <textarea name="body" rows="3" placeholder="Comment (Revision ke liye zaroori)" class="block w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('body') }}</textarea>
                                <x-input-error :messages="$errors->get('body')" />
                                <div class="flex flex-wrap gap-2">
                                    <button name="action" value="approved" class="px-3 py-2 rounded-md bg-green-600 text-white text-sm font-semibold hover:bg-green-500">Approve</button>
                                    <button name="action" value="revision" class="px-3 py-2 rounded-md bg-orange-500 text-white text-sm font-semibold hover:bg-orange-400">Revision</button>
                                    <button name="action" value="rejected" class="px-3 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-500" onclick="return confirm('Reject karna hai? Ye final hai.')">Reject</button>
                                </div>
                            </form>
                        </div>
                    @endcan

                    @can('markStatus', $content)
                        <form method="POST" action="{{ route('contents.status', $content) }}" class="bg-white shadow-sm sm:rounded-lg p-4">
                            @csrf
                            <x-primary-button>{{ $content->status === App\Enums\ContentStatus::Approved ? 'Mark Scheduled' : 'Mark Published' }}</x-primary-button>
                        </form>
                    @endcan

                    @can('resubmit', $content)
                        <div class="bg-white shadow-sm sm:rounded-lg p-4 border-2 border-orange-200">
                            <h3 class="font-semibold text-gray-800 mb-2">Naya version bhejo (V{{ $content->current_version + 1 }})</h3>
                            <form method="POST" action="{{ route('contents.resubmit', $content) }}" enctype="multipart/form-data" class="space-y-3">
                                @csrf
                                @include('contents._upload-fields', ['type' => $type, 'caption' => $version->caption, 'hashtags' => $version->hashtags])
                                <x-primary-button>Resubmit</x-primary-button>
                            </form>
                        </div>
                    @endcan

                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Comments &amp; History</h3>

                        @can('comment', $content)
                            <form method="POST" action="{{ route('contents.comment', $content) }}" class="space-y-2 mb-4">
                                @csrf
                                <input type="hidden" name="version" value="{{ $version->number }}">
                                <textarea name="body" rows="2" required placeholder="V{{ $version->number }} par comment..." class="block w-full border-gray-300 rounded-md shadow-sm text-sm"></textarea>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($images->count() > 1)
                                        <select name="slide" class="border-gray-300 rounded-md shadow-sm text-sm">
                                            <option value="">Poora carousel</option>
                                            @foreach ($images as $img)
                                                <option value="{{ $loop->iteration }}">Slide {{ $loop->iteration }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if ($isReel)
                                        <input name="at" id="at" placeholder="0:15" class="w-20 border-gray-300 rounded-md shadow-sm text-sm">
                                        <button type="button" class="text-xs text-indigo-600 hover:underline"
                                            onclick="const t=Math.floor(document.getElementById('reel').currentTime);document.getElementById('at').value=Math.floor(t/60)+':'+String(t%60).padStart(2,'0')">Video ka abhi ka time lo</button>
                                    @endif
                                    <x-secondary-button type="submit">Comment</x-secondary-button>
                                </div>
                                <x-input-error :messages="$errors->get('at')" />
                            </form>
                        @endcan

                        <ul class="space-y-3 text-sm">
                            @php($actionLabels = ['submitted' => 'Upload kiya', 'approved' => 'Approve kiya', 'revision' => 'Revision maangi', 'rejected' => 'Reject kiya', 'scheduled' => 'Scheduled mark kiya', 'published' => 'Published mark kiya'])
                            @forelse ($content->comments->reverse() as $c)
                                <li class="border-l-4 pl-3 {{ $c->action === 'revision' ? 'border-orange-400' : ($c->action === 'comment' ? 'border-gray-200' : 'border-indigo-200') }}">
                                    <div class="text-xs text-gray-500">
                                        <strong class="text-gray-800">{{ $c->user->name }}</strong>
                                        · V{{ $c->version->number }} · {{ $c->created_at->format('d M, h:i A') }}
                                        @if ($c->action !== 'comment') · <span class="font-semibold">{{ $actionLabels[$c->action] ?? $c->action }}</span>@endif
                                    </div>
                                    @if ($c->slide)
                                        <a href="#slide-{{ $c->slide }}" class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-xs">Slide {{ $c->slide }}</a>
                                    @endif
                                    @if ($c->timestamp())
                                        <button type="button" class="inline-block mt-1 px-2 py-0.5 rounded bg-gray-100 text-xs text-indigo-700"
                                            onclick="const v=document.getElementById('reel');if(v){v.currentTime={{ $c->at_seconds }};v.play();}">▶ {{ $c->timestamp() }}</button>
                                    @endif
                                    @if ($c->body)<div class="mt-1 whitespace-pre-line text-gray-800">{{ $c->body }}</div>@endif
                                </li>
                            @empty
                                <li class="text-gray-500">Abhi kuch nahi.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
