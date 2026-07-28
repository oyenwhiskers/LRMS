<aside
    id="app-sidebar"
    class="app-sidebar"
    data-app-sidebar
    aria-label="Primary navigation"
>
    <div class="app-sidebar__rail">
        <div class="app-sidebar__header">
            <a href="{{ url('/') }}" class="app-sidebar__brand" aria-label="Tsang &amp; Co. home">
                <img
                    src="{{ asset('images/tsangco-logo.png') }}"
                    alt="Tsang &amp; Co."
                    class="app-sidebar__brand-mark"
                    width="42"
                    height="42"
                >
                <span class="app-sidebar__brand-copy">
                    <span class="app-sidebar__brand-title">TSANG &amp; CO</span>
                    <span class="app-sidebar__brand-subtitle">Legal Records Management</span>
                </span>
            </a>

            <button
                type="button"
                class="app-sidebar__collapse"
                data-sidebar-collapse
                aria-pressed="false"
                data-tooltip="Collapse sidebar"
            >
                <span class="sr-only">Collapse sidebar</span>
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </button>
        </div>

        @include('layouts.partials.sidebar-nav', ['navigation' => $navigation])

        <div class="app-sidebar__profile" data-dropdown>
            <button
                type="button"
                class="app-sidebar__profile-trigger"
                data-dropdown-trigger
                aria-expanded="false"
                aria-haspopup="true"
                data-tooltip="{{ $user->name }}"
            >
                <span class="app-sidebar__profile-avatar" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="app-sidebar__profile-copy">
                    <span class="app-sidebar__profile-name">{{ $user->name }}</span>
                    <span class="app-sidebar__profile-role">{{ $roleLabel }}</span>
                </span>
                <svg class="app-sidebar__profile-chevron size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>

            <div class="app-sidebar__profile-menu hidden" data-dropdown-menu role="menu">
                <p class="app-sidebar__profile-menu-meta">{{ $user->email }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="app-sidebar__profile-menu-item" type="submit" role="menuitem">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</aside>

<div class="app-sidebar__overlay hidden" data-sidebar-overlay aria-hidden="true"></div>
