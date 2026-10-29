{{-- Month switcher: ‹ October 2026 › --}}
<div class="flex items-center gap-2">
    <a href="{{ request()->fullUrlWithQuery(['month' => $month->subMonth()->format('Y-m')]) }}" class="p-2 rounded-full hover:bg-gray-100" aria-label="Previous month"><x-icon name="chevron-left" class="w-5 h-5" /></a>
    <span class="font-semibold min-w-[8rem] text-center">{{ $month->format('F Y') }}</span>
    <a href="{{ request()->fullUrlWithQuery(['month' => $month->addMonth()->format('Y-m')]) }}" class="p-2 rounded-full hover:bg-gray-100" aria-label="Next month"><x-icon name="chevron-right" class="w-5 h-5" /></a>
</div>
