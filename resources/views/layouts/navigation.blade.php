<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 lg:-my-px lg:ms-10 lg:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('calendars.index')" :active="request()->routeIs('calendars.*')">
                        {{ __('Calendars') }}
                    </x-nav-link>
                    <x-nav-link :href="route('schedule')" :active="request()->routeIs('schedule')">
                        {{ __('Schedule') }}
                    </x-nav-link>
                    <x-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')">
                        {{ __('Assets') }}
                    </x-nav-link>
                    @php($chatUnread = array_sum(App\Models\ChatMessage::unreadCounts(Auth::user())))
                    <x-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.*')">
                        {{ __('Chat') }}@if ($chatUnread)<span class="ms-1 min-w-[18px] px-1 rounded-full bg-red-600 text-white text-[11px] text-center">{{ $chatUnread }}</span>@endif
                    </x-nav-link>
                    @if (Auth::user()->role->isCreator() || Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                        <x-nav-link :href="route('tasks.index')" :active="request()->routeIs('tasks.*')">
                            {{ Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks) ? __('Tasks') : __('My Tasks') }}
                        </x-nav-link>
                    @endif
                    @unless (Auth::user()->hasRole('client'))
                        <x-nav-link :href="route('leaderboard')" :active="request()->routeIs('leaderboard')">
                            {{ __('Leaderboard') }}
                        </x-nav-link>
                    @endunless
                    @if (Auth::user()->hasRole('client') || Auth::user()->hasPermission(App\Enums\Permission::ApproveContent) || Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                        <x-nav-link :href="route('contents.index')" :active="request()->routeIs('contents.*')">
                            {{ __('Approvals') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasPermission(App\Enums\Permission::ManageClients))
                        <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                            {{ __('Clients') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasPermission(App\Enums\Permission::ManageUsers))
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Users') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                            {{ __('Roles') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                @include('layouts._bell')
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }} <span class="text-xs text-gray-400">({{ Auth::user()->role->label() }})</span></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                @include('layouts._bell')
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('calendars.index')" :active="request()->routeIs('calendars.*')">
                {{ __('Calendars') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('schedule')" :active="request()->routeIs('schedule')">
                {{ __('Schedule') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.*')">
                {{ __('Assets') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.*')">
                {{ __('Chat') }}@if ($chatUnread ?? 0) ({{ $chatUnread }})@endif
            </x-responsive-nav-link>
            @if (Auth::user()->role->isCreator() || Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                <x-responsive-nav-link :href="route('tasks.index')" :active="request()->routeIs('tasks.*')">
                    {{ Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks) ? __('Tasks') : __('My Tasks') }}
                </x-responsive-nav-link>
            @endif
            @unless (Auth::user()->hasRole('client'))
                <x-responsive-nav-link :href="route('leaderboard')" :active="request()->routeIs('leaderboard')">
                    {{ __('Leaderboard') }}
                </x-responsive-nav-link>
            @endunless
            @if (Auth::user()->hasRole('client') || Auth::user()->hasPermission(App\Enums\Permission::ApproveContent) || Auth::user()->hasPermission(App\Enums\Permission::ViewAllTasks))
                <x-responsive-nav-link :href="route('contents.index')" :active="request()->routeIs('contents.*')">
                    {{ __('Approvals') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission(App\Enums\Permission::ManageClients))
                <x-responsive-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    {{ __('Clients') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasPermission(App\Enums\Permission::ManageUsers))
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    {{ __('Users') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.roles.index')" :active="request()->routeIs('admin.roles.*')">
                    {{ __('Roles') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
