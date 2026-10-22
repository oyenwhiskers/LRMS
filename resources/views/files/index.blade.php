@extends('layouts.app')

@section('title', 'Case Register')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="hidden lg:block">
            <p class="eyebrow">Physical records</p>
            <h1 class="mt-1 text-3xl font-semibold">Case Register</h1>
        </div>
        <div class="flex w-full items-center gap-3 lg:w-auto lg:justify-end">
            <div class="view-toggle min-w-0 w-full lg:w-auto lg:flex-none" role="tablist" aria-label="Registry view">
                <a @class(['view-toggle__item', 'is-active' => $viewMode === 'case']) href="{{ route('files.index', array_merge(request()->except('page'), ['view' => 'case'])) }}">Case</a>
                <a @class(['view-toggle__item', 'is-active' => $viewMode === 'customer']) href="{{ route('files.index', array_merge(request()->except('page'), ['view' => 'customer'])) }}">Customer</a>
            </div>
            @can('files.create')
                <a class="btn-icon-primary hidden shrink-0 lg:inline-flex" href="{{ route('files.create') }}" aria-label="Register case" title="Register case">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                </a>
            @endcan
        </div>
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_240px]" method="GET" data-case-filter-form>
        <input type="hidden" name="view" value="{{ $viewMode }}">
        <input class="form-input" type="search" name="search" value="{{ request('search') }}" placeholder="Reference, purchaser, vendor, property or holder" data-case-filter-search>
        <select class="form-input" name="status" data-case-filter-status data-list-filter-select>
            <option value="">All statuses</option>
            @foreach(\App\Models\LegalFile::statuses() as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\LegalFile::make(['status' => $status])->statusLabel() }}</option>
            @endforeach
        </select>
    </form>

    @php
        $isCaseEmpty = $viewMode === 'customer' ? $customers->isEmpty() : $files->isEmpty();
    @endphp

    {{-- Desktop tables --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => ! $isCaseEmpty,
        'list-page__table--empty lg:flex' => $isCaseEmpty,
    ])>
        @if($viewMode === 'customer')
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-16 text-center">#</th>
                        <th>Customer</th>
                        <th class="text-right">Cases</th>
                        <th class="text-right">Stored</th>
                        <th class="text-right">Borrowed</th>
                        <th class="text-right">Missing</th>
                        <th class="whitespace-nowrap">Updated</th>
                    </tr>
                </thead>
                @if($customers->isNotEmpty())
                    <tbody>
                        @foreach($customers as $customer)
                            <tr
                                class="table-row-link"
                                data-row-href="{{ route('files.index', ['view' => 'case', 'search' => $customer->purchaser]) }}"
                                tabindex="0"
                                role="link"
                                aria-label="View cases for {{ $customer->purchaser }}"
                            >
                                <td class="text-center">{{ ($customers->firstItem() ?? 1) + $loop->index }}</td>
                                <td><strong>{{ $customer->purchaser }}</strong></td>
                                <td class="text-right">{{ $customer->total_cases }}</td>
                                <td class="text-right">{{ $customer->stored_cases }}</td>
                                <td class="text-right">{{ $customer->borrowed_cases }}</td>
                                <td @class(['text-right', 'font-semibold text-red-600' => (int) $customer->missing_cases > 0])>
                                    {{ $customer->missing_cases }}
                                </td>
                                <td class="whitespace-nowrap">{{ $customer->last_activity_at ? \Illuminate\Support\Carbon::parse($customer->last_activity_at)->format('d M Y') : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
            @if($customers->isEmpty())
                @include('layouts.partials.table-empty', [
                    'icon' => 'files',
                    'title' => 'No customers match this search',
                    'message' => 'Try a different search term or clear filters to see all customers.',
                ])
            @endif
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-16 text-center">#</th>
                        <th>File</th>
                        <th>Purchaser</th>
                        <th>Shelf</th>
                        <th>Holder</th>
                        <th>Status</th>
                    </tr>
                </thead>
                @if($files->isNotEmpty())
                    <tbody>
                        @foreach($files as $file)
                            @php
                                $shelfLabel = $file->shelf?->full_location
                                    ?? match ($file->status) {
                                        \App\Models\LegalFile::STATUS_COMPLETED => 'Ready to store',
                                        \App\Models\LegalFile::STATUS_REGISTERED,
                                        \App\Models\LegalFile::STATUS_PENDING => 'Not stored yet',
                                        default => '—',
                                    };
                                $fileUrl = route('files.show', $file);
                            @endphp
                            <tr
                                class="table-row-link"
                                data-row-href="{{ $fileUrl }}"
                                tabindex="0"
                                role="link"
                                aria-label="Open file {{ $file->reference_number }}"
                            >
                                <td class="text-center">{{ ($files->firstItem() ?? 1) + $loop->index }}</td>
                                <td>
                                    <a class="accent-link font-semibold hover:underline" href="{{ $fileUrl }}">{{ $file->reference_number }}</a>
                                    <small>{{ $file->file_identifier }}</small>
                                </td>
                                <td><strong>{{ $file->purchaser }}</strong><small class="max-w-md truncate">{{ $file->property }}</small></td>
                                <td>{{ $shelfLabel }}</td>
                                <td>{{ $file->currentHolder?->full_name ?? '—' }}</td>
                                <td><span class="status-badge status-{{ $file->statusTone() }}">{{ $file->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
            </table>
            @if($files->isEmpty())
                @include('layouts.partials.table-empty', [
                    'icon' => 'files',
                    'title' => 'No cases match this search',
                    'message' => 'Try another search or status filter, or register a new case.',
                ])
            @endif
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $isCaseEmpty])>
        @if($viewMode === 'customer')
            @if($customers->isNotEmpty())
                <div
                    class="mobile-list"
                    data-infinite-list
                    data-infinite-next="{{ $customers->hasMorePages() ? $customers->currentPage() + 1 : '' }}"
                    data-infinite-window="100"
                >
                    @include('files.partials.mobile-customer-cards', ['customers' => $customers])
                </div>
                <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
                <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $customers->hasMorePages()]) aria-hidden="true"></div>
            @else
                @include('layouts.partials.table-empty', [
                    'icon' => 'files',
                    'title' => 'No customers match this search',
                    'message' => 'Try a different search term or clear filters to see all customers.',
                ])
            @endif
        @else
            @if($files->isNotEmpty())
                <div
                    class="mobile-list"
                    data-infinite-list
                    data-infinite-next="{{ $files->hasMorePages() ? $files->currentPage() + 1 : '' }}"
                    data-infinite-window="100"
                >
                    @include('files.partials.mobile-case-cards', ['files' => $files])
                </div>
                <div class="infinite-scroll-status lg:hidden" data-infinite-status hidden></div>
                <div class="infinite-scroll-sentinel lg:hidden" data-infinite-sentinel @class(['hidden' => ! $files->hasMorePages()]) aria-hidden="true"></div>
            @else
                @include('layouts.partials.table-empty', [
                    'icon' => 'files',
                    'title' => 'No cases match this search',
                    'message' => 'Try another search or status filter, or register a new case.',
                ])
            @endif
        @endif
    </div>

    <div class="list-page__footer hidden lg:block">{{ ($viewMode === 'customer' ? $customers : $files)->links() }}</div>
</div>

@can('files.create')
    <a class="fab-add" href="{{ route('files.create') }}" aria-label="Register case" title="Register case">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    </a>
@endcan
@endsection
