@extends('layouts.app')

@section('title', 'Movement History')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="hidden lg:block">
            <p class="eyebrow text-amber-700">Audit trail</p>
            <h1 class="mt-1 text-3xl font-semibold">File movement history</h1>
        </div>
        <div class="flex w-full flex-wrap gap-3 lg:w-auto lg:items-center lg:justify-end">
            <button
                type="button"
                class="btn-secondary inline-flex h-11 min-w-0 flex-1 items-center justify-center gap-2 px-4 lg:hidden"
                data-modal-open="history-period"
                aria-label="Date filter: {{ $periodLabel }}"
            >
                <span class="size-5 shrink-0" aria-hidden="true">
                    @include('layouts.partials.sidebar-icon', ['icon' => 'calendar'])
                </span>
                <span class="truncate">{{ $periodLabel }}</span>
            </button>

            <div class="history-filter hidden lg:block" data-dropdown>
                <button
                    type="button"
                    class="btn-secondary inline-flex h-10 items-center gap-2 px-4"
                    data-dropdown-trigger
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-label="Date filter: {{ $periodLabel }}"
                >
                    <span class="size-5 shrink-0" aria-hidden="true">
                        @include('layouts.partials.sidebar-icon', ['icon' => 'calendar'])
                    </span>
                    <span>{{ $periodLabel }}</span>
                </button>

                <div class="history-filter__popover hidden" data-dropdown-menu>
                    @include('movements.partials.history-period-form')
                </div>
            </div>

            @can('exports.movements')
                <a class="btn-secondary inline-flex h-11 min-w-0 flex-1 items-center justify-center lg:h-10 lg:flex-none" href="{{ route('exports.movements') }}">Export Excel</a>
            @endcan
        </div>
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_220px]" method="GET" data-list-filter-form>
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="date" value="{{ $selectedDate }}">
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search file or person"
            data-list-filter-search
        >
        <select class="form-input" name="movement" data-list-filter-select>
            <option value="">All movements</option>
            @foreach($movementTypes as $value => $label)
                <option value="{{ $value }}" @selected($movementType === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $movements->isNotEmpty(),
        'list-page__table--empty lg:flex' => $movements->isEmpty(),
    ])>
        <table class="data-table history-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>File</th>
                    <th>Movement</th>
                    <th>Person</th>
                    <th>Shelf</th>
                    <th>By</th>
                </tr>
            </thead>
            @if($movements->isNotEmpty())
                <tbody>
                    @foreach($movements as $movement)
                        <tr>
                            <td class="history-table__when">{{ $movement->occurred_at?->format('d M Y H:i') }}</td>
                            <td class="history-table__file">
                                @if($movement->legalFile)
                                    <a class="font-semibold text-amber-800" href="{{ route('files.show', $movement->legalFile) }}">{{ $movement->legalFile->reference_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $movement->typeLabel() }}</td>
                            <td>{{ $movement->historyPersonLabel() ?: '—' }}</td>
                            <td>{{ $movement->historyShelfLabel() ?: '—' }}</td>
                            <td>{{ $movement->processor?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($movements->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'history',
                'title' => 'No movements for this period',
                'message' => 'Try another date range, or check back after file room activity is recorded.',
            ])
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $movements->isEmpty()])>
        @if($movements->isNotEmpty())
            <div
                class="mobile-list movement-history-mobile"
                data-infinite-list
                data-infinite-next="{{ $movements->hasMorePages() ? $movements->currentPage() + 1 : '' }}"
                data-infinite-window="50"
            >
                @include('movements.partials.mobile-history-cards', ['movements' => $movements])
            </div>
            <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
            <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $movements->hasMorePages()]) aria-hidden="true"></div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'history',
                'title' => 'No movements for this period',
                'message' => 'Try another date range, or check back after file room activity is recorded.',
            ])
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ $movements->links() }}</div>
</div>

<div class="modal-overlay hidden lg:hidden" data-modal="history-period" aria-hidden="true">
    <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="history-period-title">
        <div class="modal-header">
            <div>
                <p class="eyebrow text-amber-700">Audit trail</p>
                <h2 id="history-period-title" class="mt-1 text-2xl font-semibold">Date range</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
        </div>
        <div class="mt-5">
            @include('movements.partials.history-period-form')
        </div>
    </div>
</div>
@endsection
