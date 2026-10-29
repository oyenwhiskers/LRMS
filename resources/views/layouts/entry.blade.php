<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12352f">
    <title>@yield('title', 'Entry') · Entry</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $navHome = request()->routeIs('entry.home');
    $navScan = request()->routeIs('entry.scan', 'entry.review', 'entry.shelf');
    $navHistory = request()->routeIs('entry.history');
    $navOperators = request()->routeIs('entry.operators.*');
    $navPrint = request()->routeIs('entry.print-station');
    $navAi = request()->routeIs('entry.ai-usage.*');
    $navProd = request()->routeIs('entry.productivity.*');
    $navManageOps = request()->routeIs('entry.manage-operators.*');
    $navMore = $navPrint || $navAi || $navProd || $navManageOps;
@endphp
<body class="entry-app">
    <div class="entry-app__shell">
        <nav class="entry-app__tabbar" aria-label="Menu Entry">
            <div class="entry-app__nav-section entry-app__nav-section--primary">
                <p class="entry-app__nav-label">Operasi</p>
                <div class="entry-app__nav-list">
                    <a class="entry-app__tab {{ $navHome ? 'is-active' : '' }}" href="{{ route('entry.home') }}" @if($navHome) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 4.5l7.5 6V19.5a1.5 1.5 0 01-1.5 1.5h-3.75v-6h-4.5v6H6A1.5 1.5 0 014.5 19.5v-9z"/></svg>
                        </span>
                        <span>Utama</span>
                    </a>
                    <a class="entry-app__tab {{ $navOperators ? 'is-active' : '' }}" href="{{ route('entry.operators.index') }}" @if($navOperators) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.125a7.125 7.125 0 0114.25 0V20.25H4.5v-1.125z"/></svg>
                        </span>
                        <span>Operator</span>
                    </a>
                    <a class="entry-app__tab {{ $navScan ? 'is-active' : '' }}" href="{{ route('entry.scan') }}" @if($navScan) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4.5H5.25A1.75 1.75 0 003.5 6.25V8M17 4.5h1.75A1.75 1.75 0 0120.5 6.25V8M7 19.5H5.25A1.75 1.75 0 013.5 17.75V16M17 19.5h1.75a1.75 1.75 0 001.75-1.75V16M7.5 12h9"/></svg>
                        </span>
                        <span>Imbas</span>
                    </a>
                    <a class="entry-app__tab {{ $navHistory ? 'is-active' : '' }}" href="{{ route('entry.history') }}" @if($navHistory) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3 1.75M20 12a8 8 0 11-8-8 8.3 8.3 0 013.5.75L18 3v4.5h-4.5"/></svg>
                        </span>
                        <span>Sejarah</span>
                    </a>
                    <div class="entry-app__more entry-app__more--mobile" data-entry-more>
                        <button
                            class="entry-app__tab {{ $navMore ? 'is-active' : '' }}"
                            type="button"
                            data-entry-more-toggle
                            aria-expanded="false"
                            aria-controls="entry-more-panel"
                            aria-haspopup="true"
                        >
                            <span class="entry-app__tab-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M6 12h.01M12 12h.01M18 12h.01"/></svg>
                            </span>
                            <span>Lagi</span>
                        </button>
                        <div
                            class="entry-app__more-panel hidden"
                            id="entry-more-panel"
                            data-entry-more-panel
                            role="menu"
                            aria-label="Menu lanjut"
                        >
                            <a class="entry-app__more-item {{ $navPrint ? 'is-active' : '' }}" href="{{ route('entry.print-station') }}" role="menuitem">
                                <span class="entry-app__more-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5V4.5h9v3M6 13.5h12M8.25 19.5h7.5A1.75 1.75 0 0017.5 17.75v-4.5H6.5v4.5A1.75 1.75 0 008.25 19.5zM6.5 13.25V10.5A1.5 1.5 0 018 9h8a1.5 1.5 0 011.5 1.5v2.75"/></svg>
                                </span>
                                Cetak
                            </a>
                            <a class="entry-app__more-item {{ $navAi ? 'is-active' : '' }}" href="{{ route('entry.ai-usage.index') }}" role="menuitem">
                                <span class="entry-app__more-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75l1.35 3.9 3.9 1.35-3.9 1.35L12 14.25l-1.35-3.9-3.9-1.35 3.9-1.35L12 3.75zM6.75 15.75l.7 2.05 2.05.7-2.05.7-.7 2.05-.7-2.05-2.05-.7 2.05-.7.7-2.05zM17.25 14.25l.55 1.6 1.6.55-1.6.55-.55 1.6-.55-1.6-1.6-.55 1.6-.55.55-1.6z"/></svg>
                                </span>
                                AI
                            </a>
                            <a class="entry-app__more-item {{ $navProd ? 'is-active' : '' }}" href="{{ route('entry.productivity.index') }}" role="menuitem">
                                <span class="entry-app__more-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19.5h16.5M6 16.5V9.75M12 16.5V4.5M18 16.5v-4.5"/></svg>
                                </span>
                                Produktiviti
                            </a>
                            <a class="entry-app__more-item {{ $navManageOps ? 'is-active' : '' }}" href="{{ route('entry.manage-operators.index') }}" role="menuitem">
                                <span class="entry-app__more-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 11-6 0 3 3 0 016 0zM4.5 19.5a7.5 7.5 0 0115 0V20.25H4.5V19.5zM16.5 8.25l1.5 1.5 3-3"/></svg>
                                </span>
                                Urus
                            </a>
                            <form method="POST" action="{{ route('logout') }}" role="none">
                                @csrf
                                <button class="entry-app__more-item entry-app__more-item--danger" type="submit" role="menuitem">
                                    <span class="entry-app__more-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 12h8.25M17.25 8.25L21 12l-3.75 3.75"/></svg>
                                    </span>
                                    Log keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="entry-app__nav-section entry-app__nav-section--aside">
                <p class="entry-app__nav-label">Stesen</p>
                <div class="entry-app__nav-list">
                    <a class="entry-app__tab {{ $navPrint ? 'is-active' : '' }}" href="{{ route('entry.print-station') }}" @if($navPrint) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5V4.5h9v3M6 13.5h12M8.25 19.5h7.5A1.75 1.75 0 0017.5 17.75v-4.5H6.5v4.5A1.75 1.75 0 008.25 19.5zM6.5 13.25V10.5A1.5 1.5 0 018 9h8a1.5 1.5 0 011.5 1.5v2.75"/></svg>
                        </span>
                        <span>Cetak</span>
                    </a>
                    <a class="entry-app__tab {{ $navAi ? 'is-active' : '' }}" href="{{ route('entry.ai-usage.index') }}" @if($navAi) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75l1.35 3.9 3.9 1.35-3.9 1.35L12 14.25l-1.35-3.9-3.9-1.35 3.9-1.35L12 3.75zM6.75 15.75l.7 2.05 2.05.7-2.05.7-.7 2.05-.7-2.05-2.05-.7 2.05-.7.7-2.05zM17.25 14.25l.55 1.6 1.6.55-1.6.55-.55 1.6-.55-1.6-1.6-.55 1.6-.55.55-1.6z"/></svg>
                        </span>
                        <span>AI</span>
                    </a>
                    <a class="entry-app__tab {{ $navProd ? 'is-active' : '' }}" href="{{ route('entry.productivity.index') }}" @if($navProd) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 19.5h16.5M6 16.5V9.75M12 16.5V4.5M18 16.5v-4.5"/></svg>
                        </span>
                        <span>Produktiviti</span>
                    </a>
                    <a class="entry-app__tab {{ $navManageOps ? 'is-active' : '' }}" href="{{ route('entry.manage-operators.index') }}" @if($navManageOps) aria-current="page" @endif>
                        <span class="entry-app__tab-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 11-6 0 3 3 0 016 0zM4.5 19.5a7.5 7.5 0 0115 0V20.25H4.5V19.5zM16.5 8.25l1.5 1.5 3-3"/></svg>
                        </span>
                        <span>Urus</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="entry-app__body">
            <header class="entry-app__header">
                <div class="entry-app__brand">
                    <p class="entry-app__product">{{ auth()->user()->name }}</p>
                    <p class="entry-app__mark">{{ auth()->user()->email }}</p>
                </div>
                <div class="entry-app__header-actions">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="entry-app__logout" type="submit">Log keluar</button>
                    </form>
                </div>
            </header>

            @if (session('success'))
                <p class="entry-app__flash entry-app__flash--ok">{{ session('success') }}</p>
            @endif
            @if (session('warning'))
                <p class="entry-app__flash entry-app__flash--warn">{{ session('warning') }}</p>
            @endif

            <main class="entry-app__main">
                @yield('content')
            </main>
        </div>
    </div>

    <div class="modal-overlay hidden" data-modal="entry-print-queued" aria-hidden="true">
        <div class="modal-panel max-w-md" role="dialog" aria-modal="true" aria-labelledby="entry-print-queued-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">Stesen cetak</p>
                    <h2 id="entry-print-queued-title" class="mt-1 text-xl font-semibold" data-print-queue-success-title>
                        Berjaya dihantar
                    </h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Tutup">Tutup</button>
            </div>
            <p class="mt-5 text-sm text-stone-600" data-print-queue-success-message>
                Job berjaya dihantar ke stesen cetak.
            </p>
            <p class="mt-2 text-sm text-stone-500">
                Label akan dicetak di stesen. Anda boleh tutup dialog ini.
            </p>
            <div class="mt-6 flex justify-end">
                <button class="btn-primary" type="button" data-modal-close>OK</button>
            </div>
        </div>
    </div>
</body>
</html>
