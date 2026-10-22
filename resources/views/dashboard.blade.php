@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $greeting = now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening');
    $chartPayload = json_encode($registrationChart, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT);
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-3xl font-semibold tracking-[-0.03em] text-stone-900 sm:text-4xl">Good {{ $greeting }},<br>{{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-stone-500">{{ now()->format('l, d F Y') }}</p>
    </div>
</div>

@can('files.view')
    <form method="GET" action="{{ route('files.index') }}" class="dashboard-search mt-8" role="search">
        <label class="sr-only" for="dashboard-search">Search files</label>
        <span class="dashboard-search-icon" aria-hidden="true">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.35-5.15a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>
            </svg>
        </span>
        <input id="dashboard-search" class="dashboard-search-input" type="search" name="search" value="{{ request('search') }}" placeholder="Search by reference number, purchaser, vendor, property or current holder..." autocomplete="off">
    </form>
@endcan

<section class="mt-10">
    <div class="flex items-center justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Case Registration</p><h2 class="mt-1 text-2xl font-semibold text-stone-900">Registration summary</h2></div>
        <div class="dashboard-export">
            <button type="button" class="btn-secondary lg:hidden" data-modal-open="export-registration">Export</button>
            <div class="hidden lg:block" data-dropdown>
                <button type="button" class="btn-secondary" data-dropdown-trigger aria-expanded="false" aria-haspopup="true">Export</button>
                <div class="dashboard-export__popover hidden" data-dropdown-menu>
                    @include('dashboard.partials.export-form', [
                        'action' => route('exports.dashboard', 'registration-summary'),
                        'scopeLabel' => 'Include',
                        'scopes' => [
                            'all' => 'All metrics',
                            'today' => 'Today only',
                            'week' => 'This week only',
                            'month' => 'This month only',
                            'year' => 'This year only',
                        ],
                    ])
                </div>
            </div>
        </div>
    </div>
    <div class="today-activity mt-4">
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-amber-700">{{ $registry['registered_today'] }}</span><span class="today-metric-label">Cases registered today</span><span class="today-metric-help">Added to LRMS</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-stone-900">{{ $registry['registered_week'] }}</span><span class="today-metric-label">Cases registered this week</span><span class="today-metric-help">Current week</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-sky-700">{{ $registry['registered_month'] }}</span><span class="today-metric-label">Cases registered this month</span><span class="today-metric-help">{{ now()->format('F Y') }}</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-emerald-700">{{ $registry['registered_year'] }}</span><span class="today-metric-label">Cases registered this year</span><span class="today-metric-help">{{ now()->format('Y') }}</span></span></div>
    </div>

    <div class="mt-6 overflow-hidden border border-stone-200 bg-white" data-category-registry>
        <div class="flex flex-col gap-3 border-b border-stone-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-semibold text-stone-900">By case category</h3>
            @if($categoryRegistry->isNotEmpty())
                <label class="sr-only" for="category-registry-search">Search categories</label>
                <input
                    id="category-registry-search"
                    class="form-input w-full sm:max-w-xs"
                    type="search"
                    placeholder="Search category code or name"
                    autocomplete="off"
                    data-category-registry-search
                >
            @endif
        </div>
        <div
            @class([
                'dashboard-category-table',
                'dashboard-category-table--framed',
                'dashboard-category-table--scroll' => $categoryRegistry->count() > 5,
                'dashboard-category-table--empty' => $categoryRegistry->isEmpty(),
            ])
            data-category-registry-scroll
            data-category-scroll-threshold="5"
        >
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-right">Today</th>
                        <th class="text-right">This week</th>
                        <th class="text-right">This month</th>
                    </tr>
                </thead>
                @if($categoryRegistry->isNotEmpty())
                    <tbody>
                        @foreach($categoryRegistry as $row)
                            <tr data-category-registry-row data-category-search="{{ strtolower($row['code'].' '.$row['name']) }}">
                                <td>
                                    <strong>{{ $row['code'] }}</strong>
                                    <small>{{ $row['name'] }}</small>
                                </td>
                                <td class="text-right">{{ $row['today'] }}</td>
                                <td class="text-right">{{ $row['week'] }}</td>
                                <td class="text-right">{{ $row['month'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
            <div
                @class(['dashboard-category-table__empty', 'hidden' => $categoryRegistry->isNotEmpty()])
                data-category-registry-empty
                role="status"
            >
                @include('layouts.partials.table-empty', [
                    'icon' => 'briefcase',
                    'title' => $categoryRegistry->isEmpty()
                        ? 'No category registrations in these periods yet'
                        : 'No categories match this search',
                    'message' => $categoryRegistry->isEmpty()
                        ? 'New registrations will appear here by case category.'
                        : 'Try another category code or name.',
                ])
            </div>
        </div>
        @if($categoryRegistry->isNotEmpty())
            <div class="border-t border-stone-200 px-5 py-3 text-sm text-stone-500" data-category-registry-footer>
                Showing {{ $categoryRegistry->count() }} {{ Str::plural('category', $categoryRegistry->count()) }} with activity
            </div>
        @endif
    </div>
</section>

<section class="mt-8">
    <div class="flex items-center justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Current File Status</p><h2 class="mt-1 text-2xl font-semibold text-stone-900">Live custody view</h2></div>
        <div class="dashboard-export">
            <button type="button" class="btn-secondary lg:hidden" data-modal-open="export-custody">Export</button>
            <div class="hidden lg:block" data-dropdown>
                <button type="button" class="btn-secondary" data-dropdown-trigger aria-expanded="false" aria-haspopup="true">Export</button>
                <div class="dashboard-export__popover hidden" data-dropdown-menu>
                    @include('dashboard.partials.export-form', [
                        'action' => route('exports.dashboard', 'custody-view'),
                        'scopeLabel' => 'Status scope',
                        'scopes' => [
                            'all' => 'All statuses',
                            'registered' => 'Active registered',
                            'pending' => 'Pending review',
                            'completed' => 'Ready for storage',
                            'stored' => 'Currently stored',
                            'borrowed' => 'Currently borrowed',
                            'missing' => 'Missing',
                        ],
                    ])
                </div>
            </div>
        </div>
    </div>
    <div class="today-activity mt-4">
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-stone-800">{{ $statusSummary['active_registered'] }}</span><span class="today-metric-label">Active registered</span><span class="today-metric-help">Still with handlers</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-amber-700">{{ $statusSummary['pending_review'] }}</span><span class="today-metric-label">Pending review</span><span class="today-metric-help">Awaiting boss approval</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-sky-700">{{ $statusSummary['ready_for_storage'] }}</span><span class="today-metric-label">Ready for storage</span><span class="today-metric-help">Completed, not shelved</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-emerald-700">{{ $statusSummary['currently_stored'] }}</span><span class="today-metric-label">Currently stored</span><span class="today-metric-help">Inside file room</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-amber-800">{{ $statusSummary['currently_borrowed'] }}</span><span class="today-metric-label">Currently borrowed</span><span class="today-metric-help">Out with staff</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-red-700">{{ $statusSummary['missing'] }}</span><span class="today-metric-label">Missing</span><span class="today-metric-help">Needs attention</span></span></div>
    </div>
</section>

<section class="mt-8">
        <div class="border border-stone-200 bg-white p-4 sm:p-6" data-dashboard-chart-panel>
            <div class="dashboard-chart-header">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Trend</p>
                    <h2 class="mt-1 text-xl font-semibold text-stone-900" data-chart-title>{{ $registrationChart['title'] }}</h2>
                </div>
                <div class="flex w-full flex-wrap items-start justify-end gap-3 lg:w-auto">
                    <form method="GET" action="{{ route('dashboard') }}" @class(['dashboard-chart-filters', 'has-month' => $registrationChart['mode'] === 'month']) data-chart-filter-form>
                        <div class="dashboard-chart-filters__field">
                            <label class="form-label" for="chart">View</label>
                            <select id="chart" class="form-input" name="chart" data-chart-mode-select>
                                <option value="30d" @selected($registrationChart['mode'] === '30d')>Last 30 days</option>
                                <option value="year" @selected($registrationChart['mode'] === 'year')>This year</option>
                                <option value="month" @selected($registrationChart['mode'] === 'month')>Specific month</option>
                            </select>
                        </div>
                        <div class="dashboard-chart-filters__field" data-chart-month-field @class(['hidden' => $registrationChart['mode'] !== 'month'])>
                            <label class="form-label" for="month">Month</label>
                            <select id="month" class="form-input" name="month" data-chart-month-select>
                                @foreach($chartMonthOptions as $option)
                                    <option value="{{ $option['value'] }}" @selected($registrationChart['selected_month'] === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
        </div>
        <p class="mt-2 text-xs uppercase tracking-[0.14em] text-stone-500" data-chart-period>{{ $registrationChart['period_label'] }}</p>
        <div class="mt-4 sm:mt-6">
            <div
                class="dashboard-chart"
                data-dashboard-chart
                data-chart='{{ $chartPayload }}'
            >
                <div class="dashboard-chart__surface">
                    <svg class="dashboard-chart__svg" viewBox="0 0 360 200" role="img" aria-label="{{ $registrationChart['title'] }}"></svg>
                    <div class="dashboard-chart__tooltip" data-dashboard-chart-tooltip hidden>
                        <p class="dashboard-chart__tooltip-label" data-dashboard-chart-tooltip-label></p>
                        <p class="dashboard-chart__tooltip-value" data-dashboard-chart-tooltip-value></p>
                    </div>
                </div>
                <div class="dashboard-chart__axis" data-dashboard-chart-axis></div>
            </div>
        </div>
    </div>
</section>

<section class="mt-10">
    <div class="flex items-center justify-between gap-3">
        <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Daily Movement</p><h2 class="mt-1 text-2xl font-semibold text-stone-900">Today&apos;s file activity</h2></div>
    </div>
    <div class="today-activity mt-4 sm:grid-cols-2">
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-emerald-700">{{ $movementSummary['borrowed_today'] }}</span><span class="today-metric-label">Borrowed today</span><span class="today-metric-help">Checked out</span></span></div>
        <div class="today-metric today-metric--static"><span><span class="today-metric-value text-sky-700">{{ $movementSummary['returned_today'] }}</span><span class="today-metric-label">Returned today</span><span class="today-metric-help">Received back</span></span></div>
    </div>
</section>

<section class="mt-10">
    <p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Recent file activity</p>

    {{-- Desktop table --}}
    <div class="mt-4 hidden overflow-hidden border border-stone-200 bg-white lg:block">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Reference No.</th>
                        <th>Movement</th>
                        <th>Actor</th>
                        <th>Receiver</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                        @php
                            $file = $activity->legalFile;
                            $fileUrl = $file ? route('files.show', $file) : null;
                        @endphp
                        <tr
                            @if($fileUrl)
                                class="table-row-link"
                                data-row-href="{{ $fileUrl }}"
                                tabindex="0"
                                role="link"
                                aria-label="Open file {{ $file->reference_number }}"
                            @endif
                        >
                            <td class="whitespace-nowrap text-stone-600">{{ $activity->occurred_at?->format('h:i A') ?? '—' }}</td>
                            <td>@if($file)<strong>{{ $file->reference_number }}</strong>@else — @endif</td>
                            <td>{{ str($activity->type)->headline() }}</td>
                            <td>{{ $activity->employee?->full_name ?? '—' }}</td>
                            <td>{{ $activity->receiver?->full_name ?? '—' }}</td>
                            <td><span class="status-badge status-{{ $file?->statusTone() ?? 'pending' }}">{{ $file?->statusLabel() ?? 'Unknown' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-stone-500">No file activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="mt-4 lg:hidden">
        @if($activities->isNotEmpty())
            <div class="mobile-list">
                @foreach($activities as $activity)
                    @php
                        $file = $activity->legalFile;
                        $fileUrl = $file ? route('files.show', $file) : null;
                        $movement = str($activity->type)->headline();
                        $time = $activity->occurred_at?->format('h:i A') ?? '—';
                        $actor = $activity->employee?->full_name;
                        $receiver = $activity->receiver?->full_name;
                        $detailParts = array_values(array_filter([
                            $actor,
                            $receiver && $actor ? '→ '.$receiver : $receiver,
                        ]));
                    @endphp
                    @include('layouts.partials.mobile-list-card', [
                        'title' => $file?->reference_number ?? '—',
                        'meta' => $time.' · '.$movement,
                        'detail' => $detailParts ? implode(' ', $detailParts) : null,
                        'badge' => $file?->statusLabel() ?? 'Unknown',
                        'badgeTone' => $file?->statusTone() ?? 'pending',
                        'href' => $fileUrl,
                        'ariaLabel' => $file ? 'Open file '.$file->reference_number : 'Activity without file',
                    ])
                @endforeach
            </div>
        @else
            <div class="border border-stone-200 bg-white px-4 py-10 text-center text-sm text-stone-500">
                No file activity yet.
            </div>
        @endif
    </div>
</section>

<div class="modal-overlay hidden" data-modal="export-registration" aria-hidden="true">
    <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="export-registration-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Case Registration</p>
                <h2 id="export-registration-title" class="mt-1 text-2xl font-semibold">Export registration summary</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
        </div>
        <div class="mt-5">
            @include('dashboard.partials.export-form', [
                'action' => route('exports.dashboard', 'registration-summary'),
                'scopeLabel' => 'Include',
                'scopes' => [
                    'all' => 'All metrics',
                    'today' => 'Today only',
                    'week' => 'This week only',
                    'month' => 'This month only',
                    'year' => 'This year only',
                ],
            ])
        </div>
    </div>
</div>

<div class="modal-overlay hidden" data-modal="export-custody" aria-hidden="true">
    <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="export-custody-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Current File Status</p>
                <h2 id="export-custody-title" class="mt-1 text-2xl font-semibold">Export custody view</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
        </div>
        <div class="mt-5">
            @include('dashboard.partials.export-form', [
                'action' => route('exports.dashboard', 'custody-view'),
                'scopeLabel' => 'Status scope',
                'scopes' => [
                    'all' => 'All statuses',
                    'registered' => 'Active registered',
                    'pending' => 'Pending review',
                    'completed' => 'Ready for storage',
                    'stored' => 'Currently stored',
                    'borrowed' => 'Currently borrowed',
                    'missing' => 'Missing',
                ],
            ])
        </div>
    </div>
</div>
@endsection
