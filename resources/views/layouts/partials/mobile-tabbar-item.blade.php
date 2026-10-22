@if($tab['type'] === 'link')
    <a
        href="{{ $tab['route'] }}"
        @class(['mobile-tabbar__item', 'is-active' => $tab['active']])
        @if($tab['active']) aria-current="page" @endif
    >
        <span class="mobile-tabbar__icon" aria-hidden="true">
            @include('layouts.partials.sidebar-icon', ['icon' => $tab['icon']])
        </span>
        <span class="mobile-tabbar__label">{{ $tab['label'] }}</span>
    </a>
@else
    <button
        type="button"
        @class(['mobile-tabbar__item', 'is-active' => $tab['active']])
        data-mobile-sheet-open="{{ $tab['sheet'] }}"
        aria-haspopup="dialog"
        aria-expanded="false"
    >
        <span class="mobile-tabbar__icon" aria-hidden="true">
            @include('layouts.partials.sidebar-icon', ['icon' => $tab['icon']])
        </span>
        <span class="mobile-tabbar__label">{{ $tab['label'] }}</span>
    </button>
@endif
