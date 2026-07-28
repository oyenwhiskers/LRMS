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
            @php
                $user = auth()->user();
                $showSidebarShell = $user?->isApproved();
                $roleLabel = $user?->isAdmin()
                    ? 'Administrator'
                    : ($user?->staff?->position?->name ?? ucfirst((string) $user?->status));

                $navigation = $user ? [
                    [
                        'label' => 'Dashboard',
                        'route' => route('dashboard'),
                        'icon' => 'dashboard',
                        'permission' => 'dashboard.view',
                        'active' => ['dashboard'],
                    ],
                    [
                        'label' => 'Approvals',
                        'route' => route('admin.registrations.index'),
                        'icon' => 'approvals',
                        'permission' => 'registrations.view',
                        'active' => ['admin.registrations.*'],
                    ],
                    [
                        'label' => 'File Registry',
                        'route' => route('files.index'),
                        'icon' => 'files',
                        'permission' => 'files.view',
                        'active' => ['files.*'],
                    ],
                    [
                        'label' => 'Borrow',
                        'route' => route('movements.borrow'),
                        'icon' => 'borrow',
                        'permission' => 'movements.borrow',
                        'active' => ['movements.borrow', 'movements.borrow.*'],
                    ],
                    [
                        'label' => 'Return',
                        'route' => route('movements.return'),
                        'icon' => 'return',
                        'permission' => 'movements.return',
                        'active' => ['movements.return', 'movements.return.*'],
                    ],
                    [
                        'label' => 'History',
                        'route' => route('movements.history'),
                        'icon' => 'history',
                        'permission' => 'movements.history',
                        'active' => ['movements.history'],
                    ],
                    [
                        'label' => 'Staff',
                        'route' => route('staff.index'),
                        'icon' => 'staff',
                        'permission' => 'staff.view',
                        'active' => ['staff.*'],
                    ],
                    [
                        'label' => 'Settings',
                        'icon' => 'settings',
                        'children' => [
                            [
                                'label' => 'Positions',
                                'route' => route('admin.positions.index'),
                                'icon' => 'briefcase',
                                'permission_check' => fn () => $user->can('viewAny', App\Models\Position::class),
                                'active' => ['admin.positions.*'],
                            ],
                            [
                                'label' => 'Storage',
                                'route' => route('storage.index'),
                                'icon' => 'archive',
                                'permission' => 'storage.view',
                                'active' => ['storage.*'],
                            ],
                        ],
                    ],
                ] : [];
            @endphp

            @if($showSidebarShell)
                <div class="app-shell" data-sidebar-shell>
                    @include('layouts.partials.sidebar', [
                        'navigation' => $navigation,
                        'user' => $user,
                        'roleLabel' => $roleLabel,
                    ])

                    <div class="app-content-shell">
                        <header class="page-chrome" data-page-chrome>
                            <button
                                type="button"
                                class="page-chrome-toggle"
                                data-sidebar-toggle
                                aria-controls="app-sidebar"
                                aria-expanded="false"
                            >
                                <span class="sr-only">Toggle navigation</span>
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
                                </svg>
                            </button>

                            <div class="min-w-0">
                                <p class="page-chrome-eyebrow">Tsang &amp; Co.</p>
                                <h2 class="page-chrome-title">@yield('title', 'LRMS')</h2>
                            </div>
                        </header>

                        <main class="site-main flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
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

                    </div>
                </div>
            @else
                <header class="border-b border-amber-400/20 bg-neutral-950 text-stone-100">
                    <div class="flex min-h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <a href="{{ url('/') }}" class="site-header-brand" aria-label="Tsang &amp; Co. home">
                            <img
                                src="{{ asset('images/tsangco-logo.png') }}"
                                alt="Tsang &amp; Co."
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
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300 transition hover:text-amber-200" type="submit">Sign out</button>
                            </form>
                        @endauth
                    </div>
                </header>

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

            @endif
        @endif
    </div>
</body>
</html>
