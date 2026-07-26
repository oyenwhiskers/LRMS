<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <title>@yield('title', 'LRMS') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <div class="flex min-h-screen flex-col">
        @hasSection('auth_fullscreen')
            <main class="flex min-h-screen flex-1 flex-col">
                @yield('auth_fullscreen')
            </main>
        @else
            <header class="site-header border-b border-amber-400/20 bg-neutral-950 text-stone-100">
                <div class="site-header-inner">
                    <a href="{{ url('/') }}" class="site-header-brand" aria-label="Tsang & Co. home">
                        <img
                            src="{{ asset('images/tsangco-logo.png') }}"
                            alt="Tsang & Co."
                            class="h-9 w-auto shrink-0 object-contain"
                            width="36"
                            height="36"
                        >
                        <span class="min-w-0">
                            <span class="block truncate font-display text-sm font-semibold tracking-[0.08em] text-amber-300">TSANG &amp; CO</span>
                            <span class="hidden truncate text-[10px] uppercase tracking-[0.18em] text-stone-400 sm:block">Legal Records Management</span>
                        </span>
                    </a>

                    @auth
                        <button type="button" class="nav-toggle site-header-toggle" aria-controls="primary-nav" aria-expanded="false">
                            <span class="sr-only">Toggle navigation</span>
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M4 7h16M4 12h16M4 17h16"/></svg>
                        </button>

                        @php
                            $canSettingsNav = auth()->user()->can('viewAny', App\Models\Position::class)
                                || auth()->user()->can('storage.view');
                            $settingsNavActive = request()->routeIs('admin.positions.*', 'storage.*');
                        @endphp

                        <nav id="primary-nav" class="primary-nav">
                            @if(auth()->user()->isApproved())
                                <div class="nav-primary-links" data-primary-links>
                                    <span class="nav-indicator" data-nav-indicator aria-hidden="true"></span>

                                    @can('dashboard.view')
                                        <a
                                            class="nav-link"
                                            href="{{ route('dashboard') }}"
                                            @if(request()->routeIs('dashboard')) aria-current="page" @endif
                                        >Dashboard</a>
                                    @endcan

                                    @can('registrations.view')
                                        <a
                                            class="nav-link"
                                            href="{{ route('admin.registrations.index') }}"
                                            @if(request()->routeIs('admin.registrations.*')) aria-current="page" @endif
                                        >Approvals</a>
                                    @endcan

                                    @can('files.view')
                                        <a
                                            class="nav-link"
                                            href="{{ route('files.index') }}"
                                            @if(request()->routeIs('files.*')) aria-current="page" @endif
                                        >File Registry</a>
                                    @endcan

                                    @can('movements.borrow')
                                        <a
                                            class="nav-link"
                                            href="{{ route('movements.borrow') }}"
                                            @if(request()->routeIs('movements.borrow*')) aria-current="page" @endif
                                        >Borrow</a>
                                    @endcan

                                    @can('movements.return')
                                        <a
                                            class="nav-link"
                                            href="{{ route('movements.return') }}"
                                            @if(request()->routeIs('movements.return*')) aria-current="page" @endif
                                        >Return</a>
                                    @endcan

                                    @can('movements.history')
                                        <a
                                            class="nav-link"
                                            href="{{ route('movements.history') }}"
                                            @if(request()->routeIs('movements.history')) aria-current="page" @endif
                                        >History</a>
                                    @endcan

                                    @can('staff.view')
                                        <a
                                            class="nav-link"
                                            href="{{ route('staff.index') }}"
                                            @if(request()->routeIs('staff.*')) aria-current="page" @endif
                                        >Staff</a>
                                    @endcan

                                    @if($canSettingsNav)
                                        <div class="nav-dropdown" data-dropdown>
                                            <button
                                                type="button"
                                                class="nav-dropdown-trigger"
                                                data-dropdown-trigger
                                                data-nav-active-target
                                                aria-expanded="false"
                                                aria-haspopup="true"
                                                @if($settingsNavActive) aria-current="page" @endif
                                            >
                                                Settings
                                                <svg class="size-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                                </svg>
                                            </button>
                                            <div class="nav-dropdown-menu hidden" data-dropdown-menu role="menu">
                                                @can('viewAny', App\Models\Position::class)
                                                    <a href="{{ route('admin.positions.index') }}" role="menuitem" @if(request()->routeIs('admin.positions.*')) aria-current="page" @endif>Positions</a>
                                                @endcan
                                                @can('storage.view')
                                                    <a href="{{ route('storage.index') }}" role="menuitem" @if(request()->routeIs('storage.*')) aria-current="page" @endif>Storage</a>
                                                @endcan
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="primary-nav-mobile-user">
                                <p class="mb-2 text-xs text-stone-400">{{ auth()->user()->name }}</p>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300 transition hover:text-amber-200" type="submit">Sign out</button>
                                </form>
                            </div>
                        </nav>

                        <div class="user-menu site-header-user" data-dropdown>
                            <button
                                type="button"
                                class="user-menu-trigger"
                                data-dropdown-trigger
                                aria-expanded="false"
                                aria-haspopup="true"
                            >
                                <span class="user-menu-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="max-w-36 truncate">{{ auth()->user()->isAdmin() ? 'Admin' : auth()->user()->name }}</span>
                                <svg class="size-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>
                            <div class="user-menu-panel hidden" data-dropdown-menu role="menu">
                                <p class="px-4 py-2 text-xs text-stone-500">{{ auth()->user()->name }}</p>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full px-4 py-2.5 text-left text-sm text-stone-100 transition hover:bg-stone-900 hover:text-amber-300" type="submit" role="menuitem">Sign out</button>
                                </form>
                            </div>
                        </div>
                    @endauth
                </div>
            </header>

            @hasSection('hero')
                @yield('hero')
            @endif

            <main class="site-main flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
                @if(session('success'))
                    <div class="mb-6 border-l-4 border-emerald-600 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                    <div class="mb-6 border-l-4 border-red-700 bg-red-50 px-4 py-3 text-sm text-red-900" role="alert">
                        <p class="font-semibold">Please correct the following:</p>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="mt-auto border-t border-stone-200 bg-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-stone-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                    <p>&copy; {{ now()->year }} Legal Records Management System.</p>
                    <p class="uppercase tracking-[0.14em]">Confidential · Authorized use only</p>
                </div>
            </footer>
        @endif
    </div>
</body>
</html>
