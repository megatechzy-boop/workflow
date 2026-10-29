<x-public-layout :title="$title ?? 'Thank you'">
    <div class="card p-8 text-center">
        <div class="mx-auto w-14 h-14 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
            <x-icon name="tick" class="w-7 h-7" />
        </div>
        <p class="mt-4 text-gray-700">{{ $message ?? "We've got your enquiry. Our team will call or WhatsApp you within one working day." }}</p>
        <a href="https://www.megatechzy.com" class="inline-block mt-6 text-brand-600 hover:underline">Back to megatechzy.com</a>
    </div>
</x-public-layout>
