@extends('layouts.app')

@section('title', 'Home')

@section('content')
@php
    $greeting = now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening');
    $user = auth()->user();
    $staff = $user->staff;
    $position = $staff?->position?->name;

    $shortcuts = collect([
        [
            'label' => 'Case register',
            'help' => 'Search and open legal files',
            'route' => route('files.index'),
            'icon' => 'files',
            'show' => $user->can('files.view'),
        ],
        [
            'label' => 'Borrow file',
            'help' => 'Scan borrower and file QR',
            'route' => route('movements.borrow'),
            'icon' => 'borrow',
            'show' => $user->can('movements.borrow'),
        ],
        [
            'label' => 'Return file',
            'help' => 'Receive a borrowed file',
            'route' => route('movements.return'),
            'icon' => 'return',
            'show' => $user->can('movements.pic_return_receive'),
        ],
        [
            'label' => 'Store file',
            'help' => 'Place completed files on a shelf',
            'route' => route('movements.pic-intake'),
            'icon' => 'inbox',
            'show' => $user->can('movements.pic_intake'),
        ],
        [
            'label' => 'Movement history',
            'help' => 'Review recent file movements',
            'route' => route('movements.history'),
            'icon' => 'history',
            'show' => $user->can('movements.history'),
        ],
        [
            'label' => 'Change password',
            'help' => 'Update your sign-in password',
            'route' => route('account.password.edit'),
            'icon' => 'lock',
            'show' => true,
        ],
    ])->filter(fn (array $item) => $item['show'])->values();
@endphp

<div class="home-page">
    <div class="home-page__intro">
        <p class="eyebrow">Welcome</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-[-0.03em] text-stone-900 sm:text-4xl">
            Good {{ $greeting }},<br>{{ $user->name }}
        </h1>
        <p class="mt-2 text-sm text-stone-500">{{ now()->format('l, d F Y') }}</p>
    </div>

    @can('files.view')
        <form method="GET" action="{{ route('files.index') }}" class="dashboard-search mt-8" role="search">
            <label class="sr-only" for="home-search">Search files</label>
            <span class="dashboard-search-icon" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
                </svg>
            </span>
            <input id="home-search" class="dashboard-search-input" type="search" name="search" placeholder="Search by reference number, purchaser, vendor, property or current holder..." autocomplete="off">
        </form>
    @endcan

    <section class="home-identity mt-8">
        <div class="home-identity__header">
            <div>
                <p class="eyebrow">Your profile</p>
                <h2 class="mt-1 text-xl font-semibold text-stone-900">Staff identity</h2>
            </div>
            @if($staff)
                <button type="button" class="btn-secondary hidden shrink-0 sm:inline-flex" data-modal-open="home-digital-id">
                    View digital ID
                </button>
            @endif
        </div>

        <dl class="home-identity__fields">
            <div>
                <dt>Name</dt>
                <dd>{{ $staff?->full_name ?? $user->name }}</dd>
            </div>
            <div>
                <dt>Staff number</dt>
                <dd>{{ $staff?->staff_number ?? '—' }}</dd>
            </div>
            <div>
                <dt>Position</dt>
                <dd>{{ $position ?? 'Staff' }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd class="break-all">{{ $user->email }}</dd>
            </div>
        </dl>

        @if($staff)
            <p class="home-identity__hint sm:hidden">
                Tap below or use the centre <span class="font-semibold text-stone-800">ID</span> button to show your staff QR for scanning.
            </p>
            <button type="button" class="btn-primary mt-4 w-full sm:hidden" data-modal-open="home-digital-id">
                Show digital ID
            </button>
        @endif
    </section>

    <section class="mt-10">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Shortcuts</p>
            <h2 class="mt-1 text-2xl font-semibold text-stone-900">What you can do</h2>
            <p class="mt-2 text-sm text-stone-600">Tools available for your role. Open one to get started.</p>
        </div>

        @if($shortcuts->isNotEmpty())
            <ul class="home-actions mt-5">
                @foreach($shortcuts as $item)
                    <li>
                        <a class="home-action" href="{{ $item['route'] }}">
                            <span class="home-action__icon" aria-hidden="true">
                                @include('layouts.partials.sidebar-icon', ['icon' => $item['icon']])
                            </span>
                            <span class="home-action__copy">
                                <span class="home-action__label">{{ $item['label'] }}</span>
                                <span class="home-action__help">{{ $item['help'] }}</span>
                            </span>
                            <span class="home-action__chevron" aria-hidden="true">
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                </svg>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="mt-5 border border-stone-200 bg-white p-5">
                <p class="text-sm text-stone-600">No module shortcuts are assigned to your position yet. Ask an administrator if you need access.</p>
            </div>
        @endif
    </section>
</div>

@if($staff)
    <div class="modal-overlay hidden" data-modal="home-digital-id" aria-hidden="true">
        <div
            class="modal-panel staff-qr-modal max-w-2xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="home-digital-id-title"
        >
            <div class="modal-header">
                <div>
                    <p class="staff-qr-card__eyebrow">Tsang &amp; Co.</p>
                    <h2 id="home-digital-id-title" class="staff-qr-card__title">Digital ID</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            @include('staff.partials.qr-card', [
                'staffMember' => $staff,
                'qr' => $staff->qrDataUri(),
            ])
        </div>
    </div>
@endif
@endsection
