@php($item = $task->item)
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->calendar->client->name }} · {{ $item->post_date->format('d M l') }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $item->typeLabel() }} · Member: {{ $task->assignee->name }}</p>
            </div>
            <div class="text-right {{ $task->isOverdue() ? 'text-red-600' : 'text-gray-700' }}">
                <div class="text-xs uppercase">Deadline</div>
                <div class="font-semibold">{{ $task->deadline->format('d M, D') }} {{ $task->isOverdue() ? '(Late)' : '' }}</div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif

            {{-- Brief (calendar se) --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid md:grid-cols-2 gap-6 text-sm">
                @foreach (['creative_copy' => 'Creative copy', 'visual_brief' => 'Visual brief', 'brief' => 'Brief about the post', 'remarks' => 'Remarks'] as $field => $label)
                    @if ($item->$field)
                        <div>
                            <div class="font-semibold text-gray-800 mb-1">{{ $label }}</div>
                            <div class="whitespace-pre-line text-gray-700">{{ $item->$field }}</div>
                        </div>
                    @endif
                @endforeach
                @if ($item->reference_link)
                    <div><div class="font-semibold text-gray-800 mb-1">Reference</div><a href="{{ $item->reference_link }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 underline break-all">{{ $item->reference_link }}</a></div>
                @endif
            </div>

            {{-- Content per format --}}
            @foreach ($item->post_types as $postType)
                @php($content = $task->contents->firstWhere('post_type', $postType))
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-lg text-gray-800">{{ App\Models\CalendarItem::typeOptions()[$postType] ?? $postType }}</h3>
                        @if ($content)
                            <span class="px-2 py-1 rounded text-xs {{ $content->status->color() }}">{{ $content->status->label() }} · V{{ $content->current_version }}</span>
                        @endif
                    </div>

                    @if ($content)
                        <p class="text-sm text-gray-600">Approver: {{ $content->approver->name }}</p>
                        <a href="{{ route('contents.show', $content) }}" class="inline-block mt-3 text-indigo-600 hover:underline">Open content →</a>
                    @elseif (auth()->user()->can('upload', [App\Models\Content::class, $task]))
                        <form method="POST" action="{{ route('tasks.contents.store', $task) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <input type="hidden" name="post_type" value="{{ $postType }}">
                            @include('contents._upload-fields', ['type' => App\Models\Content::typeOf($postType), 'caption' => $item->creative_copy])
                            <div>
                                <x-input-label value="Approver" />
                                <select name="approver_id" required class="mt-1 block w-full sm:w-1/2 border-gray-300 rounded-md shadow-sm">
                                    <option value="">— Choose approver —</option>
                                    @foreach ($approvers as $a)
                                        <option value="{{ $a->id }}" @selected(old('approver_id') == $a->id)>{{ $a->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('approver_id')" class="mt-2" />
                                <x-input-error :messages="$errors->get('post_type')" class="mt-2" />
                            </div>
                            <x-primary-button>Upload &amp; Submit</x-primary-button>
                        </form>
                    @else
                        <p class="text-sm text-gray-500">Not uploaded yet.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
