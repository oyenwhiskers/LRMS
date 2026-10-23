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
    $navPrint = request()->routeIs('entry.print-station');
    $navAi = request()->routeIs('entry.ai-usage.*');
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
                    <a class="entry-app__logout entry-app__logout--mobile {{ $navPrint ? 'is-active' : '' }}" href="{{ route('entry.print-station') }}">Cetak</a>
                    <a class="entry-app__logout entry-app__logout--mobile {{ $navAi ? 'is-active' : '' }}" href="{{ route('entry.ai-usage.index') }}">AI</a>
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
