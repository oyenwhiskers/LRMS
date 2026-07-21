<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111111">
    <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">
    <title>@yield('title', 'LRMS') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <div class="min-h-screen">
        <header class="border-b border-amber-400/20 bg-neutral-950 text-stone-100">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="LRMS home">
                    <span class="grid size-10 place-items-center border border-amber-400/60 bg-amber-400/10 text-sm font-bold tracking-widest text-amber-300">LR</span>
                    <span>
                        <span class="block text-sm font-semibold tracking-[0.16em]">LRMS</span>
                        <span class="hidden text-[10px] uppercase tracking-[0.18em] text-stone-400 sm:block">Legal Records Management</span>
                    </span>
                </a>

                @auth
                    <button type="button" class="nav-toggle rounded-md border border-stone-700 p-2 text-stone-300 md:hidden" aria-controls="primary-nav" aria-expanded="false">
                        <span class="sr-only">Toggle navigation</span>
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <nav id="primary-nav" class="primary-nav hidden absolute inset-x-0 top-18 z-40 border-b border-stone-800 bg-neutral-950 px-4 py-4 md:static md:flex md:items-center md:gap-6 md:border-0 md:p-0">
                        @if(auth()->user()->isApproved())
                            @can('dashboard.view')
                                <a class="nav-link" href="{{ route('dashboard') }}">Dashboard</a>
                            @endcan
                            @can('registrations.view')
                                <a class="nav-link" href="{{ route('admin.registrations.index') }}">Registrations</a>
                            @endcan
                            @can('viewAny', App\Models\Position::class)
                                <a class="nav-link" href="{{ route('admin.positions.index') }}">Positions</a>
                            @endcan
                            @can('staff.view')
                                <a class="nav-link" href="{{ route('staff.index') }}">Staff</a>
                            @endcan
                            @can('storage.view')
                                <a class="nav-link" href="{{ route('storage.index') }}">Storage</a>
                            @endcan
                            @can('files.view')
                                <a class="nav-link" href="{{ route('files.index') }}">Files</a>
                            @endcan
                            @can('movements.borrow')
                                <a class="nav-link" href="{{ route('movements.borrow') }}">Borrow</a>
                            @endcan
                            @can('movements.return')
                                <a class="nav-link" href="{{ route('movements.return') }}">Return</a>
                            @endcan
                            @can('movements.history')
                                <a class="nav-link" href="{{ route('movements.history') }}">History</a>
                            @endcan
                            @can('imports.view')
                                <a class="nav-link" href="{{ route('imports.index') }}">Excel</a>
                            @endcan
                        @endif
                        <div class="mt-4 border-t border-stone-800 pt-4 md:mt-0 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                            <p class="mb-2 text-xs text-stone-400 md:hidden">{{ auth()->user()->name }}</p>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300 transition hover:text-amber-200">Sign out</button>
                            </form>
                        </div>
                    </nav>
                @endauth
            </div>
        </header>

        @hasSection('hero')
            @yield('hero')
        @endif

        <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
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

        <footer class="border-t border-stone-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-stone-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>&copy; {{ now()->year }} Legal Records Management System.</p>
                <p class="uppercase tracking-[0.14em]">Confidential · Authorized use only</p>
            </div>
        </footer>
    </div>
    @livewireScripts
</body>
</html>
