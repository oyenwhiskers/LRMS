@php
    $visibleItems = collect($navigation)->filter(function (array $item) {
        if (isset($item['children'])) {
            return collect($item['children'])->contains(function (array $child) {
                if (isset($child['permission_check'])) {
                    return (bool) $child['permission_check']();
                }

                return ! isset($child['permission']) || auth()->user()->can($child['permission']);
            });
        }

        if (isset($item['permission_check'])) {
            return (bool) $item['permission_check']();
        }

        return ! isset($item['permission']) || auth()->user()->can($item['permission']);
    })->values();
@endphp

@php
    $mainItems = $visibleItems->filter(fn (array $item) => ! isset($item['children']))->values();
    $settingsItems = $visibleItems
        ->filter(fn (array $item) => isset($item['children']))
        ->flatMap(function (array $item) {
            return collect($item['children'])->filter(function (array $child) {
                if (isset($child['permission_check'])) {
                    return (bool) $child['permission_check']();
                }

                return ! isset($child['permission']) || auth()->user()->can($child['permission']);
            })->map(function (array $child) use ($item) {
                $child['section_icon'] = $child['icon'] ?? ($item['icon'] ?? 'settings');

                return $child;
            });
        })
        ->values();
@endphp

<nav class="app-sidebar__nav" aria-label="Primary">
    <div class="app-sidebar__nav-scroll">
        <ul class="app-sidebar__nav-list">
            @foreach($mainItems as $item)
                @php
                    $isActive = collect($item['active'] ?? [])->contains(fn (string $pattern) => request()->routeIs($pattern));
                @endphp
                <li class="app-sidebar__nav-item">
                    <a
                        href="{{ $item['route'] }}"
                        class="app-sidebar__link {{ $isActive ? 'is-active' : '' }}"
                        data-sidebar-link
                        data-tooltip="{{ $item['label'] }}"
                        @if($isActive) aria-current="page" @endif
                    >
                        <span class="app-sidebar__link-icon" aria-hidden="true">
                            @include('layouts.partials.sidebar-icon', ['icon' => $item['icon']])
                        </span>
                        <span class="app-sidebar__link-label">{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if($settingsItems->isNotEmpty())
            <div class="app-sidebar__section app-sidebar__section--expanded">
                <p class="app-sidebar__section-label">Settings</p>
                <ul class="app-sidebar__nav-list">
                    @foreach($settingsItems as $item)
                        @php
                            $isActive = collect($item['active'] ?? [])->contains(fn (string $pattern) => request()->routeIs($pattern));
                        @endphp
                        <li class="app-sidebar__nav-item">
                            <a
                                href="{{ $item['route'] }}"
                                class="app-sidebar__link {{ $isActive ? 'is-active' : '' }}"
                                data-sidebar-link
                                data-tooltip="{{ $item['label'] }}"
                                @if($isActive) aria-current="page" @endif
                            >
                                <span class="app-sidebar__link-icon" aria-hidden="true">
                                    @include('layouts.partials.sidebar-icon', ['icon' => $item['section_icon'] ?? 'settings'])
                                </span>
                                <span class="app-sidebar__link-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="app-sidebar__section app-sidebar__section--collapsed">
                <ul class="app-sidebar__nav-list">
                    @foreach($settingsItems as $item)
                        @php
                            $isActive = collect($item['active'] ?? [])->contains(fn (string $pattern) => request()->routeIs($pattern));
                        @endphp
                        <li class="app-sidebar__nav-item">
                            <a
                                href="{{ $item['route'] }}"
                                class="app-sidebar__link {{ $isActive ? 'is-active' : '' }}"
                                data-sidebar-link
                                data-tooltip="{{ $item['label'] }}"
                                @if($isActive) aria-current="page" @endif
                            >
                                <span class="app-sidebar__link-icon" aria-hidden="true">
                                    @include('layouts.partials.sidebar-icon', ['icon' => $item['section_icon'] ?? 'settings'])
                                </span>
                                <span class="app-sidebar__link-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</nav>
