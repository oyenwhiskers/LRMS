@php
    $user = auth()->user();

    $can = fn (string $permission) => $user->can($permission);
    $canPositions = $user->can('viewAny', App\Models\Position::class);

    $roomItems = collect([
        [
            'label' => 'Borrow File',
            'route' => route('movements.borrow'),
            'icon' => 'borrow',
            'show' => $can('movements.borrow'),
            'active' => ['movements.borrow', 'movements.borrow.*'],
        ],
        [
            'label' => 'Return File',
            'route' => route('movements.return'),
            'icon' => 'return',
            'show' => $can('movements.pic_return_receive'),
            'active' => ['movements.return', 'movements.return.*'],
        ],
        [
            'label' => 'Store File',
            'route' => route('movements.pic-intake'),
            'icon' => 'inbox',
            'show' => $can('movements.pic_intake'),
            'active' => ['movements.pic-intake', 'movements.pic-intake.*'],
        ],
    ])->filter(fn (array $item) => $item['show'])->values();

    $moreItems = collect([
        [
            'label' => 'Approvals',
            'route' => route('admin.registrations.index'),
            'icon' => 'approvals',
            'show' => $can('registrations.view'),
            'active' => ['admin.registrations.*'],
        ],
        [
            'label' => 'Completion',
            'route' => route('completions.index'),
            'icon' => 'approvals',
            'show' => $can('completions.review'),
            'active' => ['completions.*'],
        ],
        [
            'label' => 'History',
            'route' => route('movements.history'),
            'icon' => 'history',
            'show' => $can('movements.history'),
            'active' => ['movements.history'],
        ],
        [
            'label' => 'Staff',
            'route' => route('staff.index'),
            'icon' => 'staff',
            'show' => $can('staff.view'),
            'active' => ['staff.*'],
        ],
        [
            'label' => 'Positions',
            'route' => route('admin.positions.index'),
            'icon' => 'briefcase',
            'show' => $canPositions,
            'active' => ['admin.positions.*'],
        ],
        [
            'label' => 'Case Categories',
            'route' => route('categories.index'),
            'icon' => 'files',
            'show' => $can('categories.view'),
            'active' => ['categories.*'],
        ],
        [
            'label' => 'Storage',
            'route' => route('storage.index'),
            'icon' => 'archive',
            'show' => $can('storage.view'),
            'active' => ['storage.*'],
        ],
        [
            'label' => 'Change password',
            'route' => route('account.password.edit'),
            'icon' => 'lock',
            'show' => true,
            'active' => ['account.password.*'],
        ],
    ])->filter(fn (array $item) => $item['show'])->values();

    $isRoomActive = $roomItems->contains(
        fn (array $item) => collect($item['active'])->contains(fn (string $pattern) => request()->routeIs($pattern))
    );
    $isMoreActive = $moreItems->contains(
        fn (array $item) => collect($item['active'])->contains(fn (string $pattern) => request()->routeIs($pattern))
    );

    $user->loadMissing('staff.position');
    $myStaff = $user->staff;

    $leftTabs = collect([
        [
            'type' => 'link',
            'label' => 'Home',
            'route' => route('dashboard'),
            'icon' => 'dashboard',
            'show' => true,
            'active' => request()->routeIs('dashboard'),
        ],
        [
            'type' => 'link',
            'label' => 'Cases',
            'route' => route('files.index'),
            'icon' => 'files',
            'show' => $can('files.view'),
            'active' => request()->routeIs('files.*'),
        ],
    ])->filter(fn (array $tab) => $tab['show'])->values();

    $rightTabs = collect([
        [
            'type' => 'sheet',
            'label' => 'Room',
            'icon' => 'inbox',
            'sheet' => 'room',
            'show' => $roomItems->isNotEmpty(),
            'active' => $isRoomActive,
        ],
        [
            'type' => 'sheet',
            'label' => 'More',
            'icon' => 'settings',
            'sheet' => 'more',
            'show' => true,
            'active' => $isMoreActive,
        ],
    ])->filter(fn (array $tab) => $tab['show'])->values();
@endphp

<nav class="mobile-tabbar lg:hidden" aria-label="Primary mobile" data-mobile-tabbar>
    <div class="mobile-tabbar__side">
        @foreach($leftTabs as $tab)
            @include('layouts.partials.mobile-tabbar-item', ['tab' => $tab])
        @endforeach
    </div>

    <button
        type="button"
        class="mobile-tabbar__item mobile-tabbar__item--center"
        data-mobile-sheet-open="id"
        aria-haspopup="dialog"
        aria-expanded="false"
        aria-label="Staff digital ID"
    >
        <span class="mobile-tabbar__center-fab" aria-hidden="true">
            @include('layouts.partials.sidebar-icon', ['icon' => 'id'])
        </span>
        <span class="mobile-tabbar__label">ID</span>
    </button>

    <div class="mobile-tabbar__side">
        @foreach($rightTabs as $tab)
            @include('layouts.partials.mobile-tabbar-item', ['tab' => $tab])
        @endforeach
    </div>
</nav>

    <div class="mobile-sheet-overlay hidden lg:hidden" data-mobile-sheet="id" aria-hidden="true">
        <button type="button" class="mobile-sheet-overlay__backdrop" data-mobile-sheet-close aria-label="Close"></button>
        <div class="mobile-sheet mobile-sheet--id" role="dialog" aria-modal="true" aria-labelledby="mobile-sheet-id-title">
            <div class="mobile-sheet__handle" aria-hidden="true"></div>
            <div class="staff-qr-card__header">
                <p class="staff-qr-card__eyebrow">Tsang &amp; Co.</p>
                <h2 id="mobile-sheet-id-title" class="staff-qr-card__title">Digital ID</h2>
            </div>
            @if($myStaff)
                @include('staff.partials.qr-card', [
                    'staffMember' => $myStaff,
                    'qr' => $myStaff->qrDataUri(),
                ])
            @else
                <p class="mt-4 text-sm text-stone-600">Your account is not linked to a staff record, so no QR identity is available.</p>
            @endif
            <button type="button" class="btn-secondary mt-6 w-full" data-mobile-sheet-close>Close</button>
        </div>
    </div>

    @if($roomItems->isNotEmpty())
        <div class="mobile-sheet-overlay hidden lg:hidden" data-mobile-sheet="room" aria-hidden="true">
            <button type="button" class="mobile-sheet-overlay__backdrop" data-mobile-sheet-close aria-label="Close"></button>
            <div class="mobile-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-sheet-room-title">
                <div class="mobile-sheet__handle" aria-hidden="true"></div>
                <h2 id="mobile-sheet-room-title" class="mobile-sheet__title">File Room</h2>
                <ul class="mobile-sheet__list">
                    @foreach($roomItems as $item)
                        @php
                            $itemActive = collect($item['active'])->contains(fn (string $pattern) => request()->routeIs($pattern));
                        @endphp
                        <li>
                            <a href="{{ $item['route'] }}" @class(['mobile-sheet__link', 'is-active' => $itemActive])>
                                <span class="mobile-sheet__icon" aria-hidden="true">
                                    @include('layouts.partials.sidebar-icon', ['icon' => $item['icon']])
                                </span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="mobile-sheet-overlay hidden lg:hidden" data-mobile-sheet="more" aria-hidden="true">
        <button type="button" class="mobile-sheet-overlay__backdrop" data-mobile-sheet-close aria-label="Close"></button>
        <div class="mobile-sheet" role="dialog" aria-modal="true" aria-labelledby="mobile-sheet-more-title">
            <div class="mobile-sheet__handle" aria-hidden="true"></div>
            <h2 id="mobile-sheet-more-title" class="mobile-sheet__title">More</h2>
            @if($moreItems->isNotEmpty())
                <ul class="mobile-sheet__list">
                    @foreach($moreItems as $item)
                        @php
                            $itemActive = collect($item['active'])->contains(fn (string $pattern) => request()->routeIs($pattern));
                        @endphp
                        <li>
                            <a href="{{ $item['route'] }}" @class(['mobile-sheet__link', 'is-active' => $itemActive])>
                                <span class="mobile-sheet__icon" aria-hidden="true">
                                    @include('layouts.partials.sidebar-icon', ['icon' => $item['icon']])
                                </span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mobile-sheet__profile">
                <div class="mobile-sheet__profile-copy">
                    <p class="mobile-sheet__profile-name">{{ $user->name }}</p>
                    <p class="mobile-sheet__profile-meta">{{ $user->email }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-secondary w-full" type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </div>
