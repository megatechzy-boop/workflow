<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">Quick post</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <p class="text-sm text-gray-600">Upload a ready photo, carousel or reel and publish it. No calendar, no designer task and no Customer approval:
                it is Approved at once and joins the brand's calendar for that month (marked "Quick post").</p>
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('contents.quickStore') }}" enctype="multipart/form-data" class="card p-4 space-y-4"
                x-data="{ type: @js(old('post_type', 'post')) }">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block text-sm text-gray-700">Brand
                        <select name="client_id" required class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                            <option value="">Pick a brand</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm text-gray-700">Post date
                        <input type="date" name="post_date" required min="{{ today()->toDateString() }}" value="{{ old('post_date', today()->toDateString()) }}"
                            class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                    </label>
                </div>

                <fieldset>
                    <legend class="text-sm text-gray-700">Format</legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach (['post' => 'Photo post', 'carousel' => 'Carousel', 'reel' => 'Reel'] as $key => $label)
                            <label class="inline-flex items-center gap-2 px-4 min-h-[44px] rounded-xl border border-gray-300 text-sm cursor-pointer"
                                :class="type === '{{ $key }}' ? 'border-brand-600 bg-brand-50 text-brand-700 font-semibold' : 'text-gray-700'">
                                <input type="radio" name="post_type" value="{{ $key }}" x-model="type" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-sm text-gray-700">Platforms</legend>
                    <div class="mt-1 flex flex-wrap gap-4">
                        @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook'] as $key => $label)
                            <label class="inline-flex items-center gap-2 min-h-[44px] text-sm text-gray-700">
                                <input type="checkbox" name="platforms[]" value="{{ $key }}" class="rounded border-gray-300" @checked(in_array($key, old('platforms', ['instagram', 'facebook']), true))>{{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- only the chosen format's file inputs are in the form (disabled ones are not sent) --}}
                <template x-if="type === 'post'"><div>@include('contents._upload-fields', ['type' => App\Enums\PostType::Post])</div></template>
                <template x-if="type === 'carousel'"><div>@include('contents._upload-fields', ['type' => App\Enums\PostType::Carousel])</div></template>
                <template x-if="type === 'reel'"><div>@include('contents._upload-fields', ['type' => App\Enums\PostType::Reel])</div></template>

                <label class="block text-sm text-gray-700">Title (optional, shows in the calendar)
                    <input type="text" name="title" maxlength="255" value="{{ old('title') }}" placeholder="e.g. Diwali greeting"
                        class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                </label>
                <label class="block text-sm text-gray-700">Caption
                    <textarea name="caption" rows="6" required maxlength="2200" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm text-sm">{{ old('caption') }}</textarea>
                </label>
                <label class="block text-sm text-gray-700">Hashtags (optional)
                    <input type="text" name="hashtags" maxlength="1000" value="{{ old('hashtags') }}" placeholder="#yoga #solapur"
                        class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm text-sm min-h-[44px]">
                </label>

                <button class="px-5 min-h-[44px] rounded-xl bg-brand-600 text-white text-sm font-semibold">Save quick post</button>
                <p class="text-xs text-gray-500">Next page: "Publish to Facebook + Instagram", now or at a time you pick.</p>
            </form>
        </div>
    </div>
</x-app-layout>
