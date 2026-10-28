@php($unread = Auth::user()->unreadNotifications()->count())
<a href="{{ route('notifications.index') }}" class="relative inline-flex p-2 rounded-full text-gray-500 hover:text-gray-800 hover:bg-gray-100" aria-label="Notifications{{ $unread ? " ($unread unread)" : '' }}">
    <x-icon name="bell" class="w-6 h-6" />
    @if ($unread)
        <span class="absolute top-0.5 right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-accent-500 text-white text-[11px] leading-[18px] text-center">{{ $unread > 99 ? '99+' : $unread }}</span>
    @endif
</a>
