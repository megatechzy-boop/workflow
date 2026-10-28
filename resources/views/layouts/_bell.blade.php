@php($unread = Auth::user()->unreadNotifications()->count())
<a href="{{ route('notifications.index') }}" class="relative inline-flex p-2 text-gray-500 hover:text-gray-700" aria-label="Notifications{{ $unread ? " ($unread unread)" : '' }}">
    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
    </svg>
    @if ($unread)
        <span class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[11px] leading-[18px] text-center">{{ $unread > 99 ? '99+' : $unread }}</span>
    @endif
</a>
