<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">{{ $post->exists ? 'Edit GBP post' : 'New GBP post' }}</h2>
    </x-slot>

    @php($fmt = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->format('Y-m-d\TH:i') : '')

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($post->status === 'failed')
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm break-words">Failed after {{ $post->attempts }} tries. Google said: {{ $post->error }}. Fix it and save to try again.</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">Please fix the fields marked below.</div>
            @endif

            <form method="POST" enctype="multipart/form-data" action="{{ $post->exists ? route('gbp-posts.update', $post) : route('gbp-posts.store') }}" class="card p-4 sm:p-6 space-y-4" id="gbp-form">
                @csrf
                @if ($post->exists)
                    @method('PUT')
                @endif

                <div>
                    <x-input-label for="client_id" value="Client" />
                    <select id="client_id" name="client_id" required class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                        <option value="">Choose…</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" data-location="{{ $client->gbpLocation->label() }}" data-healthcare="{{ $client->is_healthcare ? 1 : 0 }}"
                                @selected(old('client_id', $post->client_id) == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    <p id="location" class="mt-1 text-xs text-gray-500"></p>
                    <p id="healthcare" class="mt-1 text-xs text-amber-700 hidden">Healthcare client: no patient names, photos, conditions or treatment details.</p>
                    @if ($clients->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">No client has a Google Business location yet (Admin &gt; Google Business).</p>
                    @endif
                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label value="Post type" />
                    <div class="mt-1 grid grid-cols-3 gap-2">
                        @foreach (\App\Models\GbpPost::TYPES as $key => $label)
                            <label class="flex items-center justify-center gap-2 border border-gray-300 rounded-xl px-3 min-h-[44px] text-sm cursor-pointer has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                                <input type="radio" name="post_type" value="{{ $key }}" class="sr-only" @checked(old('post_type', $post->post_type) === $key)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label for="caption" value="Caption" />
                    <textarea id="caption" name="caption" rows="6" required maxlength="1500" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">{{ old('caption', $post->caption) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500 text-right"><span id="count">0</span> / 1500</p>
                    <x-input-error :messages="$errors->get('caption')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="image" value="Image (JPG or PNG, at least 250 × 250, up to 5 MB)" />
                    @if ($post->exists)
                        <img src="{{ route('gbp-posts.image', $post) }}" alt="" class="mt-1 w-32 h-32 rounded-xl object-cover">
                    @endif
                    <input id="image" type="file" name="image" accept="image/jpeg,image/png" @required(! $post->exists) class="mt-1 block w-full text-sm">
                    @if ($post->exists)
                        <p class="mt-1 text-xs text-gray-500">Choose a file only to replace the image.</p>
                    @endif
                    <x-input-error :messages="$errors->get('image')" class="mt-2" />
                </div>

                <div data-show="offer event" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <x-input-label for="title" value="Title (offer / event name)" />
                        <x-text-input id="title" name="title" maxlength="58" class="mt-1 block w-full" :value="old('title', $post->title)" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="starts_at" value="Starts" />
                        <x-text-input id="starts_at" type="datetime-local" name="starts_at" class="mt-1 block w-full" :value="old('starts_at', $fmt($post->starts_at))" />
                        <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ends_at" value="Ends" />
                        <x-text-input id="ends_at" type="datetime-local" name="ends_at" class="mt-1 block w-full" :value="old('ends_at', $fmt($post->ends_at))" />
                        <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                    </div>
                </div>

                <div data-show="offer" class="grid grid-cols-1 gap-4">
                    <div>
                        <x-input-label for="coupon" value="Coupon code (optional)" />
                        <x-text-input id="coupon" name="coupon" maxlength="58" class="mt-1 block w-full" :value="old('coupon', $post->coupon)" />
                    </div>
                    <div>
                        <x-input-label for="terms" value="Terms (optional)" />
                        <textarea id="terms" name="terms" rows="2" maxlength="5000" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">{{ old('terms', $post->terms) }}</textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div data-show="update event">
                        <x-input-label for="cta_type" value="Button" />
                        <select id="cta_type" name="cta_type" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                            <option value="">No button</option>
                            @foreach (\App\Models\GbpPost::CTAS as $key => $label)
                                <option value="{{ $key }}" @selected(old('cta_type', $post->cta_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('cta_type')" class="mt-2" />
                    </div>
                    <div id="cta_url_box">
                        <x-input-label for="cta_url" id="cta_url_label" value="Button link" />
                        <x-text-input id="cta_url" type="url" name="cta_url" maxlength="500" placeholder="https://" class="mt-1 block w-full" :value="old('cta_url', $post->cta_url)" />
                        <x-input-error :messages="$errors->get('cta_url')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="scheduled_at" value="Post on" />
                    <x-text-input id="scheduled_at" type="datetime-local" name="scheduled_at" class="mt-1 block w-full sm:w-auto" :value="old('scheduled_at', $fmt($post->scheduled_at))" />
                    <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>Schedule</x-primary-button>
                    <button type="submit" name="now" value="1" class="inline-flex items-center px-4 py-2 border border-brand-600 rounded-xl text-sm font-semibold text-brand-700 hover:bg-brand-50 min-h-[44px]">Post now</button>
                    <a href="{{ route('gbp-posts.index') }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const form = document.getElementById('gbp-form'), client = document.getElementById('client_id'),
                caption = document.getElementById('caption'), cta = document.getElementById('cta_type');
            const update = () => {
                const type = (form.querySelector('[name=post_type]:checked') || {}).value || 'update';
                form.querySelectorAll('[data-show]').forEach((el) => el.classList.toggle('hidden', !el.dataset.show.split(' ').includes(type)));
                const option = client.selectedOptions[0];
                document.getElementById('location').textContent = option && option.dataset.location ? 'Google location: ' + option.dataset.location : '';
                document.getElementById('healthcare').classList.toggle('hidden', !(option && option.dataset.healthcare === '1'));
                document.getElementById('count').textContent = caption.value.length;
                // offers: the link is "Redeem online"; "Call now" needs no link
                document.getElementById('cta_url_box').classList.toggle('hidden', type !== 'offer' && (!cta.value || cta.value === 'CALL'));
                document.getElementById('cta_url_label').textContent = type === 'offer' ? 'Redeem online link (optional)' : 'Button link';
            };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        })();
    </script>
</x-app-layout>
