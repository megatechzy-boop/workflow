<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-900 tracking-tight">WhatsApp</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="p-3 rounded bg-green-50 text-green-800 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="p-3 rounded bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="card p-4 space-y-2">
                <h3 class="font-semibold text-gray-900">Company number</h3>
                @if (! $ready)
                    <p class="text-sm text-amber-700">Not set up. Put WHATSAPP_TOKEN, WHATSAPP_WABA_ID, WHATSAPP_PHONE_ID and WHATSAPP_VERIFY_TOKEN in the server .env.</p>
                @elseif ($error)
                    <p class="text-sm text-red-700">Meta said: {{ $error }}</p>
                @else
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-500">Number</dt><dd class="text-gray-900">{{ $info['display_phone_number'] ?? '-' }}</dd>
                        <dt class="text-gray-500">Name</dt><dd class="text-gray-900">{{ $info['verified_name'] ?? '-' }}</dd>
                        <dt class="text-gray-500">Quality</dt><dd class="text-gray-900">{{ $info['quality_rating'] ?? '-' }}</dd>
                        <dt class="text-gray-500">WhatsApp app too</dt>
                        <dd class="{{ ($info['is_on_biz_app'] ?? false) ? 'text-green-700' : 'text-amber-700' }}">{{ ($info['is_on_biz_app'] ?? false) ? 'Yes (phone + Web keep working)' : 'No / unknown' }}</dd>
                    </dl>
                @endif
                <p class="text-sm text-gray-600">{{ $chats }} chat(s). Last message in: {{ $lastIn ? \Illuminate\Support\Carbon::parse($lastIn)->diffForHumans() : 'none yet' }}.</p>
            </div>

            <div class="card p-4 space-y-2">
                <h3 class="font-semibold text-gray-900">Webhook (Meta app &gt; WhatsApp &gt; Configuration)</h3>
                <p class="text-sm text-gray-700">Callback URL: <code class="bg-gray-100 px-1 rounded break-all">{{ $webhook }}</code></p>
                <p class="text-sm text-gray-700">Verify token: the WHATSAPP_VERIFY_TOKEN from the .env {{ $hasVerifyToken ? '(set)' : '(not set yet)' }}.</p>
                <p class="text-sm text-gray-700">Webhook fields: messages, smb_message_echoes, history, smb_app_state_sync.</p>
            </div>

            @if ($ready)
                <form method="POST" action="{{ route('admin.whatsapp.connect') }}" class="card p-4 space-y-3">
                    @csrf
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="sync" value="1" class="mt-1 rounded border-gray-300">
                        <span>Also bring in the phone's contacts and the last 6 months of chats (Meta allows this only once, within 24 hours of linking the number).</span>
                    </label>
                    <button class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold min-h-[44px]">Connect webhook</button>
                </form>
            @endif

            <p class="text-xs text-gray-500">Who can chat: Admins and anyone with "WhatsApp inbox" (Roles, or Users &gt; Edit). Groups are not synced by WhatsApp.</p>
        </div>
    </div>
</x-app-layout>
