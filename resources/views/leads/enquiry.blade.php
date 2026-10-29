<x-public-layout title="Tell us about your business">
    <form method="POST" action="{{ route('enquiry.submit') }}" class="card p-6 space-y-5">
        @csrf
        <p class="text-sm text-gray-600">Takes 2 minutes. We'll call or WhatsApp you within one working day.</p>
        {{-- Honeypot for bots --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="name" value="Your name *" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="business" value="Business name" />
                <x-text-input id="business" name="business" class="mt-1 block w-full" :value="old('business')" />
            </div>
            <div>
                <x-input-label for="phone" value="Phone / WhatsApp *" />
                <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone')" placeholder="+91 98765 43210" required />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">What do you need help with? *</legend>
            <div class="mt-2 grid sm:grid-cols-2 gap-2">
                @foreach (App\Models\Lead::NEEDS as $need)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="needs[]" value="{{ $need }}" class="rounded border-gray-300" @checked(in_array($need, old('needs', []), true))>
                        {{ $need }}
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('needs')" class="mt-2" />
        </fieldset>

        <div>
            <x-input-label for="goal" value="What result are you hoping for? *" />
            <textarea id="goal" name="goal" rows="3" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm" placeholder="More leads from Instagram, a new website…" required>{{ old('goal') }}</textarea>
            <x-input-error :messages="$errors->get('goal')" class="mt-2" />
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="budget" value="Monthly budget in mind" />
                <select id="budget" name="budget" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                    <option value="">Select</option>
                    @foreach (App\Models\Lead::BUDGETS as $b)<option @selected(old('budget') === $b)>{{ $b }}</option>@endforeach
                </select>
            </div>
            <div>
                <x-input-label for="timeline" value="When do you want to start?" />
                <select id="timeline" name="timeline" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">
                    <option value="">Select</option>
                    @foreach (App\Models\Lead::TIMELINES as $t)<option @selected(old('timeline') === $t)>{{ $t }}</option>@endforeach
                </select>
            </div>
        </div>

        <div>
            <x-input-label for="source" value="How did you hear about us?" />
            <x-text-input id="source" name="source" class="mt-1 block w-full" :value="old('source')" placeholder="Instagram, Google, a friend…" />
        </div>
        <div>
            <x-input-label for="notes" value="Anything else we should know?" />
            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-xl shadow-sm">{{ old('notes') }}</textarea>
        </div>

        <button class="w-full py-3 rounded-xl bg-brand-600 text-white font-semibold">Send enquiry</button>
    </form>
</x-public-layout>
