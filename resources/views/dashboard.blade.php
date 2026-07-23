@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $greeting = now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening');
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="font-display text-3xl font-semibold text-stone-900 sm:text-4xl">
            Good {{ $greeting }}, {{ auth()->user()->name }}
        </h1>
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
        <input
            id="dashboard-search"
            class="dashboard-search-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search by Reference Number, Purchaser, Vendor, Property or Current Holder..."
            autocomplete="off"
        >
    </form>
@endcan

<section class="mt-10">
    <p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Today's activity</p>
    <div class="today-activity mt-4">
        @can('movements.history')
            <a class="today-metric" href="{{ route('movements.history') }}">
        @else
            <div class="today-metric today-metric--static">
        @endcan
            <span class="today-metric-icon today-metric-icon--borrowed" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                </svg>
            </span>
            <span>
                <span class="today-metric-value text-emerald-700">{{ $today['borrowed'] }}</span>
                <span class="today-metric-label">Borrowed today</span>
                <span class="today-metric-help">Files checked out</span>
            </span>
        @can('movements.history')
            </a>
        @else
            </div>
        @endcan

        @can('movements.history')
            <a class="today-metric" href="{{ route('movements.history') }}">
        @else
            <div class="today-metric today-metric--static">
        @endcan
            <span class="today-metric-icon today-metric-icon--returned" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                </svg>
            </span>
            <span>
                <span class="today-metric-value text-sky-700">{{ $today['returned'] }}</span>
                <span class="today-metric-label">Returned today</span>
                <span class="today-metric-help">Files returned</span>
            </span>
        @can('movements.history')
            </a>
        @else
            </div>
        @endcan

        @can('files.view')
            <a class="today-metric" href="{{ route('files.index') }}">
        @else
            <div class="today-metric today-metric--static">
        @endcan
            <span class="today-metric-icon today-metric-icon--registered" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
            </span>
            <span>
                <span class="today-metric-value text-amber-700">{{ $today['registered'] }}</span>
                <span class="today-metric-label">New files today</span>
                <span class="today-metric-help">Files registered</span>
            </span>
        @can('files.view')
            </a>
        @else
            </div>
        @endcan

        @can('files.view')
            <a class="today-metric" href="{{ route('files.index', ['status' => 'borrowed']) }}">
        @else
            <div class="today-metric today-metric--static">
        @endcan
            <span class="today-metric-icon today-metric-icon--still-out" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span>
            <span>
                <span class="today-metric-value text-rose-700">{{ $today['still_out'] }}</span>
                <span class="today-metric-label">Still out</span>
                <span class="today-metric-help">Not yet returned</span>
            </span>
        @can('files.view')
            </a>
        @else
            </div>
        @endcan
    </div>
</section>

<section class="mt-10">
    <p class="text-xs font-bold uppercase tracking-[0.16em] text-stone-500">Recent file activity</p>
    <div class="mt-4 overflow-hidden border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Reference No.</th>
                        <th>File type</th>
                        <th>Vendor</th>
                        <th>Current holder</th>
                        <th>Status</th>
                        <th class="w-16"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                        @php
                            $file = $activity->legalFile;
                            $holder = $activity->type === 'borrow'
                                ? ($file?->currentHolder ?? $activity->employee)
                                : ($activity->type === 'return' ? $activity->employee : $file?->currentHolder);
                            $statusClass = match ($activity->type) {
                                'borrow' => 'activity-status--borrowed',
                                'return' => 'activity-status--returned',
                                'missing' => 'activity-status--missing',
                                'found' => 'activity-status--found',
                                default => 'activity-status--default',
                            };
                            $statusLabel = match ($activity->type) {
                                'borrow' => 'Borrowed',
                                'return' => 'Returned',
                                'missing' => 'Missing',
                                'found' => 'Found',
                                default => ucfirst($activity->type),
                            };
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap text-stone-600">{{ $activity->occurred_at?->format('h:i A') ?? '—' }}</td>
                            <td>
                                @if($file)
                                    <a class="font-semibold text-amber-900 hover:text-amber-700" href="{{ route('files.show', $file) }}">{{ $file->reference_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $file?->matter_type ?: '—' }}</td>
                            <td>{{ $file?->vendor ?: '—' }}</td>
                            <td>
                                @if($holder)
                                    <span class="font-semibold text-stone-900">{{ $holder->full_name }}</span>
                                    @if($holder->position?->name)
                                        <small>{{ $holder->position->name }}</small>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td>
                                @if($file)
                                    <a class="inline-flex size-9 items-center justify-center text-stone-500 transition hover:text-amber-800" href="{{ route('files.show', $file) }}" aria-label="View {{ $file->reference_number }}">
                                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-stone-500">No file activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
