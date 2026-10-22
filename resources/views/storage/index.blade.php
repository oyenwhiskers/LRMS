@extends('layouts.app')

@section('title', 'Storage Locations')
@section('list_layout')
@endsection

@section('content')
<div class="list-page">
    <div class="list-page__header hidden items-end justify-between gap-4 lg:flex">
        <div>
            <p class="eyebrow text-amber-700">Master data</p>
            <h1 class="mt-1 text-3xl font-semibold">Storage locations</h1>
            <p class="mt-2 text-sm text-stone-500">
                Manage racks and shelves in {{ config('lrms.storage.room_name') }}.
            </p>
        </div>
        @can('storage.manage')
            <button class="btn-icon-primary shrink-0" type="button" data-modal-open="add-rack" aria-label="Add rack" title="Add rack">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                </svg>
            </button>
        @endcan
    </div>

    <form class="list-page__filters grid gap-3 border border-stone-200 bg-white p-4 sm:grid-cols-[1fr_180px]" method="GET" data-list-filter-form>
        <input
            class="form-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search rack code or name"
            data-list-filter-search
        >
        <select class="form-input" name="status" data-list-filter-select>
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </form>

    {{-- Desktop table --}}
    <div @class([
        'list-page__table hidden',
        'lg:block' => $racks->isNotEmpty(),
        'list-page__table--empty lg:flex' => $racks->isEmpty(),
    ])>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rack code</th>
                    <th>Rack name</th>
                    <th class="text-center">Shelves</th>
                    <th class="text-center">Active shelves</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            @if($racks->isNotEmpty())
                <tbody>
                    @foreach($racks as $rack)
                        <tr>
                            <td class="font-semibold">{{ $rack->code }}</td>
                            <td>{{ $rack->name }}</td>
                            <td class="text-center">{{ $rack->shelves_count }}</td>
                            <td class="text-center">{{ $rack->active_shelves_count }}</td>
                            <td class="text-center">
                                <span class="status-badge {{ $rack->is_active ? 'status-approved' : 'status-rejected' }}">
                                    {{ $rack->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a class="btn-secondary" href="{{ route('storage.racks.show', $rack) }}">Manage</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endif
        </table>
        @if($racks->isEmpty())
            @include('layouts.partials.table-empty', [
                'icon' => 'archive',
                'title' => 'No racks configured yet',
                'message' => 'Add a rack to start defining shelves for file storage.',
            ])
        @endif
    </div>

    {{-- Mobile cards --}}
    <div @class(['list-page__mobile lg:hidden', 'list-page__mobile--empty' => $racks->isEmpty()])>
        @if($racks->isNotEmpty())
            <div class="mobile-list">
                @foreach($racks as $rack)
                    @include('layouts.partials.mobile-list-card', [
                        'title' => $rack->code,
                        'meta' => $rack->name,
                        'detail' => number_format($rack->shelves_count).' shelves · '.number_format($rack->active_shelves_count).' active',
                        'badge' => $rack->is_active ? 'Active' : 'Inactive',
                        'badgeTone' => $rack->is_active ? 'approved' : 'rejected',
                        'href' => route('storage.racks.show', $rack),
                        'ariaLabel' => 'Manage rack '.$rack->code,
                    ])
                @endforeach
            </div>
        @else
            @include('layouts.partials.table-empty', [
                'icon' => 'archive',
                'title' => 'No racks configured yet',
                'message' => 'Add a rack to start defining shelves for file storage.',
            ])
        @endif
    </div>
</div>

@can('storage.manage')
    <button class="fab-add" type="button" data-modal-open="add-rack" aria-label="Add rack" title="Add rack">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    </button>

    <div class="modal-overlay hidden" data-modal="add-rack" aria-hidden="true">
        <div class="modal-panel max-w-lg" role="dialog" aria-modal="true" aria-labelledby="add-rack-title">
            <div class="modal-header">
                <div>
                    <p class="eyebrow text-amber-700">Storage</p>
                    <h2 id="add-rack-title" class="mt-1 text-2xl font-semibold">Add rack</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Close modal">Close</button>
            </div>
            <form class="mt-5 grid gap-4" method="POST" action="{{ route('storage.racks.store') }}">
                @csrf
                <label class="block">
                    <span class="form-label">Rack code</span>
                    <input class="form-input mt-1.5" name="code" placeholder="e.g. A1" required autofocus>
                </label>
                <label class="block">
                    <span class="form-label">Rack name</span>
                    <input class="form-input mt-1.5" name="name" placeholder="e.g. Residential Conveyancing Bay" required>
                </label>
                <div class="flex justify-end gap-3">
                    <button class="btn-secondary" type="button" data-modal-close>Cancel</button>
                    <button class="btn-primary">Add rack</button>
                </div>
            </form>
        </div>
    </div>
@endcan
@endsection
