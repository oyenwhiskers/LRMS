@php
    $sectionOrder = ['Overview', 'Cases', 'File Room', 'Settings'];

    $visibleItems = collect($navigation)->flatMap(function (array $item) {
        if (isset($item['children'])) {
            return collect($item['children'])->filter(function (array $child) {
                if (isset($child['permission_check'])) {
                    return (bool) $child['permission_check']();
                }

                return ! isset($child['permission']) || auth()->user()->can($child['permission']);
            })->map(function (array $child) use ($item) {
                $child['section'] = $item['label'];
                $child['section_icon'] = $child['icon'] ?? ($item['icon'] ?? 'settings');

                return $child;
            })->values();
        }

        $canView = isset($item['permission_check'])
            ? (bool) $item['permission_check']()
            : (! isset($item['permission']) || auth()->user()->can($item['permission']));

        return $canView ? [[$item]] : [];
    })->map(function ($item) {
        return is_array($item) && array_key_exists(0, $item) && is_array($item[0]) ? $item[0] : $item;
    })->values();

    $groupedSections = collect($sectionOrder)->mapWithKeys(function (string $section) use ($visibleItems) {
        return [$section => $visibleItems->filter(fn (array $item) => ($item['section'] ?? 'Overview') === $section)->values()];
    })->filter(fn ($items) => $items->isNotEmpty());
@endphp

<nav class="app-sidebar__nav" aria-label="Primary">
    <div class="app-sidebar__nav-scroll">
        @foreach($groupedSections as $section => $items)
            <div class="app-sidebar__section app-sidebar__section--expanded">
                <p class="app-sidebar__section-label">{{ $section }}</p>
                <ul class="app-sidebar__nav-list">
                    @foreach($items as $item)
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
                                    @include('layouts.partials.sidebar-icon', ['icon' => $item['section_icon'] ?? $item['icon']])
                                </span>
                                <span class="app-sidebar__link-label">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="app-sidebar__section app-sidebar__section--collapsed">
            <ul class="app-sidebar__nav-list">
                @foreach($visibleItems as $item)
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
                                @include('layouts.partials.sidebar-icon', ['icon' => $item['section_icon'] ?? $item['icon']])
                            </span>
                            <span class="app-sidebar__link-label">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</nav>
